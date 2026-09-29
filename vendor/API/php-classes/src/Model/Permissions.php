<?php

namespace Hcode\Model;
use \Hcode\DB\Sql;

class Permissions
{
    public static function definitions(): array
    {
        return [
            'DASHBOARD_VIEW' => ['description' => 'Visualizar dashboard', 'module_name' => 'DASHBOARD'],

            'GADSAN_VIEW' => ['description' => 'Visualizar colaboradores GADSAN', 'module_name' => 'GADSAN'],
            'GADSAN_EDIT' => ['description' => 'Cadastrar e editar colaboradores GADSAN', 'module_name' => 'GADSAN'],
            'GADSAN_AUX' => ['description' => 'Gerenciar cadastros auxiliares GADSAN', 'module_name' => 'GADSAN'],
            'GADSAN_IMPORT' => ['description' => 'Importar e revisar dados GADSAN', 'module_name' => 'GADSAN'],
            'FUNCIONARIOS_VIEW' => ['description' => 'Visualizar funcionários', 'module_name' => 'FUNCIONARIOS'],
            'FUNCIONARIOS_CREATE' => ['description' => 'Cadastrar funcionários', 'module_name' => 'FUNCIONARIOS'],
            'FUNCIONARIOS_UPDATE' => ['description' => 'Editar funcionários', 'module_name' => 'FUNCIONARIOS'],
            'FUNCIONARIOS_DELETE' => ['description' => 'Excluir/inativar funcionários', 'module_name' => 'FUNCIONARIOS'],
            'FUNCIONARIOS_PASSWORD' => ['description' => 'Alterar senha de funcionários', 'module_name' => 'FUNCIONARIOS'],

            'ACL_PROFILES_MANAGE' => ['description' => 'Gerenciar permissões por perfil', 'module_name' => 'SEGURANCA'],
            'ACL_DENIED_VIEW' => ['description' => 'Visualizar acessos negados', 'module_name' => 'SEGURANCA'],
            'USUARIOS_SECURITY_MANAGE' => ['description' => 'Gerenciar status e bloqueio de usuários', 'module_name' => 'SEGURANCA'],
            'AUDITORIA_VIEW' => ['description' => 'Visualizar logs de auditoria', 'module_name' => 'SEGURANCA'],
            'UPDATE_MONITORING_VIEW' => ['description' => 'Visualizar monitoramento de atualizações', 'module_name' => 'SEGURANCA'],
            'UPDATE_PUBLISH_MANAGE' => ['description' => 'Publicar atualizações do sistema', 'module_name' => 'SEGURANCA'],
            'PLANILHA_EXPORT' => ['description' => 'Gerar e baixar planilhas', 'module_name' => 'RELATORIOS'],
            'SISTEMA_DEBUG' => ['description' => 'Acessar rotas de debug', 'module_name' => 'SEGURANCA'],
        ];
    }

    public static function routeMap(): array
    {
        return [
            '/admin' => 'DASHBOARD_VIEW',
            '/admin/index' => 'DASHBOARD_VIEW',
            '/admin/funcionarios' => 'FUNCIONARIOS_VIEW',
            '/admin/funcionarios/create' => 'FUNCIONARIOS_CREATE',
            '/admin/funcionarios/:id_usuario' => 'FUNCIONARIOS_UPDATE',
            '/admin/funcionarios/:id_usuario/password' => 'FUNCIONARIOS_PASSWORD',
            '/admin/funcionarios/:id_usuario/delete' => 'FUNCIONARIOS_DELETE',
            '/admin/funcionarios/verificar-cpf' => 'FUNCIONARIOS_UPDATE',
            '/admin/seguranca/permissoes' => 'ACL_PROFILES_MANAGE',
            '/admin/seguranca/acessos-negados' => 'ACL_DENIED_VIEW',
            '/admin/usuarios/seguranca' => 'USUARIOS_SECURITY_MANAGE',
            '/admin/seguranca/auditoria' => 'AUDITORIA_VIEW',
            '/admin/monitoramento-atualizacoes' => 'UPDATE_MONITORING_VIEW',
            '/admin/api/updates/monitoring' => 'UPDATE_MONITORING_VIEW',
            '/admin/publicar-atualizacao' => 'UPDATE_PUBLISH_MANAGE',
            '/admin/api/update-publish/preview' => 'UPDATE_PUBLISH_MANAGE',
            '/admin/api/update-publish/test-connection' => 'UPDATE_PUBLISH_MANAGE',
            '/admin/api/update-publish/publish' => 'UPDATE_PUBLISH_MANAGE',
        ];
    }

