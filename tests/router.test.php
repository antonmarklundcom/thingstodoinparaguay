<?php
declare(strict_types=1);

use Ttp\Cache;
use Ttp\Router;
use Ttp\UrlMap;
use Ttp\View;

test('paths are normalised before anything else looks at them', function (): void {
    assert_same('/about/', Router::normalise('/about/?utm_source=x'));
    assert_same('/about/', Router::normalise('//about//'));
    assert_same('/', Router::normalise(''));
    assert_same('/tag/day-trip/', Router::normalise('/tag/day-trip/'));
});

test('trailing slashes are added to paths but not to filenames', function (): void {
    assert_same('/about/', Router::canonicalPath('/about'));
    assert_same('/about/', Router::canonicalPath('/about/'));
    assert_same('/', Router::canonicalPath('/'));
    assert_same('/robots.txt', Router::canonicalPath('/robots.txt'));
    assert_same('/wp-login.php', Router::canonicalPath('/wp-login.php'));
    assert_same('/wp-sitemap.xml', Router::canonicalPath('/wp-sitemap.xml'));
    assert_same('/about/', Router::canonicalPath('/About'), 'paths are lower-cased');
    assert_same('/wp-login.php', Router::canonicalPath('/WP-Login.php'));
});

test('the URL map has no duplicate rows and no 301 without a target', function (): void {
    $seen = [];
    foreach (UrlMap::rows() as $row) {
        assert_true(!isset($seen[$row['old_path']]), 'duplicate row ' . $row['old_path']);
        $seen[$row['old_path']] = true;
        assert_true(
            in_array($row['action'], ['keep', '301', '410'], true),
            "unknown action '{$row['action']}' for {$row['old_path']}"
        );
        if ($row['action'] === '301') {
            assert_true($row['target'] !== '', '301 without a target: ' . $row['old_path']);
            assert_true(str_starts_with($row['target'], '/'), 'target must be a path: ' . $row['target']);
        }
    }
    assert_true(count($seen) > 100, 'the map should still cover the whole live site');
});

test('every 301 target is itself a kept URL', function (): void {
    $keep = [];
    foreach (UrlMap::rows() as $row) {
        if ($row['action'] === 'keep') {
            $keep[$row['old_path']] = true;
        }
    }
    foreach (UrlMap::rows() as $row) {
        if ($row['action'] === '301') {
            assert_true(
                isset($keep[$row['target']]),
                "{$row['old_path']} redirects to {$row['target']}, which is not a kept URL"
            );
        }
    }
});

test('the page cache stores, reads back, forgets and flushes', function (): void {
    $dir = sys_get_temp_dir() . '/ttp-cache-test-' . bin2hex(random_bytes(4));
    Cache::use($dir, 3600);
    try {
        assert_same(null, Cache::get('/about/'), 'a cold cache must miss');

        Cache::put('/about/', '<p>about</p>');
        assert_same('<p>about</p>', Cache::get('/about/'));

        Cache::put('/blog/', '<p>blog</p>');
        assert_true(Cache::forget('/about/'));
        assert_same(null, Cache::get('/about/'));
        assert_same('<p>blog</p>', Cache::get('/blog/'), 'forgetting one path must not drop the others');

        assert_same(1, Cache::flush());
        assert_same(null, Cache::get('/blog/'));

        // An entry older than the TTL is treated as a miss and removed.
        Cache::use($dir, 1);
        Cache::put('/stale/', 'old');
        touch(Cache::file('/stale/'), time() - 10);
        assert_same(null, Cache::get('/stale/'));
        assert_true(!is_file(Cache::file('/stale/')), 'a stale entry should be unlinked');

        // ttl 0 disables caching entirely — how bin/verify.php runs.
        Cache::use($dir, 0);
        assert_true(!Cache::enabled());
        assert_true(!Cache::cacheable('GET', '/about/', ''));
    } finally {
        Cache::use($dir, 3600);
        Cache::flush();
        @rmdir($dir);
        Cache::reset();
    }
});

test('the cache is used for a plain GET of a public page', function (): void {
    $dir = sys_get_temp_dir() . '/ttp-cache-rules-' . bin2hex(random_bytes(4));
    Cache::use($dir, 3600);
    try {
        assert_true(Cache::cacheable('GET', '/about/', ''));
    } finally {
        Cache::reset();
    }
});

test('the table of contents ids the H2s it links to', function (): void {
    [$html, $toc] = View::withToc('<h2>First one</h2><p>x</p><h2>First one</h2><h3>Nope</h3>');
    assert_same(2, count($toc));
    assert_same('first-one', $toc[0]['id']);
    assert_same('first-one-2', $toc[1]['id'], 'duplicate headings must get distinct ids');
    assert_contains('<h2 id="first-one">', $html);
    assert_contains('<h2 id="first-one-2">', $html);
});

test('reading time rounds up and never returns zero', function (): void {
    assert_same(1, View::readingTime(0));
    assert_same(1, View::readingTime(10));
    assert_same(5, View::readingTime(1000));
});
