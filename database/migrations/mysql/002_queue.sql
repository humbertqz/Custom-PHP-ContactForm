CREATE TABLE IF NOT EXISTS queue_submissions (
    uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    form_id VARCHAR(64) NOT NULL,
    mode VARCHAR(24) NOT NULL,
    payload JSON,
    request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin UNIQUE,
    request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin,
    created_at BIGINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS deliveries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    channel VARCHAR(32) NOT NULL,
    status VARCHAR(24) NOT NULL,
    attempts INTEGER NOT NULL DEFAULT 0,
    last_error_code VARCHAR(64),
    next_retry_at BIGINT,
    processing_started_at BIGINT,
    processing_token CHAR(36),
    delivered_at BIGINT,
    created_at BIGINT NOT NULL,
    updated_at BIGINT NOT NULL,
    UNIQUE KEY delivery_channel (submission_id, channel),
    INDEX deliveries_due (status, next_retry_at, id),
    INDEX deliveries_lease (status, processing_started_at),
    FOREIGN KEY (submission_id) REFERENCES queue_submissions(uuid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
