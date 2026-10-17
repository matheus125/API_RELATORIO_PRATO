-- ACL do Portal de Relatórios.
-- Usa os mesmos nomes de colunas consumidos por Hcode\Model\Permissions.

CREATE TABLE IF NOT EXISTS tb_permissions (
  id_permission INT NOT NULL AUTO_INCREMENT,
  permission_key VARCHAR(100) NOT NULL,
  description VARCHAR(255) NOT NULL,
  module_name VARCHAR(80) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_permission),
  UNIQUE KEY uq_permission_key (permission_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tb_profile_permissions (
  id_profile_permission INT NOT NULL AUTO_INCREMENT,
  perfil ENUM('ADMIN','SUPERVISOR','ASSESSOR','CONSULTA') NOT NULL,
  id_permission INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_profile_permission),
  UNIQUE KEY uq_profile_permission (perfil, id_permission),
  KEY idx_profile_permission_permission (id_permission),
  CONSTRAINT fk_profile_permission_permission FOREIGN KEY (id_permission)
    REFERENCES tb_permissions (id_permission) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tb_permissions (permission_key, description, module_name) VALUES
('DASHBOARD_VIEW','Visualizar dashboard','DASHBOARD'),
('ANALISE_INTELIGENTE_VIEW','Visualizar análises e indicadores','ANALISE_INTELIGENTE'),
('PDF_HISTORICO_VIEW','Consultar e baixar relatórios PDF','PDF_HISTORICO'),
('PLANILHA_VIEW','Visualizar painel e histórico de planilhas','PLANILHA'),
('PLANILHA_EXPORT','Gerar e baixar planilhas','PLANILHA'),
('UNIDADES_ATUALIZADAS_VIEW','Consultar atualizações das unidades','UNIDADES_ATUALIZADAS'),
('CONSULTA_UNIFICADA_VIEW','Consultar cadastros e baixar CSV','CONSULTA_UNIFICADA'),
('CONSULTA_UNIFICADA_SYNC','Sincronizar cadastros das unidades','CONSULTA_UNIFICADA'),
('FUNCIONARIOS_VIEW','Visualizar funcionários','FUNCIONARIOS'),
('FUNCIONARIOS_CREATE','Cadastrar funcionários','FUNCIONARIOS'),
('FUNCIONARIOS_UPDATE','Editar funcionários','FUNCIONARIOS'),
('FUNCIONARIOS_DELETE','Excluir/inativar funcionários','FUNCIONARIOS'),
('FUNCIONARIOS_PASSWORD','Alterar senha de funcionários','FUNCIONARIOS'),
('ACL_PROFILES_MANAGE','Gerenciar permissões por perfil','ACL_PROFILES'),
('ACL_DENIED_VIEW','Visualizar acessos negados','ACL_DENIED'),
('USUARIOS_SECURITY_MANAGE','Gerenciar status e bloqueio de usuários','USUARIOS_SECURITY'),
('AUDITORIA_VIEW','Visualizar logs de auditoria','AUDITORIA'),
('UPDATE_MONITORING_VIEW','Visualizar monitoramento de atualizações','UPDATE_MONITORING'),
('UPDATE_MONITORING_MANAGE','Alterar configurações do monitoramento','UPDATE_MONITORING'),
('UPDATE_PUBLISH_MANAGE','Publicar atualizações do sistema','UPDATE_PUBLISH'),
('NOTIFICACOES_VIEW','Visualizar notificações','NOTIFICACOES'),
('NOTIFICACOES_UPDATE','Marcar notificações como lidas','NOTIFICACOES')
ON DUPLICATE KEY UPDATE description = VALUES(description), module_name = VALUES(module_name);

-- O catálogo nasce com os padrões mínimos; alterações posteriores devem ser
-- feitas na tela de permissões e não reexecutando concessões automaticamente.
INSERT IGNORE INTO tb_profile_permissions (perfil, id_permission)
SELECT 'ADMIN', id_permission FROM tb_permissions;

INSERT IGNORE INTO tb_profile_permissions (perfil, id_permission)
SELECT 'SUPERVISOR', id_permission FROM tb_permissions
WHERE permission_key IN (
  'DASHBOARD_VIEW','ANALISE_INTELIGENTE_VIEW','PDF_HISTORICO_VIEW',
  'PLANILHA_VIEW','PLANILHA_EXPORT','UNIDADES_ATUALIZADAS_VIEW',
  'CONSULTA_UNIFICADA_VIEW','CONSULTA_UNIFICADA_SYNC','FUNCIONARIOS_VIEW',
  'FUNCIONARIOS_CREATE','FUNCIONARIOS_UPDATE','FUNCIONARIOS_PASSWORD',
  'ACL_DENIED_VIEW','AUDITORIA_VIEW','NOTIFICACOES_VIEW','NOTIFICACOES_UPDATE'
);

INSERT IGNORE INTO tb_profile_permissions (perfil, id_permission)
SELECT 'ASSESSOR', id_permission FROM tb_permissions
WHERE permission_key IN (
  'DASHBOARD_VIEW','ANALISE_INTELIGENTE_VIEW','PDF_HISTORICO_VIEW',
  'PLANILHA_VIEW','UNIDADES_ATUALIZADAS_VIEW','CONSULTA_UNIFICADA_VIEW',
  'CONSULTA_UNIFICADA_SYNC','FUNCIONARIOS_VIEW'
);

INSERT IGNORE INTO tb_profile_permissions (perfil, id_permission)
SELECT 'CONSULTA', id_permission FROM tb_permissions
WHERE permission_key IN ('ANALISE_INTELIGENTE_VIEW','CONSULTA_UNIFICADA_VIEW');
