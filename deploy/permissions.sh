#!/bin/sh
# Makes the directories PHP writes to writable by the web server user, and
# confirms `data/` (the SQLite file's home) is not reachable over HTTP — it
# must live outside the document root, which is `public/` (plan §1.1/§1.11).
#
# Usage: sh deploy/permissions.sh

set -eu
cd "$(dirname "$0")/.."

mkdir -p data cache public/media
chmod 775 data cache public/media
find public/media -type d -exec chmod 775 {} \;
find public/media -type f -exec chmod 664 {} \; 2>/dev/null || true

# Belt-and-braces: public/.htaccess already denies *.sqlite/.env/*.md, but data/
# should not even be inside the document root to begin with. Fail loudly if it is.
doc_root_dir="$(cd public && pwd)"
data_dir="$(cd data && pwd)"
case "$data_dir" in
    "$doc_root_dir"*)
        echo "permissions: data/ ($data_dir) is INSIDE the document root ($doc_root_dir)." >&2
        echo "             Move DB_PATH outside public/ before going further — the SQLite" >&2
        echo "             file would otherwise be downloadable if .htaccess is ever bypassed." >&2
        exit 1
        ;;
esac

echo "permissions: data/, cache/, public/media/ writable; data/ confirmed outside the document root"
