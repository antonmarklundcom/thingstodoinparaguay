# Known issues

Build sessions append here (plan §4.3). Format: `- [phase] short description — impact — suggested fix`.

- [plan] `docs/url-map.csv` sub-sitemap filenames other than `wp-sitemap-posts-post-1.xml` and
  `wp-sitemap-posts-metform-form-1.xml` are inferred standard WP names — harmless, all 301 to /sitemap.xml.
- [plan] Old site has a client-side script that auto-navigates to a random URL 1–3 s after load
  (`docs/wp-scan-brief.md` §5). Irrelevant to the rebuild but explains odd analytics; do not port any JS.
- [o1] `docs/wp-scan.md` is the only source of old content (plan §1.13) — there is no WordPress
  export tooling and none is wanted. Impact: post bodies are the live site's Lorem Ipsum and no
  legacy images exist. Fix: S4 writes the copy, S3/S4 supply the imagery; neither depends on the
  old site.
- [o1] `/yerba-mate-tour/` has no structured tour fields — the scan's `**Headings**:` entry for it is
  prose, not a heading list, because the live page stacks two service templates on one URL. Its copy
  is preserved whole in the Markdown body. Fix: S4 rewrites this page (already in its scope).
- [o1] Six tour pages (`asuncion-city-tour`, `bars-asuncion-tour`, `food-paraguay-tour`,
  `restaurants-asuncion-guide`, `shopping-asuncion`, `paraguay-souvenirs`) produced no itinerary rows
  because their scan sections use a different section layout. Hook/solution/FAQ/closing were captured.
  Fix: S4 fills the itinerary from scratch.
- [o1] Every FAQ has 6–8 empty answers — collapsed accordions the scan never expanded. Listed per
  page in `docs/content-gaps.md`. Fix: S4 writes the answers (already in its scope).
- [o1] 18 of the 32 posts have no category (the live site filed them under "Uncategorized", which
  `docs/url-map.csv` 301s to `/blog/`), and the `living` category has no posts at all. Impact: those
  posts show no category badge and `/category/living/` renders an empty archive with a link back to
  the blog. Fix: S4 assigns a real category per post when it writes the copy.
- [o1] No real images exist yet — `public/media/` is empty because the WP REST API was unreachable,
  so no item has a `cover_media_id` and `<img>` markup is only exercised by the (empty) media path.
  `og:image` falls back to the generated placeholder `public/assets/og-default.png`. Fix: S3 supplies
  the imagery (Higgsfield or placeholders, plan §6.2) and a real default OG image.
- [o1] `bin/verify.php` asserts the sitemap equals the kept URLs minus any that return
  `robots: noindex`. If a later phase adds a route that 200s but is deliberately absent from the
  sitemap without being noindexed, verify will flag it — that is the intended behaviour, not a bug.
- [o2] Scheduled items go live via `bin/publish-due.php`, which needs a cron entry (documented in
  `docs/admin-guide.md`). Without cron they publish on the next admin page load instead. Impact: a
  scheduled post can be late on a host with no cron and nobody signed in. Fix: add the cron entry
  during the S5 deploy; the runbook should list it.
- [o2] The admin's "Download backup" zips `content/` and a media manifest, not the image files
  themselves — a full media library would exhaust PHP's memory limit on shared hosting. Impact:
  restoring needs `public/media/` copied separately (the zip's README says so). Fix: none needed
  unless the library outgrows an FTP copy.
- [o2] `bin/migrate.php` splits schema files on `;` after stripping `--` comments. That is fine for
  the schema we have, but a future migration containing a trigger, a `BEGIN … END` block or a
  semicolon inside a string literal would be split wrongly. Fix: if such a migration is ever needed,
  give the runner a real statement splitter first. Documented in `db/README.md`.
- [o2] The panel shows and stores every timestamp in UTC, including the scheduled publish time, and
  labels the fields "(UTC)". Asunción is UTC−3/−4. Impact: whoever schedules a post has to do the
  arithmetic. Fix: a display timezone setting, if it turns out to matter.
- [o2] `login_attempts` rows are pruned only after a successful sign-in (`Auth::pruneAttempts()`).
  A site nobody signs into keeps its failure rows for as long as the guessing lasts. Impact: table
  growth only, a few kB. Fix: prune from `bin/publish-due.php` if it ever matters.
- [s3] No real imagery was generated (Higgsfield budget) — every card/cover is the CSS placeholder
  panel from `templates/partials/card.php` / `templates/tour.php` (a type-tinted gradient + icon),
  and `public/assets/og-default.png` is a generated brand-coloured default. `public/media/` is still
  empty, matching the o1 known issue above. Fix: S4's content pass is the natural place to generate
  and upload real covers through the O2 admin's media pipeline (`src/Uploader.php` already resizes
  to 400/800/1600 + WebP) — the placeholder system degrades cleanly either way.
