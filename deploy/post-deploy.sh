#!/bin/sh
# Runs after every `git pull` on the server (hPanel Git deploy's "Deploy" button,
# or its post-deploy hook field — see deploy/README.md). Idempotent: safe to run
# after every deploy, staging or production, including the very first one.
#
# Does NOT create the admin account — that needs a real password and is a
# one-time manual step (deploy/README.md "First deploy only").
#
# Usage: sh deploy/post-deploy.sh
# Requires: PHP 8.2+ on PATH with pdo_sqlite, gd, mbstring, curl (same as local dev).

set -eu
cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
    echo "post-deploy: no .env found — copy deploy/env.staging.example (or .env.example" >&2
    echo "             for production) to .env and fill it in before running this script." >&2
    exit 1
fi

echo "post-deploy: applying schema/migrations"
php bin/migrate.php --quiet

echo "post-deploy: importing content/ + docs/url-map.csv"
php bin/seed.php

echo "post-deploy: fixing permissions on writable directories"
sh "$(dirname "$0")/permissions.sh"

echo "post-deploy: clearing the HTML page cache"
php bin/cache-clear.php

echo "post-deploy: done. Run 'php bin/create-admin.php --list' to check the admin"
echo "             account exists, and 'php bin/seo-audit.php --strict' as a sanity check."
