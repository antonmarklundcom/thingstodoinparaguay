<?php
declare(strict_types=1);

/**
 * Structured-data audit. Crawls /sitemap.xml on a running site and checks each
 * page's JSON-LD against the properties Google's rich-result docs require or
 * recommend, plus the mistakes that silently break markup (empty strings,
 * relative URLs, dangling @id references, images that 404).
 *
 * This is an offline approximation, not Google's Rich Results Test: it cannot
 * say whether Google will show a rich result, only that the markup is complete.
 * Note Google shows FAQ rich results only for well-known government and health
 * sites; FAQPage markup here is still valid and read by other consumers.
 *
 * Usage: php bin/schema-audit.php [--base=http://localhost:8080] [--verbose]
 * Exit code 1 when any error is found; warnings do not fail the run.
 */

$opts    = getopt('', ['base::', 'verbose']);
$base    = rtrim((string) ($opts['base'] ?? 'http://localhost:8080'), '/');
$verbose = isset($opts['verbose']);

$get = static function (string $url, bool $head = false): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_NOBODY         => $head,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, is_string($body) ? $body : ''];
};

[$code, $xml] = $get($base . '/sitemap.xml');
if ($code !== 200) {
    fwrite(STDERR, "schema-audit: {$base}/sitemap.xml returned {$code}\n");
    exit(2);
}
preg_match_all('#<url>\s*<loc>([^<]+)</loc>#', $xml, $m);
$urls = $m[1];

$errors        = [];
$warns         = [];
$stats         = [];
$imagesChecked = [];

$err  = static function (string $page, string $msg) use (&$errors): void {
    $errors[] = "{$page}: {$msg}";
};
$warn = static function (string $page, string $msg) use (&$warns): void {
    $warns[] = "{$page}: {$msg}";
};

$isAbs = static fn (mixed $v): bool => is_string($v) && preg_match('#^https?://#', $v) === 1;
$blank = static fn (mixed $v): bool => !is_string($v) || trim($v) === '';

