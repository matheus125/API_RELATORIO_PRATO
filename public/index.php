<?php

require_once __DIR__ . '/../config/paths.php';
require_once ROOT_DIR . '/vendor/autoload.php';
require_once APP_DIR . '/helpers/functions.php';

// Harden the session before starting it. The application is served behind HTTPS
// in production, while the local development server remains usable over HTTP.
$httpsRequest = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $httpsRequest,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
if ($httpsRequest) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

use Slim\Slim;
use Hcode\Middleware\PerfilMiddleware;

$debugEnabled = portal_env_bool('APP_DEBUG', false);
if (!is_dir(LOG_DIR)) {
    @mkdir(LOG_DIR, 0775, true);
}
ini_set('log_errors', '1');
ini_set('error_log', LOG_DIR . DIRECTORY_SEPARATOR . 'app-errors.log');
ini_set('display_errors', $debugEnabled ? '1' : '0');
ini_set('display_startup_errors', $debugEnabled ? '1' : '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

$app = new Slim();
$app->config('debug', $debugEnabled);
$app->add(new PerfilMiddleware());

require_once APP_DIR . '/routes/admin.php';
require_once APP_DIR . '/routes/admin-funcionarios.php';
require_once APP_DIR . '/routes/admin-gadsan.php';
require_once APP_DIR . '/core/jwt.php';

$app->run();
