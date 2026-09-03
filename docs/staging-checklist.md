# Staging checklist

Puts the site on a Hostinger subdomain — never the live domain, never touching DNS or the
WordPress installation (plan §6.3 hard limits). Do this before the cutover runbook. Scripts
referenced here live in `deploy/`; `deploy/README.md` explains each one in full.

- [ ] **Subdomain created** — hPanel → Domains → Subdomains, e.g.
      `staging.thingstodoinparaguay.com`.
- [ ] **Git repository connected** — hPanel → Advanced → Git, `main` branch, checked out
      to a directory *outside* `public_html` (e.g. `staging-app`). Deploy at least once.
- [ ] **Document root set** to `<checkout dir>/public` (the front controller, not the repo
      root — the repo root must never be web-reachable).
- [ ] **`.env` created** from `deploy/env.staging.example`, `SITE_URL` set to the real
      `https://staging....` address, `APP_ENV=prod`.
- [ ] **PHP version** — hPanel → Advanced → PHP Configuration → 8.2 or newer for this
      (sub)domain. Confirm `pdo_sqlite`, `gd`, `mbstring`, `curl` are enabled (they usually
      are by default on Hostinger's PHP profiles; the panel lists enabled extensions).
- [ ] **First deploy steps run over SSH** (`deploy/README.md` "First deploy only"):
      `php bin/migrate.php`, `php bin/create-admin.php --email=...`.
- [ ] **`sh deploy/post-deploy.sh` run** — seeds content, fixes permissions, clears cache.
- [ ] **`data/` confirmed unreachable over HTTP** — `curl -I https://staging.../../data/site.sqlite`
      style probes should 403/404. `deploy/permissions.sh` already hard-fails if `data/`
      resolves inside the document root, but confirm from outside the server too, since a
      misconfigured document root wouldn't be caught by a script running inside it.
- [ ] **Cron job added** for `php bin/publish-due.php` (hPanel → Advanced → Cron Jobs;
      absolute path, see `deploy/README.md`).
- [ ] **`php bin/verify.php --base=https://staging.thingstodoinparaguay.com` passes.** Run
      it from a machine that can reach the staging URL — this is the same 138-row,
      515-assertion check CI runs locally, pointed at the real deploy instead of a booted
      `php -S`. Fix anything Hostinger-specific it turns up (`.htaccess` rewrite behaviour
      differing from local Apache, a missing PHP extension, file permissions) before
      moving on — this is the gate plan §6.3 sets before writing the runbook's "go" step.
- [ ] **LiteSpeed cache checked**, not assumed — see `deploy/README.md`'s LiteSpeed section.
- [ ] **Manual smoke test** in a real browser: home, one post, one tour, `/blog/`,
      `/contact/` (submit the form — confirm a row lands in `/admin/` → Leads), `/admin/`
      login, `/sitemap.xml`, `/feed.xml`, `/robots.txt`.
- [ ] **Lighthouse mobile** on `/`, one post, one tour, `/blog/` — S3's bar was ≥95 on all
      four categories (it hit 100/100/100/100 locally); confirm the real host doesn't
      regress it materially. Some drop from real network latency vs. localhost is expected
      and fine — a large drop (missing compression, no HTTP/2, cold PHP opcache) is not.

Once every box is checked, staging is ready for `docs/cutover-runbook.md`.
