<?php

namespace Hcode\Model;
use \Hcode\DB\Sql;

class Permissions
{
    public static function definitions(): array
    {
        return [
            'DASHBOARD_VIEW' => ['description' => 'Visualizar dashboard', 'module_name' => 'DASHBOARD'],
            'ANALISE_INTELIGENTE_VIEW' => ['description' => 'Visualizar análises e indicadores', 'module_name' => 'ANALISE_INTELIGENTE'],
            'PDF_HISTORICO_VIEW' => ['description' => 'Consultar e baixar relatórios PDF', 'module_name' => 'PDF_HISTORICO'],
            'PLANILHA_VIEW' => ['description' => 'Visualizar painel e histórico de planilhas', 'module_name' => 'PLANILHA'],
            'UNIDADES_ATUALIZADAS_VIEW' => ['description' => 'Consultar atualizações das unidades', 'module_name' => 'UNIDADES_ATUALIZADAS'],
            'CONSULTA_UNIFICADA_VIEW' => ['description' => 'Consultar cadastros e baixar CSV', 'module_name' => 'CONSULTA_UNIFICADA'],
            'CONSULTA_UNIFICADA_SYNC' => ['description' => 'Sincronizar cadastros das unidades', 'module_name' => 'CONSULTA_UNIFICADA'],

            'GADSAN_VIEW' => ['description' => 'Visualizar colaboradores GADSAN', 'module_name' => 'GADSAN'],
            'GADSAN_EDIT' => ['description' => 'Cadastrar e editar colaboradores GADSAN', 'module_name' => 'GADSAN'],
            'GADSAN_AUX' => ['description' => 'Gerenciar cadastros auxiliares GADSAN', 'module_name' => 'GADSAN'],
            'GADSAN_IMPORT' => ['description' => 'Importar e revisar dados GADSAN', 'module_name' => 'GADSAN'],

            'FUNCIONARIOS_VIEW' => ['description' => 'Visualizar funcionários', 'module_name' => 'FUNCIONARIOS'],
            'FUNCIONARIOS_CREATE' => ['description' => 'Cadastrar funcionários', 'module_name' => 'FUNCIONARIOS'],
            'FUNCIONARIOS_UPDATE' => ['description' => 'Editar funcionários', 'module_name' => 'FUNCIONARIOS'],
            'FUNCIONARIOS_DELETE' => ['description' => 'Excluir/inativar funcionários', 'module_name' => 'FUNCIONARIOS'],
            'FUNCIONARIOS_PASSWORD' => ['description' => 'Alterar senha de funcionários', 'module_name' => 'FUNCIONARIOS'],

            'ACL_PROFILES_MANAGE' => ['description' => 'Gerenciar permissões por perfil', 'module_name' => 'ACL_PROFILES'],
            'ACL_DENIED_VIEW' => ['description' => 'Visualizar acessos negados', 'module_name' => 'ACL_DENIED'],
            'USUARIOS_SECURITY_MANAGE' => ['description' => 'Gerenciar status e bloqueio de usuários', 'module_name' => 'USUARIOS_SECURITY'],
            'AUDITORIA_VIEW' => ['description' => 'Visualizar logs de auditoria', 'module_name' => 'AUDITORIA'],
            'UPDATE_MONITORING_VIEW' => ['description' => 'Visualizar monitoramento de atualizações', 'module_name' => 'UPDATE_MONITORING'],
            'UPDATE_MONITORING_MANAGE' => ['description' => 'Alterar configurações do monitoramento', 'module_name' => 'UPDATE_MONITORING'],
            'UPDATE_PUBLISH_MANAGE' => ['description' => 'Publicar atualizações do sistema', 'module_name' => 'UPDATE_PUBLISH'],
            'PLANILHA_EXPORT' => ['description' => 'Gerar e baixar planilhas', 'module_name' => 'PLANILHA'],
            'NOTIFICACOES_VIEW' => ['description' => 'Visualizar notificações', 'module_name' => 'NOTIFICACOES'],
            'NOTIFICACOES_UPDATE' => ['description' => 'Marcar notificações como lidas', 'module_name' => 'NOTIFICACOES'],
        ];
    }

