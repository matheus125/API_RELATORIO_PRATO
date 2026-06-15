<?php

$defaultSource = realpath(dirname(ROOT_DIR) . DIRECTORY_SEPARATOR . 'prato_web');

$config = [
    'source_root' => portal_env('UPDATE_SOURCE_ROOT', $defaultSource ?: ''),
    'driver' => portal_env('UPDATE_PUBLISH_DRIVER', 'local'),
    'public_base_url' => rtrim((string)portal_env('UPDATE_PUBLISH_PUBLIC_BASE_URL', 'https://prato-cheio.ms-tecnologia.app.br/prato/updates'), '/'),
    'remote_dir' => rtrim((string)portal_env('UPDATE_PUBLISH_REMOTE_DIR', ''), '/'),
    'http_upload_url' => (string)portal_env('UPDATE_PUBLISH_HTTP_UPLOAD_URL', ''),
    'http_upload_token' => (string)portal_env('UPDATE_PUBLISH_HTTP_UPLOAD_TOKEN', ''),
    'ftp_host' => (string)portal_env('UPDATE_PUBLISH_FTP_HOST', ''),
    'ftp_port' => (int)portal_env('UPDATE_PUBLISH_FTP_PORT', 21),
    'ftp_user' => (string)portal_env('UPDATE_PUBLISH_FTP_USER', ''),
    'ftp_password' => (string)portal_env('UPDATE_PUBLISH_FTP_PASSWORD', ''),
    'ftp_remote_dir' => rtrim((string)portal_env('UPDATE_PUBLISH_FTP_REMOTE_DIR', ''), '/'),
    'ftp_ssl' => filter_var(portal_env('UPDATE_PUBLISH_FTP_SSL', false), FILTER_VALIDATE_BOOLEAN),
    'sftp_host' => (string)portal_env('UPDATE_PUBLISH_SFTP_HOST', ''),
    'sftp_port' => (int)portal_env('UPDATE_PUBLISH_SFTP_PORT', 22),
    'sftp_user' => (string)portal_env('UPDATE_PUBLISH_SFTP_USER', ''),
    'sftp_password' => (string)portal_env('UPDATE_PUBLISH_SFTP_PASSWORD', ''),
    'sftp_remote_dir' => rtrim((string)portal_env('UPDATE_PUBLISH_SFTP_REMOTE_DIR', ''), '/'),
    'php_cli_path' => (string)portal_env('PHP_CLI_PATH', 'php'),
    'env_global_keys' => array_filter(array_map('trim', explode(',', (string)portal_env(
        'UPDATE_PUBLISH_ENV_KEYS',
        'SMTP_HOST,SMTP_PORT,SMTP_USER,SMTP_FROM_EMAIL,SMTP_FROM_NAME,SMTP_SECURE,UPDATE_MONITOR_URL,UPDATE_MONITOR_ADMIN_URL,PHP_CLI_PATH'
    )))),
    'log_file' => 'update-publish.log',
];

$localConfig = STORAGE_DIR . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'update-publish.php';
if (is_file($localConfig)) {
    $override = require $localConfig;
    if (is_array($override)) {
        $config = array_merge($config, $override);
    }
}

return $config;
