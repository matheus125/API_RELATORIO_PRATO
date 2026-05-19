-- Permissão para controlar quem pode exportar a planilha mensal
INSERT IGNORE INTO tb_permissions (permission_key, description, module_name)
VALUES ('PLANILHA_EXPORT', 'Gerar e baixar planilhas', 'RELATORIOS');

INSERT IGNORE INTO tb_profile_permissions (perfil, id_permission)
SELECT 'ADMIN', id_permission FROM tb_permissions WHERE permission_key = 'PLANILHA_EXPORT';

INSERT IGNORE INTO tb_profile_permissions (perfil, id_permission)
SELECT 'SUPERVISOR', id_permission FROM tb_permissions WHERE permission_key = 'PLANILHA_EXPORT';

-- A tabela tb_relatorios_pdf já é reaproveitada para histórico de arquivos .xlsx.
-- Caso ainda não exista no ambiente, crie antes de usar o histórico persistente.
