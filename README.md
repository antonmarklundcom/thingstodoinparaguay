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
php bin/create-admin.php      # the account /admin/ signs in with
php -S localhost:8080 -t public public/index.php
```

Then open <http://localhost:8080/>, and <http://localhost:8080/admin/> for the panel.
`public/index.php` doubles as the router script for the built-in server, so real files under
`public/` (CSS, images) are still served directly.

The HTML page cache is off when `APP_ENV=dev`, so template edits show up on reload. Set `CACHE_TTL`
explicitly to exercise it locally. Nothing under `/admin/` is ever cached.

### The admin panel

`/admin/` is the publishing panel (plan §5.2): session login with bcrypt passwords, a CSRF token on
every mutating request and a five-attempt lockout. `docs/admin-guide.md` is the guide for whoever
publishes; the short version for developers:

- `src/Admin/App.php` routes and controls it; `src/Admin/ContentWriter.php` owns every write to
  `content_items`, including the 301 a slug change leaves behind and the cache paths it clears.
- `src/SeoScore.php` is the one implementation of the score — the editor and `bin/seo-audit.php`
  both call it, so they can never disagree.
- `src/Uploader.php` validates an upload by its real mime type and writes 400/800/1600 px WebP plus
  the original format into `public/media/YYYY/MM/`.
- `public/assets/admin/` holds the panel's CSS and its editor JavaScript. Both are written for this
  project and vendored here: no CDN, no build step.

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
| `php bin/create-admin.php` | Creates the `/admin/` account, or resets its password. `--list` shows who has one. |
| `php bin/seo-audit.php [--details] [--strict]` | The SEO score (`src/SeoScore.php`) for every published item. `--strict` is phase S4's gate: ≥ 80 everywhere and no Lorem Ipsum. |
| `php bin/publish-due.php` | Publishes anything whose scheduled time has passed. Put it on cron — see `docs/admin-guide.md`. |

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
src/         Router, Seo, Cache, View, Db, Repo/*, Markdown, SeoScore, Uploader,
             Exporter, Admin/* (the panel), vendored Parsedown
templates/   layout + one template per content type (unstyled until phase S3),
             admin/ — the panel's own shell and screens
tests/       *.test.php, run by bin/test.php
```

## Docs
- `docs/wp-scan-brief.md` — facts about the old site
- `docs/url-map.csv` — old URL → new URL contract (tested by `bin/verify.php`)
- `docs/content-gaps.md` — placeholder copy and missing answers, per URL, for phase S4
- `docs/admin-guide.md` — how to publish a post in five steps, written for a non-technical author
- `KNOWN-ISSUES.md` — running list of minor issues
