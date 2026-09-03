<?php
declare(strict_types=1);

/**
 * Exports the live WordPress site into content/ via its REST API.
 *
 * Usage:
 *   php bin/wp-export.php [--api=URL] [--from-scan] [--force] [--no-media] [--quiet]
 *
 * --api        REST base, default https://thingstodoinparaguay.com/wp-json/wp/v2/
 * --from-scan  skip the network entirely and build content/ from docs/wp-scan.md
 * --no-media   do not download featured images into public/media/legacy/
 *
 * If the API cannot be reached the script does NOT fail the build: it reports why
 * and falls back to bin/scan-import.php, exactly as plan §5.1 requires.
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

use Ttp\FrontMatter;
use Ttp\HtmlToMarkdown;
use Ttp\Markdown;
use Ttp\Str;

$opts    = getopt('', ['api::', 'from-scan', 'force', 'no-media', 'quiet']);
$api     = rtrim((string) ($opts['api'] ?? 'https://thingstodoinparaguay.com/wp-json/wp/v2/'), '/') . '/';
$force   = isset($opts['force']);
$quiet   = isset($opts['quiet']);
$noMedia = isset($opts['no-media']);
$say = static function (string $m) use ($quiet): void { if (!$quiet) { echo $m, "\n"; } };

$root = ttp_root();

$runScanFallback = static function (string $reason) use ($root, $force, $quiet, $say): never {
    $say("wp-export: falling back to docs/wp-scan.md — {$reason}");
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/bin/scan-import.php');
    if ($force) { $cmd .= ' --force'; }
    if ($quiet) { $cmd .= ' --quiet'; }
    passthru($cmd, $code);
    exit($code);
};

if (isset($opts['from-scan'])) {
    $runScanFallback('--from-scan requested');
}

/**
 * @return array{0:int,1:string,2:array<string,string>}  status, body, headers
 */
$http = static function (string $url): array {
    $ch = curl_init($url);
    $headers = [];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_USERAGENT      => 'ttp-wp-export/1.0',
        CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$headers): int {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $headers[strtolower(trim($k))] = trim($v);
            }
            return strlen($line);
        },
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return [0, $err, []];
    }
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, (string) $body, $headers];
};

$fetchAll = static function (string $endpoint) use ($api, $http, $say): array {
    $items = [];
    $page  = 1;
    do {
        $url = $api . $endpoint . (str_contains($endpoint, '?') ? '&' : '?') . 'per_page=100&page=' . $page;
        [$status, $body, $headers] = $http($url);
        if ($status !== 200) {
            throw new RuntimeException("GET {$url} -> " . ($status === 0 ? $body : "HTTP {$status}"));
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException("GET {$url} -> response was not JSON");
        }
        $items = array_merge($items, $decoded);
        $total = (int) ($headers['x-wp-totalpages'] ?? 1);
        $say(sprintf('  %s page %d/%d (%d items)', $endpoint, $page, max($total, 1), count($decoded)));
        $page++;
    } while ($page <= $total);
    return $items;
};

// --- reachability check ------------------------------------------------------
[$status, $body] = $http($api);
if ($status !== 200) {
    $runScanFallback('WP REST API not reachable at ' . $api . ' (' . ($status === 0 ? $body : "HTTP {$status}") . ')');
}

$say('wp-export: WP REST API reachable at ' . $api);

try {
    $categories = $fetchAll('categories');
    $tags       = $fetchAll('tags');
    $posts      = $fetchAll('posts?status=publish');
    $pages      = $fetchAll('pages?status=publish');
} catch (Throwable $e) {
    $runScanFallback($e->getMessage());
}

$catById = [];
foreach ($categories as $c) {
    $catById[(int) $c['id']] = $c;
}
$tagById = [];
foreach ($tags as $t) {
    $tagById[(int) $t['id']] = $t;
}

$mediaCache = [];
$fetchMedia = static function (int $id) use ($api, $http, &$mediaCache): ?array {
    if ($id <= 0) {
        return null;
    }
    if (array_key_exists($id, $mediaCache)) {
        return $mediaCache[$id];
    }
    [$status, $body] = $http($api . 'media/' . $id);
    $data = $status === 200 ? json_decode($body, true) : null;
    return $mediaCache[$id] = is_array($data) ? $data : null;
};

$written = 0;
$skipped = 0;

$writeFile = static function (string $path, string $contents) use ($force, &$written, &$skipped, $say, $root): void {
    if (is_file($path) && !$force) {
        $skipped++;
        return;
    }
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0775, true);
    }
    file_put_contents($path, $contents);
    $written++;
    $say('  + ' . substr($path, strlen($root) + 1));
};

