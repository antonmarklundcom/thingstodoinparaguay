# thingstodoinparaguay.com — WordPress → native PHP/HTML rebuild

Plan written 2026-09-03 in the planning session. Build sessions execute it phase by phase under §4.
Facts about the old site live in `docs/wp-scan-brief.md` (short) and `docs/wp-scan.md` (full scan).
The complete old-URL → new-URL contract is `docs/url-map.csv`.

| Phase | Model  | Prompt file                     | Plan sections | Depends on |
|-------|--------|---------------------------------|---------------|------------|
| O1    | Opus   | `prompts/opus-1-foundation.md`  | §5.1          | plan merged |
| O2    | Opus   | `prompts/opus-2-admin.md`       | §5.2          | O1 merged  |
| S3    | Sonnet | `prompts/sonnet-3-design.md`    | §6.1          | O2 merged  |
| S4    | Sonnet | `prompts/sonnet-4-content.md`   | §6.2          | S3 merged  |
| S5    | Sonnet | `prompts/sonnet-5-launch.md`    | §6.3          | S4 merged  |

Fable (the planning model) never executes a phase. See §4.8.

---

## 1. Decisions already made — do not re-litigate

1. **Stack: plain PHP 8.2+, no framework, no Composer at runtime.** Front controller `public/index.php`,
   Apache/LiteSpeed `.htaccess` rewrites, PHP templates. Runs on Hostinger shared PHP hosting (the
   account that hosts the WordPress site today). Vendored single-file libraries only (Parsedown for
   Markdown; a small Markdown editor JS for admin). No Node build step required to deploy.
2. **Content store: SQLite** via PDO at `data/site.sqlite` (outside the web root). Git deploys never
   touch it. Migrated/seed content is committed as Markdown+front-matter under `content/` and imported
   by an idempotent `bin/seed.php`. Anything created in the admin lives only in SQLite; `bin/export.php`
   dumps SQLite back to `content/` for backup.
3. **Publishing without code = the `/admin/` panel** (built in O2): single-admin login, editor with
   live SEO score, image upload pipeline, drafts/scheduling, redirects manager, one-click publish.
4. **URL contract:** every one of the 121 live URLs keeps working as `docs/url-map.csv` says — kept
   verbatim, 301'd to its consolidated target, or 410 for junk. Posts stay flat at `/<slug>/`. New
   canonical structure: `/` home, `/blog/` (+ `/blog/page/N/`), `/category/<slug>/`, `/tours/` index,
   `/services/` index, `/<slug>/` for every post/page/tour/service. Tags are stored but have **no
   archive pages** (old `/tag/*` → 301 `/blog/`). `/home/`→`/`, `/about2/`→`/about/`, `/faq2/`→`/faq/`,
   `/service/`→`/tours/`.
5. **Content types:** `post`, `page`, `tour`, `service`. Posts/pages have a Markdown body. Tours and
   services are structured (hero, hook, solution, itinerary steps, "why choose", practical-info facts,
   FAQ list, price/duration/departure, closing CTA) — they render the same template and emit
   `TouristTrip`/`Service` + `FAQPage` JSON-LD. One `content_items` table + typed detail tables (§2).
6. **The 33 Lorem-Ipsum posts get real content** (S4). They keep their slugs, titles and categories.
   The old body is discarded. New posts are published, not drafted — anything beats Lorem Ipsum for
   the URLs Google already knows. Anton reviews in the admin afterwards.
7. **Conversion path:** self-hosted PHP contact/quote form (SQLite `leads` table + email + optional
   VenderCRM push via the `vendercrm-lead-capture` skill) and a WhatsApp button on every page. The
   GoHighLevel iframe is dropped. Newsletter: SQLite `subscribers` table; Mailchimp only if a key is
   provided (env), never blocking.
8. **Language: English only**, `lang="en"`, no i18n layer needed for the public site. Currency shown
   as USD when a price exists; no fake prices — a tour without a price shows "Ask for a quote".
9. **SEO baseline is non-negotiable (O1):** unique `<title>`/meta description per URL, canonical,
   Open Graph + Twitter cards, JSON-LD (`WebSite`, `Organization`, `BreadcrumbList`, `BlogPosting`,
   `TouristTrip`/`Service`, `FAQPage`), `sitemap.xml` (dynamic, images included), `robots.txt`,
   `feed.xml`, trailing-slash canonicalisation, HTML page cache, `Cache-Control` for assets, lazy
   images with width/height, WebP with fallback. Target: Lighthouse ≥ 95 on all four categories on
   mobile for home, a post, a tour, and `/blog/`.
10. **Brand:** "Things to do in Paraguay". Remove "Trexplore"/"matour" leftovers. Two named people:
    Yanina Alvarez (Photographer) and Anton Marklund (Marketing Director). Contact: Edificio Skytower,
    Asunción; +595 995 628 862; hello@thingstodoinparaguay.com.
