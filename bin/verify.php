<?php
declare(strict_types=1);

/**
 * The URL contract test (plan §4.11). CI runs it on every PR; a phase that
 * breaks it is not mergeable.
 *
 * It boots `php -S` against a throwaway SQLite seeded from content/, requests
 * every row of docs/url-map.csv and asserts the promised status and target,
 * then checks each kept HTML URL for exactly one <h1>, a <title>, a meta
 * description, a canonical and parseable JSON-LD. Finally it checks that the
 * sitemap lists the kept URLs and nothing else, and that the feed parses.
 *
 * Usage:
 *   php bin/verify.php                       boot a local server and test it
 *   php bin/verify.php --base=https://host   test a deployed site instead (S5)
 *   php bin/verify.php --verbose             print every row, not just failures
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

use Ttp\Router;
use Ttp\UrlMap;

$opts    = getopt('', ['base::', 'verbose', 'keep']);
$verbose = isset($opts['verbose']);
$started = microtime(true);

$failures = [];
$checks   = 0;

$fail = static function (string $path, string $message) use (&$failures): void {
    $failures[] = $path . ' — ' . $message;
};
$ok = static function () use (&$checks): void {
    $checks++;
};

// ---------------------------------------------------------------------------
// Target: either a base URL that is already serving, or a server we boot here.
// ---------------------------------------------------------------------------
$server  = null;
$workDir = null;

if (!empty($opts['base'])) {
    $base = rtrim((string) $opts['base'], '/');
    echo "verify: testing {$base}\n";
} else {
    $workDir = sys_get_temp_dir() . '/ttp-verify-' . bin2hex(random_bytes(4));
    mkdir($workDir . '/cache', 0775, true);
    $dbPath = $workDir . '/verify.sqlite';

    $php = PHP_BINARY;
    foreach (['migrate', 'seed'] as $script) {
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg(ttp_root() . "/bin/{$script}.php")
             . ' --db=' . escapeshellarg($dbPath) . ' --quiet 2>&1';
        exec($cmd, $out, $code);
        if ($code !== 0) {
            fwrite(STDERR, "verify: {$script} failed:\n" . implode("\n", $out) . "\n");
            exit(1);
        }
    }

    // Grab a free port, then hand it straight to the built-in server.
    $probe = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if ($probe === false) {
        fwrite(STDERR, "verify: cannot allocate a port: {$errstr}\n");
        exit(1);
    }
    $name = (string) stream_socket_get_name($probe, false);
    $port = (int) substr($name, (int) strrpos($name, ':') + 1);
    fclose($probe);

    $base = 'http://127.0.0.1:' . $port;
    $env  = [
        'PATH'      => getenv('PATH') ?: '/usr/bin:/bin',
        'DB_PATH'   => $dbPath,
        'CACHE_DIR' => $workDir . '/cache',
        'CACHE_TTL' => '0',            // never serve this test a cached page
        'SITE_URL'  => $base,
        'APP_ENV'   => 'test',
    ];
    $cmd = [$php, '-S', '127.0.0.1:' . $port, '-t', ttp_root() . '/public', ttp_root() . '/public/index.php'];
    $server = proc_open($cmd, [1 => ['file', $workDir . '/server.log', 'a'], 2 => ['file', $workDir . '/server.log', 'a']], $pipes, ttp_root(), $env);
    if (!is_resource($server)) {
        fwrite(STDERR, "verify: could not start php -S\n");
        exit(1);
    }

    $ready = false;
    for ($i = 0; $i < 100; $i++) {
        $sock = @stream_socket_client('tcp://127.0.0.1:' . $port, $e1, $e2, 0.2);
        if ($sock !== false) {
            fclose($sock);
            $ready = true;
            break;
        }
        usleep(50_000);
    }
    if (!$ready) {
        fwrite(STDERR, "verify: server did not come up\n" . (string) @file_get_contents($workDir . '/server.log'));
        proc_terminate($server);
        exit(1);
    }
    echo "verify: booted {$base} (db {$dbPath})\n";
}

$cleanup = static function () use (&$server, $workDir, $opts): void {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
        $server = null;
    }
    if ($workDir !== null && !isset($opts['keep'])) {
        foreach ((array) glob($workDir . '/{,.}*', GLOB_BRACE) as $file) {
            if (is_string($file) && is_file($file)) {
                @unlink($file);
            }
        }
        foreach ((array) glob($workDir . '/cache/*') as $file) {
            if (is_string($file)) {
                @unlink($file);
            }
        }
        @rmdir($workDir . '/cache');
        @rmdir($workDir);
    }
};
register_shutdown_function($cleanup);

// ---------------------------------------------------------------------------
// HTTP
// ---------------------------------------------------------------------------
/** @return array{status:int,location:string,body:string,type:string} */
$request = static function (string $path) use ($base): array {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_USERAGENT      => 'ttp-verify/1.0',
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['status' => 0, 'location' => '', 'body' => $error, 'type' => ''];
    }
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $result = [
        'status'   => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
        'location' => (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL),
        'type'     => (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE),
        'body'     => substr((string) $raw, $headerSize),
    ];
    curl_close($ch);
    return $result;
};

