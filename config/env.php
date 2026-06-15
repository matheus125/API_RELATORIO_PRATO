<?php

if (!function_exists('portal_load_env')) {
    function portal_load_env($path = null): void
    {
        static $loaded = [];

        if ($path === null) {
            $root = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__);
            $path = $root . DIRECTORY_SEPARATOR . '.env';
        }

        $real = realpath($path);
        if ($real === false || isset($loaded[$real]) || !is_file($real)) {
            return;
        }

        $loaded[$real] = true;
        $lines = file($real, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key === '' || preg_match('/^[A-Z0-9_]+$/i', $key) !== 1) {
                continue;
            }

            if (
                strlen($value) >= 2 &&
                (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            if (getenv($key) === false) {
                putenv($key . '=' . $value);
            }
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
            }
            if (!array_key_exists($key, $_SERVER)) {
                $_SERVER[$key] = $value;
            }
        }
    }
}

if (!function_exists('portal_env')) {
    function portal_env(string $key, $default = null)
    {
        $value = getenv($key);
        if ($value === false && array_key_exists($key, $_ENV)) {
            $value = $_ENV[$key];
        }
        if ($value === false && array_key_exists($key, $_SERVER)) {
            $value = $_SERVER[$key];
        }
        return ($value === false || $value === '') ? $default : $value;
    }
}
