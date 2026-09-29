-- Import Excel through scripts/import-gadsan.php (validation and transactions).
-- Aggregate verification, without exposing personal data:
SELECT id, nome_arquivo, status, total_linhas, total_importados, total_atualizados,
       total_ignorados, total_inconsistencias FROM importacoes ORDER BY id DESC;
SELECT importacao_id, aba, COUNT(*) AS registros FROM importacao_origens GROUP BY importacao_id, aba;
SELECT importacao_id, tipo_inconsistencia, COUNT(*) AS ocorrencias
FROM importacao_inconsistencias GROUP BY importacao_id, tipo_inconsistencia;