    public static function routeMap(): array
    {
        return [
            '/admin' => 'DASHBOARD_VIEW',
            '/admin/index' => 'DASHBOARD_VIEW',
            '/admin/analise-inteligente' => 'ANALISE_INTELIGENTE_VIEW',
            '/admin/api/dashboard/resumo' => 'ANALISE_INTELIGENTE_VIEW',
            '/admin/api/dashboard/ranking-unidades' => 'ANALISE_INTELIGENTE_VIEW',
            '/admin/api/dashboard/analise-inteligente' => 'ANALISE_INTELIGENTE_VIEW',
            '/admin/api/dashboard/unidades' => 'ANALISE_INTELIGENTE_VIEW',
            '/admin/api/dashboard/comparativo-semanal' => 'ANALISE_INTELIGENTE_VIEW',
            '/admin/api/dashboard/comparativo-mensal' => 'ANALISE_INTELIGENTE_VIEW',
            '/admin/api/dashboard/comparativo-anual' => 'ANALISE_INTELIGENTE_VIEW',
            '/admin/api/dashboard/alertas' => 'ANALISE_INTELIGENTE_VIEW',
            '/admin/relatorio/pdf/historico' => 'PDF_HISTORICO_VIEW',
            '/admin/api/relatorio/pdf/historico' => 'PDF_HISTORICO_VIEW',
            '/admin/relatorio/pdf/download-lote' => 'PDF_HISTORICO_VIEW',
            '/admin/relatorio/planilha' => 'PLANILHA_VIEW',
            '/admin/api/relatorio/planilha/painel' => 'PLANILHA_VIEW',
            '/admin/api/relatorio/planilha/arquivo' => 'PLANILHA_EXPORT',
            '/admin/api/relatorio/planilha/exportar' => 'PLANILHA_EXPORT',
            '/admin/unidades-atualizadas' => 'UNIDADES_ATUALIZADAS_VIEW',
            '/admin/consulta-unificada' => 'CONSULTA_UNIFICADA_VIEW',
            '/admin/api/consulta-unificada/resumo' => 'CONSULTA_UNIFICADA_VIEW',
            '/admin/api/consulta-unificada/lista' => 'CONSULTA_UNIFICADA_VIEW',
            '/admin/api/consulta-unificada/download' => 'CONSULTA_UNIFICADA_VIEW',
            '/admin/api/consulta-unificada/sincronizar' => 'CONSULTA_UNIFICADA_SYNC',
            '/admin/funcionarios' => 'FUNCIONARIOS_VIEW',
            '/admin/funcionarios/create' => 'FUNCIONARIOS_CREATE',
            '/admin/funcionarios/verificar-cpf' => 'FUNCIONARIOS_UPDATE',
            '/admin/funcionarios/:id_usuario/password' => 'FUNCIONARIOS_PASSWORD',
            '/admin/funcionarios/:id_usuario/delete' => 'FUNCIONARIOS_DELETE',
            '/admin/funcionarios/:id_usuario' => 'FUNCIONARIOS_UPDATE',
            '/admin/seguranca/permissoes' => 'ACL_PROFILES_MANAGE',
            '/admin/seguranca/acessos-negados' => 'ACL_DENIED_VIEW',
            '/admin/usuarios/seguranca' => 'USUARIOS_SECURITY_MANAGE',
            '/admin/usuarios/:id_usuario/status' => 'USUARIOS_SECURITY_MANAGE',
            '/admin/funcionarios/:id_pessoa/status-funcionario' => 'USUARIOS_SECURITY_MANAGE',
            '/admin/seguranca/auditoria' => 'AUDITORIA_VIEW',
            '/admin/monitoramento-atualizacoes' => 'UPDATE_MONITORING_VIEW',
            '/admin/api/updates/monitoring' => 'UPDATE_MONITORING_VIEW',
            '/admin/api/updates/unit/:unidade_id' => 'UPDATE_MONITORING_VIEW',
            '/admin/api/updates/version/:versao' => 'UPDATE_MONITORING_VIEW',
            '/admin/api/updates/config' => 'UPDATE_MONITORING_MANAGE',
            '/admin/publicar-atualizacao' => 'UPDATE_PUBLISH_MANAGE',
            '/admin/api/update-publish/preview' => 'UPDATE_PUBLISH_MANAGE',
            '/admin/api/update-publish/suggest-version' => 'UPDATE_PUBLISH_MANAGE',
            '/admin/api/update-publish/test-connection' => 'UPDATE_PUBLISH_MANAGE',
            '/admin/api/update-publish/publish' => 'UPDATE_PUBLISH_MANAGE',
            '/admin/api/source-sync/status' => 'UPDATE_PUBLISH_MANAGE',
            '/admin/api/source-sync/snapshot' => 'UPDATE_PUBLISH_MANAGE',
            '/admin/api/source-sync/history' => 'UPDATE_PUBLISH_MANAGE',
            '/admin/api/source-sync/diff' => 'UPDATE_PUBLISH_MANAGE',
            '/admin/api/source-sync/rollback-preview' => 'UPDATE_PUBLISH_MANAGE',
            '/admin/api/source-sync/rollback' => 'UPDATE_PUBLISH_MANAGE',
            '/admin/api/source-sync/upload' => 'UPDATE_PUBLISH_MANAGE',
            '/admin/api/notificacoes' => 'NOTIFICACOES_VIEW',
            '/admin/api/notificacoes/:id/lida' => 'NOTIFICACOES_UPDATE',
            '/admin/api/notificacoes/marcar-todas-lidas' => 'NOTIFICACOES_UPDATE',
        ];
    }