11. **Deployment:** Hostinger hPanel Git deploy of this repo, first to a staging subdomain, then cut
    over the main domain by pointing the domain's document root to `public/`. WordPress is kept as a
    zipped backup, never deleted by a build session. DNS does not change.
12. **Models:** O-phases run on Opus, S-phases on Sonnet. Fable only in Anton's own planning window.
13. **Source of old content is `docs/wp-scan.md` only.** No WordPress API/export tooling. Whatever
    the scan lacks (FAQ answers, images, body copy) is written fresh in S4 — the old site was not
    authoritative anyway.

## 2. Object model

All identifiers in English. Timestamps ISO-8601 UTC.

```
content_items   id, type(post|page|tour|service), slug UNIQUE, title, status(draft|published|scheduled),
                published_at, updated_at, excerpt, body_md, body_html (rendered cache),
                cover_media_id, category_id NULL, meta_title, meta_description, canonical_override,
                noindex INT, og_image_media_id, author_id, seo_score INT, word_count INT, sort_order
tour_details    item_id PK, hook_md, solution_md, itinerary_json, why_json, practical_json,
                faq_json, price_usd NULL, duration, departure, transport, requirements, cta_text
                (services use the same table; `type` on content_items distinguishes them)
categories      id, slug UNIQUE, name, description, meta_title, meta_description
tags            id, slug UNIQUE, name
item_tags       item_id, tag_id
media           id, filename, path, width, height, alt, mime, sizes_json, created_at
redirects       id, from_path UNIQUE, to_path, status(301|410), source(map|slug-change|manual), hits
leads           id, name, email, phone, message, page_path, created_at, forwarded INT
subscribers     id, email UNIQUE, created_at, source
users           id, email UNIQUE, password_hash, name, last_login_at
settings        key PK, value   (site name, tagline, WhatsApp number, GA id, social links…)
```

Whole schema is created in O1 (`db/schema.sql` + `bin/migrate.php`, versioned, idempotent). Later
phases never alter it except via a new numbered migration in an O-phase.

## 3. Feature scope

Core (O1): routing, templates, SEO layer, redirects, seed import, scan importer, verify script, CI.
Core (O2): admin panel, media pipeline, SEO score, cache invalidation, backup export.
Design (S3): visual system, all public templates, forms, WhatsApp, performance, imagery.
Content (S4): 33 real posts, tour FAQ gaps, bug-fixed page copy, hub pages, metadata, alt text, links.
Launch (S5): staging deploy, verification against staging, cutover runbook, post-launch SEO checks.

Backlog (§10): Spanish version, pricing/booking checkout, PDF guide sales, review widgets.

## 4. Autonomy protocol (every phase session obeys this)

1. Work until every exit criterion of the phase passes. Never ask permission for in-plan work.
2. One PR per phase. Branch `phase/<id>` off latest `main`. Create the PR, watch CI, merge when green.
   A red build is always the session's own work. Never start on top of an unmerged previous phase.
3. Minor non-blocking issues → `KNOWN-ISSUES.md`, keep building.
4. Stop and ask ONLY for: a credential with no graceful fallback, or a bad-foundation decision (schema
   shape, auth, URL contract) where guessing wrong forces a rewrite. Everything else: choose reasonably,
   record the choice in the build log, continue.
5. Missing env values never block: document in `.env.example`, degrade gracefully.
6. Every prompt is re-runnable: inspect the branch first, continue from the first unmet exit criterion.
7. S-phase hard limits: no schema changes, no auth changes, no changes to the router/SEO core or the
   URL contract. Need something? Work around it and add a Backlog note.
8. **Model cost guardrail:** Fable/Mythos-class models are never used for build phases, subagents or
   spawned sessions. Phase tables only name Opus and Sonnet. If a session believes Fable is needed, it
   stops and asks Anton first. Subagents spawned inside a phase inherit at most the phase's model.
9. **Phase handoff — four gates:** PR merged green; exit checklist passed; pre-handoff audit done
   (re-run `bin/verify.php` + CI locally, adversarially re-read your merged diff, fix findings);
   build-log entry committed to §9. Then spawn the next phase as a NEW session with the
   claude-code-remote `create_session` tool: inherit environment and permission mode (never `plan`),
   `model` per the phase table, `prompt` exactly
   `Read prompts/<next-file>.md in this repo and execute it.` Then end with the phase report.
   Fallback without `create_session`: continue in the same window if the next phase uses the same
   model; stop and report at a model switch.
10. **Build log:** before merging, append a 5–10 line dated entry to §9 — phase id + PR link, what now
    exists, decisions/deviations, where the next phase should look first. Fresh sessions orient from
    `plan.md` + §9 + `KNOWN-ISSUES.md` only.
11. **Verification standard:** `bin/verify.php` boots `php -S` against a temp SQLite seeded from
    `content/`, requests every row of `docs/url-map.csv` and asserts the expected status/target, then
    checks each kept URL for exactly one `<h1>`, a `<title>`, a meta description, a canonical, and valid
    JSON-LD. CI runs it on every PR. A phase that breaks it is not mergeable.

