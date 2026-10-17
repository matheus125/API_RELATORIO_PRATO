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
        $dbVersion = $this->latestDatabaseVersion();
        if ($dbVersion !== '') {
            return $dbVersion;
        }

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

    public function suggestNextVersion(): string
    {
        $version = $this->currentVersion();
        if ($version === '' || $version === '0.0.0') {
            $version = '1.000';
        }

        $candidate = $this->incrementVersion($version);
        $guard = 0;
        while ($this->versionExists($candidate) && $guard < 1000) {
            $candidate = $this->incrementVersion($candidate);
            $guard++;
        }

        if ($guard >= 1000) {
            throw new Exception('Nao foi possivel encontrar uma versao livre automaticamente.');
        }

        return $candidate;
    }

    public function preview(): array
    {
        $this->requireSourceRoot();

        $included = [];
        $ignored = [];
        $includedMap = [];
        $preflight = $this->buildPreflightReport(false);
        $previousManifest = $this->latestManifest();

        foreach ($this->detectChangedFiles() as $file) {
            $file = $this->normalizeDetectedFile($file, $previousManifest);
            $reason = $this->ignoreReason($file['path']);
            if ($reason !== '') {
                $file['reason'] = $reason;
                $file['include'] = false;
                $ignored[] = $file;
                continue;
            }

            if (!is_file($this->path($file['path']))) {
                if (($file['status_type'] ?? '') === 'removed') {
                    $file['include'] = false;
                    $file['reason'] = 'arquivo removido sera registrado no manifesto';
                    $ignored[] = $file;
                } else {
                    $file['reason'] = 'arquivo removido ou inexistente';
                    $file['include'] = false;
                    $ignored[] = $file;
                }
                continue;
            }

            $file['include'] = true;
            $included[] = $file;
            $includedMap[$file['path']] = true;
        }

        $required = $this->requiredFilesStatus();
        $critical = $this->criticalClassesStatus();
        foreach ($critical['found'] as $path) {
            if (!isset($includedMap[$path])) {
                $file = $this->normalizeDetectedFile(['path' => $path, 'status' => 'classe'], $previousManifest);
                $file['include'] = true;
                $included[] = $file;
                $includedMap[$path] = true;
            }
        }

        $required['found'] = array_values(array_unique(array_merge($required['found'], $critical['found'])));
        $required['missing'] = array_values(array_unique(array_merge($required['missing'], $critical['missing'])));

        foreach ($required['found'] as $path) {
            if (!isset($includedMap[$path])) {
                $file = $this->normalizeDetectedFile(['path' => $path, 'status' => 'obrigatorio'], $previousManifest);
                $file['include'] = true;
                $included[] = $file;
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
            'source_audit' => $this->sourceAudit(),
            'included' => $included,
            'ignored' => $ignored,
            'required' => $required,
            'critical_classes' => $critical,
            'preflight' => $preflight,
            'previous_manifest' => $previousManifest ? [
                'version' => (string)($previousManifest['version'] ?? ''),
                'published_at' => (string)($previousManifest['published_at'] ?? ''),
                'files_total' => isset($previousManifest['files']) && is_array($previousManifest['files']) ? count($previousManifest['files']) : 0,
            ] : null,
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
        $this->log('PUBLICACAO inicio | versao=' . $version . ' | usuario=' . $this->currentUserLabel());
        $this->validatePhpFiles($preview['included']);
        $manifest = $this->buildUpdateManifest($version, $changelog, $zipName, $preview);
        $zip = $this->createZip($zipPath, $preview['included'], $manifest);
        $manifest['package']['size'] = $zip['size'];
        $manifest['package']['sha256'] = $zip['sha256'];
        $manifest['package']['path'] = $zipPath;
        $manifest['package']['url'] = $this->downloadUrl($zipName);
        $signature = $this->signPackage($zipPath, $zipName, $zip['sha256'], (int)$zip['size']);
        if (!empty($signature['enabled'])) {
            $manifest['package']['signature'] = $signature;
        }
        $this->writeManifestFile($version, $manifest);
        $this->validateZipRequiredFiles($zipPath, $preview['required']['found']);
        $postValidation = $this->validatePublishedZip($zipPath, $preview['included'], $manifest, $zip['sha256']);

        $url = $this->downloadUrl($zipName);
        $jsonData = $this->buildVersionJson($version, $zipName, $url, $changelog, $zip['sha256'], $zip['size'], $signature);
        $backups = $this->backupVersionFiles($version);

        $zipUpload = ['success' => false];
        $jsonStatus = ['local' => false, 'remote' => false];
        try {
            $this->writeJsonFile('version.json', $jsonData);
            $this->writeJsonFile('version-lite.json', $jsonData);
            $jsonStatus['local'] = true;

            $uploads = $this->uploadFiles([
                ['path' => $zipPath, 'name' => $zipName],
                ['path' => (string)($signature['signature_path'] ?? ''), 'name' => basename((string)($signature['signature_path'] ?? ''))],
                ['path' => (string)($signature['public_key_path'] ?? ''), 'name' => basename((string)($signature['public_key_path'] ?? ''))],
                ['path' => $this->updatesDir . DIRECTORY_SEPARATOR . 'version.json', 'name' => 'version.json'],
                ['path' => $this->updatesDir . DIRECTORY_SEPARATOR . 'version-lite.json', 'name' => 'version-lite.json'],
                ['path' => $this->manifestPath($version), 'name' => 'manifest-v' . $version . '.json'],
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
            'signature' => $signature,
            'url' => $url,
            'included' => $preview['included'],
            'ignored' => $preview['ignored'],
            'upload' => $zipUpload,
            'json_status' => $jsonStatus,
            'json_backups' => $backups,
            'preflight' => $preflight,
            'manifest' => [
                'path' => $this->manifestPath($version),
                'url' => $this->downloadBaseUrl() . '/manifest-v' . rawurlencode($version) . '.json',
                'files_total' => count($manifest['files']),
            ],
            'post_validation' => $postValidation,
        ];
        $this->log('SUCESSO publicacao versao=' . $version . ' | zip=' . $zipName . ' | url=' . $url);
        $this->logPublicationAudit($result);
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

    private function normalizeDetectedFile(array $file, ?array $previousManifest = null): array
    {
        $path = $this->normalizeRelativePath((string)($file['path'] ?? ''));
        $status = trim((string)($file['status'] ?? ''));
        $fullPath = $path !== '' ? $this->path($path) : '';
        $exists = $path !== '' && is_file($fullPath);
        $hash = $exists ? hash_file('sha256', $fullPath) : '';
        $size = $exists ? (int)filesize($fullPath) : 0;
        $mtime = $exists ? portal_format_manaus(filemtime($fullPath)) : '';
        $previous = $this->previousManifestFile($previousManifest, $path);
        $statusType = $this->statusType($status, $exists, $previous, $hash);

        return array_merge($file, array(
            'path' => $path,
            'status' => $status !== '' ? $status : $statusType,
            'status_type' => $statusType,
            'exists' => $exists,
            'include' => false,
            'size' => $size,
            'size_human' => $this->formatBytes($size),
            'sha256' => $hash,
            'mtime' => $mtime,
            'previous_sha256' => is_array($previous) ? (string)($previous['sha256'] ?? '') : '',
            'previous_size' => is_array($previous) ? (int)($previous['size'] ?? 0) : 0,
        ));
    }

    private function normalizeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path, "\"' \t\r\n"));
        $path = preg_replace('#/+#', '/', $path);
        return ltrim((string)$path, '/');
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2, ',', '.') . ' KB';
        }
        return $bytes . ' B';
    }

    private function statusType(string $gitStatus, bool $exists, ?array $previous, string $hash): string
    {
        $status = strtoupper(str_replace(' ', '', $gitStatus));
        if (!$exists || strpos($status, 'D') !== false) {
            return 'removed';
        }
        if (strpos($status, '??') !== false || strpos($status, 'A') !== false || $previous === null) {
            return 'new';
        }
        $oldHash = (string)($previous['sha256'] ?? '');
        if ($oldHash !== '' && $hash !== '' && !hash_equals($oldHash, $hash)) {
            return 'modified';
        }
        if (strpos($status, 'M') !== false || strpos($status, 'R') !== false || strpos($status, 'C') !== false) {
            return 'modified';
        }
        if ($gitStatus === 'obrigatorio' || $gitStatus === 'classe') {
            return 'required';
        }
        return 'unchanged';
    }

    private function previousManifestFile(?array $manifest, string $path): ?array
    {
        if (!$manifest || empty($manifest['files']) || !is_array($manifest['files'])) {
            return null;
        }
        foreach ($manifest['files'] as $file) {
            if (isset($file['path']) && $this->normalizeRelativePath((string)$file['path']) === $path) {
                return is_array($file) ? $file : null;
            }
        }
        return null;
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
        $dashboardFixes = $this->adminDashboardFixStatus();
        $sourceSync = $this->sourceSyncPreflight();
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
                'key' => 'admin_dashboard_fixes',
                'label' => 'Correcoes do admin presentes',
                'ok' => empty($dashboardFixes['missing']),
                'message' => empty($dashboardFixes['missing'])
                    ? 'A arvore fonte contem as correcoes de JSON, /admin/api e cache de views.'
                    : 'A arvore fonte do prato_web esta desatualizada: ' . implode(', ', $dashboardFixes['missing'])
            ),
            array(
                'key' => 'env_protected',
                'label' => '.env protegido',
                'ok' => true,
                'message' => '.env e variacoes locais continuam bloqueados pelo empacotador.'
            ),
            array(
                'key' => 'source_sync',
                'label' => 'Fonte sincronizada e auditavel',
                'ok' => !empty($sourceSync['success']),
                'message' => (string)($sourceSync['message'] ?? 'Status da sincronizacao indisponivel.')
            )
        );

        $report = array(
            'success' => empty($blocked) && empty($critical['missing']) && empty($dashboardFixes['missing']) && !empty($sourceSync['success']),
            'blocked' => $blocked,
            'trees' => $reports,
            'checks' => $checks,
            'critical_classes' => $critical,
            'admin_dashboard_fixes' => $dashboardFixes,
            'source_sync' => $sourceSync
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

        if ($throwOnBlock && !empty($dashboardFixes['missing'])) {
            throw new Exception(
                'Publicacao bloqueada: a copia fonte do prato_web usada pelo publicador esta desatualizada. ' .
                'Pendencias: ' . implode(', ', $dashboardFixes['missing'])
            );
        }

        if ($throwOnBlock && empty($sourceSync['success'])) {
            throw new Exception('Publicacao bloqueada: sincronize a fonte oficial do prato_web antes de publicar. ' . (string)($sourceSync['message'] ?? ''));
        }

        return $report;
    }

    private function sourceSyncPreflight(): array
    {
        if (empty($this->config['require_source_sync'])) {
            return array(
                'success' => true,
                'message' => 'Validacao de sincronizacao desativada por configuracao.',
                'required' => false
            );
        }

        $path = __DIR__ . DIRECTORY_SEPARATOR . 'SourceSyncService.php';
        if (!class_exists('SourceSyncService') && is_file($path)) {
            require_once $path;
        }

        if (!class_exists('SourceSyncService')) {
            return array(
                'success' => false,
                'message' => 'Servico de sincronizacao da fonte nao encontrado.',
                'required' => true
            );
        }

        try {
            $service = new SourceSyncService($this->config);
            return $service->validateForPublish();
        } catch (Throwable $e) {
            $this->log('WARN source sync preflight falhou: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => 'Falha ao validar sincronizacao da fonte: ' . $e->getMessage(),
                'required' => true
            );
        }
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
            'admin.php',
            'public/index.php', 'config/env.php', 'config/php-compat.php',
            'app/helpers/view-cache.php',
            'app/routes/admin.php',
            'app/routes/admin-relatorio.php',
            'app/routes/admin-clientes.php', 'app/routes/admin-funcionarios.php',
            'app/views/admin/header.html', 'app/views/admin/footer.html', 'app/views/admin/index.html',
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

    private function sourceAudit(): array
    {
        $audit = array(
            'root' => $this->sourceRoot ?: '',
            'available' => $this->hasSourceRoot(),
            'git_head' => '',
            'git_branch' => '',
            'git_status_count' => 0,
            'files' => array()
        );

        if (!$this->hasSourceRoot()) {
            return $audit;
        }

        if ($this->hasGitMetadata($this->sourceRoot)) {
            $head = $this->runProcess('git -C ' . escapeshellarg($this->sourceRoot) . ' log -1 --format=%H', 10);
            $branch = $this->runProcess('git -C ' . escapeshellarg($this->sourceRoot) . ' rev-parse --abbrev-ref HEAD', 10);
            $status = $this->runProcess('git -C ' . escapeshellarg($this->sourceRoot) . ' status --porcelain', 10);
            $audit['git_head'] = trim($head['stdout']);
            $audit['git_branch'] = trim($branch['stdout']);
            $audit['git_status_count'] = $status['stdout'] === '' ? 0 : count(preg_split('/\r\n|\r|\n/', trim($status['stdout'])));
        }

        foreach ($this->auditProbeFiles() as $file) {
            $path = $this->path($file);
            $audit['files'][] = array(
                'path' => $file,
                'exists' => is_file($path),
                'mtime' => is_file($path) ? portal_format_manaus(filemtime($path)) : '',
                'sha256' => is_file($path) ? hash_file('sha256', $path) : ''
            );
        }

        return $audit;
    }

    private function auditProbeFiles(): array
    {
        return array(
            'app/views/admin/footer.html',
            'app/views/admin/header.html',
            'app/views/admin/index.html',
            'app/routes/admin.php',
            'app/routes/admin-relatorio.php',
            'app/helpers/view-cache.php',
            'app/core/Hcode/Page.php'
        );
    }

    private function adminDashboardFixStatus(): array
    {
        $checks = array(
            array(
                'file' => 'app/views/admin/footer.html',
                'pattern' => "/fetchJson\\('\\/admin\\/api\\/total-titulares'\\)/",
                'label' => 'footer.html usando /admin/api/total-titulares'
            ),
            array(
                'file' => 'app/views/admin/footer.html',
                'pattern' => "/fetchJson\\('\\/admin\\/api\\/usuarios-titulares'\\)/",
                'label' => 'footer.html usando /admin/api/usuarios-titulares'
            ),
            array(
                'file' => 'app/views/admin/header.html',
                'pattern' => '/legacyApiAliases/',
                'label' => 'header.html com aliases de API antiga'
            ),
            array(
                'file' => 'app/views/admin/header.html',
                'pattern' => '/Response\\.prototype\\.json/',
                'label' => 'header.html com protecao de JSON vazio'
            ),
            array(
                'file' => 'app/helpers/view-cache.php',
                'pattern' => '/PC_VIEW_CACHE_SIGNATURE/',
                'label' => 'view-cache.php com assinatura de cache'
            ),
            array(
                'file' => 'app/core/Hcode/Page.php',
                'pattern' => '/views_signature/',
                'label' => 'Page.php usando assinatura no cache de views'
            ),
            array(
                'file' => 'admin.php',
                'pattern' => '/public\\/index\\.php/',
                'label' => 'admin.php raiz encaminhando para public/index.php'
            )
        );

        $found = array();
        $missing = array();
        foreach ($checks as $check) {
            $path = $this->path($check['file']);
            if (!is_file($path)) {
                $missing[] = $check['file'] . ' ausente';
                continue;
            }

            if (!preg_match($check['pattern'], (string)file_get_contents($path))) {
                $missing[] = $check['label'];
                continue;
            }

            $found[] = $check['label'];
        }

        return array('found' => $found, 'missing' => $missing);
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

    private function buildUpdateManifest(string $version, string $changelog, string $zipName, array $preview): array
    {
        $files = [];
        $added = [];
        $modified = [];
        $removed = [];
        foreach ($preview['included'] as $file) {
            $entry = $this->manifestFileEntry($file, true);
            $files[] = $entry;
            if ($entry['status_type'] === 'new') $added[] = $entry['path'];
            if (in_array($entry['status_type'], ['modified', 'required', 'unchanged'], true)) $modified[] = $entry['path'];
        }
        foreach ($preview['ignored'] as $file) {
            if (($file['status_type'] ?? '') === 'removed') {
                $entry = $this->manifestFileEntry($file, false);
                $removed[] = $entry['path'];
                $files[] = $entry;
            }
        }

        $manifest = [
            'schema' => 'prato-update-manifest-v1',
            'version' => $version,
            'published_at' => portal_utc_now(),
            'generated_by' => $this->currentUserLabel(),
            'source_root' => $this->sourceRoot,
            'source_audit' => $this->sourceAudit(),
            'changelog' => $changelog,
            'package' => [
                'file' => $zipName,
                'path' => '',
                'url' => '',
                'size' => 0,
                'sha256' => '',
            ],
            'totals' => [
                'included' => count($preview['included']),
                'ignored' => count($preview['ignored']),
                'added' => count($added),
                'modified' => count($modified),
                'removed' => count($removed),
            ],
            'added' => $added,
            'modified' => $modified,
            'removed' => $removed,
            'files' => $files,
            'ignored' => array_map(function ($file) {
                return [
                    'path' => (string)($file['path'] ?? ''),
                    'status' => (string)($file['status'] ?? ''),
                    'status_type' => (string)($file['status_type'] ?? ''),
                    'reason' => (string)($file['reason'] ?? ''),
                    'include' => false,
                    'size' => (int)($file['size'] ?? 0),
                    'sha256' => (string)($file['sha256'] ?? ''),
                    'mtime' => (string)($file['mtime'] ?? ''),
                ];
            }, $preview['ignored']),
        ];

        $this->log('MANIFEST gerado | versao=' . $version . ' | arquivos=' . count($files));
        return $manifest;
    }

    private function manifestFileEntry(array $file, bool $include): array
    {
        return [
            'path' => $this->normalizeRelativePath((string)($file['path'] ?? '')),
            'status' => (string)($file['status'] ?? ''),
            'status_type' => (string)($file['status_type'] ?? ''),
            'include' => $include,
            'exists' => !empty($file['exists']),
            'size' => (int)($file['size'] ?? 0),
            'sha256' => (string)($file['sha256'] ?? ''),
            'mtime' => (string)($file['mtime'] ?? ''),
            'previous_sha256' => (string)($file['previous_sha256'] ?? ''),
        ];
    }

    private function manifestPath(string $version): string
    {
        return $this->manifestDir() . DIRECTORY_SEPARATOR . 'manifest-v' . preg_replace('/[^A-Za-z0-9_.-]+/', '-', $version) . '.json';
    }

    private function manifestDir(): string
    {
        $dir = $this->updatesDir . DIRECTORY_SEPARATOR . 'manifests';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private function writeManifestFile(string $version, array $manifest): void
    {
        $path = $this->manifestPath($version);
        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents($path, $json . PHP_EOL) === false) {
            throw new Exception('Nao foi possivel gravar manifesto da atualizacao.');
        }
        $this->log('MANIFEST gravado | arquivo=' . $path);
    }

    private function latestManifest(): ?array
    {
        $files = glob($this->manifestDir() . DIRECTORY_SEPARATOR . 'manifest-v*.json') ?: [];
        usort($files, function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });
        foreach ($files as $file) {
            $json = json_decode((string)file_get_contents($file), true);
            if (is_array($json) && isset($json['files']) && is_array($json['files'])) {
                return $json;
            }
        }
        return null;
    }

    private function validatePublishedZip(string $zipPath, array $expectedFiles, array $manifest, string $expectedSha): array
    {
        $realSha = hash_file('sha256', $zipPath);
        if (!hash_equals($expectedSha, $realSha)) {
            throw new Exception('Hash do ZIP mudou apos criacao. Esperado=' . $expectedSha . ' real=' . $realSha);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new Exception('Nao foi possivel abrir ZIP para validacao final.');
        }

        $expected = [];
        foreach ($expectedFiles as $file) {
            if (!empty($file['include']) && !empty($file['path'])) {
                $expected[] = $this->normalizeRelativePath((string)$file['path']);
            }
        }
        $expected[] = 'manifest.json';

        $missing = [];
        foreach (array_values(array_unique($expected)) as $name) {
            if ($zip->locateName($name) === false) {
                $missing[] = $name;
            }
        }

        $blocked = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $this->normalizeRelativePath((string)$zip->getNameIndex($i));
            $reason = $this->zipBlockedReason($name);
            if ($reason !== '') {
                $blocked[] = $name . ' (' . $reason . ')';
            }
        }

        $numFiles = $zip->numFiles;
        $zip->close();

        if (!empty($missing)) {
            throw new Exception('Validacao final falhou: arquivos esperados ausentes no ZIP: ' . implode(', ', $missing));
        }
        if (!empty($blocked)) {
            throw new Exception('Validacao final falhou: ZIP contem arquivos proibidos: ' . implode(', ', $blocked));
        }

        $result = [
            'success' => true,
            'zip_entries' => $numFiles,
            'expected_entries' => count(array_values(array_unique($expected))),
            'sha256' => $realSha,
            'manifest_files' => count($manifest['files'] ?? []),
        ];
        $this->log('VALIDACAO FINAL ZIP ok | entradas=' . $numFiles . ' | sha256=' . $realSha);
        return $result;
    }

    private function zipBlockedReason(string $name): string
    {
        $base = basename($name);
        if ($name === '' || $name[0] === '/' || preg_match('/^[A-Za-z]:/', $name) || strpos($name, '../') !== false || strpos($name, '/..') !== false) {
            return 'caminho inseguro';
        }
        if ($base !== '.env.update' && preg_match('/^\.env(?:\.|$)/i', $base)) {
            return 'configuracao local';
        }
        if (preg_match('#(^|/)(\.git|\.idea|\.vscode|node_modules|storage/logs|storage/cache|storage/backup|cache|tmp|temp|logs|views-cache)(/|$)#i', $name)) {
            return 'diretorio proibido';
        }
        if (preg_match('/\.(sql|zip|bak|tmp|temp|part|cache|log)$/i', $base) || preg_match('/\.rtpl\.php$/i', $base)) {
            return 'extensao proibida';
        }
        return '';
    }

    private function createZip($zipPath, array $files, array $manifest): array
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
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
        $this->log('ZIP incluido | arquivo=manifest.json');
        $total++;
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

    private function signPackage(string $zipPath, string $zipName, string $sha256, int $size): array
    {
        if (empty($this->config['package_signing_enabled'])) {
            return ['enabled' => false];
        }
        if (!function_exists('openssl_sign')) {
            throw new Exception('Assinatura de pacote habilitada, mas OpenSSL nao esta disponivel no PHP.');
        }

        $keys = $this->ensureSigningKeys();
        $private = openssl_pkey_get_private((string)file_get_contents($keys['private']));
        if (!$private) {
            throw new Exception('Nao foi possivel carregar chave privada de assinatura do pacote.');
        }

        $payload = implode("\n", [
            'prato-update-package-v1',
            'file:' . $zipName,
            'sha256:' . $sha256,
            'size:' . $size,
        ]) . "\n";

        $signature = '';
        if (!openssl_sign($payload, $signature, $private, OPENSSL_ALGO_SHA256)) {
            throw new Exception('Nao foi possivel assinar o pacote de atualizacao.');
        }

        $sigName = $zipName . '.sig';
        $sigPath = $this->updatesDir . DIRECTORY_SEPARATOR . $sigName;
        $pubName = 'update-package-public.pem';
        $pubTarget = $this->updatesDir . DIRECTORY_SEPARATOR . $pubName;
        file_put_contents($sigPath, base64_encode($signature) . PHP_EOL);
        copy($keys['public'], $pubTarget);

        $this->log('ASSINATURA pacote ok | arquivo=' . $zipName . ' | sig=' . $sigName);
        return [
            'enabled' => true,
            'algorithm' => 'RSA-SHA256',
            'payload' => $payload,
            'signature_base64' => base64_encode($signature),
            'signature_file' => $sigName,
            'signature_path' => $sigPath,
            'signature_url' => $this->downloadBaseUrl() . '/' . rawurlencode($sigName),
            'public_key_file' => $pubName,
            'public_key_path' => $pubTarget,
            'public_key_url' => $this->downloadBaseUrl() . '/' . rawurlencode($pubName),
        ];
    }

    private function ensureSigningKeys(): array
    {
        $private = (string)($this->config['package_signing_private_key'] ?? '');
        $public = (string)($this->config['package_signing_public_key'] ?? '');
        if ($private === '' || $public === '') {
            throw new Exception('Caminhos das chaves de assinatura nao configurados.');
        }

        foreach ([dirname($private), dirname($public)] as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
                throw new Exception('Nao foi possivel criar pasta de chaves: ' . $dir);
            }
        }

        if (!is_file($private) || !is_file($public)) {
            $key = openssl_pkey_new([
                'private_key_bits' => 3072,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);
            if (!$key) {
                throw new Exception('Nao foi possivel gerar chave RSA de assinatura.');
            }
            $privatePem = '';
            openssl_pkey_export($key, $privatePem);
            $details = openssl_pkey_get_details($key);
            if ($privatePem === '' || empty($details['key'])) {
                throw new Exception('Nao foi possivel exportar chaves de assinatura.');
            }
            file_put_contents($private, $privatePem);
            @chmod($private, 0600);
            file_put_contents($public, $details['key']);
            @chmod($public, 0644);
            $this->log('ASSINATURA chaves RSA geradas | public=' . $public);
        }

        return ['private' => $private, 'public' => $public];
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
            $reason = $this->zipBlockedReason($name);
            if ($reason !== '') {
                $blocked[] = $name . ' (' . $reason . ')';
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
            '# Gerado em ' . portal_utc_now() . ' UTC',
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
                if (empty($file['path']) || empty($file['name'])) {
                    continue;
                }
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
            if ($localPath === '' || $remoteName === '') {
                continue;
            }
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
        if ($this->versionExists($version)) {
            throw new Exception('A versao ' . $version . ' ja existe no historico de publicacoes.');
        }

        foreach (['version.json', 'version-lite.json'] as $name) {
            $path = $this->updatesDir . DIRECTORY_SEPARATOR . $name;
            if (!is_file($path)) continue;
            $json = json_decode((string)file_get_contents($path), true);
            if (is_array($json) && (string)($json['version'] ?? '') === (string)$version) throw new Exception('A versao ' . $version . ' ja esta publicada em ' . $name . '.');
        }
    }

    private function buildVersionJson($version, $zipName, $url, $changelog, $sha256, int $size = 0, array $signature = []): array
    {
        return [
            'version' => $version,
            'file' => $zipName,
            'url' => $url,
            'published_at' => portal_utc_now(),
            'notes' => $changelog,
            'changelog' => $changelog,
            'sha256' => $sha256,
            'checksum_sha256' => $sha256,
            'size' => $size,
            'size_bytes' => $size,
            'manifest' => $this->downloadBaseUrl() . '/manifest-v' . rawurlencode($version) . '.json',
            'signature' => !empty($signature['enabled']) ? [
                'enabled' => true,
                'algorithm' => (string)($signature['algorithm'] ?? 'RSA-SHA256'),
                'signature_url' => (string)($signature['signature_url'] ?? ''),
                'public_key_url' => (string)($signature['public_key_url'] ?? ''),
                'payload' => (string)($signature['payload'] ?? ''),
            ] : ['enabled' => false],
        ];
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
            $columns = $this->tableColumns($sql, 'tb_atualizacoes');
            $payload = [
                ':versao' => $data['version'],
                ':arquivo' => $data['file'],
                ':url' => $data['url'],
                ':changelog' => $data['changelog'],
                ':checksum' => $data['checksum_sha256'],
                ':publicado' => $data['published_at'],
            ];
            $insertColumns = ['versao', 'arquivo_zip', 'url_download', 'changelog', 'checksum_sha256', 'publicado_em'];
            $insertValues = [':versao', ':arquivo', ':url', ':changelog', ':checksum', ':publicado'];

            $optional = [
                'tamanho_bytes' => [':tamanho_bytes', (int)($data['size_bytes'] ?? $data['size'] ?? 0)],
                'tamanho_zip' => [':tamanho_zip', (int)($data['size_bytes'] ?? $data['size'] ?? 0)],
                'status' => [':status', 'publicada'],
                'responsavel' => [':responsavel', $this->currentUserLabel()],
                'observacoes' => [':observacoes', (string)($data['notes'] ?? '')],
                'manifest_url' => [':manifest_url', (string)($data['manifest'] ?? '')],
                'signature_url' => [':signature_url', (string)($data['signature']['signature_url'] ?? '')],
                'public_key_url' => [':public_key_url', (string)($data['signature']['public_key_url'] ?? '')],
                'signature_algorithm' => [':signature_algorithm', (string)($data['signature']['algorithm'] ?? '')],
            ];

            foreach ($optional as $column => $value) {
                if (in_array($column, $columns, true)) {
                    $insertColumns[] = $column;
                    $insertValues[] = $value[0];
                    $payload[$value[0]] = $value[1];
                }
            }

            if (in_array('created_at', $columns, true)) {
                $insertColumns[] = 'created_at';
                $insertValues[] = 'UTC_TIMESTAMP()';
            }

            $sql->query(
                'INSERT INTO tb_atualizacoes (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $insertValues) . ')',
                $payload
            );
            if (class_exists('Hcode\\Model\\Notification')) {
                Hcode\Model\Notification::addUpdatePublished($data);
            }
        } catch (Throwable $e) {
            $this->log('WARN registro tb_atualizacoes falhou: ' . $e->getMessage());
            throw $e;
        }
    }

    private function tableColumns($sql, string $table): array
    {
        try {
            $rows = $sql->select('SHOW COLUMNS FROM ' . $table);
            return array_values(array_filter(array_map(function ($row) {
                return isset($row['Field']) ? (string)$row['Field'] : '';
            }, $rows ?: [])));
        } catch (Throwable $e) {
            $this->log('WARN nao foi possivel ler colunas de ' . $table . ': ' . $e->getMessage());
            return [];
        }
    }

    private function latestDatabaseVersion(): string
    {
        try {
            $sql = new Hcode\DB\Sql();
            $exists = $sql->select("SHOW TABLES LIKE 'tb_atualizacoes'");
            if (empty($exists)) return '';
            $rows = $sql->select("
                SELECT versao
                FROM tb_atualizacoes
                WHERE versao IS NOT NULL AND versao <> ''
                ORDER BY publicado_em DESC, created_at DESC, id_atualizacao DESC
                LIMIT 1
            ");
            return trim((string)($rows[0]['versao'] ?? ''));
        } catch (Throwable $e) {
            $this->log('WARN consulta ultima versao no banco falhou: ' . $e->getMessage());
            return '';
        }
    }

    private function versionExists(string $version): bool
    {
        $version = trim($version);
        if ($version === '') return false;

        try {
            $sql = new Hcode\DB\Sql();
            $exists = $sql->select("SHOW TABLES LIKE 'tb_atualizacoes'");
            if (!empty($exists)) {
                $rows = $sql->select("SELECT COUNT(*) AS total FROM tb_atualizacoes WHERE versao = :versao", [
                    ':versao' => $version,
                ]);
                if ((int)($rows[0]['total'] ?? 0) > 0) return true;
            }
        } catch (Throwable $e) {
            $this->log('WARN validacao duplicidade versao no banco falhou: ' . $e->getMessage());
        }

        foreach (['version.json', 'version-lite.json'] as $name) {
            $path = $this->updatesDir . DIRECTORY_SEPARATOR . $name;
            if (!is_file($path)) continue;
            $json = json_decode((string)file_get_contents($path), true);
            if (is_array($json) && trim((string)($json['version'] ?? '')) === $version) return true;
        }

        return is_file($this->updatesDir . DIRECTORY_SEPARATOR . 'sistema-v' . $version . '.zip');
    }

    private function incrementVersion(string $version): string
    {
        $base = preg_replace('/[-+].*$/', '', trim($version));
        $parts = explode('.', $base);
        if (count($parts) < 2) {
            $parts = [$base ?: '1', '000'];
        }

        $last = array_pop($parts);
        if (!preg_match('/^\d+$/', $last)) {
            $last = '0';
        }
        $width = strlen($last);
        $next = (string)((int)$last + 1);
        if ($width > 1) {
            $next = str_pad($next, $width, '0', STR_PAD_LEFT);
        }
        $parts[] = $next;
        return implode('.', $parts);
    }

    private function logPublicationAudit(array $result): void
    {
        $usuario = null;
        try {
            if (class_exists('Hcode\\Model\\Funcionarios')) {
                $usuario = Hcode\Model\Funcionarios::getFromSession();
            }
        } catch (Throwable $e) {
            $usuario = null;
        }

        $nome = $usuario && method_exists($usuario, 'getnome_funcionario') ? $usuario->getnome_funcionario() : 'sistema';
        $cpf = $usuario && method_exists($usuario, 'getcpf') ? $usuario->getcpf() : '';
        $this->log('AUDITORIA publicacao | usuario=' . $nome . ' | cpf=' . $cpf . ' | versao=' . ($result['version'] ?? '') . ' | zip=' . ($result['zip_name'] ?? '') . ' | arquivos=' . count($result['included'] ?? []));
    }

    private function downloadUrl($zipName): string
    {
        $base = $this->downloadBaseUrl();
        if ($base === '') throw new Exception('UPDATE_PUBLISH_PUBLIC_BASE_URL nao configurado.');
        return $base . '/' . rawurlencode($zipName);
    }

    private function downloadBaseUrl(): string
    {
        return rtrim((string)($this->config['public_base_url'] ?? ''), '/');
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
        @file_put_contents($dir . DIRECTORY_SEPARATOR . $file, '[' . portal_log_timestamp() . '] ' . $message . PHP_EOL, FILE_APPEND);
    }
}
