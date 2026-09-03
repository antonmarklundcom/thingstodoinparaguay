-- Phase O2 — admin panel & publishing pipeline (plan §5.2).
-- Mirrors the same changes made in db/schema.sql, for databases that already exist.

-- The focus keyword the SEO score (src/SeoScore.php) grades an item against.
ALTER TABLE content_items ADD COLUMN focus_keyword TEXT NOT NULL DEFAULT '';

-- Login throttling for /admin/ (plan §5.2 "login rate limit").
CREATE TABLE IF NOT EXISTS login_attempts (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    ip         TEXT NOT NULL DEFAULT '',
    email      TEXT NOT NULL DEFAULT '',
    successful INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts(ip, created_at);
CREATE INDEX IF NOT EXISTS idx_login_attempts_email ON login_attempts(email, created_at);