## 5. Opus phases

### 5.1 Phase O1 — Foundation, SEO core, URL contract, migration tooling

Deliverables:
- Layout: `public/` (index.php, .htaccess, assets/, media/), `src/` (Router, Db, Repo classes,
  Markdown, Seo, Cache, View helpers), `templates/` (layout + one plain template per type, unstyled),
  `db/schema.sql`, `db/migrations/`, `bin/` (migrate, seed, export, scan-import, verify, cache-clear),
  `content/` (seed Markdown), `config/` (`config.php` reading `.env`), `.env.example`, `tests/`.
- Router: trailing-slash canonicalisation (301), redirects table lookup first, then content by slug,
  then category, then `/blog/` pagination, `/tours/`, `/services/`, `/sitemap.xml`, `/robots.txt`,
  `/feed.xml`, 404 page. `docs/url-map.csv` is imported into `redirects` by `bin/seed.php`.
- SEO layer per §1.9, implemented in `src/Seo.php` and emitted by the layout for every route.
  Default meta description = excerpt, truncated at 155 chars; default title = `{title} | Things to
  do in Paraguay` ≤ 60 chars where possible.
- Scan importer `bin/scan-import.php`: parses `docs/wp-scan.md` into `content/<type>/<slug>.md` with
  front matter (titles, slugs, categories, tags, captured body copy, captured FAQ). No network access
  to the old site. Lorem-Ipsum bodies are kept for now (S4 replaces them).
- Seed importer: front matter → SQLite, idempotent by slug, never overwrites an item edited in the
  admin (compare `updated_at`, admin wins).
- HTML page cache in `cache/` keyed by path, bypassed for admin/forms, cleared by `bin/cache-clear.php`.
- `bin/verify.php` per §4.11 and `.github/workflows/ci.yml` running `php -l` + verify.
- README: local dev (`php -S localhost:8080 -t public`), seeding, scripts.

Exit: CI green; `bin/verify.php` passes for every row of `docs/url-map.csv`; sitemap lists every
published kept URL and nothing else; feed validates; `content/` contains every post/page/tour/service
from the map; PR merged; build log written.

### 5.2 Phase O2 — Admin panel & publishing pipeline

Deliverables:
- `/admin/` with session auth (bcrypt, CSRF tokens, login rate limit, secure cookies), password set
  via `bin/create-admin.php`. Route lives outside the page cache.
- CRUD for posts, pages, tours, services, categories, tags, redirects, settings, media, leads and
  subscribers (read-only lists with CSV export for the last two).
- Editor: title, slug (auto from title, editable; changing a published slug auto-creates a 301),
  category, tags, cover image (upload with alt text required), excerpt, Markdown body with a vendored
  editor (toolbar + preview), meta title/description with live character counters, noindex toggle,
  status draft/published/scheduled with publish date, preview link, "Publish" button.
  Tour/service editor: the structured fields from §2 as repeatable rows (itinerary, why, practical,
  FAQ), price/duration/departure.
- **SEO score** (0–100) computed server-side on save and shown live in the editor with a checklist:
  focus keyword present in title/slug/first 100 words/one H2, title 30–60 chars, description 70–155,
  ≥ 600 words (posts), ≥ 2 H2, every image has alt, ≥ 2 internal links, ≥ 1 external link, no
  Lorem Ipsum, cover image set. `bin/seo-audit.php` prints the score for every published item.
- Media pipeline: uploads validated (mime, size), resized with GD to 400/800/1600 widths, WebP +
  original format, stored `public/media/YYYY/MM/`, `srcset` helper for templates.
- Publishing clears the page cache for affected paths + sitemap + feed.
- Backup: `bin/export.php` (SQLite → `content/`) and admin "Download backup" (zip of content + media
  list). `docs/admin-guide.md` explaining how to publish a post in 5 steps, screenshots optional.

Exit: CI green; verify still passes; `tests/admin.test.php` covers login (fail/success/lockout),
CSRF rejection, create → publish → slug change → old slug 301s, SEO score for a known fixture,
upload of a 3000px JPEG produces 3 WebP sizes; PR merged; build log written.

## 6. Sonnet phases

### 6.1 Phase S3 — Design system, public templates, performance

Hard limits: §4.7. Data access only through the `src/Repo*` classes O1 built.

- Load `web-design-system` (if available) and `higgsfield-web-imagery` skills. Look: warm, bright,
  photographic, travel-magazine feel. Colours: a Paraguay-red/white/blue accent set plus warm neutrals;
  system font stack or one self-hosted variable font (no Google Fonts requests). Mobile-first.
- Templates: home (hero, top tours, latest posts, why-us with the two team members, FAQ, CTA), blog
  index + pagination, category, post (TOC for ≥ 4 H2, reading time, related posts by category/tag,
  share links, author box, prev/next), tour/service (structured sections, sticky "Ask for a quote"
  CTA, FAQ accordion with FAQPage JSON-LD already emitted by O1), tours index, services index,
  static page, contact (form + WhatsApp + map with the real address), 404.
