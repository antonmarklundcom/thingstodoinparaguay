# Phase O1 — status and remaining work

O1 was split because the build session ran out of usage budget. This file is the resume point:
it lists what is done, what is not, and the order to do the rest in. Plan reference: `plan.md` §5.1.

## Done (merged in the `phase/o1-foundation` PR)

| Area | Files | Notes |
|---|---|---|
| Object model | `db/schema.sql`, `db/README.md`, `bin/migrate.php` | Complete §2 model incl. tables O1 doesn't use yet. Idempotent; re-running migrate is a no-op. |
| Config | `config/config.php`, `.env.example` | Env vars beat `.env`; every value has a safe default. |
| Core library | `src/Db.php`, `src/Yaml.php`, `src/FrontMatter.php`, `src/Markdown.php`, `src/Str.php`, `src/UrlMap.php` | YAML-lite parser + dumper round-trips (nested maps, lists of maps, block scalars). |
| Vendored | `src/Vendor/Parsedown.php` (1.7.4) + `Parsedown.LICENSE.txt`, `src/Vendor/README.md` | No Composer at runtime. |
| Migration tooling | `bin/wp-export.php`, `bin/scan-import.php`, `src/HtmlToMarkdown.php`, `src/ScanImport.php`, `src/TourParser.php` | `wp-export` hits the WP REST API and falls back to the scan automatically. |
| Repositories | `src/Repo/{Content,Category,Redirect,Settings,Media}Repo.php` | The only data access S3 templates are allowed to use (plan §6.1). |
| Seed content | `content/` — 32 posts, 5 pages, 18 tours, 7 services, 6 categories | One file per `keep` row of `docs/url-map.csv` that maps to a content type. |
| Gap report | `docs/content-gaps.md` | Auto-generated; every placeholder body and missing FAQ answer, per URL. |

## Not done — the rest of O1, in order

1. **`src/Router.php` + `public/index.php` + `public/.htaccess`.**
   Order of resolution: trailing-slash canonicalisation (301) → `redirects` table → content by slug
   → `/category/<slug>/` → `/blog/` and `/blog/page/N/` → `/tours/`, `/services/`,
   `/tourist-attractions-paraguay/` hubs → `/sitemap.xml`, `/robots.txt`, `/feed.xml` → 404.
2. **`src/Seo.php`** — plan §1.9 in full: title (`{title} | Things to do in Paraguay`, ≤ 60 where
   possible), meta description (excerpt truncated at 155), canonical, OG + Twitter, and JSON-LD for
   `WebSite`, `Organization`, `BreadcrumbList`, `BlogPosting`, `TouristTrip`/`Service`, `FAQPage`.
3. **`src/View.php` + `templates/`** — layout plus one plain template per type. Unstyled is fine;
   markup must be final-quality (one `<h1>`, `<header>/<main>/<footer>` landmarks, breadcrumbs)
   because S3 only skins it.
4. **`bin/seed.php`** — `content/` → SQLite. Idempotent by slug. Must never overwrite an item edited
   in the admin: compare `updated_at`, admin wins (`content_items.source` and `content_hash` exist
   for this). Also imports every row of `docs/url-map.csv` into `redirects` with `source='map'`.
5. **`src/Cache.php` + `bin/cache-clear.php`** — HTML page cache in `cache/`, keyed by path,
   bypassed for `/admin/` and form posts.
6. **`bin/verify.php`** — plan §4.11, the contract test: boot `php -S` against a temp SQLite seeded
   from `content/`, request every row of `docs/url-map.csv`, assert status/target, then assert each
   kept URL has exactly one `<h1>`, a `<title>`, a meta description, a canonical and valid JSON-LD.
   Must run in under 60 s. Support `--base=URL` so S5 can point it at staging.
7. **`bin/export.php`** — SQLite → `content/` (the backup direction; `FrontMatter::render()` and
   `Yaml::dump()` already do the writing).
8. **CI + README** — `.github/workflows/ci.yml` already calls `bin/verify.php` and `bin/test.php`
   when they exist, so step 6 turns CI on by itself. Add `tests/` unit tests (start with `Yaml`
   round-trip and `TourParser`) behind `bin/test.php`. README: local dev, seeding, scripts.

## Gates still owed for O1 (plan §4.9)

- [ ] `bin/verify.php` passes every row of `docs/url-map.csv`
- [ ] sitemap lists every published kept URL and nothing else; `feed.xml` validates
- [ ] seed is idempotent (run twice, no duplicates)
- [ ] README covers local dev
- [ ] CI green, PR merged, `plan.md` §9 entry for the completing half
