<?php

declare(strict_types=1);

// Run only against a disposable MySQL instance. The application .env is never loaded.
// php tests/permissions/mysql.php
// Optional: ACL_TEST_DB_PORT, ACL_TEST_DB_NAME, ACL_TEST_PERMISSIONS_CLASS,
// ACL_TEST_FUNCIONARIOS_CLASS (the last two also apply to request subprocesses).
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use Hcode\Model\Funcionarios;
use Hcode\Model\Permissions;

$root = dirname(__DIR__, 2);
$port = getenv('ACL_TEST_DB_PORT') ?: '33316';
$database = getenv('ACL_TEST_DB_NAME') ?: 'portal_acl_test';
if (!ctype_digit($port) || (int)$port < 1024 || (int)$port > 65535 ||
    !preg_match('/^portal_acl_test(?:_[a-z0-9_]+)?$/D', $database)) {
    fwrite(STDERR, "Use uma porta de teste e um banco portal_acl_test ou portal_acl_test_* .\n");
    exit(1);
}

foreach ([
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => $port,
    'DB_USER' => 'root',
    'DB_PASSWORD' => '',
    'DB_NAME' => $database,
    'DB_CONNECT_TIMEOUT' => '2',
    'DB_QUERY_TIMEOUT_MS' => '5000',
] as $name => $value) {
    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

// Sql includes config/env.php; its guarded definition respects this no-op loader.
function portal_load_env($path = null): void
{
}

require $root . '/vendor/autoload.php';
require getenv('ACL_TEST_PERMISSIONS_CLASS') ?: $root . '/vendor/API/php-classes/src/Model/Permissions.php';
require getenv('ACL_TEST_FUNCIONARIOS_CLASS') ?: $root . '/vendor/API/php-classes/src/Model/Funcionarios.php';

function testDatabase(): PDO
{
    return new PDO(
        'mysql:host=127.0.0.1;port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_NAME') . ';charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

function sortedKeys(array $keys): array
{
    $keys = array_values(array_unique($keys));
    sort($keys);
    return $keys;
}

function storedPermissions(PDO $db, string $profile): array
{
    $statement = $db->prepare('SELECT p.permission_key FROM tb_permissions p
        INNER JOIN tb_profile_permissions pp ON pp.id_permission = p.id_permission
        WHERE pp.perfil = ? ORDER BY p.permission_key');
    $statement->execute([$profile]);
    return $statement->fetchAll(PDO::FETCH_COLUMN);
}

function setSession(string $profile, array $stalePermissions, int $userId = 8001): void
{
    $_SESSION = [Funcionarios::SESSION => [
        'id_usuario' => $userId,
        'perfil' => $profile,
        'permissions' => $stalePermissions,
    ]];
}

// Each subprocess represents another HTTP request with a persisted, possibly stale session.
if (($argv[1] ?? '') === '--request') {
    try {
        $request = json_decode(base64_decode($argv[2] ?? '', true), true, 512, JSON_THROW_ON_ERROR);
        setSession($request['profile'], $request['stale']);
        $first = Funcionarios::getPermissions();
        $session = $_SESSION[Funcionarios::SESSION]['permissions'] ?? null;
        $result = [
            'first' => $first,
            'session' => $session,
            'repeated' => Funcionarios::getPermissions(),
        ];

        if (($request['mode'] ?? '') === 'refresh') {
            $db = testDatabase();
            $delete = $db->prepare('DELETE FROM tb_profile_permissions WHERE perfil = ?');
            $delete->execute([$request['profile']]);
            $result['same_request'] = Funcionarios::getPermissions();
            $result['refreshed'] = Funcionarios::refreshPermissions();
        } elseif (($request['mode'] ?? '') === 'profile-switch') {
            setSession($request['next_profile'], $first);
            $result['switched'] = Funcionarios::getPermissions();
        } elseif (($request['mode'] ?? '') === 'user-switch') {
            $db = testDatabase();
            $delete = $db->prepare('DELETE FROM tb_profile_permissions WHERE perfil = ?');
            $delete->execute([$request['profile']]);
            setSession($request['profile'], $first, 8002);
            $result['switched'] = Funcionarios::getPermissions();
        }

        echo json_encode($result, JSON_THROW_ON_ERROR) . "\n";
        exit(0);
    } catch (Throwable $error) {
        fwrite(STDERR, 'Falha na request isolada: ' . $error->getMessage() . "\n");
        exit(1);
    }
}

$checks = 0;
$failures = [];
function verify(bool $condition, string $label): void
{
    global $checks, $failures;
    ++$checks;
    if (!$condition) {
        $failures[] = $label;
        fwrite(STDERR, 'FALHOU: ' . $label . "\n");
    }
}

function verifyPermissions(array $actual, array $expected, string $label): void
{
    verify(sortedKeys($actual) === sortedKeys($expected), $label);
}

function requestPermissions(array $request): array
{
    $process = proc_open(
        [PHP_BINARY, __FILE__, '--request', base64_encode(json_encode($request, JSON_THROW_ON_ERROR))],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Não foi possível iniciar a request isolada.');
    }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Request isolada falhou: ' . trim($error));
    }
    return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
}

try {
    $db = testDatabase();
    verify($db->query('SELECT DATABASE()')->fetchColumn() === $database, 'conexão restrita ao banco de teste');
    $existingTables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    if (array_diff($existingTables, ['tb_permissions', 'tb_profile_permissions'])) {
        throw new RuntimeException('Banco de teste contém tabelas inesperadas; nada foi removido.');
    }
    $db->exec('DROP TABLE IF EXISTS tb_profile_permissions');
    $db->exec('DROP TABLE IF EXISTS tb_permissions');
    $db->exec("CREATE TABLE tb_permissions (
        id_permission INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        permission_key VARCHAR(100) NOT NULL UNIQUE,
        description VARCHAR(255) NOT NULL,
        module_name VARCHAR(80) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->exec("CREATE TABLE tb_profile_permissions (
        id_profile_permission INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        perfil ENUM('ADMIN','SUPERVISOR','ASSESSOR','CONSULTA') NOT NULL,
        id_permission INT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_profile_permission (perfil, id_permission),
        FOREIGN KEY (id_permission) REFERENCES tb_permissions(id_permission) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $defaults = Permissions::defaultProfilePermissions();
    Permissions::syncDefinitions();
    verifyPermissions(
        $db->query('SELECT permission_key FROM tb_permissions')->fetchAll(PDO::FETCH_COLUMN),
        array_keys(Permissions::definitions()),
        'primeira sincronização cadastra todo o catálogo'
    );
    foreach ($defaults as $profile => $keys) {
        verifyPermissions(storedPermissions($db, $profile), $keys, "primeira instalação aplica padrão de $profile");
    }

    // The POST route saves all profiles sequentially; a later save must not undo an earlier one.
    $expected = [
        'ADMIN' => ['ACL_PROFILES_MANAGE'],
        'SUPERVISOR' => ['FUNCIONARIOS_VIEW'],
        'ASSESSOR' => [],
        'CONSULTA' => [],
    ];
    foreach ($expected as $profile => $keys) {
        Permissions::saveProfilePermissions($profile, $keys);
    }
    foreach ($expected as $profile => $keys) {
        verifyPermissions(storedPermissions($db, $profile), $keys, "salvamento sequencial preserva desmarcações de $profile");
    }
    for ($reload = 1; $reload <= 3; ++$reload) {
        Permissions::syncDefinitions();
        Permissions::listAll();
        foreach ($expected as $profile => $keys) {
            verifyPermissions(Permissions::listByProfile($profile), $keys, "reabertura $reload preserva $profile");
        }
    }

    // Existing catalogue entries can be granted to profiles that do not receive them by default.
    $expected['SUPERVISOR'][] = 'FUNCIONARIOS_DELETE';
    Permissions::saveProfilePermissions('SUPERVISOR', $expected['SUPERVISOR']);
    $db->exec("INSERT INTO tb_permissions (permission_key, description, module_name)
        VALUES ('ACL_TEST_CUSTOM', 'Permissão sintética local', 'TESTE')");
    $expected['ASSESSOR'] = ['ACL_TEST_CUSTOM'];
    Permissions::saveProfilePermissions('ASSESSOR', $expected['ASSESSOR']);
    Permissions::syncDefinitions();
    verifyPermissions(storedPermissions($db, 'SUPERVISOR'), $expected['SUPERVISOR'], 'concessão fora do padrão persiste');
    verifyPermissions(storedPermissions($db, 'ASSESSOR'), $expected['ASSESSOR'], 'permissão cadastrada fora do catálogo persiste');

    $db->exec("UPDATE tb_permissions SET description = 'Descrição antiga', module_name = 'ANTIGO'
        WHERE permission_key = 'DASHBOARD_VIEW'");
    Permissions::syncDefinitions();
    $metadata = $db->query("SELECT description, module_name FROM tb_permissions WHERE permission_key = 'DASHBOARD_VIEW'")->fetch();
    verify($metadata === Permissions::definitions()['DASHBOARD_VIEW'], 'sincronização atualiza metadados existentes');
    foreach ($expected as $profile => $keys) {
        verifyPermissions(storedPermissions($db, $profile), $keys, "atualizar catálogo não restaura concessões de $profile");
    }

    // Removing one definition simulates installing a release with a genuinely new catalogue key.
    $newKey = 'NOTIFICACOES_UPDATE';
    $db->exec("DELETE FROM tb_permissions WHERE permission_key = 'NOTIFICACOES_UPDATE'");
    Permissions::syncDefinitions();
    foreach ($expected as $profile => $keys) {
        $withNewDefinition = $keys;
        if (in_array($newKey, $defaults[$profile], true)) {
            $withNewDefinition[] = $newKey;
        }
        verifyPermissions(storedPermissions($db, $profile), $withNewDefinition, "nova definição recebe padrão uma vez para $profile");
    }
    foreach ($expected as $profile => $keys) {
        Permissions::saveProfilePermissions($profile, $keys);
    }
    Permissions::syncDefinitions();
    foreach ($expected as $profile => $keys) {
        verifyPermissions(storedPermissions($db, $profile), $keys, "revogação da nova definição persiste para $profile");
    }

    foreach ($expected as $profile => $keys) {
        $response = requestPermissions(['profile' => $profile, 'stale' => $defaults[$profile]]);
        verifyPermissions($response['first'], $keys, "nova request atualiza sessão antiga de $profile");
        verifyPermissions($response['session'], $keys, "sessão recebe permissões atuais de $profile");
        verifyPermissions($response['repeated'], $keys, "mesma request mantém permissões atualizadas de $profile");
    }
    $response = requestPermissions(['profile' => 'SUPERVISOR', 'stale' => []]);
    verifyPermissions($response['first'], $expected['SUPERVISOR'], 'nova concessão aparece em sessão anteriormente vazia');
    $response = requestPermissions(['profile' => 'CONSULTA', 'stale' => ['CONSULTA_UNIFICADA_VIEW']]);
    verifyPermissions($response['first'], [], 'perfil sem concessões permanece vazio sem fallback');

    $response = requestPermissions([
        'profile' => 'SUPERVISOR', 'stale' => [], 'mode' => 'profile-switch', 'next_profile' => 'ASSESSOR',
    ]);
    verifyPermissions($response['switched'], $expected['ASSESSOR'], 'troca de perfil invalida cache da request');
    $response = requestPermissions(['profile' => 'SUPERVISOR', 'stale' => [], 'mode' => 'refresh']);
    verifyPermissions($response['same_request'], $expected['SUPERVISOR'], 'request mantém snapshot até atualização explícita');
    verifyPermissions($response['refreshed'], [], 'refresh explícito reflete revogação total');
    Permissions::saveProfilePermissions('SUPERVISOR', $expected['SUPERVISOR']);
    $response = requestPermissions(['profile' => 'SUPERVISOR', 'stale' => [], 'mode' => 'user-switch']);
    verifyPermissions($response['switched'], [], 'troca de usuário invalida cache mesmo com perfil igual');
    Permissions::saveProfilePermissions('SUPERVISOR', $expected['SUPERVISOR']);

    // Both ACL tables still exist, but this malformed column forces the actual grants query to fail.
    $db->exec('ALTER TABLE tb_permissions CHANGE permission_key unavailable_permission_key VARCHAR(100) NOT NULL');
    try {
        foreach (['ADMIN', 'SUPERVISOR', 'ASSESSOR'] as $profile) {
            $response = requestPermissions(['profile' => $profile, 'stale' => $defaults[$profile]]);
            verifyPermissions($response['first'], [], "erro na consulta não concede permissões a $profile");
            verifyPermissions($response['session'], [], "erro elimina permissões antigas da sessão de $profile");
        }
    } finally {
        $db->exec('ALTER TABLE tb_permissions CHANGE unavailable_permission_key permission_key VARCHAR(100) NOT NULL');
    }

    if ($failures) {
        fwrite(STDERR, count($failures) . " falhas em $checks verificações MySQL de permissões.\n");
        exit(1);
    }
    echo "OK: $checks verificações MySQL de permissões; banco isolado $database em 127.0.0.1:$port.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Teste MySQL de permissões interrompido: ' . $error->getMessage() . "\n");
    exit(1);
}
