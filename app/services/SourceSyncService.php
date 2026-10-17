<?php

class SourceSyncService
{
    private $config;
    private $sourceRoot;
    private $syncDir;
    private $manifestDir;
    private $backupDir;
    private $logFile;

    public function __construct(array $config)
    {
        $this->config = $config;
        $root = (string)($config['source_root'] ?? '');
        $realRoot = $root !== '' ? realpath($root) : false;
        $this->sourceRoot = $realRoot ?: rtrim($root, DIRECTORY_SEPARATOR);
        $this->syncDir = rtrim((string)($config['source_sync_dir'] ?? (STORAGE_DIR . DIRECTORY_SEPARATOR . 'source-sync')), DIRECTORY_SEPARATOR);
        $this->manifestDir = $this->syncDir . DIRECTORY_SEPARATOR . 'manifests';
        $this->backupDir = $this->syncDir . DIRECTORY_SEPARATOR . 'backups';
        $this->logFile = (defined('LOG_DIR') ? LOG_DIR : (ROOT_DIR . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs')) . DIRECTORY_SEPARATOR . 'source-sync.log';
    }

    public function status(): array
    {
        $this->ensureDirs();
        $current = $this->buildSourceManifest(false);
        $latest = $this->latestSnapshot();
        $validation = $this->validateSourceMarkers($current);
        $matches = $latest && hash_equals((string)($latest['fingerprint'] ?? ''), (string)($current['fingerprint'] ?? ''));

        return [
            'success' => true,
            'source_root' => $this->sourceRoot,
            'available' => is_dir($this->sourceRoot),
            'fingerprint' => $current['fingerprint'] ?? '',
            'total_files' => $current['total_files'] ?? 0,
            'total_bytes' => $current['total_bytes'] ?? 0,
            'total_bytes_human' => $this->formatBytes((int)($current['total_bytes'] ?? 0)),
            'git' => $current['git'] ?? [],
            'latest_sync' => $latest ? $this->snapshotSummary($latest) : null,
            'synced' => (bool)$matches,
            'requires_sync' => !$matches,
            'validation' => $validation,
            'history' => $this->history(5),
        ];
    }

    public function snapshot(string $notes = ''): array
    {
        $this->ensureDirs();
        $this->log('SYNC inicio | usuario=' . $this->currentUserLabel());
        $manifest = $this->buildSourceManifest(true);
        $validation = $this->validateSourceMarkers($manifest);
        if (!$validation['success']) {
            throw new Exception('Sincronizacao bloqueada: ' . implode(', ', $validation['missing']));
        }

        $syncId = date('Ymd-His') . '-' . substr((string)$manifest['fingerprint'], 0, 12);
        $manifest['sync_id'] = $syncId;
        $manifest['notes'] = trim($notes);
        $manifest['responsavel'] = $this->currentUserLabel();
        $manifest['created_at'] = portal_utc_now(DateTimeInterface::ATOM);
        $manifestPath = $this->manifestDir . DIRECTORY_SEPARATOR . 'sync-' . $syncId . '.json';
        $manifest['manifest_path'] = $manifestPath;

        $backupPath = '';
        if (!empty($this->config['source_sync_backup_enabled'])) {
            $backupPath = $this->createBackupZip($syncId, $manifest['files']);
        }
        $manifest['backup_path'] = $backupPath;

        $this->writeJson($manifestPath, $manifest);
        $this->registerSync($manifest);
        $this->log('SYNC sucesso | id=' . $syncId . ' | arquivos=' . count($manifest['files']) . ' | fingerprint=' . $manifest['fingerprint']);

        return [
            'success' => true,
            'message' => 'Fonte sincronizada com sucesso.',
            'sync' => $this->snapshotSummary($manifest),
            'validation' => $validation,
        ];
    }

    public function validateForPublish(): array
    {
        $status = $this->status();
        $checks = [];
        $checks[] = [
            'key' => 'source_available',
            'label' => 'Fonte oficial disponivel',
            'ok' => !empty($status['available']),
            'message' => !empty($status['available']) ? 'Pasta fonte encontrada.' : 'Pasta fonte nao encontrada.',
        ];
        $checks[] = [
            'key' => 'source_markers',
            'label' => 'Marcadores obrigatorios da fonte',
            'ok' => !empty($status['validation']['success']),
            'message' => !empty($status['validation']['success']) ? 'Arquivos obrigatorios encontrados.' : 'Pendencias: ' . implode(', ', $status['validation']['missing'] ?? []),
        ];
        $checks[] = [
            'key' => 'source_sync_snapshot',
            'label' => 'Fonte sincronizada antes da publicacao',
            'ok' => !empty($status['synced']),
            'message' => !empty($status['synced'])
                ? 'Fingerprint atual confere com a ultima sincronizacao.'
                : 'Registre uma sincronizacao da fonte antes de publicar.',
        ];

        $ok = !empty($status['available']) && !empty($status['validation']['success']) && !empty($status['synced']);
        return [
            'success' => $ok,
            'checks' => $checks,
            'status' => $status,
            'message' => $ok ? 'Fonte validada para publicacao.' : 'Fonte ainda nao validada para publicacao.',
        ];
    }

    public function history(int $limit = 20): array
    {
        $this->ensureDirs();
        $files = glob($this->manifestDir . DIRECTORY_SEPARATOR . 'sync-*.json') ?: [];
        rsort($files);
        $rows = [];
        foreach (array_slice($files, 0, max(1, $limit)) as $file) {
            $json = json_decode((string)file_get_contents($file), true);
            if (is_array($json)) {
                $rows[] = $this->snapshotSummary($json);
            }
        }
        return $rows;
    }

    public function diff(string $fromSyncId = '', string $toSyncId = 'current'): array
    {
        $from = $fromSyncId !== '' ? $this->loadSnapshot($fromSyncId) : $this->latestSnapshot();
        if (!$from) {
            throw new Exception('Snapshot de origem nao encontrado.');
        }

        $to = $toSyncId === '' || $toSyncId === 'current'
            ? $this->buildSourceManifest(true)
            : $this->loadSnapshot($toSyncId);
        if (!$to) {
            throw new Exception('Snapshot de destino nao encontrado.');
        }

        $fromMap = $this->filesMap($from);
        $toMap = $this->filesMap($to);
        $added = [];
        $modified = [];
        $removed = [];

        foreach ($toMap as $path => $file) {
            if (!isset($fromMap[$path])) {
                $added[] = $file;
                continue;
            }
            if (!hash_equals((string)($fromMap[$path]['sha256'] ?? ''), (string)($file['sha256'] ?? ''))) {
                $file['previous_sha256'] = (string)($fromMap[$path]['sha256'] ?? '');
                $modified[] = $file;
            }
        }

        foreach ($fromMap as $path => $file) {
            if (!isset($toMap[$path])) {
                $removed[] = $file;
            }
        }

        return [
            'success' => true,
            'from' => $this->snapshotSummary($from),
            'to' => $toSyncId === 'current' || $toSyncId === '' ? [
                'sync_id' => 'current',
                'created_at' => portal_utc_now(DateTimeInterface::ATOM),
                'fingerprint' => (string)($to['fingerprint'] ?? ''),
                'total_files' => (int)($to['total_files'] ?? 0),
                'total_bytes' => (int)($to['total_bytes'] ?? 0),
                'total_bytes_human' => $this->formatBytes((int)($to['total_bytes'] ?? 0)),
            ] : $this->snapshotSummary($to),
            'totals' => [
                'added' => count($added),
                'modified' => count($modified),
                'removed' => count($removed),
            ],
            'added' => array_slice($added, 0, 200),
            'modified' => array_slice($modified, 0, 200),
            'removed' => array_slice($removed, 0, 200),
        ];
    }

    public function rollbackPreview(string $syncId): array
    {
        $syncId = preg_replace('/[^0-9A-Za-z_.-]/', '', trim($syncId));
        if ($syncId === '') {
            throw new Exception('Informe a sincronizacao para preview de rollback.');
        }
        $diff = $this->diff($syncId, 'current');
        $diff['rollback_preview'] = true;
        $diff['message'] = 'Rollback para este snapshot removeria arquivos novos atuais, restauraria modificados e reverteria removidos.';
        return $diff;
    }

    public function receiveUploadedZip(string $tmpPath, string $notes = ''): array
    {
        if (!is_file($tmpPath)) {
            throw new Exception('Arquivo enviado nao encontrado.');
        }

        $this->ensureDirs();
        $this->log('UPLOAD fonte inicio | usuario=' . $this->currentUserLabel() . ' | arquivo=' . $tmpPath);
        if (is_dir($this->sourceRoot)) {
            try {
                $this->snapshot('Backup automatico antes do upload da fonte');
            } catch (Throwable $e) {
                $this->log('WARN backup antes do upload falhou, seguindo com validacao do ZIP enviado: ' . $e->getMessage());
            }
        }

        $tempDir = $this->syncDir . DIRECTORY_SEPARATOR . 'upload-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4));
        if (!mkdir($tempDir, 0775, true) && !is_dir($tempDir)) {
            throw new Exception('Nao foi possivel criar pasta temporaria do upload.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpPath) !== true) {
            throw new Exception('Nao foi possivel abrir o ZIP da fonte enviada.');
        }

        $uploaded = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $this->normalizeRelativePath((string)$zip->getNameIndex($i));
            if ($entry === '' || substr($entry, -1) === '/') {
                continue;
            }
            if ($this->isForbiddenPath($entry)) {
                throw new Exception('Upload bloqueado: arquivo proibido no ZIP da fonte: ' . $entry);
            }
            $content = $zip->getFromIndex($i);
            if ($content === false) {
                throw new Exception('Nao foi possivel ler arquivo enviado: ' . $entry);
            }
            $target = $tempDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry);
            $dir = dirname($target);
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new Exception('Nao foi possivel criar pasta temporaria: ' . $dir);
            }
            file_put_contents($target, $content);
            $uploaded[$entry] = true;
        }
        $zip->close();

        if (!$uploaded) {
            throw new Exception('ZIP da fonte nao contem arquivos validos.');
        }

        $oldRoot = $this->sourceRoot;
        $this->sourceRoot = realpath($tempDir) ?: $tempDir;
        $candidate = $this->buildSourceManifest(true);
        $validation = $this->validateSourceMarkers($candidate);
        $this->sourceRoot = $oldRoot;
        if (!$validation['success']) {
            $this->removeDir($tempDir);
            throw new Exception('Upload bloqueado: fonte enviada incompleta. Pendencias: ' . implode(', ', $validation['missing']));
        }

        if (!is_dir($this->sourceRoot) && !mkdir($this->sourceRoot, 0775, true) && !is_dir($this->sourceRoot)) {
            throw new Exception('Nao foi possivel criar pasta fonte oficial.');
        }

        $this->removeAllowedFilesNotIn(array_keys($uploaded));
        foreach (array_keys($uploaded) as $relative) {
            $from = $tempDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $to = $this->sourcePath($relative);
            $dir = dirname($to);
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new Exception('Nao foi possivel criar pasta destino: ' . $dir);
            }
            copy($from, $to);
        }
        $this->removeDir($tempDir);