/** Compare a Location header with the expected path, absolute or relative. */
$locationPath = static function (string $location): string {
    if ($location === '') {
        return '';
    }
    $path = parse_url($location, PHP_URL_PATH);
    return is_string($path) ? $path : $location;
};

/** The HTML quality bar every kept page must clear. */
$checkHtml = static function (string $path, string $html) use ($fail, $ok, $base): void {
    $h1 = preg_match_all('/<h1[\s>]/i', $html);
    if ($h1 !== 1) {
        $fail($path, "expected exactly one <h1>, found {$h1}");
    } else {
        $ok();
    }

    if (preg_match('/<title>\s*(.+?)\s*<\/title>/is', $html, $m) !== 1 || trim($m[1]) === '') {
        $fail($path, 'missing or empty <title>');
    } else {
        $ok();
    }

    if (preg_match('/<meta\s+name="description"\s+content="([^"]*)"/i', $html, $m) !== 1 || trim($m[1]) === '') {
        $fail($path, 'missing or empty meta description');
    } else {
        $ok();
    }

    if (preg_match('/<link\s+rel="canonical"\s+href="([^"]*)"/i', $html, $m) !== 1) {
        $fail($path, 'missing canonical');
    } elseif ($m[1] !== $base . $path) {
        $fail($path, "canonical is {$m[1]}, expected {$base}{$path}");
    } else {
        $ok();
    }

    $blocks = [];
    preg_match_all('#<script[^>]*type="application/ld\+json"[^>]*>(.*?)</script>#is', $html, $blocks);
    if (($blocks[1] ?? []) === []) {
        $fail($path, 'no JSON-LD');
        return;
    }
    foreach ($blocks[1] as $block) {
        $data = json_decode($block, true);
        if (!is_array($data)) {
            $fail($path, 'JSON-LD does not parse: ' . json_last_error_msg());
            return;
        }
        if (!isset($data['@context'])) {
            $fail($path, 'JSON-LD has no @context');
            return;
        }
        foreach ((array) ($data['@graph'] ?? []) as $node) {
            if (!is_array($node) || !isset($node['@type'])) {
                $fail($path, 'JSON-LD @graph node without @type');
                return;
            }
        }
    }
    $ok();
};

// ---------------------------------------------------------------------------
// Every row of the URL map
// ---------------------------------------------------------------------------
$rows       = UrlMap::rows();
$machine    = ['/sitemap.xml', '/robots.txt', '/feed.xml'];
$keepPaths  = [];
$noindexed  = [];   // kept URLs that ask not to be indexed; the sitemap must skip those

foreach ($rows as $row) {
    $path   = $row['old_path'];
    $action = $row['action'];
    $res    = $request($path);

    if ($action === 'keep') {
        $keepPaths[] = $path;
        if ($res['status'] !== 200) {
            $fail($path, "expected 200, got {$res['status']}");
            continue;
        }
        $ok();
        if (!in_array($path, $machine, true)) {
            $checkHtml($path, $res['body']);
            if (str_contains($res['body'], 'content="noindex')) {
                $noindexed[] = $path;
            }
        }
    } elseif ($action === '301') {
        if ($res['status'] !== 301) {
            $fail($path, "expected 301, got {$res['status']}");
            continue;
        }
        $got = $locationPath($res['location']);
        if ($got !== $row['target']) {
            $fail($path, "301 goes to '{$got}', expected '{$row['target']}'");
            continue;
        }
        $ok();
    } elseif ($action === '410') {
        if ($res['status'] !== 410) {
            $fail($path, "expected 410, got {$res['status']}");
            continue;
        }
        $ok();
    } else {
        $fail($path, "unknown action '{$action}' in docs/url-map.csv");
    }

    if ($verbose) {
        printf("  %-3d %-8s %s\n", $res['status'], $action, $path);
    }
}

