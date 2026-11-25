CREATE TABLE IF NOT EXISTS submissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    form_id VARCHAR(64) NOT NULL,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    phone TEXT NOT NULL,
    subject TEXT NOT NULL,
    message TEXT NOT NULL,
    extra_fields JSON NOT NULL,
    sheets_route VARCHAR(64) NOT NULL,
    source_url TEXT NOT NULL,
    status VARCHAR(24) NOT NULL,
    created_at VARCHAR(20) NOT NULL,
    updated_at VARCHAR(20) NOT NULL,
    INDEX submissions_retention (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
