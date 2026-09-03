<?php
declare(strict_types=1);

/**
 * SQLite -> content/. Thin CLI wrapper around src/Exporter.php, which the admin's
 * "Download backup" button also uses.
 *
 * Usage: php bin/export.php [--db=path] [--out=dir] [--quiet]
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

use Ttp\Db;
use Ttp\Exporter;

$opts  = getopt('', ['db::', 'out::', 'quiet']);
$quiet = isset($opts['quiet']);
if (!empty($opts['db'])) {
    Db::use((string) $opts['db']);
}
$out = !empty($opts['out']) ? rtrim((string) $opts['out'], '/') : ttp_root() . '/content';

if (!Db::exists() || !Db::hasTable('content_items')) {
    fwrite(STDERR, "export: no database at " . Db::path() . " — run bin/migrate.php first\n");
    exit(1);
}

$log = $quiet ? null : static function (string $line): void {
    echo $line, "\n";
};

try {
    $result = Exporter::run($out, $log);
} catch (Throwable $e) {
    fwrite(STDERR, 'export: ' . $e->getMessage() . "\n");
    exit(1);
}

if (!$quiet) {
    printf(
        "export: %d file(s) written to %s (%d items, from %s)\n",
        $result['files'],
        $out,
        $result['items'],
        Db::path()
    );
}
exit(0);
