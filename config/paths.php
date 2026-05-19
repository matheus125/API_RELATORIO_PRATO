<?php
/**
 * Centralized project paths.
 * This file is loaded by public/index.php before Composer autoload.
 */

define('ROOT_DIR', realpath(__DIR__ . '/..'));
define('PUBLIC_DIR', ROOT_DIR . '/public');
define('APP_DIR', ROOT_DIR . '/app');
define('CONFIG_DIR', ROOT_DIR . '/config');

define('STORAGE_DIR', ROOT_DIR . '/storage');
define('LOG_DIR', STORAGE_DIR . '/logs');
define('BACKUP_DIR', STORAGE_DIR . '/backup');
define('VIEW_CACHE_DIR', STORAGE_DIR . '/cache/views');

define('VIEW_DIR', APP_DIR . '/views');
define('DATABASE_DIR', ROOT_DIR . '/database');
define('SQL_DIR', DATABASE_DIR . '/sql');
