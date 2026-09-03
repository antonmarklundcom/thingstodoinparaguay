# thingstodoinparaguay.com

Native PHP/HTML rebuild of the WordPress site, with a first-party `/admin/` for publishing
SEO-optimised posts without code. The plan is `plan.md`; each phase is a prompt file in `prompts/`.

Plain PHP 8.2+, no framework, no Composer at runtime. Content lives in SQLite; the seed content is
committed as Markdown with front matter under `content/`.

## How the build runs
1. Merge the plan PR so `main` contains `plan.md`.
2. Open a fresh **Opus** session with auto-accept permissions and paste:
   `Read prompts/opus-1-foundation.md in this repo and execute it.`
3. Each phase merges its own PR and spawns the next phase in a new session (plan §4.9).
4. If a session dies, re-paste that phase's prompt; it resumes from the first unmet exit criterion.
   Current phase = last entry in `plan.md` §9.

## Local development

Requires PHP 8.2+ with `pdo_sqlite`, `gd`, `mbstring` and `curl`. Nothing else — no Composer, no npm.

```bash
cp .env.example .env          # every value already has a safe default
php bin/migrate.php           # create data/site.sqlite from db/schema.sql
php bin/seed.php              # import content/ and docs/url-map.csv
php -S localhost:8080 -t public public/index.php
```

Then open <http://localhost:8080/>. `public/index.php` doubles as the router script for the built-in
server, so real files under `public/` (CSS, images) are still served directly.

The HTML page cache is off when `APP_ENV=dev`, so template edits show up on reload. Set `CACHE_TTL`
explicitly to exercise it locally.

## Scripts

| Command | What it does |
|---|---|
| `php bin/migrate.php` | Applies `db/schema.sql` then every `db/migrations/*.sql`. Idempotent. |
| `php bin/seed.php` | `content/` + `docs/url-map.csv` → SQLite. Idempotent; never overwrites an item edited in the admin. `--force` re-imports unchanged files. |
| `php bin/export.php` | SQLite → `content/`. The backup direction; round-trips with `seed`. |
| `php bin/verify.php` | The URL contract test — boots a server and asserts every row of `docs/url-map.csv`. |
| `php bin/test.php [filter]` | Unit and integration tests from `tests/*.test.php`. |
| `php bin/cache-clear.php [paths…]` | Empties the HTML page cache, or just the paths you name. |
| `php bin/scan-import.php --force` | Rebuilds `content/` from `docs/wp-scan.md` — the only source of old content (plan §1.13). |

All database scripts take `--db=path` so they can run against a throwaway file.

## Before you push

```bash
php bin/test.php && php bin/verify.php
```

CI runs both on every PR, plus `php -l` over every tracked PHP file. A change that breaks
`bin/verify.php` breaks the URL contract and is not mergeable (plan §4.11).

`bin/verify.php --base=https://staging.example.com` points the same assertions at a deployed site —
that is how phase S5 checks staging.

## Layout

```
bin/         command-line scripts (migrate, seed, export, verify, test, cache-clear, scan-import)
config/      config.php — reads .env; environment variables always win
content/     seed content as Markdown + front matter, one file per URL
db/          schema.sql (the complete object model) + numbered migrations
docs/        the WordPress scan, the URL map, content gaps
public/      document root — front controller, .htaccess, assets
src/         Router, Seo, Cache, View, Db, Repo/*, Markdown, vendored Parsedown
templates/   layout + one template per content type (unstyled until phase S3)
tests/       *.test.php, run by bin/test.php
```

## Docs
- `docs/wp-scan-brief.md` — facts about the old site
- `docs/url-map.csv` — old URL → new URL contract (tested by `bin/verify.php`)
- `docs/content-gaps.md` — placeholder copy and missing answers, per URL, for phase S4
- `KNOWN-ISSUES.md` — running list of minor issues
