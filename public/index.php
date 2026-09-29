<?php
session_start();

require_once __DIR__ . '/../config/paths.php';
require_once ROOT_DIR . '/vendor/autoload.php';

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

require_once APP_DIR . '/helpers/functions.php';
require_once APP_DIR . '/routes/admin.php';
require_once APP_DIR . '/routes/admin-funcionarios.php';
require_once APP_DIR . '/routes/admin-gadsan.php';
require_once APP_DIR . '/core/jwt.php';

$app->run();
