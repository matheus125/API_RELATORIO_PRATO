-- Separacao segura de dados por regiao: CAPITAL / INTERIOR.
-- Execute este arquivo no banco central do portal antes de publicar o codigo.

CREATE TABLE IF NOT EXISTS tb_regioes (
  id_regiao INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(30) NOT NULL,
  nome VARCHAR(80) NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_tb_regioes_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tb_regioes (codigo, nome, ativo) VALUES
('CAPITAL', 'Capital', 1),
('INTERIOR', 'Interior', 1)
ON DUPLICATE KEY UPDATE nome = VALUES(nome), ativo = VALUES(ativo);

ALTER TABLE tb_bases_consulta
  ADD COLUMN id_regiao INT NULL AFTER identificador,
  ADD COLUMN municipio VARCHAR(120) NULL AFTER id_regiao,
  ADD COLUMN tipo_unidade VARCHAR(80) NULL AFTER municipio,
  ADD KEY idx_bases_consulta_regiao (id_regiao),
  ADD CONSTRAINT fk_bases_consulta_regiao
    FOREIGN KEY (id_regiao) REFERENCES tb_regioes(id_regiao)
    ON DELETE RESTRICT ON UPDATE CASCADE;

UPDATE tb_bases_consulta b
JOIN tb_regioes r ON r.codigo = 'CAPITAL'
SET b.id_regiao = COALESCE(b.id_regiao, r.id_regiao),
    b.municipio = COALESCE(NULLIF(b.municipio, ''), 'Manaus'),
    b.tipo_unidade = COALESCE(NULLIF(b.tipo_unidade, ''), 'Unidade de atendimento');

ALTER TABLE tb_cadastros_unificados
  ADD COLUMN id_base_consulta INT NULL AFTER id,
  ADD KEY idx_cadastros_unificados_base (id_base_consulta),
  ADD CONSTRAINT fk_cadastros_unificados_base
    FOREIGN KEY (id_base_consulta) REFERENCES tb_bases_consulta(id)
    ON DELETE SET NULL ON UPDATE CASCADE;

UPDATE tb_cadastros_unificados c
JOIN tb_bases_consulta b ON b.nome_banco COLLATE utf8mb4_unicode_ci = c.origem_banco COLLATE utf8mb4_unicode_ci
SET c.id_base_consulta = b.id
WHERE c.id_base_consulta IS NULL;

ALTER TABLE tb_relatorios
  ADD COLUMN id_base_consulta INT NULL AFTER id,
  ADD KEY idx_relatorios_base_data (id_base_consulta, data),
  ADD CONSTRAINT fk_relatorios_base
    FOREIGN KEY (id_base_consulta) REFERENCES tb_bases_consulta(id)
    ON DELETE SET NULL ON UPDATE CASCADE;

UPDATE tb_relatorios r
JOIN tb_bases_consulta b ON b.nome_banco COLLATE utf8mb4_unicode_ci = r.nome_banco COLLATE utf8mb4_unicode_ci
SET r.id_base_consulta = b.id
WHERE r.id_base_consulta IS NULL;

UPDATE tb_relatorios r
JOIN tb_bases_consulta b ON b.identificador COLLATE utf8mb4_unicode_ci = r.nome_banco COLLATE utf8mb4_unicode_ci
SET r.id_base_consulta = b.id
WHERE r.id_base_consulta IS NULL;

ALTER TABLE tb_resumodia
  ADD COLUMN id_base_consulta INT NULL AFTER id,
  ADD KEY idx_resumodia_base_data (id_base_consulta, data_referencia),
  ADD CONSTRAINT fk_resumodia_base
    FOREIGN KEY (id_base_consulta) REFERENCES tb_bases_consulta(id)
    ON DELETE SET NULL ON UPDATE CASCADE;

UPDATE tb_resumodia r
JOIN tb_bases_consulta b ON b.nome_banco COLLATE utf8mb4_unicode_ci = r.nome_banco COLLATE utf8mb4_unicode_ci
SET r.id_base_consulta = b.id
WHERE r.id_base_consulta IS NULL;

ALTER TABLE tb_relatorios_pdf
  ADD COLUMN id_base_consulta INT NULL AFTER id,
  ADD COLUMN nome_banco VARCHAR(120) NULL AFTER id_base_consulta,
  ADD KEY idx_relatorios_pdf_base_data (id_base_consulta, data_relatorio),
  ADD KEY idx_relatorios_pdf_nome_banco (nome_banco),
  ADD CONSTRAINT fk_relatorios_pdf_base
    FOREIGN KEY (id_base_consulta) REFERENCES tb_bases_consulta(id)
    ON DELETE SET NULL ON UPDATE CASCADE;

UPDATE tb_relatorios_pdf p
JOIN tb_bases_consulta b ON b.nome_banco COLLATE utf8mb4_unicode_ci = p.nome_banco COLLATE utf8mb4_unicode_ci
SET p.id_base_consulta = b.id
WHERE p.id_base_consulta IS NULL
  AND p.nome_banco IS NOT NULL
  AND p.nome_banco <> '';

CREATE TABLE IF NOT EXISTS tb_usuario_regioes (
  id_usuario_regiao INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT NOT NULL,
  id_regiao INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_usuario_regiao (id_usuario, id_regiao),
  KEY idx_usuario_regioes_regiao (id_regiao),
  CONSTRAINT fk_usuario_regioes_usuario
    FOREIGN KEY (id_usuario) REFERENCES tb_usuario(id_usuario)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_usuario_regioes_regiao
    FOREIGN KEY (id_regiao) REFERENCES tb_regioes(id_regiao)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tb_usuario_unidades (
  id_usuario_unidade INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT NOT NULL,
  id_base_consulta INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_usuario_unidade (id_usuario, id_base_consulta),
  KEY idx_usuario_unidades_base (id_base_consulta),
  CONSTRAINT fk_usuario_unidades_usuario
    FOREIGN KEY (id_usuario) REFERENCES tb_usuario(id_usuario)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_usuario_unidades_base
    FOREIGN KEY (id_base_consulta) REFERENCES tb_bases_consulta(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuarios existentes recebem CAPITAL para preservar o comportamento atual.
INSERT IGNORE INTO tb_usuario_regioes (id_usuario, id_regiao)
SELECT u.id_usuario, r.id_regiao
FROM tb_usuario u
JOIN tb_regioes r ON r.codigo = 'CAPITAL';

-- Administradores recebem tambem INTERIOR para manter visao administrativa por vinculo regional.
INSERT IGNORE INTO tb_usuario_regioes (id_usuario, id_regiao)
SELECT u.id_usuario, r.id_regiao
FROM tb_usuario u
JOIN tb_regioes r ON r.codigo = 'INTERIOR'
WHERE UPPER(COALESCE(u.perfil, '')) = 'ADMIN';