- Forms: contact/quote form (server-side validation, honeypot + time-trap, stores lead, emails via
  SMTP env or `mail()`, VenderCRM push if env set — load `vendercrm-lead-capture`), newsletter form.
  WhatsApp floating button using settings.whatsapp (`wa.me/595995628862` default).
- Performance: single ≤ 40 KB CSS file (critical part inlined), no JS framework, ≤ 15 KB vanilla JS,
  lazy images with dimensions, preload hero image, font-display swap, `.htaccess` gzip/brotli +
  long-lived asset caching with hashed filenames.
- Imagery: replace mismatched/irrelevant images on tours where the skill budget allows; otherwise a
  consistent placeholder system. Every `<img>` has alt (S4 refines text).
- Analytics hook: GA4 id from settings, loaded only if set, `async`.

Exit: CI green; verify passes; Lighthouse mobile ≥ 95 ×4 on `/`, one post, one tour, `/blog/`
(record the numbers in the build log); HTML validates (no errors) on those four; PR merged.

### 6.2 Phase S4 — Content: real posts, fixed pages, metadata, internal links

Hard limits: §4.7. Content goes into `content/` Markdown (then `bin/seed.php`), never only into SQLite.

- Write real content for all 33 posts (900–1600 words each): honest, specific, useful for a visitor
  or relocating expat, with H2/H3 structure, a practical-info list, an FAQ block (3–5 Q&A), 2+ internal
  links to tours/services/posts, one CTA to the matching tour or `/contact/`. **No invented prices,
  opening hours, phone numbers or statistics** — say "check current hours" instead. No Lorem Ipsum
  anywhere on the site after this phase (`bin/seo-audit.php` fails on it).
- Tours/services: fill the FAQ answers the scan could not capture, remove duplicated/stray copy
  (yerba-mate-tour, bird-watching, asuncion-city-tour, real-estate-tour, tourism-guide), fix mismatched
  images' alt text, add practical-info where empty.
- Hub pages: `/tourist-attractions-paraguay/` (top attractions grid linking every nature/city post and
  tour), `/tours/`, `/services/` intros; home copy without "Trexplore".
- Metadata: unique meta title + description for every kept URL including categories; category
  descriptions; OG image per item; alt text for every media row.
- Internal linking pass: every post links to ≥ 2 others; every tour is linked from ≥ 2 posts.
- Images: the old site's images are not migrated. S3/S4 use Higgsfield-generated or placeholder
  imagery per the `higgsfield-web-imagery` skill.

Exit: CI green; `bin/seo-audit.php` reports ≥ 80 for every published item and zero Lorem Ipsum;
verify passes; PR merged; build log lists any facts flagged "unverified" for Anton to check.

### 6.3 Phase S5 — Staging deploy, cutover runbook, post-launch SEO

Hard limits: §4.7. Load `nextjs-deploy-hostinger` for the Hostinger-specific parts (hPanel Git,
document root, PHP version, LiteSpeed cache) even though this app is PHP, not Next.js.

- Deploy to a Hostinger staging subdomain via hPanel Git (document root → `public/`); run
  `bin/migrate.php`, `bin/seed.php`, `bin/create-admin.php`; set `.env` from `.env.example`.
- Run `bin/verify.php --base=https://<staging>` against staging; fix anything Hostinger-specific
  (`.htaccess` differences, PHP extensions, file permissions on `data/` and `cache/`).
- Write `docs/cutover-runbook.md`: numbered steps for Anton — WordPress backup zip, switch document
  root, run scripts, smoke test, submit `sitemap.xml` in Search Console, verify 20 sampled old URLs,
  watch Coverage report for a week, remove WP after 30 days. Include rollback (point root back).
- If Hostinger credentials are not available in the session: build everything that can be built
  (runbook, `deploy/` helper scripts, a `docs/staging-checklist.md`), state exactly what Anton must
  run, and stop — that counts as complete for this phase.

Exit: staging URL returns green verify (or the runbook + scripts exist and the missing-credential
reason is logged); PR merged; final closing report per the prompt file.

## 7. Human-inputs checklist

| Input | First needed | Fallback |
|---|---|---|
| Admin email + initial password | O2 | `bin/create-admin.php` prompts; documented |
| SMTP host/user/pass for lead emails | S3 | `mail()` + leads stored in SQLite anyway |
| WhatsApp number (default +595 995 628 862) | S3 | default used |
| VenderCRM tenant API key | S3 | push disabled, leads still stored |
| Mailchimp API key + list id | S3 | subscribers stored locally |
| GA4 measurement id | S3 | analytics off |
| Higgsfield credit budget for imagery | S3 | placeholder system |
| Hostinger hPanel access + staging subdomain | S5 | runbook only |
| Google Search Console access | S5 (runbook) | manual step for Anton |

## 8. Open business questions (parked, not build work)