// ---------------------------------------------------------------------------
// Extra contract checks
// ---------------------------------------------------------------------------

// Trailing-slash canonicalisation.
$res = $request('/about');
if ($res['status'] !== 301 || $locationPath($res['location']) !== '/about/') {
    $fail('/about', "expected 301 to /about/, got {$res['status']} {$res['location']}");
} else {
    $ok();
}

// Case canonicalisation — /About/ must not serve a duplicate of /about/.
$res = $request('/About/');
if ($res['status'] !== 301 || $locationPath($res['location']) !== '/about/') {
    $fail('/About/', "expected 301 to /about/, got {$res['status']} {$res['location']}");
} else {
    $ok();
}

// A URL nobody promised must 404, not 200.
$res = $request('/definitely-not-a-page-9f3a/');
if ($res['status'] !== 404) {
    $fail('/definitely-not-a-page-9f3a/', "expected 404, got {$res['status']}");
} else {
    $ok();
    $checkHtml('/definitely-not-a-page-9f3a/', $res['body']);
}

// Blog pagination.
$res = $request(Router::BLOG_PATH . 'page/2/');
if ($res['status'] !== 200) {
    $fail(Router::BLOG_PATH . 'page/2/', "expected 200, got {$res['status']}");
} else {
    $ok();
    $checkHtml(Router::BLOG_PATH . 'page/2/', $res['body']);
}

// Sitemap: exactly the kept HTML URLs, no more and no less.
$res = $request('/sitemap.xml');
if ($res['status'] !== 200) {
    $fail('/sitemap.xml', "expected 200, got {$res['status']}");
} else {
    $xml = @simplexml_load_string($res['body']);
    if ($xml === false) {
        $fail('/sitemap.xml', 'is not valid XML');
    } else {
        $listed = [];
        foreach ($xml->url as $url) {
            $listed[] = (string) (parse_url((string) $url->loc, PHP_URL_PATH) ?? '');
        }
        $expected = array_values(array_diff($keepPaths, $machine, $noindexed));
        sort($expected);
        sort($listed);
        $missing = array_diff($expected, $listed);
        $extra   = array_diff($listed, $expected);
        if ($missing !== []) {
            $fail('/sitemap.xml', 'missing kept URLs: ' . implode(', ', $missing));
        }
        if ($extra !== []) {
            $fail('/sitemap.xml', 'lists URLs that are not kept: ' . implode(', ', $extra));
        }
        if ($missing === [] && $extra === []) {
            $ok();
        }
    }
}

// Feed.
$res = $request('/feed.xml');
if ($res['status'] !== 200) {
    $fail('/feed.xml', "expected 200, got {$res['status']}");
} else {
    $rss = @simplexml_load_string($res['body']);
    if ($rss === false) {
        $fail('/feed.xml', 'is not valid XML');
    } elseif (!isset($rss->channel) || count($rss->channel->item) === 0) {
        $fail('/feed.xml', 'has no <item> entries');
    } else {
        $ok();
    }
}

// robots.txt must point at the sitemap.
$res = $request('/robots.txt');
if ($res['status'] !== 200 || !str_contains($res['body'], 'Sitemap:')) {
    $fail('/robots.txt', 'missing a Sitemap: line');
} else {
    $ok();
}

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------
$elapsed = microtime(true) - $started;
printf("verify: %d rows, %d assertions, %.1fs\n", count($rows), $checks, $elapsed);

if ($failures !== []) {
    echo "\nFAILED (" . count($failures) . "):\n";
    foreach ($failures as $line) {
        echo '  ✗ ' . $line . "\n";
    }
    exit(1);
}

echo "verify: OK — every row of docs/url-map.csv holds.\n";
exit(0);
