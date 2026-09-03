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

/**
 * Runs a schema file statement by statement.
 *
 * SQLite has no `ADD COLUMN IF NOT EXISTS`, and db/README.md asks every O-phase to
 * put the same change in schema.sql *and* in a numbered migration — so on a fresh
 * install the migration's ALTER TABLE would hit a column schema.sql just created.
 * Such a statement is skipped instead of aborting the run. Everything else is
 * executed as written, so a genuine error still fails the migration.
 *
 * The splitter is deliberately simple: `--` comments are stripped and statements
 * end at `;`. Our schema has no triggers, no `BEGIN … END` blocks and no semicolons
 * inside string literals; anything of that shape needs a different runner.
 */
function apply_sql(PDO $pdo, string $sql): void
{
    $sql = (string) preg_replace('/^\s*--[^\n]*$/m', '', $sql);
    foreach (explode(';', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement === '') {
            continue;
        }
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            $duplicate = str_contains($e->getMessage(), 'duplicate column name');
            if ($duplicate && preg_match('/^\s*ALTER\s+TABLE\b/i', $statement) === 1) {
                continue;
            }
            throw $e;
        }
    }
}

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

    apply_sql($pdo, $sql);
    Db::run(
        'INSERT INTO schema_migrations (name, checksum, applied_at) VALUES (?, ?, ?)
         ON CONFLICT(name) DO UPDATE SET checksum = excluded.checksum, applied_at = excluded.applied_at',
        [$name, $sum, gmdate('c')]
    );
    $applied++;
    $say("  + {$name}");
}

$say(sprintf('migrate: %d file(s) applied, database at %s', $applied, Db::path()));