// --- categories ---------------------------------------------------------------
foreach ($categories as $c) {
    $slug = (string) $c['slug'];
    $writeFile($root . "/content/category/{$slug}.md", FrontMatter::render([
        'slug'             => $slug,
        'name'             => html_entity_decode((string) $c['name'], ENT_QUOTES, 'UTF-8'),
        'description'      => trim(strip_tags((string) ($c['description'] ?? ''))),
        'meta_title'       => '',
        'meta_description' => '',
    ], ''));
}

// --- posts and pages ----------------------------------------------------------
$mediaDir = $root . '/public/media/legacy';

$export = static function (array $item, string $type) use (
    $writeFile, $root, $catById, $tagById, $fetchMedia, $noMedia, $mediaDir, $http, $say
): void {
    $slug  = (string) $item['slug'];
    $title = html_entity_decode(strip_tags((string) ($item['title']['rendered'] ?? $slug)), ENT_QUOTES, 'UTF-8');
    $html  = (string) ($item['content']['rendered'] ?? '');
    $md    = HtmlToMarkdown::convert($html);

    $excerptHtml = (string) ($item['excerpt']['rendered'] ?? '');
    $excerpt = Markdown::truncate(trim(html_entity_decode(strip_tags($excerptHtml), ENT_QUOTES, 'UTF-8')), 155);

    $data = [
        'type'         => $type,
        'title'        => $title,
        'status'       => 'published',
        'published_at' => gmdate('Y-m-d\TH:i:s\Z', strtotime((string) ($item['date_gmt'] ?? 'now')) ?: time()),
    ];

    if ($type === 'post') {
        $catSlug = '';
        foreach ((array) ($item['categories'] ?? []) as $cid) {
            $cat = $catById[(int) $cid] ?? null;
            if ($cat !== null && $cat['slug'] !== 'uncategorized') {
                $catSlug = (string) $cat['slug'];
                break;
            }
        }
        $data['category'] = $catSlug;
        $data['tags'] = array_values(array_filter(array_map(
            static fn ($tid): string => (string) ($tagById[(int) $tid]['slug'] ?? ''),
            (array) ($item['tags'] ?? [])
        )));
    }

    $featured = $fetchMedia((int) ($item['featured_media'] ?? 0));
    if ($featured !== null) {
        $src  = (string) ($featured['source_url'] ?? '');
        $file = basename(parse_url($src, PHP_URL_PATH) ?: '');
        if ($file !== '') {
            $data['legacy_cover']     = $file;
            $data['legacy_cover_alt'] = trim((string) ($featured['alt_text'] ?? ''));
            if (!$noMedia && $src !== '') {
                if (!is_dir($mediaDir)) {
                    mkdir($mediaDir, 0775, true);
                }
                $target = $mediaDir . '/' . $file;
                if (!is_file($target)) {
                    [$st, $bin] = $http($src);
                    if ($st === 200 && $bin !== '') {
                        file_put_contents($target, $bin);
                        $say('  ↓ media/legacy/' . $file);
                    }
                }
                if (is_file($target)) {
                    $data['cover'] = '/media/legacy/' . $file;
                    $data['cover_alt'] = $data['legacy_cover_alt'];
                }
            }
        }
    }

    $data['excerpt']          = $excerpt;
    $data['meta_title']       = '';
    $data['meta_description'] = '';
    $data['source']           = 'wp-rest';

    $writeFile($root . "/content/{$type}/{$slug}.md", FrontMatter::render($data, $md));
};

foreach ($posts as $p) {
    $export($p, 'post');
}

// Pages become page/tour/service according to docs/url-map.csv.
$typeByPath = [];
foreach (\Ttp\UrlMap::rows() as $row) {
    if ($row['action'] === 'keep' && in_array($row['new_type'], ['page', 'tour', 'service'], true)) {
        $typeByPath[trim($row['old_path'], '/')] = $row['new_type'];
    }
}
foreach ($pages as $pg) {
    $slug = (string) $pg['slug'];
    $type = $typeByPath[$slug] ?? null;
    if ($type === null) {
        continue;   // 301'd or 410'd by the URL contract — no content file needed
    }
    $export($pg, $type);
}

unset($skipped, $written, $slug, $status, $body);
$say('wp-export: done. Structured tour/service fields are NOT in the WP REST payload —');
$say('           run `php bin/scan-import.php --force` afterwards to fill them from the scan.');
