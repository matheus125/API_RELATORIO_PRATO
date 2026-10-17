ALTER TABLE tb_usuario
  MODIFY perfil ENUM('ADMIN','SUPERVISOR','ASSESSOR','CONSULTA') NOT NULL DEFAULT 'ASSESSOR';

ALTER TABLE tb_profile_permissions
  MODIFY perfil ENUM('ADMIN','SUPERVISOR','ASSESSOR','CONSULTA') NOT NULL;

INSERT INTO tb_permissions (permission_key, description, module_name, created_at)
VALUES ('CONSULTA_RESTRITA_VIEW', 'Visualizar Análise Inteligente e Consulta Unificada', 'RELATORIOS', NOW())
ON DUPLICATE KEY UPDATE
  description = VALUES(description),
  module_name = VALUES(module_name);

INSERT IGNORE INTO tb_profile_permissions (perfil, id_permission)
SELECT perfil.perfil, p.id_permission
FROM (
  SELECT 'ADMIN' AS perfil
  UNION ALL SELECT 'SUPERVISOR'
  UNION ALL SELECT 'ASSESSOR'
  UNION ALL SELECT 'CONSULTA'
) perfil
INNER JOIN tb_permissions p ON p.permission_key = 'CONSULTA_RESTRITA_VIEW';
