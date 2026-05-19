-- ACL profissional com perfis existentes em tb_usuario.perfil
CREATE TABLE IF NOT EXISTS tb_permissions (
  id_permission INT NOT NULL AUTO_INCREMENT,
  permission_key VARCHAR(80) NOT NULL,
  description VARCHAR(150) NOT NULL,
  module_name VARCHAR(80) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_permission),
  UNIQUE KEY uq_permission_key (permission_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS tb_profile_permissions (
  id_profile_permission INT NOT NULL AUTO_INCREMENT,
  profile_key ENUM('ADMIN','SUPERVISOR','ASSESSOR') NOT NULL,
  id_permission INT NOT NULL,
  allowed TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_profile_permission),
  UNIQUE KEY uq_profile_permission (profile_key, id_permission),
  CONSTRAINT fk_profile_permission_permission FOREIGN KEY (id_permission) REFERENCES tb_permissions (id_permission) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO tb_permissions (permission_key, description, module_name) VALUES
('DASHBOARD_VIEW','Visualizar dashboard','DASHBOARD'),
('FUNCIONARIOS_VIEW','Visualizar funcionários','FUNCIONARIOS'),
('FUNCIONARIOS_CREATE','Cadastrar funcionários','FUNCIONARIOS'),
('FUNCIONARIOS_UPDATE','Editar funcionários','FUNCIONARIOS'),
('FUNCIONARIOS_DELETE','Excluir funcionários','FUNCIONARIOS'),
('FUNCIONARIOS_PASSWORD','Alterar senha de funcionários','FUNCIONARIOS'),
('CLIENTES_VIEW','Visualizar clientes','CLIENTES'),
('CLIENTES_CREATE','Cadastrar clientes','CLIENTES'),
('CLIENTES_UPDATE','Editar clientes','CLIENTES'),
('CLIENTES_DELETE','Excluir clientes','CLIENTES'),
('DEPENDENTES_VIEW','Visualizar dependentes','DEPENDENTES'),
('DEPENDENTES_CREATE','Cadastrar dependentes','DEPENDENTES'),
('DEPENDENTES_UPDATE','Editar dependentes','DEPENDENTES'),
('VENDAS_VIEW','Acessar vendas','VENDAS'),
('RELATORIOS_VIEW','Visualizar relatórios','RELATORIOS'),
('BACKUP_RUN','Executar backup manual','BACKUP'),
('NOTIFICACOES_VIEW','Visualizar notificações','NOTIFICACOES'),
('NOTIFICACOES_CLEAR','Limpar notificações','NOTIFICACOES'),
('SISTEMA_DEBUG','Acessar rotas de debug','SISTEMA')
ON DUPLICATE KEY UPDATE description = VALUES(description), module_name = VALUES(module_name);

INSERT INTO tb_profile_permissions (profile_key, id_permission, allowed)
SELECT 'ADMIN', id_permission, 1 FROM tb_permissions
ON DUPLICATE KEY UPDATE allowed = VALUES(allowed);

INSERT INTO tb_profile_permissions (profile_key, id_permission, allowed)
SELECT 'SUPERVISOR', id_permission, 1 FROM tb_permissions WHERE permission_key IN (
'DASHBOARD_VIEW','FUNCIONARIOS_VIEW','FUNCIONARIOS_CREATE','FUNCIONARIOS_UPDATE','FUNCIONARIOS_PASSWORD',
'CLIENTES_VIEW','CLIENTES_CREATE','CLIENTES_UPDATE',
'DEPENDENTES_VIEW','DEPENDENTES_CREATE','DEPENDENTES_UPDATE',
'VENDAS_VIEW','RELATORIOS_VIEW','BACKUP_RUN','NOTIFICACOES_VIEW','NOTIFICACOES_CLEAR'
)
ON DUPLICATE KEY UPDATE allowed = VALUES(allowed);

INSERT INTO tb_profile_permissions (profile_key, id_permission, allowed)
SELECT 'ASSESSOR', id_permission, 1 FROM tb_permissions WHERE permission_key IN (
'DASHBOARD_VIEW','CLIENTES_VIEW','DEPENDENTES_VIEW','VENDAS_VIEW','RELATORIOS_VIEW','NOTIFICACOES_VIEW'
)
ON DUPLICATE KEY UPDATE allowed = VALUES(allowed);


-- Permissão de exportação da planilha mensal
INSERT IGNORE INTO tb_permissions (permission_key, description, module_name) VALUES ('PLANILHA_EXPORT','Gerar e baixar planilhas','RELATORIOS');

INSERT IGNORE INTO tb_profile_permissions (perfil, id_permission)
SELECT 'ADMIN', id_permission FROM tb_permissions WHERE permission_key = 'PLANILHA_EXPORT';

INSERT IGNORE INTO tb_profile_permissions (perfil, id_permission)
SELECT 'SUPERVISOR', id_permission FROM tb_permissions WHERE permission_key = 'PLANILHA_EXPORT';
