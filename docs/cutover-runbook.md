# Cutover runbook — thingstodoinparaguay.com

This is Anton's runbook, not a script — the cutover itself is his manual step (plan §6.3).
Nothing here was run against a live Hostinger account or live DNS; every step is written
from the plan and from Hostinger's documented panel behaviour. Do the staging checklist
(`docs/staging-checklist.md`) first — this runbook assumes staging is already green.

WordPress is never deleted by a build session and DNS never changes as part of this repo's
work (plan §6.3 hard limit) — this runbook is the one place those two things finally happen,
and only by Anton's hand.

## Before you start

- [ ] `docs/staging-checklist.md` fully checked, including `bin/verify.php --base=<staging>`
      passing.
- [ ] You have hPanel access to the account that currently serves the live WordPress site.
- [ ] You know which hosting account/plan (LATAM/EU/USA, per the internal infra map) holds
      `thingstodoinparaguay.com` today — the cutover happens on that same account, not a new
      one; only the document root pointer changes.
- [ ] Nobody is actively editing WordPress content right now (check with whoever last used
      it) — content edited in WP after the backup step below will not carry over.

## 1. Back up the live WordPress installation

- [ ] hPanel → **Files → Backups** (or File Manager) → zip the WordPress document root
      (usually `public_html/`) in place. Download the zip to a machine outside Hostinger too
      — a hosting-account-level backup is not a substitute for an offline copy.
- [ ] hPanel → **Databases → phpMyAdmin** → export the WordPress database (SQL dump),
      download it alongside the files zip.
- [ ] Note the exact zip/dump filenames and where they live. The 30-day removal step (§5)
      refers back to these — WordPress is kept as a zipped backup, never deleted outright.

## 2. Point the live domain at this app

- [ ] hPanel → Advanced → Git (as in `deploy/README.md`, but for the **production** checkout
      this time — either reuse the staging checkout by changing its remote's deployed branch,
      or create a second checkout at a new directory; either is fine, staging can keep running
      independently either way).
- [ ] Create `.env` in that checkout from `.env.example` (not `deploy/env.staging.example`)
      — `SITE_URL=https://thingstodoinparaguay.com`, `APP_ENV=prod`.
- [ ] Run the first-deploy steps (`deploy/README.md`): `php bin/migrate.php`,
      `php bin/create-admin.php --email=...` (a **new** password — do not reuse the staging
      one), then `sh deploy/post-deploy.sh`.
- [ ] Add the `bin/publish-due.php` cron job for this checkout's path (a separate cron entry
      from staging's, pointing at the production directory).
- [ ] hPanel → **Domains** → edit `thingstodoinparaguay.com`'s document root, change it from
      the WordPress `public_html` to `<production checkout>/public`. This is the actual
      cutover moment — do it last, after every step above is confirmed working on staging.
