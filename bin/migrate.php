<?php
declare(strict_types=1);

/**
 * Applies db/schema.sql, then every db/migrations/*.sql not yet recorded.
 * Idempotent: re-running is a no-op unless a file's checksum changed.
 *
 * Usage: php bin/migrate.php [--db=path] [--quiet]
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

use Ttp\Db;

$opts  = getopt('', ['db::', 'quiet']);
$quiet = isset($opts['quiet']);
if (!empty($opts['db'])) {
    Db::use((string) $opts['db']);
}

$say = static function (string $m) use ($quiet): void {
    if (!$quiet) {
        echo $m, "\n";
    }
};

$root = ttp_root();
$pdo  = Db::conn();

$files = [$root . '/db/schema.sql'];
$migrations = glob($root . '/db/migrations/*.sql') ?: [];
sort($migrations);
$files = array_merge($files, $migrations);

$applied = 0;
foreach ($files as $file) {
    $name = basename($file) === 'schema.sql' ? 'schema.sql' : 'migrations/' . basename($file);
    $sql  = (string) file_get_contents($file);
    $sum  = hash('sha256', $sql);

    $known = null;
    if (Db::hasTable('schema_migrations')) {
        $known = Db::value('SELECT checksum FROM schema_migrations WHERE name = ?', [$name]);
    }
    if ($known === $sum) {
        $say("  = {$name} (up to date)");
        continue;
    }

    $pdo->exec($sql);
    Db::run(
        'INSERT INTO schema_migrations (name, checksum, applied_at) VALUES (?, ?, ?)
         ON CONFLICT(name) DO UPDATE SET checksum = excluded.checksum, applied_at = excluded.applied_at',
        [$name, $sum, gmdate('c')]
    );
    $applied++;
    $say("  + {$name}");
}

$say(sprintf('migrate: %d file(s) applied, database at %s', $applied, Db::path()));
