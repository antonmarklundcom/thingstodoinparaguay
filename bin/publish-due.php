<?php
declare(strict_types=1);

/**
 * Publishes every scheduled item whose time has come, and clears the affected
 * pages from the HTML cache.
 *
 * Put it on cron so scheduling works without anyone opening the panel — every
 * quarter of an hour is plenty. docs/admin-guide.md has the exact crontab line.
 *
 * The admin panel calls the same code on every page load, so a host without cron
 * still publishes scheduled items — just not until someone signs in.
 *
 * Usage: php bin/publish-due.php [--db=path] [--quiet]
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

use Ttp\Admin\ContentWriter;
use Ttp\Db;

$opts  = getopt('', ['db::', 'quiet']);
$quiet = isset($opts['quiet']);
if (!empty($opts['db'])) {
    Db::use((string) $opts['db']);
}

if (!Db::exists() || !Db::hasTable('content_items')) {
    fwrite(STDERR, "publish-due: no database at " . Db::path() . " — run bin/migrate.php first\n");
    exit(1);
}

$published = ContentWriter::publishDue();

if (!$quiet || $published !== []) {
    printf("publish-due: %d item(s) went live\n", count($published));
    foreach ($published as $path) {
        echo '  + ' . $path . "\n";
    }
}
exit(0);
