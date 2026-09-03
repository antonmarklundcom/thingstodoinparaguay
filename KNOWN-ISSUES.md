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