    public static function defaultProfilePermissions(): array
    {
        return [
            'ADMIN' => array_keys(self::definitions()),
            'SUPERVISOR' => [
                'DASHBOARD_VIEW',
                'FUNCIONARIOS_VIEW',
                'FUNCIONARIOS_CREATE',
                'FUNCIONARIOS_UPDATE',
                'FUNCIONARIOS_PASSWORD',
                'ACL_DENIED_VIEW',
                'AUDITORIA_VIEW',
                'PLANILHA_EXPORT'
            ],
            'ASSESSOR' => [
                'DASHBOARD_VIEW',
                'FUNCIONARIOS_VIEW'
            ]
        ];
    }

    private static function normalizeDefinition(string $permissionKey, $definition): array
    {
        if (is_array($definition)) {
            return [
                'permission_key' => $permissionKey,
                'description' => $definition['description'] ?? $permissionKey,
                'module_name' => $definition['module_name'] ?? self::inferModuleName($permissionKey),
            ];
        }

        return [
            'permission_key' => $permissionKey,
            'description' => (string)$definition,
            'module_name' => self::inferModuleName($permissionKey),
        ];
    }

    private static function inferModuleName(string $permissionKey): string
    {
        $parts = explode('_', $permissionKey);
        return $parts[0] ?? 'SISTEMA';
    }

    public static function syncDefinitions(): void
    {
        $sql = new Sql();
        $existsPermissions = $sql->select("SHOW TABLES LIKE 'tb_permissions'");
        $existsProfilePermissions = $sql->select("SHOW TABLES LIKE 'tb_profile_permissions'");

        if (count($existsPermissions) === 0 || count($existsProfilePermissions) === 0) {
            return;
        }

        foreach (self::definitions() as $permissionKey => $definition) {
            $normalized = self::normalizeDefinition($permissionKey, $definition);

            $sql->query(
                "INSERT INTO tb_permissions (permission_key, description, module_name, created_at)
                 VALUES (:permission_key, :description, :module_name, NOW())
                 ON DUPLICATE KEY UPDATE
                    description = VALUES(description),
                    module_name = VALUES(module_name)",
                [
                    ':permission_key' => $normalized['permission_key'],
                    ':description' => $normalized['description'],
                    ':module_name' => $normalized['module_name'],
                ]
            );
        }

        foreach (self::defaultProfilePermissions() as $perfil => $permissionKeys) {
            foreach ($permissionKeys as $key) {
                $sql->query(
                    "INSERT IGNORE INTO tb_profile_permissions (perfil, id_permission)
                     SELECT :perfil, id_permission
                     FROM tb_permissions
                     WHERE permission_key = :permission_key",
                    [
                        ':perfil' => $perfil,
                        ':permission_key' => $key,
                    ]
                );
            }
        }
    }

    public static function listAll(): array
    {
        self::syncDefinitions();
        $sql = new Sql();
        return $sql->select("SELECT * FROM tb_permissions ORDER BY module_name, description");
    }

    public static function listByProfile(string $perfil): array
    {
        self::syncDefinitions();
        $sql = new Sql();
        $rows = $sql->select("SELECT p.*
            FROM tb_permissions p
            INNER JOIN tb_profile_permissions pp ON pp.id_permission = p.id_permission
            WHERE pp.perfil = :perfil
            ORDER BY p.module_name, p.description", [':perfil' => $perfil]);
        return array_map(fn($r) => $r['permission_key'], $rows);
    }

    public static function saveProfilePermissions(string $perfil, array $permissionKeys): void
    {
        self::syncDefinitions();
        $sql = new Sql();
        $sql->query("DELETE pp FROM tb_profile_permissions pp WHERE pp.perfil = :perfil", [':perfil' => $perfil]);
        foreach ($permissionKeys as $key) {
            $sql->query("INSERT IGNORE INTO tb_profile_permissions (perfil, id_permission)
                SELECT :perfil, id_permission FROM tb_permissions WHERE permission_key = :permission_key", [
                ':perfil' => $perfil,
                ':permission_key' => $key
            ]);
        }
    }

    public static function userHasPermission(array $user, string $permissionKey): bool
    {
        if (($user['perfil'] ?? '') === 'ADMIN') return true;
        if (!isset($_SESSION['acl_permissions']) || !is_array($_SESSION['acl_permissions'])) {
            $_SESSION['acl_permissions'] = self::listByProfile($user['perfil'] ?? 'ASSESSOR');
        }
        return in_array($permissionKey, $_SESSION['acl_permissions'], true);
    }

    public static function clearSessionCache(): void
    {
        unset($_SESSION['acl_permissions']);
    }
}
