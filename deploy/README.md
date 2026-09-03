# Deploying to Hostinger

This app is plain PHP (no Node build, no Composer at runtime — plan §1.1), so it does
**not** use Hostinger's "Node.js Apps" panel. It deploys with Hostinger's **Git** feature
for regular (shared) hosting, which does a `git pull` into a directory you choose and can
auto-deploy on every push.

No Hostinger hPanel credentials were available in the session that wrote this — everything
below is written from the plan (`plan.md` §6.3, §1.11) and Hostinger's documented Git-deploy
behaviour, not verified against a live hPanel. Treat every step as "do this, then confirm it
did what it says" rather than as already-proven. `docs/staging-checklist.md` and
`docs/cutover-runbook.md` are the numbered walkthroughs that use these scripts; read one of
those first, come back here for what each script actually does.

## One-time hPanel setup (staging)

1. hPanel → **Domains → Subdomains** → create one, e.g. `staging.thingstodoinparaguay.com`.
   Don't point its document root anywhere yet — the Git step below creates the real target.
2. hPanel → **Advanced → Git** → **Create a repository**:
   - Repository: `https://github.com/antonmarklundcom/thingstodoinparaguay` (or an SSH deploy
     key URL if the repo is private and hPanel asks for one).
   - Branch: `main`.
   - Directory: something **outside** `public_html`, e.g. `staging-app` at the account root —
     this repo's project root, not its document root. The subdomain's document root then
     points at `<that directory>/public` (step 3), which is what keeps `data/`, `content/`,
     `src/`, `.env` etc. unreachable over HTTP without relying on `.htaccess` alone.
3. hPanel → **Subdomains** → edit `staging.thingstodoinparaguay.com` → set its document root
   to `staging-app/public` (or whatever directory name step 2 used, + `/public`).
4. hPanel → **Advanced → Git** → **Deploy** (first pull).
5. SSH in (hPanel → **Advanced → SSH Access**) or use hPanel's File Manager terminal, `cd` into
   the project root from step 2, and run the **first-deploy-only** steps below.

## First deploy only

```sh
cp deploy/env.staging.example .env
# edit .env: SITE_URL must be the real https:// staging address
php bin/migrate.php
php bin/create-admin.php --email=you@example.com     # prompts for a password; do this
                                                        # over SSH, never commit a password
```

Then run `post-deploy.sh` (below) for the rest, which is also what every later deploy runs.

## Every deploy (staging and, later, production)

Either run it by hand after each `git pull` / hPanel "Deploy" click:

```sh
sh deploy/post-deploy.sh
```

or wire it into hPanel Git's **post-deploy hook / deploy actions** field if the plan on your
account has one, so a push to `main` redeploys staging automatically. `post-deploy.sh` is
idempotent — safe to run after every deploy, including the first.

What it does, in order (see the script for the exact commands):
1. Confirms `.env` exists (refuses to run without it — no defaults are assumed on a real host).
2. `php bin/migrate.php` — applies `db/schema.sql` + `db/migrations/*.sql`, no-op if unchanged.
3. `php bin/seed.php` — imports `content/` + `docs/url-map.csv`; never overwrites anything
   edited in `/admin/` (compares `updated_at`).
4. `deploy/permissions.sh` — makes `data/`, `cache/`, `public/media/` writable by the web
   server user, and hard-fails if `data/` turns out to be inside the document root.
5. `php bin/cache-clear.php` — empties the HTML page cache so the new deploy is visible
   immediately instead of after the next `CACHE_TTL` expiry.

## Cron: scheduled publishing

`bin/publish-due.php` needs to run periodically so a post scheduled for a future time goes
live on time even if nobody is signed into `/admin/` (KNOWN-ISSUES `[o2]` — without cron it
only publishes on the next admin page load). hPanel → **Advanced → Cron Jobs**:

- Command: `php /home/<user>/staging-app/bin/publish-due.php` (absolute path — cron has no
  concept of the project's working directory).
- Every 5–15 minutes is plenty; this site does not need per-minute scheduling precision.

## LiteSpeed cache — check, don't assume

Hostinger's shared hosting runs LiteSpeed (LSWS). This app already sends its own
`Cache-Control` headers (`public/.htaccess`) and has its own HTML page cache
(`src/Cache.php`, `CACHE_TTL`) — it does not need and should not get LiteSpeed's WordPress
page-cache plugin behaviour layered on top, since there is no WordPress here to run it.
Two things worth checking once staging is live, that could not be checked from this session:
- hPanel → **Speed** (or **LiteSpeed Cache**, naming varies by plan) — confirm there is no
  account-level HTML caching rule that would serve a stale page after a publish and this
  app's own `cache-clear.php` run. If there is one and it can't be scoped to exclude `/admin/`
  and `/forms/`, turn it off for this (sub)domain and rely on this app's own cache.
- `public/.htaccess`'s `<IfModule mod_brotli.c>` / `<IfModule mod_deflate.c>` blocks are
  no-ops if the module isn't loaded — confirm compression is actually happening
  (`curl -sI -H 'Accept-Encoding: br,gzip' https://staging.../ | grep -i content-encoding`)
  rather than trusting the `.htaccess` silently.

## Rollback (staging)

Staging is a subdomain pointed at a Git checkout — rolling back is `git reset --hard` to a
known-good commit in that checkout (hPanel Git → Deploy history, or SSH) followed by
`sh deploy/post-deploy.sh` again. Nothing about staging is destructive to touch; it never
holds the live domain or DNS (plan §6.3 hard limit). Production rollback is a different,
higher-stakes operation — see `docs/cutover-runbook.md`'s own rollback section, which rolls
back the *document root pointer*, not the code.
