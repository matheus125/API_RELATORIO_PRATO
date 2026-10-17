ALTER TABLE tb_cadastros_unificados
  ADD COLUMN origem_titular_id INT NULL AFTER origem_id,
  ADD KEY idx_cadastros_unificados_titular (origem_banco, origem_titular_id);

UPDATE tb_cadastros_unificados
SET origem_titular_id = NULL
WHERE tipo_cadastro = 'TITULAR';
