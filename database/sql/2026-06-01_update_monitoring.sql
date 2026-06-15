CREATE TABLE IF NOT EXISTS tb_atualizacoes (
  id_atualizacao INT AUTO_INCREMENT PRIMARY KEY,
  versao VARCHAR(50) NOT NULL,
  arquivo_zip VARCHAR(255) DEFAULT NULL,
  url_download VARCHAR(500) DEFAULT NULL,
  changelog TEXT DEFAULT NULL,
  checksum_sha256 CHAR(64) DEFAULT NULL,
  publicado_em DATETIME DEFAULT NULL,
  responsavel VARCHAR(150) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_tb_atualizacoes_versao (versao),
  KEY idx_tb_atualizacoes_publicado (publicado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tb_atualizacoes_unidades (
  id_unidade_update INT AUTO_INCREMENT PRIMARY KEY,
  unidade_id VARCHAR(120) NOT NULL,
  nome_unidade VARCHAR(180) DEFAULT NULL,
  municipio VARCHAR(120) DEFAULT NULL,
  tipo_unidade VARCHAR(120) DEFAULT NULL,
  sistema VARCHAR(80) NOT NULL DEFAULT 'prato_web',
  versao_instalada VARCHAR(50) DEFAULT NULL,
  versao_disponivel VARCHAR(50) DEFAULT NULL,
  status_atual VARCHAR(50) NOT NULL DEFAULT 'sem_atualizacao',
  status_label VARCHAR(80) DEFAULT NULL,
  etapa VARCHAR(80) DEFAULT NULL,
  mensagem TEXT DEFAULT NULL,
  stack_trace TEXT DEFAULT NULL,
  arquivos_ausentes TEXT DEFAULT NULL,
  ultima_comunicacao DATETIME DEFAULT NULL,
  ultimo_download DATETIME DEFAULT NULL,
  ultima_instalacao DATETIME DEFAULT NULL,
  data_hora_local DATETIME DEFAULT NULL,
  ip_origem VARCHAR(64) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  tempo_instalacao_segundos INT DEFAULT NULL,
  payload_json JSON DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_tb_atualizacoes_unidade_sistema (unidade_id, sistema),
  KEY idx_tb_atualizacoes_unidades_status (status_atual),
  KEY idx_tb_atualizacoes_unidades_versao (versao_instalada),
  KEY idx_tb_atualizacoes_unidades_comunicacao (ultima_comunicacao),
  KEY idx_tb_atualizacoes_unidades_disponivel (versao_disponivel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tb_logs_atualizacoes (
  id_log_update BIGINT AUTO_INCREMENT PRIMARY KEY,
  unidade_id VARCHAR(120) NOT NULL,
  nome_unidade VARCHAR(180) DEFAULT NULL,
  sistema VARCHAR(80) NOT NULL DEFAULT 'prato_web',
  versao_atual VARCHAR(50) DEFAULT NULL,
  versao_disponivel VARCHAR(50) DEFAULT NULL,
  status VARCHAR(50) NOT NULL,
  etapa VARCHAR(80) DEFAULT NULL,
  mensagem TEXT DEFAULT NULL,
  stack_trace TEXT DEFAULT NULL,
  arquivos_ausentes TEXT DEFAULT NULL,
  data_hora_local DATETIME DEFAULT NULL,
  ip_origem VARCHAR(64) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  tempo_instalacao_segundos INT DEFAULT NULL,
  payload_json JSON DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tb_logs_updates_unidade (unidade_id),
  KEY idx_tb_logs_updates_versao (versao_disponivel),
  KEY idx_tb_logs_updates_status (status),
  KEY idx_tb_logs_updates_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tb_status_atualizacao (
  id_config INT AUTO_INCREMENT PRIMARY KEY,
  chave VARCHAR(100) NOT NULL,
  valor VARCHAR(255) NOT NULL,
  descricao VARCHAR(255) DEFAULT NULL,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_tb_status_atualizacao_chave (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tb_status_atualizacao (chave, valor, descricao)
VALUES ('offline_threshold_minutes', '60', 'Minutos sem comunicacao para considerar unidade offline')
ON DUPLICATE KEY UPDATE valor = valor;
