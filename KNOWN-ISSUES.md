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
