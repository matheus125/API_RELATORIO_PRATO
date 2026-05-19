CREATE TABLE IF NOT EXISTS tb_bases_consulta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_unidade VARCHAR(120) NOT NULL,
    identificador VARCHAR(120) NULL,
    host VARCHAR(150) NOT NULL,
    porta INT NOT NULL DEFAULT 3306,
    nome_banco VARCHAR(120) NOT NULL,
    usuario VARCHAR(120) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ultima_sincronizacao DATETIME NULL,
    ultimo_status VARCHAR(30) NULL,
    ultima_mensagem VARCHAR(1000) NULL,
    registration_date TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    registration_date_update TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_base_consulta (nome_banco)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tb_cadastros_unificados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    origem_banco VARCHAR(120) NOT NULL,
    origem_unidade VARCHAR(120) NOT NULL,
    origem_tabela VARCHAR(60) NOT NULL,
    origem_id INT NOT NULL,
    tipo_cadastro ENUM('TITULAR','DEPENDENTE') NOT NULL,
    nome VARCHAR(180) NULL,
    cpf VARCHAR(20) NULL,
    cpf_normalizado VARCHAR(20) NULL,
    rg VARCHAR(30) NULL,
    data_nascimento DATE NULL,
    idade INT NULL,
    genero VARCHAR(20) NULL,
    status_cliente VARCHAR(60) NULL,
    parentesco VARCHAR(80) NULL,
    registration_date DATETIME NULL,
    registration_date_update DATETIME NULL,
    data_sync DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_origem_registro (origem_banco, origem_tabela, origem_id),
    KEY idx_nome (nome),
    KEY idx_cpf_normalizado (cpf_normalizado),
    KEY idx_tipo_cadastro (tipo_cadastro),
    KEY idx_origem_banco (origem_banco),
    KEY idx_origem_unidade (origem_unidade),
    KEY idx_data_sync (data_sync)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tb_bases_consulta_sync_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_base INT NOT NULL,
    status_execucao VARCHAR(30) NOT NULL,
    mensagem VARCHAR(1000) NULL,
    data_execucao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sync_log_base FOREIGN KEY (id_base) REFERENCES tb_bases_consulta(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    KEY idx_sync_log_base_data (id_base, data_execucao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- EXEMPLO DE CADASTRO DAS BASES
-- ajuste host, usuario e senha para o seu ambiente.
-- quando as 18 bases estiverem cadastradas, a tela do portal já consegue sincronizar.
--
-- INSERT INTO tb_bases_consulta (nome_unidade, identificador, host, porta, nome_banco, usuario, senha, ativo)
-- VALUES
-- ('PRATO CHEIO CENTRO', 'centro', '69.6.249.161', 3306, 'base_centro', 'usuario', 'senha', 1),
-- ('PRATO CHEIO COMPENSA', 'compensa', '69.6.249.161', 3306, 'base_compensa', 'usuario', 'senha', 1);
