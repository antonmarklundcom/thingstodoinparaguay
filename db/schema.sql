-- thingstodoinparaguay.com — complete object model (plan.md §2).
-- Authoritative schema for a fresh install. Every statement is idempotent, so
-- bin/migrate.php can replay this file safely. Structural CHANGES to an existing
-- database must ALSO ship as a numbered file in db/migrations/ (see db/README.md).

PRAGMA foreign_keys = ON;

-- ---------------------------------------------------------------------------
-- Migration bookkeeping
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS schema_migrations (
    name        TEXT PRIMARY KEY,
    checksum    TEXT NOT NULL,
    applied_at  TEXT NOT NULL
);

-- ---------------------------------------------------------------------------
-- People
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    email          TEXT NOT NULL UNIQUE,
    password_hash  TEXT NOT NULL,
    name           TEXT NOT NULL DEFAULT '',
    last_login_at  TEXT
);

-- ---------------------------------------------------------------------------
-- Media
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS media (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    filename    TEXT NOT NULL,
    path        TEXT NOT NULL UNIQUE,   -- web path, e.g. /media/2025/07/foo.jpg
    width       INTEGER,
    height      INTEGER,
    alt         TEXT NOT NULL DEFAULT '',
    mime        TEXT NOT NULL DEFAULT '',
    sizes_json  TEXT NOT NULL DEFAULT '[]',
    created_at  TEXT NOT NULL
);

-- ---------------------------------------------------------------------------
-- Taxonomy
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    slug             TEXT NOT NULL UNIQUE,
    name             TEXT NOT NULL,
    description      TEXT NOT NULL DEFAULT '',
    meta_title       TEXT NOT NULL DEFAULT '',
    meta_description TEXT NOT NULL DEFAULT '',
    sort_order       INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS tags (
    id    INTEGER PRIMARY KEY AUTOINCREMENT,
    slug  TEXT NOT NULL UNIQUE,
    name  TEXT NOT NULL
);

-- ---------------------------------------------------------------------------
-- Content
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS content_items (
    id                 INTEGER PRIMARY KEY AUTOINCREMENT,
    type               TEXT NOT NULL CHECK (type IN ('post','page','tour','service')),
    slug               TEXT NOT NULL UNIQUE,
    title              TEXT NOT NULL,
    status             TEXT NOT NULL DEFAULT 'draft'
                        CHECK (status IN ('draft','published','scheduled')),
    published_at       TEXT,
    updated_at         TEXT NOT NULL,
    excerpt            TEXT NOT NULL DEFAULT '',
    body_md            TEXT NOT NULL DEFAULT '',
    body_html          TEXT NOT NULL DEFAULT '',
    cover_media_id     INTEGER REFERENCES media(id) ON DELETE SET NULL,
    category_id        INTEGER REFERENCES categories(id) ON DELETE SET NULL,
    meta_title         TEXT NOT NULL DEFAULT '',
    meta_description   TEXT NOT NULL DEFAULT '',
    canonical_override TEXT NOT NULL DEFAULT '',
    noindex            INTEGER NOT NULL DEFAULT 0,
    og_image_media_id  INTEGER REFERENCES media(id) ON DELETE SET NULL,
    author_id          INTEGER REFERENCES users(id) ON DELETE SET NULL,
    seo_score          INTEGER NOT NULL DEFAULT 0,
    word_count         INTEGER NOT NULL DEFAULT 0,
    sort_order         INTEGER NOT NULL DEFAULT 0,
    source             TEXT NOT NULL DEFAULT 'seed',  -- seed | admin
    content_hash       TEXT NOT NULL DEFAULT ''       -- hash of the seed file last imported
);

CREATE INDEX IF NOT EXISTS idx_items_type_status ON content_items(type, status);
CREATE INDEX IF NOT EXISTS idx_items_published  ON content_items(status, published_at DESC);
CREATE INDEX IF NOT EXISTS idx_items_category   ON content_items(category_id);

-- Tours and services share this table; content_items.type distinguishes them.
CREATE TABLE IF NOT EXISTS tour_details (
    item_id        INTEGER PRIMARY KEY REFERENCES content_items(id) ON DELETE CASCADE,
    hook_md        TEXT NOT NULL DEFAULT '',
    solution_md    TEXT NOT NULL DEFAULT '',
    itinerary_json TEXT NOT NULL DEFAULT '[]',
    why_json       TEXT NOT NULL DEFAULT '[]',
    practical_json TEXT NOT NULL DEFAULT '[]',
    faq_json       TEXT NOT NULL DEFAULT '[]',
    price_usd      REAL,
    duration       TEXT NOT NULL DEFAULT '',
    departure      TEXT NOT NULL DEFAULT '',
    transport      TEXT NOT NULL DEFAULT '',
    requirements   TEXT NOT NULL DEFAULT '',
    cta_text       TEXT NOT NULL DEFAULT '',
    tagline        TEXT NOT NULL DEFAULT '',
    itinerary_label TEXT NOT NULL DEFAULT '',
    closing_md     TEXT NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS item_tags (
    item_id INTEGER NOT NULL REFERENCES content_items(id) ON DELETE CASCADE,
    tag_id  INTEGER NOT NULL REFERENCES tags(id) ON DELETE CASCADE,
    PRIMARY KEY (item_id, tag_id)
);

CREATE INDEX IF NOT EXISTS idx_item_tags_tag ON item_tags(tag_id);

-- ---------------------------------------------------------------------------
-- URL contract
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS redirects (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    from_path  TEXT NOT NULL UNIQUE,
    to_path    TEXT NOT NULL DEFAULT '',
    status     INTEGER NOT NULL DEFAULT 301 CHECK (status IN (301, 410)),
    source     TEXT NOT NULL DEFAULT 'manual' CHECK (source IN ('map','slug-change','manual')),
    hits       INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT ''
);

-- ---------------------------------------------------------------------------
-- Conversion
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS leads (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL DEFAULT '',
    email      TEXT NOT NULL DEFAULT '',
    phone      TEXT NOT NULL DEFAULT '',
    message    TEXT NOT NULL DEFAULT '',
    page_path  TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL,
    forwarded  INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS subscribers (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    email      TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL,
    source     TEXT NOT NULL DEFAULT ''
);

-- ---------------------------------------------------------------------------
-- Settings
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    key   TEXT PRIMARY KEY,
    value TEXT NOT NULL DEFAULT ''
);
