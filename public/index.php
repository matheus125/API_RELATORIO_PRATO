<?php
session_start();

require_once __DIR__ . '/../config/paths.php';
require_once ROOT_DIR . '/vendor/autoload.php';

use Slim\Slim;
use Hcode\Middleware\PerfilMiddleware;

$app = new Slim();
$app->config('debug', true);
$app->add(new PerfilMiddleware());

require_once APP_DIR . '/helpers/functions.php';
require_once APP_DIR . '/routes/admin.php';
require_once APP_DIR . '/routes/admin-funcionarios.php';
require_once APP_DIR . '/core/jwt.php';

$app->run();