    public static function profiles(): array
    {
        return ['ADMIN' => 'Administrador', 'SUPERVISOR' => 'Supervisor', 'ASSESSOR' => 'Assessor', 'CONSULTA' => 'Consulta'];
    }

    public static function defaultProfilePermissions(): array
    {
        return [
            'ADMIN' => array_keys(self::definitions()),
            'SUPERVISOR' => [
                'DASHBOARD_VIEW', 'ANALISE_INTELIGENTE_VIEW', 'PDF_HISTORICO_VIEW',
                'PLANILHA_VIEW', 'PLANILHA_EXPORT', 'UNIDADES_ATUALIZADAS_VIEW',
                'CONSULTA_UNIFICADA_VIEW', 'CONSULTA_UNIFICADA_SYNC',
                'FUNCIONARIOS_VIEW', 'FUNCIONARIOS_CREATE', 'FUNCIONARIOS_UPDATE',
                'FUNCIONARIOS_PASSWORD', 'ACL_DENIED_VIEW', 'AUDITORIA_VIEW',
                'NOTIFICACOES_VIEW', 'NOTIFICACOES_UPDATE',
            ],
            'ASSESSOR' => [
                'DASHBOARD_VIEW', 'ANALISE_INTELIGENTE_VIEW', 'PDF_HISTORICO_VIEW',
                'PLANILHA_VIEW', 'UNIDADES_ATUALIZADAS_VIEW',
                'CONSULTA_UNIFICADA_VIEW', 'CONSULTA_UNIFICADA_SYNC', 'FUNCIONARIOS_VIEW',
            ],
            'CONSULTA' => ['ANALISE_INTELIGENTE_VIEW', 'CONSULTA_UNIFICADA_VIEW'],
        ];
    }

    public static function modules(): array
    {
        return [
            'DASHBOARD' => ['label' => 'Dashboard', 'icon' => 'bi-speedometer2'],
            'FUNCIONARIOS' => ['label' => 'Funcionários', 'icon' => 'bi-people'],
            'ANALISE_INTELIGENTE' => ['label' => 'Análise Inteligente', 'icon' => 'bi-graph-up-arrow'],
            'PDF_HISTORICO' => ['label' => 'Histórico PDF', 'icon' => 'bi-file-earmark-pdf'],
            'PLANILHA' => ['label' => 'Planilha mensal', 'icon' => 'bi-file-earmark-excel'],
            'UNIDADES_ATUALIZADAS' => ['label' => 'Unidades atualizadas', 'icon' => 'bi-building-check'],
            'CONSULTA_UNIFICADA' => ['label' => 'Consulta unificada', 'icon' => 'bi-diagram-3'],
            'UPDATE_MONITORING' => ['label' => 'Monitoramento de atualizações', 'icon' => 'bi-activity'],
            'UPDATE_PUBLISH' => ['label' => 'Publicar atualização', 'icon' => 'bi-cloud-upload'],
            'ACL_PROFILES' => ['label' => 'Permissões', 'icon' => 'bi-shield-lock'],
            'USUARIOS_SECURITY' => ['label' => 'Usuários e status', 'icon' => 'bi-person-lock'],
            'ACL_DENIED' => ['label' => 'Acessos negados', 'icon' => 'bi-ban'],
            'AUDITORIA' => ['label' => 'Auditoria', 'icon' => 'bi-clock-history'],
            'NOTIFICACOES' => ['label' => 'Notificações', 'icon' => 'bi-bell'],
            'GADSAN' => ['label' => 'Colaboradores GADSAN', 'icon' => 'bi-person-vcard'],
        ];
    }

