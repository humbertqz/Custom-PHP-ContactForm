CREATE TABLE IF NOT EXISTS rate_limits (
    bucket CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    hits INTEGER NOT NULL,
    expires_at BIGINT NOT NULL,
    INDEX rate_limits_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
