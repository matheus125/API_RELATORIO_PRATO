CREATE TABLE IF NOT EXISTS tb_notificacoes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(180) NOT NULL,
  mensagem TEXT NOT NULL,
  tipo VARCHAR(60) NOT NULL DEFAULT 'relatorio_enviado',
  unidade_id VARCHAR(120) NULL,
  relatorio_id INT NULL,
  link_destino VARCHAR(500) NULL,
  status VARCHAR(60) NOT NULL DEFAULT 'novo',
  lida TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  read_at DATETIME NULL DEFAULT NULL,
  KEY idx_notificacoes_lida_created (lida, created_at),
  KEY idx_notificacoes_tipo (tipo),
  KEY idx_notificacoes_unidade (unidade_id),
  KEY idx_notificacoes_relatorio (relatorio_id),
  KEY idx_notificacoes_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tb_permissions (permission_key, description, module_name) VALUES
('NOTIFICACOES_VIEW', 'Visualizar notificações', 'NOTIFICACOES'),
('NOTIFICACOES_UPDATE', 'Marcar notificações como lidas', 'NOTIFICACOES')
ON DUPLICATE KEY UPDATE description = VALUES(description), module_name = VALUES(module_name);

INSERT INTO tb_profile_permissions (perfil, id_permission)
SELECT 'ADMIN', id_permission FROM tb_permissions
WHERE permission_key IN ('NOTIFICACOES_VIEW', 'NOTIFICACOES_UPDATE')
ON DUPLICATE KEY UPDATE perfil = VALUES(perfil);

INSERT INTO tb_profile_permissions (perfil, id_permission)
SELECT 'SUPERVISOR', id_permission FROM tb_permissions
WHERE permission_key IN ('NOTIFICACOES_VIEW', 'NOTIFICACOES_UPDATE')
ON DUPLICATE KEY UPDATE perfil = VALUES(perfil);
