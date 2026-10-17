CREATE TABLE IF NOT EXISTS tb_source_sync (
    id_source_sync INT NOT NULL AUTO_INCREMENT,
    sync_id VARCHAR(80) NOT NULL,
    source_root VARCHAR(500) NOT NULL,
    git_branch VARCHAR(120) NULL,
    git_head VARCHAR(120) NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'sincronizada',
    total_files INT NOT NULL DEFAULT 0,
    total_bytes BIGINT NOT NULL DEFAULT 0,
    fingerprint VARCHAR(128) NOT NULL,
    manifest_path VARCHAR(500) NOT NULL,
    backup_path VARCHAR(500) NULL,
    notes TEXT NULL,
    responsavel VARCHAR(180) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_source_sync),
    UNIQUE KEY uk_source_sync_sync_id (sync_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
