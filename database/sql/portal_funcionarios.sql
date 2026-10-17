CREATE DATABASE IF NOT EXISTS `portal_funcionarios` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `portal_funcionarios`;

CREATE TABLE IF NOT EXISTS `tb_funcionario` (
  `id_pessoa` int NOT NULL AUTO_INCREMENT,
  `nome_funcionario` varchar(255) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `nrphone` varchar(20) DEFAULT NULL,
  `dtregister` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ativo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_pessoa`),
  UNIQUE KEY `uq_funcionario_email` (`email`),
  UNIQUE KEY `uq_funcionario_phone` (`nrphone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_usuario` (
  `id_usuario` int NOT NULL AUTO_INCREMENT,
  `id_pessoa` int NOT NULL,
  `cpf` varchar(14) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `inadmin` tinyint(1) NOT NULL DEFAULT '0',
  `dtregister` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `perfil` enum('ADMIN','SUPERVISOR','ASSESSOR','CONSULTA') NOT NULL DEFAULT 'ASSESSOR',
  `ativo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `uq_usuario_cpf` (`cpf`),
  UNIQUE KEY `uq_usuario_pessoa` (`id_pessoa`),
  CONSTRAINT `fk_usuario_pessoa` FOREIGN KEY (`id_pessoa`) REFERENCES `tb_funcionario` (`id_pessoa`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_permissions` (
  `id_permission` int NOT NULL AUTO_INCREMENT,
  `permission_key` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL,
  `module_name` varchar(60) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_permission`),
  UNIQUE KEY `uq_permission_key` (`permission_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_profile_permissions` (
  `id_profile_permission` int NOT NULL AUTO_INCREMENT,
  `perfil` enum('ADMIN','SUPERVISOR','ASSESSOR','CONSULTA') NOT NULL,
  `id_permission` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_profile_permission`),
  UNIQUE KEY `uq_profile_permission` (`perfil`,`id_permission`),
  KEY `fk_profile_permission_permission` (`id_permission`),
  CONSTRAINT `fk_profile_permission_permission` FOREIGN KEY (`id_permission`) REFERENCES `tb_permissions` (`id_permission`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_access_denied` (
  `id_access_denied` int NOT NULL AUTO_INCREMENT,
  `perfil` varchar(40) NOT NULL,
  `rota` varchar(255) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_access_denied`),
  KEY `idx_access_denied_perfil` (`perfil`),
  KEY `idx_access_denied_rota` (`rota`),
  KEY `idx_access_denied_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_userlogs` (
  `id_log` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int DEFAULT NULL,
  `cpf_usuario` varchar(14) NOT NULL,
  `nome_funcionario` varchar(255) NOT NULL,
  `acao` varchar(50) NOT NULL,
  `categoria` varchar(60) DEFAULT NULL,
  `severidade` varchar(20) DEFAULT NULL,
  `modulo` varchar(50) DEFAULT NULL,
  `menu` varchar(120) DEFAULT NULL,
  `sub_menu` varchar(120) DEFAULT NULL,
  `entidade` varchar(100) DEFAULT NULL,
  `referencia_id` int DEFAULT NULL,
  `registro_id` varchar(100) DEFAULT NULL,
  `detalhes` text,
  `descricao` text,
  `dados_anteriores` longtext,
  `dados_novos` longtext,
  `alteracoes` longtext,
  `rota` varchar(255) DEFAULT NULL,
  `metodo_http` varchar(12) DEFAULT NULL,
  `ip` varchar(45) NOT NULL,
  `user_agent` text NOT NULL,
  `sessao_id` varchar(128) DEFAULT NULL,
  `status` varchar(30) DEFAULT 'SUCCESS',
  `erro` longtext,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_log`),
  KEY `idx_userlogs_usuario` (`id_usuario`),
  KEY `idx_userlogs_acao` (`acao`),
  KEY `idx_userlogs_categoria` (`categoria`),
  KEY `idx_userlogs_modulo` (`modulo`),
  KEY `idx_userlogs_entidade_registro` (`entidade`,`registro_id`),
  KEY `idx_userlogs_status` (`status`),
  KEY `idx_userlogs_severidade` (`severidade`),
  KEY `idx_userlogs_ip` (`ip`),
  KEY `idx_userlogs_created_at` (`created_at`),
  CONSTRAINT `fk_userlogs_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `tb_usuario` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tb_permissions` (`permission_key`, `description`, `module_name`) VALUES
('DASHBOARD_VIEW', 'Visualizar dashboard', 'DASHBOARD'),
('FUNCIONARIOS_VIEW', 'Visualizar funcionários', 'FUNCIONARIOS'),
('FUNCIONARIOS_CREATE', 'Cadastrar funcionários', 'FUNCIONARIOS'),
('FUNCIONARIOS_UPDATE', 'Editar funcionários', 'FUNCIONARIOS'),
('FUNCIONARIOS_DELETE', 'Excluir/inativar funcionários', 'FUNCIONARIOS'),
('FUNCIONARIOS_PASSWORD', 'Alterar senha de funcionários', 'FUNCIONARIOS'),
('ACL_PROFILES_MANAGE', 'Gerenciar permissões por perfil', 'SEGURANCA'),
('ACL_DENIED_VIEW', 'Visualizar acessos negados', 'SEGURANCA'),
('USUARIOS_SECURITY_MANAGE', 'Gerenciar status e bloqueio de usuários', 'SEGURANCA'),
('AUDITORIA_VIEW', 'Visualizar logs de auditoria', 'SEGURANCA'),
('SISTEMA_DEBUG', 'Acessar rotas de debug', 'SEGURANCA')
ON DUPLICATE KEY UPDATE
  description = VALUES(description),
  module_name = VALUES(module_name);

INSERT IGNORE INTO `tb_profile_permissions` (`perfil`, `id_permission`)
SELECT 'ADMIN', id_permission FROM tb_permissions;

INSERT IGNORE INTO `tb_profile_permissions` (`perfil`, `id_permission`)
SELECT 'SUPERVISOR', id_permission FROM tb_permissions WHERE permission_key IN (
  'DASHBOARD_VIEW','FUNCIONARIOS_VIEW','FUNCIONARIOS_CREATE','FUNCIONARIOS_UPDATE','FUNCIONARIOS_PASSWORD','ACL_DENIED_VIEW','AUDITORIA_VIEW'
);

INSERT IGNORE INTO `tb_profile_permissions` (`perfil`, `id_permission`)
SELECT 'ASSESSOR', id_permission FROM tb_permissions WHERE permission_key IN (
  'DASHBOARD_VIEW','FUNCIONARIOS_VIEW'
);

-- Nenhum usuário é criado automaticamente. Cadastre o primeiro administrador
-- com uma senha gerada por password_hash() em uma rotina administrativa segura.


-- Permissão de exportação da planilha mensal
INSERT IGNORE INTO tb_permissions (permission_key, description, module_name) VALUES ('PLANILHA_EXPORT','Gerar e baixar planilhas','RELATORIOS');

INSERT IGNORE INTO tb_profile_permissions (perfil, id_permission)
SELECT 'ADMIN', id_permission FROM tb_permissions WHERE permission_key = 'PLANILHA_EXPORT';

INSERT IGNORE INTO tb_profile_permissions (perfil, id_permission)
SELECT 'SUPERVISOR', id_permission FROM tb_permissions WHERE permission_key = 'PLANILHA_EXPORT';
