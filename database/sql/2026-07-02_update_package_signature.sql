ALTER TABLE tb_atualizacoes
    ADD COLUMN signature_url VARCHAR(500) NULL AFTER manifest_url,
    ADD COLUMN public_key_url VARCHAR(500) NULL AFTER signature_url,
    ADD COLUMN signature_algorithm VARCHAR(40) NULL AFTER public_key_url;
