# thingstodoinparaguay.com

Native PHP/HTML rebuild of the WordPress site, with a first-party `/admin/` for publishing
SEO-optimised posts without code. The plan is `plan.md`; each phase is a prompt file in `prompts/`.

## How the build runs
1. Merge the plan PR so `main` contains `plan.md`.
2. Open a fresh **Opus** session with auto-accept permissions and paste:
   `Read prompts/opus-1-foundation.md in this repo and execute it.`
3. Each phase merges its own PR and spawns the next phase in a new session (plan §4.9).
4. If a session dies, re-paste that phase's prompt; it resumes from the first unmet exit criterion.
   Current phase = last entry in `plan.md` §9.

## Local dev (after phase O1)
```
cp .env.example .env
php bin/migrate.php && php bin/seed.php
php -S localhost:8080 -t public
php bin/verify.php
```

## Docs
- `docs/wp-scan-brief.md` — facts about the old site
- `docs/url-map.csv` — old URL → new URL contract (tested by `bin/verify.php`)
- `KNOWN-ISSUES.md` — running list of minor issues