foreach ($urls as $url) {
    $page = parse_url($url, PHP_URL_PATH) ?: '/';
    [$code, $html] = $get($base . $page);
    if ($code !== 200) {
        $err($page, "HTTP {$code}");
        continue;
    }

    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $blocks);
    if ($blocks[1] === []) {
        $err($page, 'no JSON-LD');
        continue;
    }

    $nodes = [];
    foreach ($blocks[1] as $raw) {
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            $err($page, 'JSON-LD does not parse');
            continue;
        }
        foreach (($json['@graph'] ?? [$json]) as $node) {
            $nodes[] = $node;
        }
    }

    $ids = [];
    foreach ($nodes as $n) {
        if (isset($n['@id'])) {
            $ids[$n['@id']] = true;
        }
    }

    foreach ($nodes as $n) {
        $type = is_array($n['@type'] ?? null) ? implode('/', $n['@type']) : (string) ($n['@type'] ?? '');
        $stats[$type] = ($stats[$type] ?? 0) + 1;

        // Generic hygiene: no empty strings, absolute URLs, resolvable @id refs.
        array_walk_recursive($n, static function (mixed $v, string|int $k) use ($page, $err): void {
            if (is_string($v) && trim($v) === '' && $k !== '@id') {
                $err($page, "empty value for '{$k}'");
            }
        });
        foreach (['url', 'image', 'logo'] as $key) {
            if (isset($n[$key]) && is_string($n[$key]) && !$isAbs($n[$key])) {
                $err($page, "{$type}.{$key} is not an absolute URL");
            }
        }
        $refs = static function (array $node) use (&$refs, $ids, $page, $err): void {
            foreach ($node as $key => $v) {
                if ($key === 'mainEntityOfPage') {
                    continue;
                }
                if (is_array($v)) {
                    if (count($v) === 1 && isset($v['@id']) && !isset($ids[$v['@id']])) {
                        $err($page, "dangling @id reference {$v['@id']}");
                    }
                    $refs($v);
                }
            }
        };
        $refs($n);

        switch (true) {
            case str_contains($type, 'BreadcrumbList'):
                $items = $n['itemListElement'] ?? [];
                if ($items === []) {
                    $err($page, 'BreadcrumbList without items');
                }
                foreach ($items as $i => $it) {
                    if (($it['position'] ?? null) !== $i + 1) {
                        $err($page, 'Breadcrumb positions are not 1..n');
                    }
                    if ($blank($it['name'] ?? null)) {
                        $err($page, 'Breadcrumb item without name');
                    }
                    if ($i < count($items) - 1 && !$isAbs($it['item'] ?? null)) {
                        $err($page, 'non-final Breadcrumb item needs an absolute item URL');
                    }
                }
                break;

            case $type === 'FAQPage':
                $qs = $n['mainEntity'] ?? [];
                if ($qs === []) {
                    $err($page, 'FAQPage without questions');
                }
                foreach ($qs as $q) {
                    if (($q['@type'] ?? '') !== 'Question' || $blank($q['name'] ?? null)) {
                        $err($page, 'FAQ question missing name');
                    }
                    if ($blank($q['acceptedAnswer']['text'] ?? null)) {
                        $err($page, 'FAQ "' . ($q['name'] ?? '?') . '" has no answer text');
                    }
                }
                break;

            case in_array($type, ['BlogPosting', 'Article', 'NewsArticle'], true):
                foreach (['headline', 'datePublished', 'author', 'image'] as $req) {
                    if (empty($n[$req])) {
                        $err($page, "{$type} missing {$req}");
                    }
                }
                if (mb_strlen((string) ($n['headline'] ?? '')) > 110) {
                    $warn($page, 'headline over 110 characters');
                }
                foreach (['datePublished', 'dateModified'] as $d) {
                    if (isset($n[$d]) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})$/', (string) $n[$d]) !== 1) {
                        $err($page, "{$d} is not ISO 8601 with a timezone");
                    }
                }
                if (empty($n['dateModified'])) {
                    $warn($page, 'no dateModified');
                }
                break;

            case $type === 'TouristTrip' || $type === 'Service':
                foreach (['name', 'description', 'provider'] as $req) {
                    if (empty($n[$req])) {
                        $err($page, "{$type} missing {$req}");
                    }
                }
                if (empty($n['image'])) {
                    $warn($page, "{$type} has no image");
                }
                break;

            case str_contains($type, 'Organization') || str_contains($type, 'TravelAgency'):
                foreach (['name', 'url'] as $req) {
                    if (empty($n[$req])) {
                        $err($page, "Organization missing {$req}");
                    }
                }
                if (empty($n['logo'])) {
                    $warn($page, 'Organization has no logo');
                }
                break;

            case $type === 'WebSite':
                foreach (['name', 'url'] as $req) {
                    if (empty($n[$req])) {
                        $err($page, "WebSite missing {$req}");
                    }
                }
                break;
        }

        if (isset($n['image']) && is_string($n['image']) && !isset($imagesChecked[$n['image']])) {
            $imgPath = parse_url($n['image'], PHP_URL_PATH) ?: '';
            [$icode] = $get($base . $imgPath, true);
            $imagesChecked[$n['image']] = $icode;
            if ($icode !== 200) {
                $err($page, "image {$imgPath} returns {$icode}");
            }
        }
    }

    $faqs = count(array_filter($nodes, static fn (array $n): bool => ($n['@type'] ?? '') === 'FAQPage'));
    if ($faqs > 1) {
        $err($page, 'more than one FAQPage');
    }
    if ($verbose) {
        echo "ok  {$page}\n";
    }
}

ksort($stats);
echo 'schema-audit: ' . count($urls) . " pages, {$base}\n";
foreach ($stats as $t => $c) {
    echo "  {$t}: {$c}\n";
}
$warnCounts = array_count_values(array_map(static fn (string $w): string => substr($w, (int) strpos($w, ': ') + 2), $warns));
foreach ($warnCounts as $w => $c) {
    echo "warn ({$c}x): {$w}\n";
}
foreach ($errors as $e) {
    echo "ERROR {$e}\n";
}
echo count($errors) === 0 ? "schema-audit: OK\n" : 'schema-audit: ' . count($errors) . " error(s)\n";
exit(count($errors) === 0 ? 0 : 1);