    public static function groupedForManagement(): array
    {
        $groups = [];
        foreach (self::modules() as $key => $module) {
            $groups[$key] = array_merge(['key' => $key, 'permissions' => []], $module);
        }
        foreach (self::listAll() as $permission) {
            $key = $permission['module_name'];
            if (isset($groups[$key])) {
                $groups[$key]['permissions'][] = $permission;
            }
        }
        return array_values(array_filter($groups, static function ($group) {
            return count($group['permissions']) > 0;
        }));
    }

    public static function firstAllowedPath(array $permissions): string
    {
        $pages = [
            '/admin' => 'DASHBOARD_VIEW',
            '/admin/analise-inteligente' => 'ANALISE_INTELIGENTE_VIEW',
            '/admin/relatorio/pdf/historico' => 'PDF_HISTORICO_VIEW',
            '/admin/relatorio/planilha' => 'PLANILHA_VIEW',
            '/admin/unidades-atualizadas' => 'UNIDADES_ATUALIZADAS_VIEW',
            '/admin/consulta-unificada' => 'CONSULTA_UNIFICADA_VIEW',
            '/admin/funcionarios' => 'FUNCIONARIOS_VIEW',
            '/admin/monitoramento-atualizacoes' => 'UPDATE_MONITORING_VIEW',
            '/admin/publicar-atualizacao' => 'UPDATE_PUBLISH_MANAGE',
            '/admin/seguranca/permissoes' => 'ACL_PROFILES_MANAGE',
            '/admin/usuarios/seguranca' => 'USUARIOS_SECURITY_MANAGE',
            '/admin/seguranca/acessos-negados' => 'ACL_DENIED_VIEW',
            '/admin/seguranca/auditoria' => 'AUDITORIA_VIEW',
            '/admin/gadsan' => 'GADSAN_VIEW',
        ];
        foreach ($pages as $path => $key) {
            if (in_array($key, $permissions, true)) return $path;
        }
        return '/acesso-negado';
    }