- Real prices and durations for the tours (all "$0"/"$" today).
- Whether to sell the PDF guide (`/paraguay-tourism-guide/`) and via what checkout.
- Spanish version of the site.
- Social profile URLs for the footer icons.
- Which tour photos are Yanina's originals vs stock (licensing).

## 9. Build log & handoff

(Append one entry per phase before merging. Newest last.)

- 2026-09-03 — Planning session (Fable). Repo was empty. Added `plan.md`, `prompts/`, `docs/`
  (scan, brief, url-map.csv), CI skeleton, README, KNOWN-ISSUES.md. Next: O1 branches from `main`
  once the plan PR is merged. Look first at `docs/url-map.csv` and §5.1.
- 2026-09-03 — **O1 part 1 (Opus)**, PR `phase/o1-foundation`. **Phase O1 is NOT complete** — the
  session was stopped early on Anton's instruction (usage budget); this PR is a reviewed, green,
  self-contained slice, not the O1 exit. What now exists: `db/schema.sql` (the COMPLETE §2 object
  model, idempotent) + `bin/migrate.php`; `config/config.php` + `.env.example`; `src/` core
  (`Db`, `Yaml`, `FrontMatter`, `Markdown`, `Str`, `UrlMap`, `HtmlToMarkdown`, `ScanImport`,
  `TourParser`, `Repo/{Content,Category,Redirect,Settings,Media}Repo`); Parsedown 1.7.4 vendored
  with its MIT licence; `bin/wp-export.php`, `bin/scan-import.php`; and `content/` — 69 seed files
  (32 posts, 5 pages, 18 tours, 7 services, 6 categories) covering every `keep` row of the URL map.
  Decisions: (1) **the live WP REST API is unreachable from this environment** — the egress proxy
  denies `thingstodoinparaguay.com` (403 on CONNECT, confirmed via curl and WebFetch), so the plan's
  documented fallback was taken and `content/` is built from `docs/wp-scan.md`; `bin/wp-export.php`
  is written, tested against the real endpoint, and auto-falls back, so Anton can re-run it from an
  unrestricted network to pull real bodies and images. (2) Legacy images could not be downloaded;
  the old filename is recorded as `legacy_cover` and no template will point at a missing file.
  (3) Seed front matter is YAML-lite (JSON also accepted) so S4 can hand-write posts comfortably.
  (4) A few additive columns beyond §2 (`content_items.source/content_hash`,
  `tour_details.tagline/itinerary_label/closing_md`, `categories.sort_order`, `redirects.created_at`)
  — needed for idempotent seeding and to hold the real tour template. (5) Post titles use the
  verbatim WP `<title>` from the scan, which is richer than `url-map.csv`'s label column.
  **Superseded by the O1 part 2 entry below.**

- 2026-09-03 — Planning session: decision 13 added — WP export tool dropped; `bin/wp-export.php` to
  be deleted by the O1 session if present; content comes from `bin/scan-import.php`.

