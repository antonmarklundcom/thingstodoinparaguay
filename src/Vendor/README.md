# Vendored libraries

No Composer at runtime (plan §1.1). Single-file libraries are committed here with their licence.

| File | Version | Source | Licence |
|---|---|---|---|
| `Parsedown.php` | 1.7.4 | https://github.com/erusev/parsedown | MIT — `Parsedown.LICENSE.txt` |

Parsedown renders Markdown bodies authored by the site owner (seed files and the `/admin/`
editor). Inline HTML is intentionally allowed because the only author is the trusted admin;
if untrusted authors are ever added, call `setSafeMode(true)` in `src/Markdown.php`.
