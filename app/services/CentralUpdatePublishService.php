<?php

use phpseclib3\Net\SFTP;

class CentralUpdatePublishService
{
    private $sourceRoot;
    private $portalRoot;
    private $updatesDir;
    private $config;
    private $sourceRootError = '';

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->portalRoot = defined('ROOT_DIR') ? realpath(ROOT_DIR) : false;
        $this->sourceRoot = realpath((string)($config['source_root'] ?? ''));
        if (!$this->sourceRoot || !is_dir($this->sourceRoot)) {
            $this->sourceRoot = false;
            $this->sourceRootError = 'Projeto fonte do prato_web nao encontrado. Configure UPDATE_SOURCE_ROOT ou storage/config/update-publish.php.';
        }

        $this->updatesDir = STORAGE_DIR . DIRECTORY_SEPARATOR . 'updates';
        if (!is_dir($this->updatesDir)) {
            @mkdir($this->updatesDir, 0775, true);
        }
    }

    public function currentVersion(): string
    {
        $paths = [
            $this->updatesDir . DIRECTORY_SEPARATOR . 'version.json',
        ];
        if ($this->hasSourceRoot()) {
            $paths[] = $this->sourceRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'updates' . DIRECTORY_SEPARATOR . 'version.json';
            $paths[] = $this->sourceRoot . DIRECTORY_SEPARATOR . 'updater' . DIRECTORY_SEPARATOR . 'version-local.json';
        }

        foreach ($paths as $path) {
            if (!is_file($path)) continue;
            $json = json_decode((string)file_get_contents($path), true);
            if (is_array($json) && !empty($json['version'])) return (string)$json['version'];
        }
        return '0.0.0';
    }

    public function preview(): array
    {
        $this->requireSourceRoot();

        $included = [];
        $ignored = [];
        $includedMap = [];
        $preflight = $this->buildPreflightReport(false);

        foreach ($this->detectChangedFiles() as $file) {
            $reason = $this->ignoreReason($file['path']);
            if ($reason !== '') {
                $file['reason'] = $reason;
                $ignored[] = $file;
                continue;
            }

            if (!is_file($this->path($file['path']))) {
                $file['reason'] = 'arquivo removido ou inexistente';
                $ignored[] = $file;
                continue;
            }

            $included[] = $file;
            $includedMap[$file['path']] = true;
        }

        $required = $this->requiredFilesStatus();
        $critical = $this->criticalClassesStatus();
        foreach ($critical['found'] as $path) {
            if (!isset($includedMap[$path])) {
                $included[] = ['path' => $path, 'status' => 'classe'];
                $includedMap[$path] = true;
            }
        }

        $required['found'] = array_values(array_unique(array_merge($required['found'], $critical['found'])));
        $required['missing'] = array_values(array_unique(array_merge($required['missing'], $critical['missing'])));

        foreach ($required['found'] as $path) {
            if (!isset($includedMap[$path])) {
                $included[] = ['path' => $path, 'status' => 'obrigatorio'];
                $includedMap[$path] = true;
            }
        }

        usort($included, function ($a, $b) {
            return strcmp($a['path'], $b['path']);
        });
        $this->logPreview($included, $ignored, $required);

        return [
            'current_version' => $this->currentVersion(),
            'source_root' => $this->sourceRoot,
            'included' => $included,
            'ignored' => $ignored,
            'required' => $required,
            'critical_classes' => $critical,
            'preflight' => $preflight,
            'total_included' => count($included),
            'total_ignored' => count($ignored),
        ];
    }

    public function testConnection(): array
    {
        $driver = strtolower((string)($this->config['driver'] ?? 'local'));
        if ($driver === 'local') {
            $this->requireSourceRoot();
            return ['success' => true, 'message' => 'Publicacao local validada.', 'driver' => 'local', 'source_root' => $this->sourceRoot];
        }
        if ($driver === 'ftp') {
            $conn = $this->connectFtp();
            $dir = $this->ftpRemoteDir();
            if ($dir !== '') {
                $this->ensureFtpDir($conn, $dir);
            }
            @ftp_close($conn);
            return ['success' => true, 'message' => 'Conexao FTP validada.', 'driver' => 'ftp', 'remote_dir' => $dir];
        }
        if ($driver === 'http') {
            if (trim((string)($this->config['http_upload_url'] ?? '')) === '') {
                throw new Exception('UPDATE_PUBLISH_HTTP_UPLOAD_URL nao configurado.');
            }
            if (!function_exists('curl_init')) {
                throw new Exception('cURL indisponivel para upload HTTP.');
            }
            return ['success' => true, 'message' => 'Configuracao HTTP encontrada.', 'driver' => 'http'];
        }
        if ($driver === 'sftp') {
            $sftp = $this->connectSftp();
            $dir = $this->sftpRemoteDir();
            $this->ensureSftpDir($sftp, $dir);
            return ['success' => true, 'message' => 'Conexao SFTP validada.', 'driver' => 'sftp', 'remote_dir' => $dir];
        }
        throw new Exception('Driver de publicacao invalido: ' . $driver);
    }

    public function publish($version, $changelog): array
    {
        $this->requireSourceRoot();

        $version = $this->normalizeVersion($version);
        $changelog = trim((string)$changelog);
        if ($changelog === '') throw new Exception('Informe o changelog da atualizacao.');

        $current = $this->currentVersion();
        if (version_compare($version, $current, '<=')) {
            throw new Exception('A nova versao precisa ser maior que a versao atual (' . $current . ').');
        }

        $zipName = 'sistema-v' . $version . '.zip';
        $zipPath = $this->updatesDir . DIRECTORY_SEPARATOR . $zipName;
        if (is_file($zipPath)) throw new Exception('Ja existe um ZIP local para a versao ' . $version . '.');

        $preflight = $this->buildPreflightReport(true);
        $preview = $this->preview();
        if (empty($preview['included'])) throw new Exception('Nenhum arquivo seguro foi encontrado para publicar.');
        if (!empty($preview['required']['missing'])) {
            throw new Exception('Publicacao cancelada: arquivos obrigatorios ausentes no projeto: ' . implode(', ', $preview['required']['missing']));
        }
        if (!empty($preview['critical_classes']['missing'])) {
            throw new Exception('Publicacao cancelada: classes criticas chamadas por rotas/services nao foram encontradas: ' . implode(', ', $preview['critical_classes']['missing']));
        }

        $this->ensureVersionDoesNotExist($version);
        $this->validatePhpFiles($preview['included']);
        $zip = $this->createZip($zipPath, $preview['included']);
        $this->validateZipRequiredFiles($zipPath, $preview['required']['found']);

        $url = $this->downloadUrl($zipName);
        $jsonData = $this->buildVersionJson($version, $zipName, $url, $changelog, $zip['sha256']);
        $backups = $this->backupVersionFiles($version);

        $zipUpload = ['success' => false];
        $jsonStatus = ['local' => false, 'remote' => false];
        try {
            $this->writeJsonFile('version.json', $jsonData);
            $this->writeJsonFile('version-lite.json', $jsonData);
            $jsonStatus['local'] = true;

            $uploads = $this->uploadFiles([
                ['path' => $zipPath, 'name' => $zipName],
                ['path' => $this->updatesDir . DIRECTORY_SEPARATOR . 'version.json', 'name' => 'version.json'],
                ['path' => $this->updatesDir . DIRECTORY_SEPARATOR . 'version-lite.json', 'name' => 'version-lite.json'],
            ]);
            $zipUpload = $uploads[$zipName] ?? ['success' => true];
            $jsonStatus['remote'] = true;
            $this->registerPublishedVersion($jsonData);
        } catch (Exception $e) {
            $this->restoreVersionBackups($backups);
            if (!empty($zipUpload['success'])) {
                throw new Exception('ZIP enviado, mas os JSONs nao foram concluidos. Detalhe: ' . $e->getMessage());
            }
            throw $e;
        }

        $result = [
            'success' => true,
            'version' => $version,
            'zip_name' => $zipName,
            'zip_path' => $zipPath,
            'zip_size' => $zip['size'],
            'sha256' => $zip['sha256'],
            'url' => $url,
            'included' => $preview['included'],
            'ignored' => $preview['ignored'],
            'upload' => $zipUpload,
            'json_status' => $jsonStatus,
            'json_backups' => $backups,
            'preflight' => $preflight,
        ];
        $this->log('SUCESSO publicacao versao=' . $version . ' | zip=' . $zipName . ' | url=' . $url);
        return $result;
    }

    private function detectChangedFiles(): array
    {
        if (!$this->hasGitMetadata($this->sourceRoot)) {
            throw new Exception('Nao foi possivel detectar arquivos modificados: o projeto fonte nao possui pasta .git.');
        }
        $process = $this->runProcess('git -C ' . escapeshellarg($this->sourceRoot) . ' status --porcelain', 30);
        if ($process['code'] !== 0) {
            throw new Exception('Falha ao consultar Git: ' . trim($process['stdout'] . ' ' . $process['stderr']));
        }

        $files = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($process['stdout'])) as $line) {
            if ($line === '') continue;
            $path = trim(substr($line, 3));
            if (strpos($path, ' -> ') !== false) {
                $parts = explode(' -> ', $path);
                $path = trim(end($parts));
            }
            $path = str_replace('\\', '/', trim($path, "\"' "));
            if ($path !== '') $files[] = ['path' => $path, 'status' => trim(substr($line, 0, 2))];
        }
        usort($files, function ($a, $b) {
            return strcmp($a['path'], $b['path']);
        });
        return $files;
    }

    private function hasSourceRoot(): bool
    {
        return is_string($this->sourceRoot) && $this->sourceRoot !== '' && is_dir($this->sourceRoot);
    }

    private function requireSourceRoot(): void
    {
        if (!$this->hasSourceRoot()) {
            throw new Exception($this->sourceRootError ?: 'Projeto fonte do prato_web nao encontrado.');
        }
    }

    private function hasGitMetadata($root): bool
    {
        $git = rtrim((string)$root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.git';
        return is_dir($git) || is_file($git);
    }

    private function buildPreflightReport($throwOnBlock): array
    {
        $trees = array();
        $trees[] = array('name' => 'prato_web', 'root' => $this->sourceRoot);
        if ($this->portalRoot && is_dir($this->portalRoot) && $this->portalRoot !== $this->sourceRoot) {
            $trees[] = array('name' => 'portal_central', 'root' => $this->portalRoot);
        }

        $blocked = array();
        $reports = array();
        foreach ($trees as $tree) {
            $report = $this->scanUntrackedCriticalPhp($tree['name'], $tree['root']);
            $reports[] = $report;
            foreach ($report['blocked'] as $path) {
                $blocked[] = $tree['name'] . ':' . $path;
            }
        }

        $critical = $this->criticalClassesStatus();
        $checks = array(
            array(
                'key' => 'git_tracked',
                'label' => 'Git limpo ou apenas alteracoes rastreadas',
                'ok' => empty($blocked),
                'message' => empty($blocked) ? 'Nenhum PHP novo critico sem rastreamento.' : 'Existem PHPs novos nao rastreados em pastas criticas.'
            ),
            array(
                'key' => 'package_required',
                'label' => 'Pacote contem arquivos obrigatorios',
                'ok' => empty($this->requiredFilesStatus()['missing']),
                'message' => empty($this->requiredFilesStatus()['missing']) ? 'Arquivos obrigatorios encontrados.' : 'Ha arquivos obrigatorios ausentes.'
            ),
            array(
                'key' => 'critical_classes',
                'label' => 'Classes criticas encontradas',
                'ok' => empty($critical['missing']),
                'message' => empty($critical['missing']) ? 'Classes chamadas por rotas/services localizadas.' : 'Ha classes chamadas sem arquivo fisico.'
            ),
            array(
                'key' => 'env_protected',
                'label' => '.env protegido',
                'ok' => true,
                'message' => '.env e variacoes locais continuam bloqueados pelo empacotador.'
            )
        );

        $report = array(
            'success' => empty($blocked) && empty($critical['missing']),
            'blocked' => $blocked,
            'trees' => $reports,
            'checks' => $checks,
            'critical_classes' => $critical
        );

        $this->logPreflight($report, $throwOnBlock ? 'publish' : 'preview');

        if ($throwOnBlock && !empty($blocked)) {
            throw new Exception(
                "Publicação bloqueada: existem arquivos novos não rastreados no Git. " .
                "Inclua esses arquivos no commit ou confirme que devem ser ignorados antes de publicar. Arquivos: " .
                implode(', ', $blocked)
            );
        }

        if ($throwOnBlock && !empty($critical['missing'])) {
            throw new Exception('Publicacao bloqueada: classes criticas ausentes: ' . implode(', ', $critical['missing']));
        }

        return $report;
    }

    private function scanUntrackedCriticalPhp($name, $root): array
    {
        $root = realpath((string)$root);
        $blocked = array();
        $ignored = array();
        $all = array();

        if (!$root || !is_dir($root)) {
            return array('name' => $name, 'root' => (string)$root, 'available' => false, 'blocked' => $blocked, 'ignored' => $ignored, 'files' => $all);
        }
        if (!$this->hasGitMetadata($root)) {
            return array('name' => $name, 'root' => $root, 'available' => false, 'blocked' => $blocked, 'ignored' => $ignored, 'files' => $all);
        }

        $process = $this->runProcess('git -C ' . escapeshellarg($root) . ' status --porcelain --untracked-files=all', 30);
        if ($process['code'] !== 0) {
            throw new Exception('Falha ao consultar Git em ' . $name . ': ' . trim($process['stdout'] . ' ' . $process['stderr']));
        }

        $ignore = $this->deploymentIgnoreList($root);
        foreach (preg_split('/\r\n|\r|\n/', trim($process['stdout'])) as $line) {
            if ($line === '') continue;
            if (substr($line, 0, 2) !== '??') continue;

            $path = str_replace('\\', '/', trim(substr($line, 3), "\"' "));
            if ($path === '') continue;
            $all[] = $path;
            if (!$this->isCriticalUntrackedPhp($path)) continue;

            if ($this->isDeploymentIgnored($path, $ignore)) {
                $ignored[] = $path;
                continue;
            }

            $blocked[] = $path;
        }

        return array(
            'name' => $name,
            'root' => $root,
            'available' => true,
            'blocked' => array_values(array_unique($blocked)),
            'ignored' => array_values(array_unique($ignored)),
            'files' => array_values(array_unique($all))
        );
    }

    private function isCriticalUntrackedPhp($path): bool
    {
        $path = str_replace('\\', '/', ltrim((string)$path, '/'));
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'php') {
            return false;
        }

        foreach ($this->criticalUntrackedPrefixes() as $prefix) {
            if ($path === rtrim($prefix, '/') || strpos($path, rtrim($prefix, '/') . '/') === 0) {
                return true;
            }
        }

        return false;
    }

    private function criticalUntrackedPrefixes(): array
    {
        return array(
            'app',
            'vendor/API/php-classes/src',
            'routes',
            'config',
            'public',
            'updater',
            'migrations',
            'database'
        );
    }

    private function deploymentIgnoreList($root): array
    {
        $file = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'deployment-ignore.txt';
        if (!is_file($file)) {
            return array();
        }

        $items = array();
        foreach (file($file, FILE_IGNORE_NEW_LINES) ?: array() as $line) {
            $line = trim((string)$line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $items[] = str_replace('\\', '/', ltrim($line, '/'));
        }

        return array_values(array_unique($items));
    }

    private function isDeploymentIgnored($path, array $ignore): bool
    {
        $path = str_replace('\\', '/', ltrim((string)$path, '/'));
        foreach ($ignore as $item) {
            $item = str_replace('\\', '/', ltrim((string)$item, '/'));
            if ($item === '') continue;
            if (substr($item, -1) === '/') {
                if (strpos($path, rtrim($item, '/') . '/') === 0) {
                    return true;
                }
            } elseif ($path === $item) {
                return true;
            }
        }
        return false;
    }

    private function criticalClassesStatus(): array
    {
        $classes = $this->detectCriticalServiceClasses();
        $found = array();
        $missing = array();
        $map = array();

        foreach ($classes as $class) {
            foreach ($this->candidateClassFiles($class) as $relative) {
                if (is_file($this->path($relative))) {
                    $found[] = $relative;
                    $map[$class] = $relative;
                    continue 2;
                }
            }
            $missing[] = $class;
        }

        return array(
            'classes' => array_values($classes),
            'found' => array_values(array_unique($found)),
            'missing' => array_values(array_unique($missing)),
            'map' => $map
        );
    }

    private function detectCriticalServiceClasses(): array
    {
        $classes = array();
        $scanDirs = array('app/routes', 'app/services');
        $scanFiles = array('admin.php', 'admin-clientes.php', 'admin-funcionarios.php', 'admin-relatorio.php', 'admin-vendas.php', 'public/index.php');

        foreach ($scanDirs as $dir) {
            $base = $this->path($dir);
            if (!is_dir($base)) continue;
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($it as $item) {
                if (!$item->isFile() || strtolower($item->getExtension()) !== 'php') continue;
                $classes = array_merge($classes, $this->extractServiceClassNames((string)file_get_contents($item->getPathname())));
            }
        }

        foreach ($scanFiles as $file) {
            $path = $this->path($file);
            if (is_file($path)) {
                $classes = array_merge($classes, $this->extractServiceClassNames((string)file_get_contents($path)));
            }
        }

        return array_values(array_unique(array_filter($classes)));
    }

    private function extractServiceClassNames($code): array
    {
        $classes = array();
        if (preg_match_all('/(?:new\s+|::|class_exists\s*\(\s*[\'"])([A-Z][A-Za-z0-9_]*Service)\b/', (string)$code, $matches)) {
            foreach ($matches[1] as $class) {
                $classes[] = $class;
            }
        }
        return $classes;
    }

    private function candidateClassFiles($class): array
    {
        $class = preg_replace('/[^A-Za-z0-9_]/', '', (string)$class);
        if ($class === '') {
            return array();
        }
        return array(
            'app/services/' . $class . '.php',
            'vendor/API/php-classes/src/Service/' . $class . '.php',
            'vendor/API/php-classes/src/Services/' . $class . '.php'
        );
    }

    private function ignoreReason($relative): string
    {
        $relative = str_replace('\\', '/', ltrim((string)$relative, '/'));
        $basename = basename($relative);
        $protectedFiles = [
            '.env', '.env.local', '.env.production', '.env.prod', '.env.development', '.env.dev',
            'config.php', 'database.php', 'routes.php', 'config/database.php', 'config/routes.php',
            'app/config/database.php', 'mysql.cnf', 'updater/update-config.php', 'updater/version-local.json',
            'storage/config/system-settings.php', 'storage/config/update-publish.php', 'backup.lock',
        ];
        if (in_array($relative, $protectedFiles, true) || ($basename !== '.env.update' && preg_match('/^\.env(\.|$)/', $basename))) return 'arquivo sensivel ou configuracao local';

        foreach ([
            '.git/', '.agents/', '.codex/', 'cache/', 'node_modules/', 'temp/', 'tmp/', 'uploads/',
            'storage/', 'storage/logs/', 'storage/cache/', 'storage/backup/', 'storage/update-temp/',
            'logs/', 'views-cache/', 'vendor/', 'backups/', 'public/relatorios/', 'public/prato/relatorios/',
        ] as $dir) {
            if (stripos($relative, $dir) === 0) return 'diretorio protegido';
        }
        if (preg_match('/\.(sql|zip|tmp|temp|part|cache|log|bak)$/i', $basename)) return 'extensao temporaria, backup ou pacote';
        if (preg_match('/\.rtpl\.php$/i', $basename) || preg_match('/\.map$/i', $basename)) return 'arquivo gerado/cache';
        return '';
    }

    private function requiredFiles(): array
    {
        $required = [
            'public/index.php', 'config/env.php', 'config/php-compat.php',
            'app/routes/admin-clientes.php', 'app/routes/admin-funcionarios.php',
            'app/views/admin/clientes.html', 'app/views/admin/clientes-update.html',
            'app/views/admin/clientes-vulnerabilidade-update.html',
            'app/views/admin/funcionarios.html', 'app/views/admin/funcionarios-create.html',
            'app/views/admin/funcionarios-update.html', 'app/views/admin/funcionarios-password.html',
        ];
        $publicIndex = $this->path('public/index.php');
        if (is_file($publicIndex) && strpos((string)file_get_contents($publicIndex), 'hcode-overrides.php') !== false) {
            $required[] = 'app/core/hcode-overrides.php';
            $required[] = 'app/core/Hcode/Page.php';
            $required[] = 'app/core/Hcode/PageAdmin.php';
        }
        return array_values(array_unique($required));
    }

    private function requiredFilesStatus(): array
    {
        $found = [];
        $missing = [];
        foreach ($this->requiredFiles() as $file) {
            is_file($this->path($file)) ? $found[] = $file : $missing[] = $file;
        }
        return ['found' => $found, 'missing' => $missing];
    }

    private function validatePhpFiles(array $files): void
    {
        $php = trim((string)($this->config['php_cli_path'] ?? 'php')) ?: 'php';
        $errors = [];
        foreach ($files as $file) {
            if (strtolower(pathinfo($file['path'], PATHINFO_EXTENSION)) !== 'php') continue;
            $path = $this->path($file['path']);
            $cmd = escapeshellarg($php) . ' -l ' . escapeshellarg($path);
            $process = $this->runProcess($cmd, 30);
            $output = trim($process['stdout'] . "\n" . $process['stderr']);
            $filtered = $this->filterPhpOutput($output);
            $this->log('VALIDACAO PHP arquivo=' . $file['path'] . ' | comando=' . $cmd . ' | retorno=' . $process['code'] . ' | stdout=' . $process['stdout'] . ' | stderr=' . $process['stderr']);
            if (preg_match('/(?:PHP\s+)?Parse error|Errors parsing|Fatal error:.*syntax error/i', $filtered)) {
                $errors[] = $file['path'] . ': ' . $filtered;
            }
        }
        if (!empty($errors)) throw new Exception('Validacao PHP falhou: ' . implode(' | ', $errors));
    }

    private function filterPhpOutput(string $output): string
    {
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', $output) as $line) {
            if (preg_match('/AH\d{5}|mpm_winnt|Apache|Child: Unable to retrieve my generation/i', $line)) continue;
            $lines[] = $line;
        }
        return trim(implode("\n", $lines));
    }

    private function createZip($zipPath, array $files): array
    {
        if (!class_exists('ZipArchive')) throw new Exception('Extensao ZipArchive nao esta habilitada.');
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new Exception('Nao foi possivel criar o ZIP.');
        $total = 0;
        foreach ($files as $file) {
            $relative = str_replace('\\', '/', ltrim((string)$file['path'], '/'));
            $path = $this->path($relative);
            if (!is_file($path)) continue;
            $zip->addFile($path, $relative);
            $this->log('ZIP incluido | arquivo=' . $relative);
            $total++;
        }
        $envUpdate = $this->buildEnvUpdateContent();
        if ($envUpdate !== '') {
            $zip->addFromString('.env.update', $envUpdate);
            $this->log('ZIP incluido | arquivo=.env.update | variaveis=' . implode(',', $this->envGlobalKeys()));
            $total++;
        }
        $zip->close();
        if ($total === 0 || !is_file($zipPath)) throw new Exception('O ZIP nao recebeu arquivos validos.');
        return ['files' => $total, 'size' => filesize($zipPath), 'sha256' => hash_file('sha256', $zipPath)];
    }

    private function validateZipRequiredFiles($zipPath, array $required): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) throw new Exception('Nao foi possivel abrir o ZIP para validacao.');
        $missing = [];
        foreach ($required as $file) {
            if ($zip->locateName($file) === false) $missing[] = $file;
        }
        $blocked = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string)$zip->getNameIndex($i));
            $base = basename($name);
            if ($name === '' || $name[0] === '/' || preg_match('/^[a-zA-Z]:/', $name) || strpos($name, '../') !== false || strpos($name, '/..') !== false) {
                $blocked[] = $name . ' (caminho inseguro)';
                continue;
            }
            if ($base !== '.env.update' && preg_match('/^\.env(?:\.|$)/i', $base)) {
                $blocked[] = $name . ' (configuracao local)';
                continue;
            }
            if (preg_match('/\.(zip|bak|tmp|temp|part|log)$/i', $base) || preg_match('/\.rtpl\.php$/i', $base)) {
                $blocked[] = $name . ' (arquivo temporario/sensivel)';
            }
        }
        $zip->close();
        if (!empty($missing)) throw new Exception('Publicacao cancelada: arquivos obrigatorios nao entraram no ZIP: ' . implode(', ', $missing));
        if (!empty($blocked)) throw new Exception('Publicacao cancelada: ZIP contem arquivos inseguros: ' . implode(', ', $blocked));
    }

    private function buildEnvUpdateContent(): string
    {
        $lines = [
            '# Configuracoes globais distribuidas pelo pacote de atualizacao.',
            '# Aplicadas sem sobrescrever configuracoes locais protegidas da unidade.',
            '# Gerado em ' . date('Y-m-d H:i:s'),
        ];
        $added = 0;

        foreach ($this->envGlobalKeys() as $key) {
            if ($this->isSecretKey($key)) {
                $this->log('ENV_UPDATE bloqueado | variavel sensivel=' . $key);
                continue;
            }

            $value = $this->envValue($key);
            if ($value === null || $value === '') {
                $this->log('ENV_UPDATE ignorado | variavel ausente=' . $key);
                continue;
            }
            $lines[] = $key . '=' . $this->formatEnvValue($value);
            $this->log('ENV_UPDATE incluida | variavel=' . $key . ' | valor=' . ($this->isSecretKey($key) ? '[mascarado]' : $value));
            $added++;
        }

        return $added > 0 ? implode(PHP_EOL, $lines) . PHP_EOL : '';
    }

    private function envGlobalKeys(): array
    {
        $keys = $this->config['env_global_keys'] ?? [];
        if (is_string($keys)) {
            $keys = explode(',', $keys);
        }
        return array_values(array_unique(array_filter(array_map(function ($key) {
            $key = strtoupper(trim((string)$key));
            return preg_match('/^[A-Z0-9_]+$/', $key) ? $key : '';
        }, is_array($keys) ? $keys : []))));
    }

    private function envValue(string $key)
    {
        if ($key === 'PHP_CLI_PATH') {
            $clientPhp = function_exists('portal_env') ? portal_env('UPDATE_CLIENT_PHP_CLI_PATH', null) : getenv('UPDATE_CLIENT_PHP_CLI_PATH');
            if ($clientPhp !== null && $clientPhp !== false && trim((string)$clientPhp) !== '') {
                return (string)$clientPhp;
            }
        }

        $source = $this->sourceEnvValues();
        if (array_key_exists($key, $source)) {
            return $source[$key];
        }
        if (function_exists('portal_env')) {
            $value = portal_env($key, null);
            if ($value !== null && $value !== '') {
                return (string)$value;
            }
        }
        $value = getenv($key);
        return $value !== false ? (string)$value : null;
    }

    private function sourceEnvValues(): array
    {
        static $values = null;
        if ($values !== null) {
            return $values;
        }

        $values = [];
        $path = $this->sourceRoot . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($path)) {
            return $values;
        }

        foreach (preg_split('/\r\n|\r|\n/', (string)file_get_contents($path)) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = strtoupper(trim($key));
            if (!preg_match('/^[A-Z0-9_]+$/', $key)) {
                continue;
            }
            $value = trim($value);
            if ((strlen($value) >= 2) && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
                $value = substr($value, 1, -1);
            }
            $values[$key] = $value;
        }

        return $values;
    }

    private function formatEnvValue(string $value): string
    {
        if ($value === '') return '""';
        if (preg_match('/^[A-Za-z0-9_@.\/:+=,-]+$/', $value)) return $value;
        return '"' . addcslashes($value, "\\\"") . '"';
    }

    private function isSecretKey(string $key): bool
    {
        return preg_match('/PASS|PASSWORD|SECRET|TOKEN|KEY/i', $key) === 1;
    }

    private function uploadFile($localPath, $remoteName): array
    {
        if (!is_file($localPath)) throw new Exception('Arquivo nao encontrado para upload: ' . $localPath);
        $driver = strtolower((string)($this->config['driver'] ?? 'local'));
        if ($driver === 'local') return ['success' => true, 'driver' => 'local', 'remote_file' => $localPath];
        if ($driver === 'ftp') {
            $conn = $this->connectFtp();
            $dir = $this->ftpRemoteDir();
            if ($dir !== '') {
                $this->ensureFtpDir($conn, $dir);
                @ftp_chdir($conn, $dir);
            }
            if (!@ftp_put($conn, $remoteName, $localPath, FTP_BINARY)) {
                @ftp_close($conn);
                throw new Exception('Falha ao enviar por FTP: ' . $remoteName);
            }
            @ftp_close($conn);
            return ['success' => true, 'driver' => 'ftp', 'remote_file' => trim($dir . '/' . $remoteName, '/')];
        }
        if ($driver === 'http') return $this->uploadHttp($localPath, $remoteName);
        if ($driver === 'sftp') {
            $sftp = $this->connectSftp();
            $dir = $this->sftpRemoteDir();
            $this->ensureSftpDir($sftp, $dir);
            $remote = $dir . '/' . $remoteName;
            if (!$sftp->put($remote, $localPath, SFTP::SOURCE_LOCAL_FILE)) {
                throw new Exception('Falha ao enviar por SFTP: ' . $remoteName);
            }
            return ['success' => true, 'driver' => 'sftp', 'remote_file' => $remote];
        }
        throw new Exception('Driver de publicacao invalido: ' . $driver);
    }

    private function uploadFiles(array $files): array
    {
        $driver = strtolower((string)($this->config['driver'] ?? 'local'));
        if ($driver !== 'sftp') {
            $result = [];
            foreach ($files as $file) {
                $name = (string)($file['name'] ?? '');
                $result[$name] = $this->uploadFile((string)($file['path'] ?? ''), $name);
            }
            return $result;
        }

        $sftp = $this->connectSftp();
        $dir = $this->sftpRemoteDir();
        $this->ensureSftpDir($sftp, $dir);

        $result = [];
        foreach ($files as $file) {
            $localPath = (string)($file['path'] ?? '');
            $remoteName = (string)($file['name'] ?? '');
            if (!is_file($localPath)) {
                throw new Exception('Arquivo nao encontrado para upload: ' . $localPath);
            }
            if ($remoteName === '') {
                throw new Exception('Nome remoto de upload nao informado.');
            }

            $remote = $dir . '/' . $remoteName;
            if (!$sftp->put($remote, $localPath, SFTP::SOURCE_LOCAL_FILE)) {
                throw new Exception('Falha ao enviar por SFTP: ' . $remoteName);
            }
            $result[$remoteName] = ['success' => true, 'driver' => 'sftp', 'remote_file' => $remote];
        }

        return $result;
    }

    private function connectSftp(): SFTP
    {
        if (!class_exists(SFTP::class)) {
            throw new Exception('phpseclib nao esta instalado para publicacao SFTP.');
        }

        $host = trim((string)($this->config['sftp_host'] ?? ''));
        $user = trim((string)($this->config['sftp_user'] ?? ''));
        $pass = (string)($this->config['sftp_password'] ?? '');
        $port = (int)($this->config['sftp_port'] ?? 22);

        if ($host === '' || $user === '' || $pass === '') {
            throw new Exception('SFTP de publicacao nao configurado. Configure UPDATE_PUBLISH_SFTP_* no .env.');
        }

        if (preg_match('/^https?:\/\//i', $host) === 1) {
            throw new Exception('Host SFTP invalido: informe apenas o dominio ou IP, sem http/https.');
        }

        try {
            $sftp = new SFTP($host, $port, 15);
            if (!$sftp->login($user, $pass)) {
                throw new Exception('Falha ao autenticar no SFTP de publicacao.');
            }
        } catch (Throwable $e) {
            $message = $e->getMessage();
            if (stripos($message, 'identification string') !== false) {
                throw new Exception('Nao foi possivel iniciar SFTP em ' . $host . ':' . $port . '. Confira se este host/porta aceita SSH/SFTP; se for FTP comum, use UPDATE_PUBLISH_DRIVER=ftp. Detalhe: ' . $message);
            }
            throw new Exception('Falha na conexao SFTP em ' . $host . ':' . $port . '. Detalhe: ' . $message);
        }

        return $sftp;
    }

    private function sftpRemoteDir(): string
    {
        $dir = trim((string)(($this->config['sftp_remote_dir'] ?? '') ?: ($this->config['remote_dir'] ?? '')));
        if ($dir === '') {
            throw new Exception('Diretorio remoto SFTP nao configurado.');
        }
        return rtrim($dir, '/');
    }

    private function ensureSftpDir(SFTP $sftp, string $remoteDir): void
    {
        $current = '';
        foreach (explode('/', trim($remoteDir, '/')) as $part) {
            if ($part === '') continue;
            $current .= '/' . $part;
            if (!$sftp->is_dir($current) && !$sftp->mkdir($current)) {
                throw new Exception('Nao foi possivel criar diretorio SFTP: ' . $current);
            }
        }
    }

    private function connectFtp()
    {
        if (!function_exists('ftp_connect')) throw new Exception('Extensao FTP indisponivel no PHP.');
        $host = trim((string)($this->config['ftp_host'] ?? ''));
        $user = trim((string)($this->config['ftp_user'] ?? ''));
        $pass = (string)($this->config['ftp_password'] ?? '');
        if ($host === '' || $user === '' || $pass === '') throw new Exception('FTP de publicacao nao configurado.');
        $conn = !empty($this->config['ftp_ssl']) && function_exists('ftp_ssl_connect')
            ? @ftp_ssl_connect($host, (int)$this->config['ftp_port'], 15)
            : @ftp_connect($host, (int)$this->config['ftp_port'], 15);
        if (!$conn || !@ftp_login($conn, $user, $pass)) throw new Exception('Falha ao autenticar no FTP de publicacao.');
        @ftp_pasv($conn, true);
        return $conn;
    }

    private function ftpRemoteDir(): string
    {
        return trim((string)(($this->config['ftp_remote_dir'] ?? '') ?: ($this->config['remote_dir'] ?? '')), '/');
    }

    private function ensureFtpDir($conn, string $remoteDir): void
    {
        $current = '';
        foreach (explode('/', trim($remoteDir, '/')) as $part) {
            if ($part === '') continue;
            $current .= '/' . $part;
            if (!@ftp_chdir($conn, $current)) @ftp_mkdir($conn, $current);
        }
    }

    private function uploadHttp($localPath, $remoteName): array
    {
        if (!function_exists('curl_init')) throw new Exception('cURL indisponivel para upload HTTP.');
        $url = trim((string)($this->config['http_upload_url'] ?? ''));
        if ($url === '') throw new Exception('UPDATE_PUBLISH_HTTP_UPLOAD_URL nao configurado.');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['file' => new CURLFile($localPath, 'application/octet-stream', $remoteName), 'name' => $remoteName, 'token' => (string)($this->config['http_upload_token'] ?? '')],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Expect:'],
        ]);
        $response = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($response === false || $http < 200 || $http >= 300) throw new Exception('Upload HTTP falhou. HTTP=' . $http . ' erro=' . $error . ' resposta=' . trim((string)$response));
        return ['success' => true, 'driver' => 'http', 'response' => trim((string)$response)];
    }

    private function ensureVersionDoesNotExist($version): void
    {
        foreach (['version.json', 'version-lite.json'] as $name) {
            $path = $this->updatesDir . DIRECTORY_SEPARATOR . $name;
            if (!is_file($path)) continue;
            $json = json_decode((string)file_get_contents($path), true);
            if (is_array($json) && (string)($json['version'] ?? '') === (string)$version) throw new Exception('A versao ' . $version . ' ja esta publicada em ' . $name . '.');
        }
    }

    private function buildVersionJson($version, $zipName, $url, $changelog, $sha256): array
    {
        return ['version' => $version, 'file' => $zipName, 'url' => $url, 'published_at' => date('Y-m-d H:i:s'), 'notes' => $changelog, 'changelog' => $changelog, 'sha256' => $sha256, 'checksum_sha256' => $sha256];
    }

    private function writeJsonFile($name, array $data): void
    {
        $path = $this->updatesDir . DIRECTORY_SEPARATOR . $name;
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents($path, $json . PHP_EOL) === false) throw new Exception('Nao foi possivel gravar ' . $name . '.');
    }

    private function backupVersionFiles($version): array
    {
        $backupDir = $this->updatesDir . DIRECTORY_SEPARATOR . 'version-backups';
        if (!is_dir($backupDir)) @mkdir($backupDir, 0775, true);
        $stamp = date('Ymd-His') . '-antes-v' . preg_replace('/[^a-zA-Z0-9_.-]+/', '-', $version);
        $backups = [];
        foreach (['version.json', 'version-lite.json'] as $name) {
            $source = $this->updatesDir . DIRECTORY_SEPARATOR . $name;
            $target = $backupDir . DIRECTORY_SEPARATOR . $stamp . '-' . $name;
            if (is_file($source) && !copy($source, $target)) throw new Exception('Nao foi possivel criar backup de ' . $name . '.');
            $backups[$name] = is_file($source) ? $target : null;
        }
        return $backups;
    }

    private function restoreVersionBackups(array $backups): void
    {
        foreach ($backups as $name => $backup) {
            if ($backup && is_file($backup)) @copy($backup, $this->updatesDir . DIRECTORY_SEPARATOR . $name);
        }
    }

    private function registerPublishedVersion(array $data): void
    {
        try {
            $sql = new Hcode\DB\Sql();
            $sql->query("
                INSERT INTO tb_atualizacoes (versao, arquivo_zip, url_download, changelog, checksum_sha256, publicado_em, created_at)
                VALUES (:versao, :arquivo, :url, :changelog, :checksum, :publicado, NOW())
                ON DUPLICATE KEY UPDATE arquivo_zip = VALUES(arquivo_zip), url_download = VALUES(url_download), changelog = VALUES(changelog), checksum_sha256 = VALUES(checksum_sha256), publicado_em = VALUES(publicado_em)
            ", [
                ':versao' => $data['version'],
                ':arquivo' => $data['file'],
                ':url' => $data['url'],
                ':changelog' => $data['changelog'],
                ':checksum' => $data['checksum_sha256'],
                ':publicado' => $data['published_at'],
            ]);
        } catch (Exception $e) {
            $this->log('WARN registro tb_atualizacoes falhou: ' . $e->getMessage());
        }
    }

    private function downloadUrl($zipName): string
    {
        $base = rtrim((string)($this->config['public_base_url'] ?? ''), '/');
        if ($base === '') throw new Exception('UPDATE_PUBLISH_PUBLIC_BASE_URL nao configurado.');
        return $base . '/' . rawurlencode($zipName);
    }

    private function normalizeVersion($version): string
    {
        $version = trim((string)$version);
        if (!preg_match('/^\d+(?:\.\d+){1,3}(?:[-.][a-zA-Z0-9]+)?$/', $version)) throw new Exception('Informe uma versao valida, exemplo: 1.0.4.');
        return $version;
    }

    private function path(string $relative): string
    {
        return $this->sourceRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($relative, '/'));
    }

    private function runProcess($command, $timeout): array
    {
        $descriptor = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open($command, $descriptor, $pipes);
        if (!is_resource($process)) throw new Exception('Nao foi possivel executar comando: ' . $command);
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $stdout = '';
        $stderr = '';
        $start = time();
        $exitCode = null;
        while (true) {
            $stdout .= (string)stream_get_contents($pipes[1]);
            $stderr .= (string)stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (empty($status['running'])) {
                if (isset($status['exitcode']) && $status['exitcode'] !== -1) $exitCode = (int)$status['exitcode'];
                break;
            }
            if ((time() - $start) > $timeout) {
                @proc_terminate($process);
                break;
            }
            usleep(50000);
        }
        $stdout .= (string)stream_get_contents($pipes[1]);
        $stderr .= (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        return ['code' => $exitCode !== null ? $exitCode : (int)$code, 'stdout' => trim($stdout), 'stderr' => trim($stderr)];
    }

    private function logPreview(array $included, array $ignored, array $required): void
    {
        $this->log('PREVIEW incluidos=' . count($included) . ' | ignorados=' . count($ignored));
        foreach ($included as $file) $this->log('PREVIEW incluido | status=' . ($file['status'] ?? '') . ' | arquivo=' . $file['path']);
        foreach ($ignored as $file) $this->log('PREVIEW ignorado | motivo=' . ($file['reason'] ?? '') . ' | arquivo=' . $file['path']);
        foreach ($required['found'] as $file) $this->log('PREVIEW obrigatorio encontrado | arquivo=' . $file);
        foreach ($required['missing'] as $file) $this->log('PREVIEW obrigatorio ausente | arquivo=' . $file);
    }

    private function logPreflight(array $report, $action): void
    {
        $blocked = isset($report['blocked']) && is_array($report['blocked']) ? $report['blocked'] : array();
        $status = empty($blocked) && !empty($report['success']) ? 'liberada' : 'bloqueada';
        $this->log(
            'PREFLIGHT ' . $action .
            ' | usuario=' . $this->currentUserLabel() .
            ' | status=' . $status .
            ' | bloqueados=' . (empty($blocked) ? '-' : implode(',', $blocked))
        );

        if (isset($report['trees']) && is_array($report['trees'])) {
            foreach ($report['trees'] as $tree) {
                $this->log(
                    'PREFLIGHT arvore=' . (isset($tree['name']) ? $tree['name'] : '-') .
                    ' | untracked=' . (isset($tree['files']) && is_array($tree['files']) ? count($tree['files']) : 0) .
                    ' | bloqueados=' . (isset($tree['blocked']) && is_array($tree['blocked']) ? implode(',', $tree['blocked']) : '') .
                    ' | ignorados=' . (isset($tree['ignored']) && is_array($tree['ignored']) ? implode(',', $tree['ignored']) : '')
                );
            }
        }
    }

    private function currentUserLabel(): string
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        $candidates = array();
        if (isset($_SESSION['User']) && is_array($_SESSION['User'])) {
            $candidates[] = $_SESSION['User'];
        }
        if (class_exists('Hcode\\Model\\Funcionarios')) {
            try {
                $user = \Hcode\Model\Funcionarios::getFromSession();
                if ($user && method_exists($user, 'getValues')) {
                    $values = $user->getValues();
                    if (is_array($values)) {
                        $candidates[] = $values;
                    }
                }
            } catch (Throwable $e) {
                // usuario indisponivel fora do contexto web
            }
        }

        foreach ($candidates as $data) {
            foreach (array('desnome', 'nome', 'name', 'login', 'email', 'deslogin') as $key) {
                if (!empty($data[$key])) {
                    return (string)$data[$key];
                }
            }
            foreach (array('id_usuario', 'id', 'id_funcionario') as $key) {
                if (!empty($data[$key])) {
                    return 'id=' . (string)$data[$key];
                }
            }
        }

        return PHP_SAPI === 'cli' ? 'cli' : 'nao_identificado';
    }

    private function log(string $message): void
    {
        $dir = LOG_DIR;
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $file = $this->config['log_file'] ?? 'update-publish.log';
        @file_put_contents($dir . DIRECTORY_SEPARATOR . $file, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND);
    }
}