- [s3] `public/forms/contact.php` and `public/forms/subscribe.php` are real files under `public/`,
  deliberately outside `src/Router.php` (plan §4.7 forbids touching it). `src/Cache.php` excludes
  `/contact/` from the HTML page cache because the form's time-trap embeds a render-time timestamp
  that a cached page would freeze; the footer newsletter form (present on every page) is honeypot-only
  for the same reason — a site-wide time-trap would force every page out of the cache. Fix: none
  needed unless a future form needs the timing check on a page that must stay cached.
- [s3] Email delivery + the VenderCRM push run after the redirect is sent via `fastcgi_finish_request()`
  when the host is PHP-FPM; Hostinger's LiteSpeed (LSAPI) does not implement that function, so on
  LiteSpeed both calls (up to ~10s each) still block the visitor's redirect. Impact: a slow/unreachable
  SMTP host or CRM endpoint makes the contact form feel hung, though the lead is already safely in
  SQLite by then. Fix: if this proves to matter on the live host, move notification to a queued
  `bin/*.php` cron job instead of doing it inline.
- [s3] `bin/verify.php`'s per-URL checks (one `<h1>`, title, description, canonical, JSON-LD) still
  pass with the seed content's Lorem-Ipsum/placeholder copy in the body; the W3C Nu validator flags
  two *warnings* (not errors) on pages built from that copy — a "written in Lorem ipsum" language hint
  and one `<section>` with no heading (a tour's leftover raw body copy, `asuncion-city-tour`). Neither
  blocks this phase's "HTML validates (no errors)" exit criterion. Fix: resolved by S4 writing real
  content (already its job, plan §6.2).
- [s3] The design system has no dark-mode toggle control — only `prefers-color-scheme`. Do not
  reintroduce a `[data-theme]` attribute-based override in `site.css` without wiring something that
  actually sets the attribute: an earlier draft of this phase added `:root:not([data-theme="light"])`
  overrides with no code anywhere setting `data-theme`, which made the selector's specificity win
  unconditionally and silently broke light-mode contrast (footer links, ghost buttons) — caught by
  the Lighthouse accessibility pass, not by `bin/verify.php` or `bin/test.php`, since neither renders
  CSS. Fix: none needed now; a note for whoever adds a theme toggle later.
- [s4] **No real imagery still.** All 62 published items still render the CSS placeholder cover
  (`templates/partials/card.php` / `templates/tour.php`) and every SEO score is capped at 96/100 by
  the "cover image set" check (4 pts) — that ceiling is expected and doesn't block the phase's
  `--min=80` gate. `public/media/` is still empty; S4 was content-only by design given the volume of
  copy work (32 posts + 25 tour/service files + 5 pages), so imagery generation through the O2 admin
  media pipeline (`src/Uploader.php`, already resizes to 400/800/1600 + WebP) is still open. Fix:
  a dedicated imagery pass — S5 or a follow-up phase — using the `higgsfield-web-imagery` skill.
- [s4] Several scan-imported tour/service FAQ rows held a literal, non-empty scanner artifact string
  (`"[... standard \"Still have a question\" block ...]"`) instead of real or empty content, so an
  earlier gap-filling pass scoped to `a: ""` entries only skipped them — they read as filled but
  weren't. All found instances were rewritten with real answers in this phase. Fix: none needed now;
  a note in case `bin/scan-import.php` produces the same artifact again from a future scan — grep
  content/ for the literal string before trusting an "empty answers only" gap list.
- [s4] `content_items.word_count` (used by `bin/seed.php`/admin, computed by `Markdown::wordCount()`
  over the raw front-matter text) and `SeoScore`'s own word count (computed from the rendered
  document) can differ by a small margin — both stayed comfortably inside the 900-1600 word band for
  every post in this phase, but don't assume they're interchangeable when writing tooling against them.
- [s5] **Nothing in `deploy/` or the two `docs/*.md` runbooks has been run against a real Hostinger
  account** — no hPanel/SSH/FTP credentials were available in the session that wrote them (plan §6.3's
  documented fallback). The shell scripts are `sh -n` syntax-checked and were exercised against this
  local checkout (`deploy/permissions.sh` genuinely creates/chmods `data/`, `cache/`, `public/media/`
  and confirms `data/` resolves outside `public/` here), but the Hostinger-specific unknowns —
  `.htaccess` rewrite behaviour on their exact Apache/LiteSpeed build, which PHP extensions are enabled
  by default on a given PHP profile, whether an account-level LiteSpeed cache layer sits in front of
  this app's own cache — are exactly what `docs/staging-checklist.md`'s `bin/verify.php --base=`
  step and its Lighthouse/LiteSpeed checkboxes exist to catch for the first time. Fix: none needed now;
  whoever runs the staging checklist should expect to hit at least one of these and fix it there, not
  treat a clean local dry run as proof staging will be clean too.
