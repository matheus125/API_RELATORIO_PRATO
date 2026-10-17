-- Evolucao retrocompativel da tabela existente de auditoria.
-- Mantem tb_userlogs e preserva registros antigos.

ALTER TABLE tb_userlogs
  MODIFY COLUMN id_usuario INT NULL,
  ADD COLUMN categoria VARCHAR(60) NULL AFTER acao,
  ADD COLUMN severidade VARCHAR(20) NULL AFTER categoria,
  ADD COLUMN menu VARCHAR(120) NULL AFTER modulo,
  ADD COLUMN sub_menu VARCHAR(120) NULL AFTER menu,
  ADD COLUMN entidade VARCHAR(100) NULL AFTER sub_menu,
  ADD COLUMN registro_id VARCHAR(100) NULL AFTER referencia_id,
  ADD COLUMN descricao TEXT NULL AFTER detalhes,
  ADD COLUMN dados_anteriores LONGTEXT NULL AFTER descricao,
  ADD COLUMN dados_novos LONGTEXT NULL AFTER dados_anteriores,
  ADD COLUMN alteracoes LONGTEXT NULL AFTER dados_novos,
  ADD COLUMN rota VARCHAR(255) NULL AFTER alteracoes,
  ADD COLUMN metodo_http VARCHAR(12) NULL AFTER rota,
  ADD COLUMN sessao_id VARCHAR(128) NULL AFTER user_agent,
  ADD COLUMN status VARCHAR(30) NULL DEFAULT 'SUCCESS' AFTER sessao_id,
  ADD COLUMN erro LONGTEXT NULL AFTER status;

UPDATE tb_userlogs
SET
  categoria = COALESCE(categoria, acao),
  severidade = COALESCE(severidade, 'INFO'),
  descricao = COALESCE(descricao, detalhes),
  registro_id = COALESCE(registro_id, CAST(referencia_id AS CHAR)),
  status = COALESCE(status, 'SUCCESS')
WHERE categoria IS NULL
   OR severidade IS NULL
   OR descricao IS NULL
   OR registro_id IS NULL
   OR status IS NULL;

CREATE INDEX idx_userlogs_categoria ON tb_userlogs (categoria);
CREATE INDEX idx_userlogs_modulo ON tb_userlogs (modulo);
CREATE INDEX idx_userlogs_entidade_registro ON tb_userlogs (entidade, registro_id);
CREATE INDEX idx_userlogs_status ON tb_userlogs (status);
CREATE INDEX idx_userlogs_severidade ON tb_userlogs (severidade);
CREATE INDEX idx_userlogs_ip ON tb_userlogs (ip);
