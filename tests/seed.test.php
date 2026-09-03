<?php
declare(strict_types=1);

/**
 * Integration cover for the migrate → seed → export pipeline. These shell out to
 * the real scripts against a throwaway database, because the thing worth testing
 * is that running them twice on a live install is safe.
 */

use Ttp\Db;

/** @return array{0:string,1:int} */
function ttp_run_script(string $script, array $args = []): array
{
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(ttp_root() . '/bin/' . $script);
    foreach ($args as $arg) {
        $cmd .= ' ' . escapeshellarg($arg);
    }
    exec($cmd . ' 2>&1', $out, $code);
    return [implode("\n", $out), $code];
}

function ttp_temp_db(): string
{
    $dir = sys_get_temp_dir() . '/ttp-seed-test-' . bin2hex(random_bytes(4));
    mkdir($dir, 0775, true);
    return $dir . '/site.sqlite';
}

function ttp_counts(PDO $pdo): array
{
    $out = [];
    foreach (['content_items', 'categories', 'tags', 'item_tags', 'tour_details', 'redirects', 'settings'] as $table) {
        $out[$table] = (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }
    return $out;
}

test('migrate is idempotent and creates the whole §2 object model', function (): void {
    $db = ttp_temp_db();
    [$out, $code] = ttp_run_script('migrate.php', ['--db=' . $db, '--quiet']);
    assert_same(0, $code, $out);

    $pdo = new PDO('sqlite:' . $db, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ([
        'content_items', 'tour_details', 'categories', 'tags', 'item_tags', 'media',
        'redirects', 'leads', 'subscribers', 'users', 'settings', 'schema_migrations',
    ] as $table) {
        assert_true(in_array($table, $tables, true), "missing table {$table}");
    }

    [$out, $code] = ttp_run_script('migrate.php', ['--db=' . $db, '--quiet']);
    assert_same(0, $code, $out);

    unlink($db);
    @rmdir(dirname($db));
});

test('seeding twice changes nothing the second time', function (): void {
    $db = ttp_temp_db();
    ttp_run_script('migrate.php', ['--db=' . $db, '--quiet']);

    [$out, $code] = ttp_run_script('seed.php', ['--db=' . $db, '--quiet']);
    assert_same(0, $code, $out);
    $pdo    = new PDO('sqlite:' . $db, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $first  = ttp_counts($pdo);
    assert_true($first['content_items'] > 50, 'expected the seed content to import');
    assert_true($first['redirects'] > 50, 'expected the URL map to become redirects');

    [$out, $code] = ttp_run_script('seed.php', ['--db=' . $db, '--quiet']);
    assert_same(0, $code, $out);
    assert_equals($first, ttp_counts($pdo), 'a second seed must not duplicate anything');

    // --force rewrites the same rows rather than adding new ones.
    [$out, $code] = ttp_run_script('seed.php', ['--db=' . $db, '--quiet', '--force']);
    assert_same(0, $code, $out);
    assert_equals($first, ttp_counts($pdo), '--force must still be idempotent');

    unlink($db);
    @rmdir(dirname($db));
});

test('seeding never overwrites an item edited in the admin', function (): void {
    $db = ttp_temp_db();
    ttp_run_script('migrate.php', ['--db=' . $db, '--quiet']);
    ttp_run_script('seed.php', ['--db=' . $db, '--quiet']);

    $pdo = new PDO('sqlite:' . $db, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("UPDATE content_items SET title = 'Edited in the admin', source = 'admin' WHERE slug = 'about'");

    [$out, $code] = ttp_run_script('seed.php', ['--db=' . $db, '--quiet', '--force']);
    assert_same(0, $code, $out);

    $title = $pdo->query("SELECT title FROM content_items WHERE slug = 'about'")->fetchColumn();
    assert_same('Edited in the admin', $title, 'the admin must win over the seed file');

    unlink($db);
    @rmdir(dirname($db));
});

test('export writes content/ back and seeding that output round-trips', function (): void {
    $db  = ttp_temp_db();
    $dir = dirname($db);
    ttp_run_script('migrate.php', ['--db=' . $db, '--quiet']);
    ttp_run_script('seed.php', ['--db=' . $db, '--quiet']);

    [$out, $code] = ttp_run_script('export.php', ['--db=' . $db, '--out=' . $dir . '/content', '--quiet']);
    assert_same(0, $code, $out);
    assert_true(is_file($dir . '/content/page/about.md'), 'export should write page files');

    $db2 = $dir . '/round-trip.sqlite';
    ttp_run_script('migrate.php', ['--db=' . $db2, '--quiet']);
    [$out, $code] = ttp_run_script('seed.php', ['--db=' . $db2, '--quiet', '--content=' . $dir . '/content']);
    assert_same(0, $code, $out);

    $query = 'SELECT slug, type, title, status, excerpt, body_md, word_count FROM content_items ORDER BY slug';
    $a = (new PDO('sqlite:' . $db))->query($query)->fetchAll(PDO::FETCH_ASSOC);
    $b = (new PDO('sqlite:' . $db2))->query($query)->fetchAll(PDO::FETCH_ASSOC);
    assert_equals($a, $b, 'export → seed must round-trip every item');

    foreach ((array) glob($dir . '/content/*/*.md') as $file) {
        if (is_string($file)) {
            unlink($file);
        }
    }
    foreach ((array) glob($dir . '/content/*') as $sub) {
        if (is_string($sub)) {
            @rmdir($sub);
        }
    }
    @rmdir($dir . '/content');
    @unlink($db);
    @unlink($db2);
    @rmdir($dir);
});

test('the seeded database matches what the URL map promises', function (): void {
    $db = ttp_temp_db();
    ttp_run_script('migrate.php', ['--db=' . $db, '--quiet']);
    ttp_run_script('seed.php', ['--db=' . $db, '--quiet']);
    Db::use($db);

    foreach (\Ttp\UrlMap::rows() as $row) {
        if ($row['action'] !== 'keep' || !in_array($row['new_type'], ['post', 'page', 'tour', 'service'], true)) {
            continue;
        }
        $slug = trim($row['old_path'], '/');
        if ($slug === '') {
            continue;   // the home page is a route, not a content file
        }
        $item = Db::one('SELECT type, status FROM content_items WHERE slug = ?', [$slug]);
        assert_true($item !== null, "no content row for {$row['old_path']}");
        assert_same($row['new_type'], (string) $item['type'], "wrong type for {$row['old_path']}");
        assert_same('published', (string) $item['status'], "{$row['old_path']} is not published");
    }

    Db::use(ttp_config()['db_path']);
    unlink($db);
    @rmdir(dirname($db));
});
