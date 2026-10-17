<?php

declare(strict_types=1);

// Run only on the disposable MySQL instance used by tests/permissions/mysql.php.
// php tests/permissions/catalog.php
// Optional: ACL_TEST_DB_PORT, ACL_TEST_CATALOG_DB_NAME, ACL_TEST_PERMISSIONS_CLASS.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use Hcode\Model\Permissions;

$root = dirname(__DIR__, 2);
$port = getenv('ACL_TEST_DB_PORT') ?: '33316';
$database = getenv('ACL_TEST_CATALOG_DB_NAME') ?: 'portal_acl_test_catalog';
if (!ctype_digit($port) || (int)$port < 1024 || (int)$port > 65535 ||
    !preg_match('/^portal_acl_test_[a-z0-9_]+$/D', $database)) {
    fwrite(STDERR, "Use uma porta de teste e um banco portal_acl_test_* .\n");
    exit(1);
}
foreach ([
    'DB_HOST' => '127.0.0.1', 'DB_PORT' => $port, 'DB_USER' => 'root',
    'DB_PASSWORD' => '', 'DB_NAME' => $database,
    'DB_CONNECT_TIMEOUT' => '2', 'DB_QUERY_TIMEOUT_MS' => '5000',
] as $name => $value) {
    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

// Prevent Sql from loading application or production credentials from .env.
function portal_load_env($path = null): void
{
}

require $root . '/vendor/autoload.php';
require getenv('ACL_TEST_PERMISSIONS_CLASS') ?: $root . '/vendor/API/php-classes/src/Model/Permissions.php';

$checks = 0;
$failures = [];
function catalogVerify(bool $condition, string $label): void
{
    global $checks, $failures;
    ++$checks;
    if (!$condition) {
        $failures[] = $label;
        fwrite(STDERR, 'FALHOU: ' . $label . "\n");
    }
}

function catalogSorted(array $keys): array
{
    $keys = array_values(array_unique($keys));
    sort($keys);
    return $keys;
}

function catalogVerifyKeys(array $actual, array $expected, string $label): void
{
    catalogVerify(catalogSorted($actual) === catalogSorted($expected), $label);
}

function catalogSchema(PDO $db): void
{
    $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    if (array_diff($tables, ['tb_permissions', 'tb_profile_permissions'])) {
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
}

function catalogStored(PDO $db, string $profile): array
{
    $statement = $db->prepare('SELECT p.permission_key FROM tb_permissions p
        INNER JOIN tb_profile_permissions pp ON pp.id_permission = p.id_permission
        WHERE pp.perfil = ? ORDER BY p.permission_key');
    $statement->execute([$profile]);
    return $statement->fetchAll(PDO::FETCH_COLUMN);
}

function catalogSnapshot(PDO $db): array
{
    return [
        $db->query('SELECT id_permission, permission_key, description, module_name FROM tb_permissions ORDER BY id_permission')->fetchAll(),
        $db->query('SELECT perfil, id_permission FROM tb_profile_permissions ORDER BY perfil, id_permission')->fetchAll(),
    ];
}

function catalogExpectException(callable $action, string $label): void
{
    try {
        $action();
        catalogVerify(false, $label);
    } catch (Throwable $error) {
        catalogVerify(true, $label);
    }
}

try {
    $db = new PDO('mysql:host=127.0.0.1;port=' . $port . ';charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db->exec('CREATE DATABASE IF NOT EXISTS `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $db->exec('USE `' . $database . '`');
    catalogVerify($db->query('SELECT DATABASE()')->fetchColumn() === $database, 'conexão restrita ao banco isolado do catálogo');

    $definitions = Permissions::definitions();
    $defaults = Permissions::defaultProfilePermissions();
    $profiles = ['ADMIN', 'SUPERVISOR', 'ASSESSOR', 'CONSULTA'];
    $newKeys = [
        'ANALISE_INTELIGENTE_VIEW', 'PDF_HISTORICO_VIEW', 'PLANILHA_VIEW',
        'UNIDADES_ATUALIZADAS_VIEW', 'CONSULTA_UNIFICADA_VIEW',
        'CONSULTA_UNIFICADA_SYNC', 'UPDATE_MONITORING_MANAGE',
    ];
    $retiredKeys = [
        'CONSULTA_RESTRITA_VIEW', 'SISTEMA_DEBUG', 'CLIENTES_CREATE', 'CLIENTES_UPDATE',
        'CLIENTES_DELETE', 'DEPENDENTES_CREATE', 'DEPENDENTES_UPDATE', 'DEPENDENTES_VIEW',
        'VENDAS_VIEW', 'BACKUP_RUN',
    ];
    catalogVerify(count(array_intersect($newKeys, array_keys($definitions))) === count($newKeys), 'catálogo contém todas as telas e ações novas');
    catalogVerify(!array_intersect($retiredKeys, array_keys($definitions)), 'definições ativas excluem permissões obsoletas');

    // Fresh installation still receives the declared defaults.
    catalogSchema($db);
    Permissions::syncDefinitions();
    catalogVerifyKeys(array_column(Permissions::listAll(), 'permission_key'), array_keys($definitions), 'instalação nova lista somente o catálogo ativo');
    foreach ($profiles as $profile) {
        catalogVerifyKeys(catalogStored($db, $profile), $defaults[$profile], "instalação nova aplica padrões de $profile");
    }

    // Recreate the pre-migration catalogue with deliberately revoked permissions.
    catalogSchema($db);
    $insert = $db->prepare('INSERT INTO tb_permissions (permission_key, description, module_name) VALUES (?, ?, ?)');
    foreach ($definitions as $key => $definition) {
        if (!in_array($key, $newKeys, true)) {
            $insert->execute([$key, $definition['description'], $definition['module_name']]);
        }
    }
    foreach ($retiredKeys as $key) {
        $insert->execute([$key, 'Permissão legada', 'LEGADO']);
    }
    $before = [
        'ADMIN' => ['DASHBOARD_VIEW', 'CONSULTA_RESTRITA_VIEW', 'UPDATE_MONITORING_VIEW', 'ACL_PROFILES_MANAGE', 'CLIENTES_CREATE'],
        'SUPERVISOR' => ['UPDATE_MONITORING_VIEW'],
        'ASSESSOR' => ['CONSULTA_RESTRITA_VIEW'],
        'CONSULTA' => ['DASHBOARD_VIEW', 'UPDATE_MONITORING_VIEW'],
    ];
    $grant = $db->prepare('INSERT INTO tb_profile_permissions (perfil, id_permission)
        SELECT ?, id_permission FROM tb_permissions WHERE permission_key = ?');
    foreach ($before as $profile => $keys) {
        foreach ($keys as $key) {
            $grant->execute([$profile, $key]);
        }
    }
    $expectedNew = [
        'ADMIN' => $newKeys,
        'SUPERVISOR' => [],
        'ASSESSOR' => ['ANALISE_INTELIGENTE_VIEW', 'CONSULTA_UNIFICADA_VIEW'],
        'CONSULTA' => ['PDF_HISTORICO_VIEW', 'PLANILHA_VIEW', 'UNIDADES_ATUALIZADAS_VIEW', 'UPDATE_MONITORING_MANAGE'],
    ];

    // A database error during inheritance must leave both tables intact.
    $snapshot = catalogSnapshot($db);
    $db->exec("CREATE TRIGGER acl_catalog_sync_failure BEFORE INSERT ON tb_profile_permissions FOR EACH ROW
        BEGIN
            IF NEW.perfil = 'ADMIN' AND NEW.id_permission =
                (SELECT id_permission FROM tb_permissions WHERE permission_key = 'CONSULTA_UNIFICADA_VIEW') THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Falha deliberada na migração de teste';
            END IF;
        END");
    try {
        catalogExpectException(static function (): void { Permissions::syncDefinitions(); }, 'falha de banco interrompe migração');
        catalogVerify(catalogSnapshot($db) === $snapshot, 'migração com erro reverte catálogo e concessões');
    } finally {
        $db->exec('DROP TRIGGER IF EXISTS acl_catalog_sync_failure');
    }

    Permissions::syncDefinitions();
    foreach ($profiles as $profile) {
        catalogVerifyKeys(catalogStored($db, $profile), array_merge($before[$profile], $expectedNew[$profile]), "migração herda concessões atuais e preserva revogações de $profile");
    }
    catalogVerifyKeys(array_column(Permissions::listAll(), 'permission_key'), array_keys($definitions), 'permissões legadas não aparecem na matriz');
    $retained = $db->query('SELECT permission_key FROM tb_permissions')->fetchAll(PDO::FETCH_COLUMN);
    catalogVerify(count(array_intersect($retiredKeys, $retained)) === count($retiredKeys), 'migração preserva registros legados sem oferecê-los na tela');

    $expected = [
        'ADMIN' => ['ACL_PROFILES_MANAGE'],
        'SUPERVISOR' => ['ANALISE_INTELIGENTE_VIEW', 'PDF_HISTORICO_VIEW'],
        'ASSESSOR' => ['CONSULTA_UNIFICADA_VIEW', 'PLANILHA_VIEW'],
        'CONSULTA' => ['UNIDADES_ATUALIZADAS_VIEW'],
    ];
    Permissions::saveMatrix($expected);
    for ($reload = 1; $reload <= 3; ++$reload) {
        Permissions::syncDefinitions();
        Permissions::listAll();
        foreach ($profiles as $profile) {
            catalogVerifyKeys(catalogStored($db, $profile), $expected[$profile], "reabertura $reload preserva permissões independentes de $profile");
        }
    }

    foreach (['ACL_UNKNOWN_TEST', 'CLIENTES_CREATE'] as $invalidKey) {
        $invalid = $expected;
        $invalid['ADMIN'] = ['DASHBOARD_VIEW'];
        $invalid['SUPERVISOR'][] = $invalidKey;
        $snapshot = catalogSnapshot($db);
        catalogExpectException(static function () use ($invalid): void { Permissions::saveMatrix($invalid); }, "matriz rejeita chave inativa $invalidKey");
        catalogVerify(catalogSnapshot($db) === $snapshot, "chave inválida $invalidKey não altera nenhum perfil");
    }

    // Fail after the ADMIN changes, proving that all four profiles share one transaction.
    $db->exec("CREATE TRIGGER acl_catalog_save_failure BEFORE INSERT ON tb_profile_permissions FOR EACH ROW
        BEGIN
            IF NEW.perfil = 'SUPERVISOR' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Falha deliberada ao salvar matriz de teste';
            END IF;
        END");
    $snapshot = catalogSnapshot($db);
    $replacement = [
        'ADMIN' => ['DASHBOARD_VIEW'], 'SUPERVISOR' => ['FUNCIONARIOS_VIEW'],
        'ASSESSOR' => [], 'CONSULTA' => [],
    ];
    try {
        catalogExpectException(static function () use ($replacement): void { Permissions::saveMatrix($replacement); }, 'falha parcial interrompe salvamento da matriz');
        catalogVerify(catalogSnapshot($db) === $snapshot, 'falha no segundo perfil reverte mudanças em todos os perfis');
    } finally {
        $db->exec('DROP TRIGGER IF EXISTS acl_catalog_save_failure');
    }

    // A completely cleared profile must remain empty after future catalogue synchronizations.
    $empty = array_fill_keys($profiles, []);
    Permissions::saveMatrix($empty);
    Permissions::syncDefinitions();
    foreach ($profiles as $profile) {
        catalogVerifyKeys(catalogStored($db, $profile), [], "matriz vazia preserva zero concessões de $profile");
    }

    // Definitions without inheritance retain the previous one-time default behavior.
    $db->exec("DELETE FROM tb_permissions WHERE permission_key = 'NOTIFICACOES_UPDATE'");
    Permissions::syncDefinitions();
    foreach ($profiles as $profile) {
        $expectedKeys = in_array('NOTIFICACOES_UPDATE', $defaults[$profile], true) ? ['NOTIFICACOES_UPDATE'] : [];
        catalogVerifyKeys(catalogStored($db, $profile), $expectedKeys, "nova definição sem herança recebe padrão uma vez para $profile");
    }
    Permissions::saveMatrix($empty);
    Permissions::syncDefinitions();
    foreach ($profiles as $profile) {
        catalogVerifyKeys(catalogStored($db, $profile), [], "revogação do padrão permanece salva para $profile");
    }

    if ($failures) {
        fwrite(STDERR, count($failures) . " falhas em $checks verificações do catálogo.\n");
        exit(1);
    }
    echo "OK: $checks verificações de catálogo e matriz; banco isolado $database em 127.0.0.1:$port.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Teste de catálogo interrompido: ' . $error->getMessage() . "\n");
    exit(1);
}
