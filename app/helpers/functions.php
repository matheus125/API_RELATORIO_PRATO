<?php

use \Hcode\Model\Funcionarios;

/**
 * Token shared by authenticated HTML forms. API clients may send the same
 * value in X-CSRF-Token when the request has no form body.
 */
function portal_csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }
    if (!isset($_SESSION['portal_csrf_token']) || !is_string($_SESSION['portal_csrf_token']) || strlen($_SESSION['portal_csrf_token']) < 32) {
        $_SESSION['portal_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['portal_csrf_token'];
}

function portal_require_csrf(?array $input = null): void
{
    $expected = portal_csrf_token();
    $received = is_array($input) ? ($input['_csrf'] ?? '') : '';
    if (!is_string($received) || $received === '') {
        $received = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    }

    if ($expected === '' || $received === '' || !hash_equals($expected, $received)) {
        http_response_code(403);
        if (strpos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false || strpos((string)($_SERVER['REQUEST_URI'] ?? ''), '/api/') !== false) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Sessão de formulário inválida.'], JSON_UNESCAPED_UNICODE);
        } else {
            echo 'Sessão de formulário inválida. Recarregue a página e tente novamente.';
        }
        exit;
    }
}

function formatPrice($vlprice)
{
    // ✅ correção: precedência
    if (!($vlprice > 0)) $vlprice = 0;

    return number_format((float)$vlprice, 2, ",", ".");
}

function formatDate($date)
{
    return portal_format_manaus($date, 'd/m/Y');
}

function checkLogin($inadmin = true)
{
    return Funcionarios::checkLogin($inadmin);
}

function getUserName()
{
    $funcionarios = Funcionarios::getFromSession();
    return $funcionarios->getnome_funcionario();
}

/**
 * Garante que a pasta /backup exista e retorna o caminho do arquivo de log
 */
function backupLogFile(): string
{
    $dir = defined('BACKUP_DIR') ? BACKUP_DIR : ($_SERVER["DOCUMENT_ROOT"] . '/backup');
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir . '/backup_notifications.log';
}

/**
 * Lock atômico com cooldown (segundos)
 */
function podeRodarBackup(int $cooldownSegundos = 600): bool
{
    $dir = defined('BACKUP_DIR') ? BACKUP_DIR : ($_SERVER["DOCUMENT_ROOT"] . '/backup');
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $lockFile = $dir . '/backup.lock';

    $fp = fopen($lockFile, 'c+');
    if (!$fp) return false;

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return false;
    }

    // precisa ler desde o começo
    rewind($fp);
    $last = (int) trim(stream_get_contents($fp));
    $agora = time();

    if ($last > 0 && ($agora - $last) < $cooldownSegundos) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return false;
    }

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, (string) $agora);

    flock($fp, LOCK_UN);
    fclose($fp);

    return true;
}

function backupLog(string $mensagem): void
{
    $arquivo = backupLogFile();
    $data = portal_utc_now();
    $linha = "{$data}|{$mensagem}\n";
    file_put_contents($arquivo, $linha, FILE_APPEND);
}

function backupAutomatico(): void
{
    backupLog("DEBUG: backupAutomatico() foi chamado");

    // ✅ durante teste: 10 segundos
    // ✅ em produção: 86400 (24h)
    $cooldown = 10;

    if (!podeRodarBackup($cooldown)) {
        backupLog("");
        return;
    }

    backupLog("DEBUG: passou no lock, vai executar");

    try {
        $database = 'prato_cheio';

        $backupService = new \Hcode\Backup\BackupService();
        $uploadService = new \Hcode\Backup\UploadService();

        backupLog("DEBUG: antes do createBackup()");
        $file = $backupService->createBackup($database);
        backupLog("Backup OK: " . basename($file));

        backupLog("DEBUG: antes do send()");
        $uploadService->send($file);
        backupLog("Upload realizado com sucesso");

        limparBackupsAntigos(7);
    } catch (\Exception $e) {
        backupLog("Erro: " . $e->getMessage());
    }
}

function getBackupNotifications(int $limit = 10): array
{
    $arquivo = backupLogFile();
    if (!file_exists($arquivo)) return [];

    $linhas = file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $linhas = array_reverse($linhas);

    $items = [];

    foreach (array_slice($linhas, 0, $limit) as $linha) {
        $parts = explode('|', $linha, 2);
        if (count($parts) < 2) continue;

        $data = trim($parts[0]);
        $msg  = trim($parts[1]);

        $items[] = [
            'msg'  => $msg,
            'time' => portal_format_manaus($data, 'H:i'),
            'date' => portal_format_manaus($data)
        ];
    }

    return $items;
}

function getUltimoBackup(): ?string
{
    $arquivo = backupLogFile();
    if (!file_exists($arquivo)) return null;

    $linhas = file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $linhas = array_reverse($linhas);

    foreach ($linhas as $linha) {
        if (strpos($linha, 'Backup OK:') !== false) {
            $parts = explode('|', $linha, 2);
            if (count($parts) < 2) continue;
            return trim($parts[0]);
        }
    }

    return null;
}

function getStatusBackup(): string
{
    $arquivo = backupLogFile();
    if (!file_exists($arquivo)) return 'Nunca executado';

    $linhas = array_reverse(
        file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
    );

    foreach ($linhas as $linha) {
        if (strpos($linha, 'Erro:') !== false) {
            return 'Erro no último backup';
        }
        if (strpos($linha, 'Upload realizado com sucesso') !== false) {
            return 'Backup enviado com sucesso';
        }
    }

    return 'Status desconhecido';
}

function limparBackupsAntigos(int $dias = 7): void
{
    $dir = defined('BACKUP_DIR') ? BACKUP_DIR : ($_SERVER["DOCUMENT_ROOT"] . '/backup');
    if (!is_dir($dir)) return;

    $limite = time() - ($dias * 86400);

    foreach (glob($dir . "/*.sql") as $file) {
        if (filemtime($file) < $limite) {
            @unlink($file);
            @unlink($file . '.sent');
            @unlink($file . '.uploading');
            backupLog("Backup removido: " . basename($file));
        }
    }
}


function currentUserPerfil(): string
{
    return $_SESSION[\Hcode\Model\Funcionarios::SESSION]['perfil'] ?? '';
}

function canAccess($permissionKey)
{
    return Funcionarios::can($permissionKey);
}

function canAnyAccess($permissions)
{
    foreach ((array)$permissions as $permission) {
        if (Funcionarios::can($permission)) return true;
    }
    return false;
}
