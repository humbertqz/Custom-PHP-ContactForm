CREATE TABLE IF NOT EXISTS submissions (
    id INTEGER PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    form_id VARCHAR(64) NOT NULL,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    phone TEXT NOT NULL,
    subject TEXT NOT NULL,
    message TEXT NOT NULL,
    extra_fields TEXT NOT NULL CHECK (json_valid(extra_fields)),
    sheets_route VARCHAR(64) NOT NULL,
    source_url TEXT NOT NULL,
    status VARCHAR(24) NOT NULL,
    created_at VARCHAR(20) NOT NULL,
    updated_at VARCHAR(20) NOT NULL
);
CREATE INDEX IF NOT EXISTS submissions_retention ON submissions (created_at);
