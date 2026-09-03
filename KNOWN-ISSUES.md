# Known issues

Build sessions append here (plan §4.3). Format: `- [phase] short description — impact — suggested fix`.

- [plan] `docs/url-map.csv` sub-sitemap filenames other than `wp-sitemap-posts-post-1.xml` and
  `wp-sitemap-posts-metform-form-1.xml` are inferred standard WP names — harmless, all 301 to /sitemap.xml.
- [plan] Old site has a client-side script that auto-navigates to a random URL 1–3 s after load
  (`docs/wp-scan-brief.md` §5). Irrelevant to the rebuild but explains odd analytics; do not port any JS.
- [o1] Live WordPress REST API is unreachable from the build environment (egress proxy denies
  `thingstodoinparaguay.com`, 403 on CONNECT) — content was built from `docs/wp-scan.md` instead.
  Impact: post bodies are the site's Lorem Ipsum (S4 replaces them anyway) and no legacy images were
  downloaded. Fix: run `php bin/wp-export.php --force` from an unrestricted network before S4.
- [o1] `/yerba-mate-tour/` has no structured tour fields — the scan's `**Headings**:` entry for it is
  prose, not a heading list, because the live page stacks two service templates on one URL. Its copy
  is preserved whole in the Markdown body. Fix: S4 rewrites this page (already in its scope).
- [o1] Six tour pages (`asuncion-city-tour`, `bars-asuncion-tour`, `food-paraguay-tour`,
  `restaurants-asuncion-guide`, `shopping-asuncion`, `paraguay-souvenirs`) produced no itinerary rows
  because their scan sections use a different section layout. Hook/solution/FAQ/closing were captured.
  Fix: S4 fills the itinerary, or re-run `bin/wp-export.php` with API access.
- [o1] Every FAQ has 6–8 empty answers — collapsed accordions the scan never expanded. Listed per
  page in `docs/content-gaps.md`. Fix: S4 writes the answers (already in its scope).
- [o1] 18 of the 32 posts have no category (the live site filed them under "Uncategorized", which
  `docs/url-map.csv` 301s to `/blog/`), and the `living` category has no posts at all. Impact: those
  posts show no category badge and `/category/living/` renders an empty archive with a link back to
  the blog. Fix: S4 assigns a real category per post when it writes the copy.
- [o1] No real images exist yet — `public/media/` is empty because the WP REST API was unreachable,
  so no item has a `cover_media_id` and `<img>` markup is only exercised by the (empty) media path.
  `og:image` falls back to the generated placeholder `public/assets/og-default.png`. Fix: S3 supplies
  the imagery and a real default OG image; `bin/wp-export.php --force` from an unrestricted network
  would recover the originals first.
- [o1] `bin/verify.php` asserts the sitemap equals the kept URLs minus any that return
  `robots: noindex`. If a later phase adds a route that 200s but is deliberately absent from the
  sitemap without being noindexed, verify will flag it — that is the intended behaviour, not a bug.
