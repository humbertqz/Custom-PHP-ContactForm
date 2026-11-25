CREATE TABLE IF NOT EXISTS queue_submissions (
    uuid CHAR(36) PRIMARY KEY,
    form_id VARCHAR(64) NOT NULL,
    mode VARCHAR(24) NOT NULL,
    payload TEXT CHECK (payload IS NULL OR json_valid(payload)),
    request_key CHAR(64) UNIQUE,
    request_hash CHAR(64),
    created_at INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS deliveries (
    id INTEGER PRIMARY KEY,
    submission_id CHAR(36) NOT NULL REFERENCES queue_submissions(uuid) ON DELETE CASCADE,
    channel VARCHAR(32) NOT NULL,
    status VARCHAR(24) NOT NULL,
    attempts INTEGER NOT NULL DEFAULT 0,
    last_error_code VARCHAR(64),
    next_retry_at INTEGER,
    processing_started_at INTEGER,
    processing_token CHAR(36),
    delivered_at INTEGER,
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL,
    UNIQUE (submission_id, channel)
);
CREATE INDEX IF NOT EXISTS deliveries_due ON deliveries (status, next_retry_at, id);
CREATE INDEX IF NOT EXISTS deliveries_lease ON deliveries (status, processing_started_at);
