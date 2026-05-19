-- Estrutura complementar para a area "Analise Inteligente" do Portal de Relatorios.
-- As consultas usam principalmente tb_relatorios e tb_relatorios_pdf.

CREATE TABLE IF NOT EXISTS tb_relatorios (
  id INT NOT NULL AUTO_INCREMENT,
  data DATE NOT NULL,
  Total_pessoas_atendidas INT NOT NULL DEFAULT 0,
  qtd_refeicoes_servidas INT NOT NULL DEFAULT 0,
  refeicoes_ofertadas INT NOT NULL DEFAULT 0,
  sobra_refeicoes INT NOT NULL DEFAULT 0,
  sobra_senhas INT NOT NULL DEFAULT 0,
  fechado TINYINT(1) NOT NULL DEFAULT 0,
  nome_banco VARCHAR(150) DEFAULT NULL,
  origem_banco VARCHAR(150) DEFAULT NULL,
  hash_relatorio CHAR(64) DEFAULT NULL,
  cardapio TEXT DEFAULT NULL,
  ocorrencias TEXT DEFAULT NULL,
  data_recebimento TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_tb_relatorios_hash (hash_relatorio),
  KEY idx_tb_relatorios_data (data),
  KEY idx_tb_relatorios_nome_banco (nome_banco),
  KEY idx_tb_relatorios_origem_banco (origem_banco)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tb_relatorios_pdf (
  id INT NOT NULL AUTO_INCREMENT,
  data_relatorio DATE DEFAULT NULL,
  nome_arquivo VARCHAR(255) DEFAULT NULL,
  status_upload VARCHAR(50) NOT NULL DEFAULT 'RECEBIDO',
  mensagem_erro TEXT DEFAULT NULL,
  responsavel VARCHAR(150) DEFAULT NULL,
  cpf_responsavel VARCHAR(20) DEFAULT NULL,
  data_geracao DATETIME DEFAULT NULL,
  data_upload TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  url_publica VARCHAR(500) DEFAULT NULL,
  caminho_remoto VARCHAR(500) DEFAULT NULL,
  hash_arquivo CHAR(64) DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_tb_relatorios_pdf_hash (hash_arquivo),
  KEY idx_tb_relatorios_pdf_relatorio (data_relatorio),
  KEY idx_tb_relatorios_pdf_upload (data_upload),
  KEY idx_tb_relatorios_pdf_status (status_upload)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tb_backups (
  id INT NOT NULL AUTO_INCREMENT,
  nome_banco VARCHAR(150) DEFAULT NULL,
  nome_arquivo VARCHAR(255) DEFAULT NULL,
  status_upload VARCHAR(50) NOT NULL DEFAULT 'RECEBIDO',
  data_backup DATETIME DEFAULT NULL,
  data_upload TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  caminho_remoto VARCHAR(500) DEFAULT NULL,
  hash_arquivo CHAR(64) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_tb_backups_hash (hash_arquivo),
  KEY idx_tb_backups_nome_banco (nome_banco),
  KEY idx_tb_backups_upload (data_upload)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