        $snapshot = $this->snapshot($notes !== '' ? $notes : 'Fonte recebida por upload autenticado');
        $this->log('UPLOAD fonte sucesso | arquivos=' . count($uploaded) . ' | sync_id=' . ($snapshot['sync']['sync_id'] ?? ''));
        return [
            'success' => true,
            'message' => 'Fonte enviada, validada e sincronizada com sucesso.',
            'uploaded_files' => count($uploaded),
            'sync' => $snapshot['sync'] ?? null,
        ];
    }

    public function rollback(string $syncId): array
    {
        $syncId = preg_replace('/[^0-9A-Za-z_.-]/', '', trim($syncId));
        if ($syncId === '') {
            throw new Exception('Informe a sincronizacao para rollback.');
        }

        $manifestPath = $this->manifestDir . DIRECTORY_SEPARATOR . 'sync-' . $syncId . '.json';
        if (!is_file($manifestPath)) {
            throw new Exception('Manifesto de sincronizacao nao encontrado.');
        }

        $manifest = json_decode((string)file_get_contents($manifestPath), true);
        $backupPath = (string)($manifest['backup_path'] ?? '');
        if ($backupPath === '' || !is_file($backupPath)) {
            throw new Exception('Backup desta sincronizacao nao encontrado.');
        }

        $zip = new ZipArchive();
        if ($zip->open($backupPath) !== true) {
            throw new Exception('Nao foi possivel abrir o backup da sincronizacao.');
        }

        $restored = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            $relative = $this->normalizeRelativePath($entry);
            if ($relative === '' || $this->isForbiddenPath($relative)) {
                continue;
            }
            $target = $this->sourcePath($relative);
            $dir = dirname($target);
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new Exception('Nao foi possivel criar pasta no rollback: ' . $dir);
            }
            $content = $zip->getFromIndex($i);
            if ($content === false) {
                throw new Exception('Nao foi possivel ler item do backup: ' . $relative);
            }
            file_put_contents($target, $content);
            $restored++;
        }
        $zip->close();
        $this->log('ROLLBACK sucesso | sync_id=' . $syncId . ' | arquivos=' . $restored . ' | usuario=' . $this->currentUserLabel());

        return [
            'success' => true,
            'message' => 'Rollback concluido.',
            'sync_id' => $syncId,
            'restored_files' => $restored,
        ];
    }

    private function buildSourceManifest(bool $includeFiles): array
    {
        if (!is_dir($this->sourceRoot)) {
            return [
                'source_root' => $this->sourceRoot,
                'fingerprint' => '',
                'total_files' => 0,
                'total_bytes' => 0,
                'files' => [],
                'git' => [],
            ];
        }

        $files = [];
        $fingerprintParts = [];
        $totalBytes = 0;
        $directory = new RecursiveDirectoryIterator($this->sourceRoot, FilesystemIterator::SKIP_DOTS);
        $filter = new RecursiveCallbackFilterIterator($directory, function ($current) {
            $relative = $this->relativePath($current->getPathname());
            if ($relative === '') {
                return true;
            }
            return !$this->isForbiddenPath($relative);
        });
        $iterator = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $item) {
            $path = $item->getPathname();
            $relative = $this->relativePath($path);
            if ($relative === '' || $this->isForbiddenPath($relative)) {
                continue;
            }
            if (!$item->isFile()) {
                continue;
            }

            $size = (int)$item->getSize();
            $sha = hash_file('sha256', $path);
            $mtime = (int)$item->getMTime();
            $totalBytes += $size;
            $fingerprintParts[] = $relative . '|' . $sha . '|' . $size;
            if ($includeFiles) {
                $files[] = [
                    'path' => $relative,
                    'size' => $size,
                    'size_human' => $this->formatBytes($size),
                    'sha256' => $sha,
                    'mtime' => portal_format_manaus($mtime),
                ];
            }
        }

        sort($fingerprintParts, SORT_STRING);
        if ($includeFiles) {
            usort($files, function ($a, $b) {
                return strcmp($a['path'], $b['path']);
            });
        }

        return [
            'source_root' => $this->sourceRoot,
            'fingerprint' => hash('sha256', implode("\n", $fingerprintParts)),
            'total_files' => count($fingerprintParts),
            'total_bytes' => $totalBytes,
            'files' => $files,
            'git' => $this->gitInfo(),
        ];
    }

    private function validateSourceMarkers(array $manifest): array
    {
        $required = [
            'admin.php',
            'public/index.php',
            'app/routes/admin.php',
            'app/core/Hcode/Page.php',
            'app/views/admin/footer.html',
        ];
        $missing = [];
        foreach ($required as $path) {
            if (!is_file($this->sourcePath($path))) {
                $missing[] = $path;
            }
        }
        if (is_file($this->sourcePath('admin.php'))) {
            $content = (string)file_get_contents($this->sourcePath('admin.php'));
            if (strpos($content, 'public/index.php') === false) {
                $missing[] = 'admin.php encaminhando para public/index.php';
            }
        }

        $forbidden = [];
        foreach (($manifest['files'] ?? []) as $file) {
            $path = (string)($file['path'] ?? '');
            if ($this->isForbiddenPath($path)) {
                $forbidden[] = $path;
            }
        }

        return [
            'success' => empty($missing) && empty($forbidden),
            'missing' => array_values(array_unique($missing)),
            'forbidden' => array_values(array_unique($forbidden)),
        ];
    }

    private function latestSnapshot(): ?array
    {
        $history = $this->history(1);
        if (!$history) {
            return null;
        }
        $path = (string)($history[0]['manifest_path'] ?? '');
        if ($path === '' || !is_file($path)) {
            return null;
        }
        $json = json_decode((string)file_get_contents($path), true);
        return is_array($json) ? $json : null;
    }

    private function loadSnapshot(string $syncId): ?array
    {
        $syncId = preg_replace('/[^0-9A-Za-z_.-]/', '', trim($syncId));
        if ($syncId === '') {
            return null;
        }
        $path = $this->manifestDir . DIRECTORY_SEPARATOR . 'sync-' . $syncId . '.json';
        if (!is_file($path)) {
            return null;
        }
        $json = json_decode((string)file_get_contents($path), true);
        return is_array($json) ? $json : null;
    }

    private function filesMap(array $manifest): array
    {
        $map = [];
        foreach (($manifest['files'] ?? []) as $file) {
            $path = $this->normalizeRelativePath((string)($file['path'] ?? ''));
            if ($path !== '') {
                $file['path'] = $path;
                $map[$path] = $file;
            }
        }
        ksort($map, SORT_STRING);
        return $map;
    }

    private function snapshotSummary(array $manifest): array
    {
        return [
            'sync_id' => (string)($manifest['sync_id'] ?? ''),
            'created_at' => portal_format_manaus($manifest['created_at'] ?? null),
            'created_at_utc' => (string)($manifest['created_at'] ?? ''),
            'responsavel' => (string)($manifest['responsavel'] ?? ''),
            'notes' => (string)($manifest['notes'] ?? ''),
            'fingerprint' => (string)($manifest['fingerprint'] ?? ''),
            'total_files' => (int)($manifest['total_files'] ?? count($manifest['files'] ?? [])),
            'total_bytes' => (int)($manifest['total_bytes'] ?? 0),
            'total_bytes_human' => $this->formatBytes((int)($manifest['total_bytes'] ?? 0)),
            'manifest_path' => (string)($manifest['manifest_path'] ?? ''),
            'backup_path' => (string)($manifest['backup_path'] ?? ''),
            'git' => $manifest['git'] ?? [],
        ];
    }

    private function createBackupZip(string $syncId, array $files): string
    {
        $path = $this->backupDir . DIRECTORY_SEPARATOR . 'source-sync-' . $syncId . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('Nao foi possivel criar backup da fonte sincronizada.');
        }
        foreach ($files as $file) {
            $relative = $this->normalizeRelativePath((string)($file['path'] ?? ''));
            if ($relative === '' || $this->isForbiddenPath($relative)) {
                continue;
            }
            $absolute = $this->sourcePath($relative);
            if (is_file($absolute)) {
                $zip->addFile($absolute, $relative);
            }
        }
        $zip->close();
        return $path;
    }

    private function registerSync(array $manifest): void
    {
        if (!class_exists('Hcode\\DB\\Sql')) {
            return;
        }
        try {
            $sql = new Hcode\DB\Sql();
            $sql->query("
                CREATE TABLE IF NOT EXISTS tb_source_sync (
                    id_source_sync INT NOT NULL AUTO_INCREMENT,
                    sync_id VARCHAR(80) NOT NULL,
                    source_root VARCHAR(500) NOT NULL,
                    git_branch VARCHAR(120) NULL,
                    git_head VARCHAR(120) NULL,
                    status VARCHAR(40) NOT NULL DEFAULT 'sincronizada',
                    total_files INT NOT NULL DEFAULT 0,
                    total_bytes BIGINT NOT NULL DEFAULT 0,
                    fingerprint VARCHAR(128) NOT NULL,
                    manifest_path VARCHAR(500) NOT NULL,
                    backup_path VARCHAR(500) NULL,
                    notes TEXT NULL,
                    responsavel VARCHAR(180) NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id_source_sync),
                    UNIQUE KEY uk_source_sync_sync_id (sync_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $git = $manifest['git'] ?? [];
            $sql->query("
                INSERT INTO tb_source_sync
                    (sync_id, source_root, git_branch, git_head, status, total_files, total_bytes, fingerprint, manifest_path, backup_path, notes, responsavel, created_at)
                VALUES
                    (:sync_id, :source_root, :git_branch, :git_head, 'sincronizada', :total_files, :total_bytes, :fingerprint, :manifest_path, :backup_path, :notes, :responsavel, UTC_TIMESTAMP())
                ON DUPLICATE KEY UPDATE
                    total_files = VALUES(total_files),
                    total_bytes = VALUES(total_bytes),
                    fingerprint = VALUES(fingerprint),
                    manifest_path = VALUES(manifest_path),
                    backup_path = VALUES(backup_path),
                    notes = VALUES(notes),
                    responsavel = VALUES(responsavel)
            ", [
                ':sync_id' => $manifest['sync_id'],
                ':source_root' => $manifest['source_root'],
                ':git_branch' => (string)($git['branch'] ?? ''),
                ':git_head' => (string)($git['head'] ?? ''),
                ':total_files' => (int)($manifest['total_files'] ?? 0),
                ':total_bytes' => (int)($manifest['total_bytes'] ?? 0),
                ':fingerprint' => (string)($manifest['fingerprint'] ?? ''),
                ':manifest_path' => (string)($manifest['manifest_path'] ?? ''),
                ':backup_path' => (string)($manifest['backup_path'] ?? ''),
                ':notes' => (string)($manifest['notes'] ?? ''),
                ':responsavel' => (string)($manifest['responsavel'] ?? ''),
            ]);
        } catch (Throwable $e) {
            $this->log('WARN banco tb_source_sync falhou: ' . $e->getMessage());
        }
    }

    private function gitInfo(): array
    {
        if (!is_dir($this->sourceRoot . DIRECTORY_SEPARATOR . '.git') && !is_file($this->sourceRoot . DIRECTORY_SEPARATOR . '.git')) {
            return ['available' => false, 'branch' => '', 'head' => '', 'status_count' => 0];
        }
        $branch = $this->runProcess('git -C ' . escapeshellarg($this->sourceRoot) . ' rev-parse --abbrev-ref HEAD');
        $head = $this->runProcess('git -C ' . escapeshellarg($this->sourceRoot) . ' log -1 --format=%H');
        $status = $this->runProcess('git -C ' . escapeshellarg($this->sourceRoot) . ' status --porcelain --untracked-files=all');
        return [
            'available' => true,
            'branch' => trim($branch['stdout']),
            'head' => trim($head['stdout']),
            'status_count' => trim($status['stdout']) === '' ? 0 : count(preg_split('/\r\n|\r|\n/', trim($status['stdout']))),
        ];
    }

    private function runProcess(string $command): array
    {
        $descriptor = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open($command, $descriptor, $pipes);
        if (!is_resource($process)) {
            return ['code' => 1, 'stdout' => '', 'stderr' => 'proc_open indisponivel'];
        }
        fclose($pipes[0]);
        $stdout = (string)stream_get_contents($pipes[1]);
        $stderr = (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        return ['code' => (int)$code, 'stdout' => $stdout, 'stderr' => $stderr];
    }

    private function relativePath(string $absolute): string
    {
        $root = rtrim(str_replace('\\', '/', $this->sourceRoot), '/') . '/';
        $path = str_replace('\\', '/', $absolute);
        if (strpos($path, $root) === 0) {
            return $this->normalizeRelativePath(substr($path, strlen($root)));
        }
        return '';
    }

    private function sourcePath(string $relative): string
    {
        return rtrim($this->sourceRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->normalizeRelativePath($relative));
    }

    private function normalizeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#/+#', '/', $path);
        $path = ltrim($path, '/');
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }
        return implode('/', $parts);
    }

    private function isForbiddenPath(string $path): bool
    {
        $path = strtolower($this->normalizeRelativePath($path));
        if ($path === '') {
            return true;
        }
        $base = basename($path);
        if ($base === '.env' || strpos($base, '.env.') === 0 || strpos($base, '.env-') === 0) {
            return true;
        }
        if (preg_match('/\.(sql|log|tmp|temp|bak|backup|old|orig|swp|zip|tar|gz)$/', $path)) {
            return true;
        }
        foreach (['.git', '.idea', '.vscode', 'node_modules', 'storage', 'vendor'] as $part) {
            if ($path === $part || strpos($path, $part . '/') === 0 || strpos($path, '/' . $part . '/') !== false) {
                return true;
            }
        }
        return false;
    }

    private function removeAllowedFilesNotIn(array $allowed): void
    {
        $allowedMap = array_fill_keys(array_map([$this, 'normalizeRelativePath'], $allowed), true);
        if (!is_dir($this->sourceRoot)) {
            return;
        }
        $directory = new RecursiveDirectoryIterator($this->sourceRoot, FilesystemIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $relative = $this->relativePath($item->getPathname());
            if ($relative === '' || $this->isForbiddenPath($relative)) {
                continue;
            }
            if ($item->isFile() && !isset($allowedMap[$relative])) {
                @unlink($item->getPathname());
            }
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            }
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($dir);
    }

    private function ensureDirs(): void
    {
        foreach ([$this->syncDir, $this->manifestDir, $this->backupDir, dirname($this->logFile)] as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new Exception('Nao foi possivel criar pasta: ' . $dir);
            }
        }
    }

    private function writeJson(string $path, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($path, $json) === false) {
            throw new Exception('Nao foi possivel gravar JSON: ' . $path);
        }
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

    private function currentUserLabel(): string
    {
        try {
            if (class_exists('Hcode\\Model\\Funcionarios')) {
                $user = Hcode\Model\Funcionarios::getFromSession();
                if ($user && method_exists($user, 'getnome_funcionario')) {
                    return (string)$user->getnome_funcionario();
                }
            }
        } catch (Throwable $e) {
            return 'sistema';
        }
        return 'sistema';
    }

    private function log(string $message): void
    {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($this->logFile, '[' . portal_log_timestamp() . '] ' . $message . PHP_EOL, FILE_APPEND);
    }
}
