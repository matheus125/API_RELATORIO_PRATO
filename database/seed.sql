-- Only unambiguous vocabularies; other values come from the workbook.
INSERT INTO turnos (nome) VALUES ('Matutino'),('Vespertino'),('Integral') ON DUPLICATE KEY UPDATE nome=VALUES(nome);
INSERT INTO escolaridades (nome) VALUES ('Fundamental'),('Médio'),('Técnico'),('Superior') ON DUPLICATE KEY UPDATE nome=VALUES(nome);
INSERT INTO instituicoes (nome) VALUES ('SEAS'),('AADESAM') ON DUPLICATE KEY UPDATE nome=VALUES(nome);
INSERT INTO naturezas_contratacao (nome) VALUES ('Comissionado') ON DUPLICATE KEY UPDATE nome=VALUES(nome);
