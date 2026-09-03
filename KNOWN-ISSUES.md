# Known issues

Build sessions append here (plan §4.3). Format: `- [phase] short description — impact — suggested fix`.

- [plan] `docs/url-map.csv` sub-sitemap filenames other than `wp-sitemap-posts-post-1.xml` and
  `wp-sitemap-posts-metform-form-1.xml` are inferred standard WP names — harmless, all 301 to /sitemap.xml.
- [plan] Old site has a client-side script that auto-navigates to a random URL 1–3 s after load
  (`docs/wp-scan-brief.md` §5). Irrelevant to the rebuild but explains odd analytics; do not port any JS.