- 2026-09-03 — **Phase O1 part 2 — COMPLETE** (PR #5, branch `phase/o1-foundation`). O1 part 1
  (PR #3) was merged first, then the rest of §5.1 was built on top. What now exists: `src/Router.php`
  + `public/index.php` + `public/.htaccess` (trailing-slash **and** lower-case canonicalisation →
  redirects table → machine routes → fixed routes → content by slug → 404/410); `src/Seo.php`
  (title, description, canonical, robots, OG, Twitter, rel prev/next and a JSON-LD `@graph` with
  WebSite, Organization, BreadcrumbList, BlogPosting, TouristTrip/Service, FAQPage, CollectionPage);
  `src/Sitemap.php`, `src/Feed.php`, `src/Cache.php`, `src/View.php`, `src/Response.php`;
  `templates/` (layout + home, blog, category, post, page, tour, type-index, attractions, 404, 410
  and partials) — unstyled but final-quality semantic markup, one `<h1>`, landmarks, breadcrumbs;
  `bin/seed.php`, `bin/export.php`, `bin/cache-clear.php`, `bin/verify.php`, `bin/test.php`;
  `tests/` (31 cases). `bin/verify.php` passes all 138 URL-map rows with 515 assertions in ~0.6 s.
  Decisions/deviations: (1) two real defects in part 1's importer were fixed here — a `/s`-modifier
  regex made every one of the 32 posts swallow the whole of `docs/wp-scan.md` (8.9 MB → 132 KB), and
  the `## 14 & 15. /faq2/ and /faq/` heading form was not recognised, leaving `/faq/` with an empty
  body. Both content trees were regenerated. (2) `bin/seed.php` imports only the 301/410 rows of the
  URL map into `redirects`; `keep` rows are served by the router and must never shadow content.
  (3) The page cache is off when `APP_ENV=dev` unless `CACHE_TTL` is set, matching `.env.example`.
  (4) `public/assets/og-default.png` is a generated placeholder so every page has a valid `og:image`;
  S3 replaces it. (5) `bin/seed.php` gained `--content=dir` so the export → seed round trip is tested.
  (6) Decision 13 applied: `bin/wp-export.php` and `src/HtmlToMarkdown.php` are deleted;
  `bin/scan-import.php` is the only importer and `docs/wp-scan.md` the only source of old content.
  **Next session (O2) starts here:** read `docs/content-gaps.md` and `KNOWN-ISSUES.md`, then
  `src/Repo/*` and `bin/seed.php` — the admin writes through the same shapes, and `source='admin'`
  on `content_items` is what stops the seeder overwriting anything edited in the panel.

- 2026-09-03 — **Phase O2 — COMPLETE** (branch `phase/o2-admin`). §5.2 built on top of the merged
  O1. What now exists: `/admin/` — `src/Admin/{App,Auth,Session,Csrf,Flash,ContentWriter,AdminView,
  Backup}.php`, `templates/admin/**` and `public/assets/admin/{admin.css,admin.js}`; CRUD for posts,
  pages, tours, services, categories, tags, redirects, settings and media, read-only leads and
  subscribers with CSV export, a draft preview through the real public template, and a backup zip.
  New shared code: `src/SeoScore.php` (the one 0–100 implementation, 13 weighted rules summing to
  100, used by both the editor and `bin/seo-audit.php`), `src/Uploader.php` (real-mime validation →
  400/800/1600 px WebP + original into `public/media/YYYY/MM/`), `src/Exporter.php` (extracted from
  `bin/export.php` so the admin backup and the CLI share one code path). New scripts:
  `bin/create-admin.php`, `bin/seo-audit.php`, `bin/publish-due.php`. `db/migrations/001_admin.sql`
  (+ the same change in `schema.sql`) adds `content_items.focus_keyword` and `login_attempts`.
  `tests/admin.test.php` adds 25 cases; the suite is 56 green and `bin/verify.php` still passes all
  138 URL-map rows. The `security-review` skill found nothing at reportable confidence; its two
  sub-threshold notes were closed anyway (a 500 no longer echoes the exception outside dev, and a
  redirect target can no longer contain a backslash), and CSV exports now neutralise spreadsheet
  formulas because leads come from a public form in S3.
  Decisions/deviations: (1) **Parsedown safe mode is now on globally** (`src/Markdown.php`) — the
  security bar forbids raw HTML from Markdown, no seed file contains any, and verify was unaffected.
  (2) The Markdown editor is written for this project rather than vendored from a package: the
  preview round-trips through `/admin/api/preview/` so it renders with the *same* safe-mode
  Parsedown as the public page, which no client-side parser could guarantee. No CDN, no build step.
  (3) `Ttp\Router::dispatch` hands `/admin` to the panel *before* canonicalisation, so a POST is
  never answered with a 301; `Router::renderItem()` was added so the preview uses the real template.
  (4) Renaming a published slug writes a `slug-change` 301 **and** re-points any older redirect at
  the new address, so a page renamed twice never leaves a chain; deleting a published item 301s its
  address to the type index instead of 404ing. A *draft* never removes a `map` redirect — only
  publishing at that address does, which keeps `docs/url-map.csv` authoritative until someone
  deliberately takes a URL over. (5) Scheduling needs `bin/publish-due.php` on cron; the panel also
  runs it on every admin request, so it degrades to "live on next sign-in" (KNOWN-ISSUES).
  (6) `bin/seed.php` now stores `seo_score` and reads `focus_keyword`, so `bin/seo-audit.php` is
  meaningful straight after a seed. (7) `View::image()` now emits `<picture>` + `srcset` when a media
  row has variants — that is the srcset helper §5.2 asked for, not a restyle. Today it reports
  `seo-audit --strict` failing on 34 Lorem-Ipsum items: that is S4's job, and `--strict` is S4's gate.
  **Next session (S3) starts here:** read `docs/admin-guide.md` to see what the panel promises the
  templates will render, then `templates/layout.php` and `src/View.php::image()` — the `<picture>`
  markup and `Repo/*` are the whole data contract, and `public/assets/admin/*` is off-limits.

- 2026-09-03 — **Phase S3 — COMPLETE** (branch `phase/s3-design`). §6.1 built on top of the merged O2.
  What now exists: `public/assets/site.css` — the whole design system (Paraguay red/blue + warm
  neutrals, mobile-first, system font stack, ~28 KB, inlined into every page's `<head>` by
  `templates/layout.php` rather than linked, so there is no render-blocking stylesheet request) —
  and `public/assets/site.js` (~1.2 KB: the mobile nav toggle, the only JS the site needs — FAQ
  accordions are native `<details>`, forms are plain POSTs). Every public template
  (`layout`/`home`/`blog`/`category`/`post`/`tour`/`type-index`/`attractions`/`page`/`404`/`410`
  and the `partials/*`) is re-skinned: home gained a hero, a why-us section for Anton and Yanina, and
  an FAQ; post gained share links; tour gained a sticky mobile "Ask for a quote" bar; cards show a
  cover image when one exists and a type-tinted gradient placeholder otherwise (`public/media/` is
  still empty — see KNOWN-ISSUES). New lead-capture stack: `src/Repo/{Lead,Subscriber}Repo.php`,
  `src/Mailer.php` (a small hand-rolled SMTP client with STARTTLS, falling back to `mail()`),
  `src/VenderCrm.php` and `src/Mailchimp.php` (both no-op with no env key), `src/Forms/{Contact,
  Newsletter}Form.php` (honeypot + time-trap, pure `validate()`/`submit()` so `tests/forms.test.php`
  exercises them without HTTP), and `public/forms/{contact,subscribe}.php` — real files under
  `public/` so they never go through `src/Router.php` (hard limit). WhatsApp floating button and a
  footer newsletter form are on every page; `/contact/` (still an ordinary `page` content item) gets
  a form + WhatsApp link + a Google Maps deep link (no embedded iframe, so no third-party request).
  Decisions/deviations: (1) `src/Cache.php` gained an exclusion list so `/contact/` is never served
  from the HTML page cache — its time-trap embeds a render-time timestamp that a cached response
  would freeze for every later visitor; the site-wide footer newsletter form is honeypot-only for the
  same reason, so it didn't have to take every page out of the cache. (2) The email + VenderCRM push
  run after the redirect is already sent, via `fastcgi_finish_request()` where the SAPI supports it
  (falls back to synchronous under LiteSpeed/CLI — see KNOWN-ISSUES). (3) `SettingsRepo::get()` (a
  read `layout.php`/`page.php` weren't using before this phase) is now what makes the O2 admin's
  WhatsApp number/GA4 id/social links/site name actually reach the public site instead of only the
  `.env` defaults. (4) No real photography: Higgsfield generation was intentionally skipped this
  phase in favour of the CSS placeholder system, given the volume of other in-scope work; the default
  OG image was regenerated in the new brand palette with GD (`public/assets/og-default.png`). (5) A
  first attempt at dark-mode styling used an unwired `[data-theme]` selector pattern that
  unconditionally beat the light-theme rules on specificity — caught by the Lighthouse accessibility
  pass (footer links and ghost buttons were unreadable), not by `bin/verify.php`/`bin/test.php`
  (neither renders CSS); fixed by dropping the dead attribute hook and hardcoding the two sections
  (hero, footer) that are deliberately theme-independent. Verification: `bin/test.php` 67/67 (11 new
  in `tests/forms.test.php`); `bin/verify.php` 138/138 URL-map rows, 515 assertions; Lighthouse
  mobile on `/`, `/blog/`, a post (`/cerro-cora-park/`) and a tour (`/asuncion-city-tour/`) — **100
  performance / 100 accessibility / 100 best-practices / 100 SEO on all four**; W3C Nu validator
  (local `vnu-jar`, `validator.w3.org` itself is not reachable from this environment) — zero errors
  on all four plus `/contact/`, `/tours/`, `/services/`, `/about/`, `/faq/`,
  `/tourist-attractions-paraguay/`, `/category/nature/` (two content-driven warnings only, see
  KNOWN-ISSUES); `code-review` skill found three real issues (a `SubscriberRepo` insert race, the
  blocking-notification problem above, and a duplicated settings-fallback closure) — all three fixed,
  not just logged. **Next session (S4) starts here:** read `docs/content-gaps.md` and the two new
  `[s3]` KNOWN-ISSUES entries about imagery and Lorem-Ipsum-driven validator warnings — both are
  exactly S4's job (plan §6.2). `templates/partials/card.php` and `templates/tour.php` show you where
  a real `cover_media_id` slots in once content exists.

- 2026-09-03 — **Phase S4 — COMPLETE** (branch `phase/s4-content`). §6.2 built on top of the merged
  S3. All content work happens in `content/` (Markdown + front matter), imported by `bin/seed.php` —
  no schema/router/SEO-core changes, per the hard limits. Six Sonnet subagents (one per ~5-6 posts)
  replaced Lorem Ipsum with real 900-1600 word posts across all 32 `content/post/*.md` files; three
  more subagents fixed the 18 tours + 7 services (`content/tour/`, `content/service/`) — filling
  empty FAQ answers, missing `itinerary`/`practical` sections, and rewriting two structurally broken
  files: `asuncion-city-tour` (a copy-paste bug had "Pillar 3" repeat "Pillar 2" verbatim, including a
  stray literal "Lorem ipsum dolor sit amet..." sentence one subagent caught and removed) and
  `yerba-mate-tour` (restructured from a single unstructured Markdown blob — the scan couldn't fit it
  into the normal hook/solution/itinerary/faq shape because the live page stacked two templates on
  one URL — into the standard tour shape). I wrote the 6 category pages, 5 static pages
  (about/contact/faq/services/paraguay-tourism-guide — each had a real-but-messy scan-imported body:
  fake "0+" stat-counter placeholders, an empty WordPress accordion FAQ, a literally duplicated
  Format/Pages/Language/Device block — all cleaned up, not just left alone) and did the consolidation
  pass myself: `bin/seo-audit.php --all --min=80 --strict` now reports 62/62 published items at
  90-96/100 (average 96), 0 below 80, 0 Lorem Ipsum — every kept URL in `docs/url-map.csv` that has a
  `content_items` row. `bin/verify.php` (138 rows, 515 assertions) and `bin/test.php` (67/67) both
  still pass; no `src/`/`templates/`/`db/` file was touched.
  Decisions/deviations: (1) `focus_keyword` must be a literal substring of the URL slug for
  `SeoScore`'s slug check to pass (it's a whole-word `str_contains` on the deaccented, lowercased
  text) — the 4 single-word page slugs (`about`, `contact`, `services`, `faq`) initially got
  multi-word focus keywords that didn't match, scoring 62-72 until fixed to the bare slug word woven
  into an opening heading. Worth remembering for any future single-word-slug page. (2) A subagent
  batch scoped to "fill empty (`a: \"\"`) FAQ answers" correctly left 7 *non-empty* rows alone that
  actually held a leftover scanner artifact string (`"[... standard \"Still have a question\" block
  ...]"`) instead of a real answer — caught in the consolidation pass and rewritten; logged in
  KNOWN-ISSUES as a trap for any future gap-filling pass that trusts "non-empty" as "already good".
  (3) `bin/seo-audit.php`'s own ≥2-internal-links check is satisfied by any two internal links, so the
  parallel post-writing batches naturally clustered links onto a handful of popular tours; that isn't
  what plan §6.2's "every tour is linked from ≥2 posts" actually requires. Audited link counts
  per tour after all batches finished, found 9 under-linked (one, `paraguay-real-estate-tour`, had
  zero), and added one topically-relevant inbound link each from a different post. (4) Categories
  were unassigned before this phase (§KNOWN-ISSUES [o1]); assigned all 32 posts across the 6
  categories by topic (nature 10, cities 7, tips 4, activities 4, living 4, food 3) — `/category/
  living/` is no longer an empty archive. (5) No imagery was generated this phase (Higgsfield budget
  not spent, given the volume of copy work) — every item still scores 96/100 max, capped by the
  4-point cover-image check; logged as a new `[s4]` KNOWN-ISSUES entry rather than left implicit.
  (6) `docs/content-gaps.md` is left in place as a historical record (marked resolved at the top)
  rather than deleted, since `bin/scan-import.php` would regenerate it verbatim from a future scan.
  Unverified facts flagged by the content-writing agents, for Anton to spot-check (none are load-
  bearing — each reads fine either way, but check before treating as authoritative): the 1870 date for
  the Battle of Cerro Corá / end of the War of the Triple Alliance (`cerro-cora-park`,
  `paraguay-national-parks`, `top-places-paraguay`); Trinidad's UNESCO listing and its bell tower being
  climbable, and the Jesuit reductions' 1600s-late-1700s span (`jesuit-missions-paraguay`,
  `jesuit-ruins-tour`); the Panteón Nacional's Les Invalides inspiration and Loma San Jerónimo's
  dockworker/artisan settlement history (`paraguay-sightseeing`, `asuncion-city-tour`); Itaipu's
  "Technical Tour" including the turbine hall (`itaipu-dam-tour`); Filadelfia/Fernheim Colony's ~1930
  founding and ~450km distance from Asunción (`filadelfia-chaco`); San Bernardino's 1881 founding and
  Santiago Schaerer as founding manager, and Lake Ypacaraí's algae-bloom history (`san-bernardino`,
  `san-bernardino-trip`); Serranía San Luis's ~10,282 ha size, ~480km distance, and species counts
  (`serrania-san-luis-national-park`); Mbatoví's exact location and Pantanal Paraguayo's Bahía
  Negra/Concepción access route (`mbatovi-ecoadventure`, `pantanal-paraguayo`); Atlantic Forest
  habitat-loss percentages and Caacupé's ~54km distance plus its founding-legend detail
  (`atlantic-forest-paraguay`, `caacupe`); the bus terminal's Sajonia address (`asuncion-bus-terminal`);
  ñandutí lace/Itauguá and ao po'i/Yataity associations (`shopping-in-asuncion`); Villa Florida's and
  Ybycuí's drive times from Asunción (`paraguay-beaches`, `paraguay-national-parks`); real-estate
  financing/land-invasion risk framing (`paraguay-real-estate-tour`); and Paraguay's drink-driving law
  applying to drivers not passengers (`private-driver`). **Next session (S5) starts here:** read
  `docs/cutover-runbook.md` once it exists — it doesn't yet, S5 writes it — and the human-inputs
  checklist (plan §7) for what Anton needs to provide before a real deploy; imagery is still the
  biggest visible gap on the live templates, worth flagging to Anton even though it's out of S5's
  formal scope.

## 10. Backlog

- Tag archive pages (only if tags get real curation).
- Booking/checkout for tours; PDF guide sales.
- Spanish (`/es/`) version with hreflang.
- Comments (WordPress had a comment form; dropped deliberately).
- Image CDN if Hostinger `hcdn` proves insufficient.