    // Existing installations inherit their saved choices, never the factory defaults.
    private static function inheritedPermissions(): array
    {
        return [
            'ANALISE_INTELIGENTE_VIEW' => ['CONSULTA_RESTRITA_VIEW'],
            'PDF_HISTORICO_VIEW' => ['DASHBOARD_VIEW'],
            'PLANILHA_VIEW' => ['DASHBOARD_VIEW'],
            'UNIDADES_ATUALIZADAS_VIEW' => ['DASHBOARD_VIEW'],
            'CONSULTA_UNIFICADA_VIEW' => ['CONSULTA_RESTRITA_VIEW'],
            'CONSULTA_UNIFICADA_SYNC' => ['CONSULTA_RESTRITA_VIEW', 'DASHBOARD_VIEW'],
            'UPDATE_MONITORING_MANAGE' => ['UPDATE_MONITORING_VIEW', 'DASHBOARD_VIEW'],
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
        if (!$sql->select("SHOW TABLES LIKE 'tb_permissions'") || !$sql->select("SHOW TABLES LIKE 'tb_profile_permissions'")) {
            return;
        }

        $sql->query('START TRANSACTION');
        try {
            $existing = $sql->select('SELECT permission_key FROM tb_permissions');
            $freshInstall = count($existing) === 0;
            $inherited = self::inheritedPermissions();
            $defaults = self::defaultProfilePermissions();
            foreach (self::definitions() as $permissionKey => $definition) {
                $normalized = self::normalizeDefinition($permissionKey, $definition);
                $params = [
                    ':permission_key' => $normalized['permission_key'],
                    ':description' => $normalized['description'],
                    ':module_name' => $normalized['module_name'],
                ];
                $insert = $sql->query(
                    "INSERT IGNORE INTO tb_permissions (permission_key, description, module_name, created_at)
                     VALUES (:permission_key, :description, :module_name, NOW())", $params
                );
                if ($insert->rowCount() !== 1) {
                    $sql->query("UPDATE tb_permissions SET description = :description, module_name = :module_name
                        WHERE permission_key = :permission_key", $params);
                    continue;
                }

                foreach ($defaults as $profile => $keys) {
                    $grant = in_array($permissionKey, $keys, true);
                    if (!$freshInstall && isset($inherited[$permissionKey])) {
                        $grant = true;
                        foreach ($inherited[$permissionKey] as $parentKey) {
                            $parent = $sql->select("SELECT pp.id_permission FROM tb_profile_permissions pp
                                INNER JOIN tb_permissions p ON p.id_permission = pp.id_permission
                                WHERE pp.perfil = :perfil AND p.permission_key = :permission_key",
                                [':perfil' => $profile, ':permission_key' => $parentKey]);
                            if (!$parent) { $grant = false; break; }
                        }
                    }
                    if ($grant) {
                        $sql->query("INSERT IGNORE INTO tb_profile_permissions (perfil, id_permission)
                            SELECT :perfil, id_permission FROM tb_permissions WHERE permission_key = :permission_key",
                            [':perfil' => $profile, ':permission_key' => $permissionKey]);
                    }
                }
            }
            $sql->query('COMMIT');
        } catch (\Throwable $error) {
            $sql->query('ROLLBACK');
            throw $error;
        }
    }

    public static function listAll(): array
    {
        self::syncDefinitions();
        $sql = new Sql();
        $definitions = self::definitions();
        return array_values(array_filter(
            $sql->select("SELECT * FROM tb_permissions ORDER BY module_name, description"),
            static function ($row) use ($definitions) { return isset($definitions[$row['permission_key']]); }
        ));
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

    public static function saveMatrix(array $matrix): void
    {
        $definitions = self::definitions();
        $profiles = self::profiles();
        if (array_diff(array_keys($matrix), array_keys($profiles))) {
            throw new \InvalidArgumentException('Perfil inválido.');
        }
        foreach ($profiles as $profile => $label) {
            $keys = $matrix[$profile] ?? [];
            if (!is_array($keys)) throw new \InvalidArgumentException('Seleção de permissões inválida.');
            foreach ($keys as $key) {
                if (!is_string($key) || !isset($definitions[$key])) {
                    throw new \InvalidArgumentException('Permissão inválida. Atualize a página e tente novamente.');
                }
            }
            $matrix[$profile] = array_values(array_unique($keys));
        }
        self::syncDefinitions();
        $sql = new Sql();
        $sql->query('START TRANSACTION');
        try {
            foreach ($matrix as $profile => $keys) {
                $sql->query('DELETE FROM tb_profile_permissions WHERE perfil = :perfil', [':perfil' => $profile]);
                foreach ($keys as $key) {
                    $sql->query("INSERT INTO tb_profile_permissions (perfil, id_permission)
                        SELECT :perfil, id_permission FROM tb_permissions WHERE permission_key = :permission_key",
                        [':perfil' => $profile, ':permission_key' => $key]);
                }
            }
            $sql->query('COMMIT');
        } catch (\Throwable $error) {
            $sql->query('ROLLBACK');
            throw $error;
        }
        self::clearSessionCache();
    }

    public static function userHasPermission(array $user, string $permissionKey): bool
    {
        if (!isset(self::definitions()[$permissionKey])) return false;
        return in_array($permissionKey, self::listByProfile($user['perfil'] ?? 'ASSESSOR'), true);
    }

    public static function clearSessionCache(): void
    {
        unset($_SESSION['acl_permissions'], $_SESSION[Funcionarios::SESSION]['permissions']);
    }
}