- [ ] Confirm HTTPS/SSL is still issued for the domain after the document root change
      (Hostinger's AutoSSL usually re-validates automatically; check hPanel → SSL if not).

DNS does not change in this step — the domain already resolves to this Hostinger account;
only what it serves changes.

## 3. Smoke test the live domain immediately

- [ ] Load `https://thingstodoinparaguay.com/` in a real browser, no cache, and check it is
      this app (not WordPress, not a 500).
- [ ] `php bin/verify.php --base=https://thingstodoinparaguay.com` — the same 138-row check,
      now against production. Every row must pass before you tell anyone the cutover is done.
- [ ] Submit a real lead through `/contact/` and confirm it appears in `/admin/` → Leads.

## 4. Search Console

- [ ] If `thingstodoinparaguay.com` is not already a verified property in Google Search
      Console, verify it (DNS TXT record or the HTML file method — either works, this does
      not require a code change).
- [ ] Search Console → **Sitemaps** → submit `https://thingstodoinparaguay.com/sitemap.xml`.
- [ ] Search Console → **Removals** (optional but recommended) → nothing to remove yet; this
      is just the moment to note the property is now watching the new site, not WordPress.
- [ ] Note today's date somewhere you'll see it in a week — step 6 below asks you to come
      back to the Coverage report.

## 5. 20-URL post-cutover spot check

Check every URL below on the **live domain**, right after cutover. Expected result is what
`docs/url-map.csv` says for that path — most should just load; a few are deliberate
redirects or a deliberate 410. If any row fails, do not consider the cutover finished —
either fix it or fall back to the rollback step below and investigate on staging instead.

| # | URL | Expect |
|---|-----|--------|
| 1 | `/` | 200, this app's home page |
| 2 | `/blog/` | 200 |
| 3 | `/tours/` | 200 |
| 4 | `/services/` | 200 |
| 5 | `/about/` | 200 |
| 6 | `/contact/` | 200, form present |
| 7 | `/faq/` | 200 |
| 8 | `/category/nature/` | 200 |
| 9 | `/cerro-cora-park/` | 200, post |
| 10 | `/paraguay-beaches/` | 200, post |
| 11 | `/is-paraguay-safe/` | 200, post |
| 12 | `/asuncion-city-tour/` | 200, tour |
| 13 | `/yerba-mate-tour/` | 200, tour |
| 14 | `/private-driver/` | 200, service |
| 15 | `/paraguay-residency-service/` | 200, service |
| 16 | `/tourist-attractions-paraguay/` | 200, hub page |
| 17 | `/home/` | 301 → `/` |
| 18 | `/about2/` | 301 → `/about/` |
| 19 | `/wp-admin/` | 410 Gone |
| 20 | `/sitemap.xml` | 200, XML, lists every row above except #17/18/19 |

For each: view source and confirm a `<title>`, one `<h1>`, a meta description and a
canonical tag pointing at the live domain (not staging) — `bin/verify.php` already checked
this mechanically in step 3, this pass is the human eyeball check on top of it.

## 6. Watch Coverage for a week

- [ ] Days 1–7: Search Console → **Pages** (formerly "Coverage") — watch for a spike in
      "Not found (404)" or "Crawled – not indexed" that would mean the URL map missed
      something `docs/url-map.csv` doesn't cover. Cross-check any surprise 404 against
      `docs/url-map.csv` — if it's genuinely missing, add a row and a redirect, don't guess.
- [ ] Days 1–7: spot-check a handful of the site's top organic-traffic pages in
      Google's cache/site: search to confirm the new content is what's indexed, not a
      cached WordPress version.
- [ ] Keep the WordPress backup zip and DB dump from step 1 untouched during this window.

## 7. Remove WordPress after 30 days

- [ ] Only after 30 days with no Coverage regressions and no missed-URL reports: delete the
      old WordPress `public_html` files and drop the WordPress database from hPanel — but
      only if the zip + SQL dump from step 1 are confirmed downloaded and readable somewhere
      outside Hostinger first. This step is optional to ever do; keeping the old backup
      indefinitely costs only disk space.

## Rollback

The cutover is one document-root pointer change (§2's last checkbox) — rolling back is the
same change in reverse, and safe at any point:

- [ ] hPanel → Domains → edit `thingstodoinparaguay.com` → set the document root back to the
      original WordPress `public_html`.
- [ ] Confirm the site is WordPress again at `https://thingstodoinparaguay.com/`.
- [ ] This app's checkout, database and admin account are untouched by a rollback — nothing
      needs to be undone on that side. Fix whatever failed against staging, re-run the
      staging checklist, and re-attempt §2 when ready.
- [ ] If the rollback happened after Search Console sitemap submission (§4), no action is
      needed there — Search Console re-crawling WordPress again after the rollback is
      harmless; it will simply see the WordPress pages again until you cut over a second time.

Nothing in this runbook changes DNS. If a rollback is needed for a reason that isn't fixed
by the document-root pointer (e.g. Hostinger account trouble), that is outside this runbook's
scope — DNS changes are a bigger, separate decision the plan deliberately keeps out of any
build session's hands.
