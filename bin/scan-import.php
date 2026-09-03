<?php
declare(strict_types=1);

/**
 * Builds content/ from docs/wp-scan.md + docs/url-map.csv.
 *
 * This is the fallback path required by plan §5.1 when the live WordPress REST
 * API cannot be reached. bin/wp-export.php calls it with --from-scan; it is also
 * runnable on its own. Re-runnable: existing files are overwritten only when
 * --force is given, so hand edits by later phases survive.
 *
 * Usage: php bin/scan-import.php [--force] [--quiet]
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

use Ttp\FrontMatter;
use Ttp\ScanImport;
use Ttp\Str;
use Ttp\TourParser;
use Ttp\UrlMap;

$opts  = getopt('', ['force', 'quiet']);
$force = isset($opts['force']);
$quiet = isset($opts['quiet']);
$say = static function (string $m) use ($quiet): void { if (!$quiet) { echo $m, "\n"; } };

$root     = ttp_root();
$scanPath = $root . '/docs/wp-scan.md';
if (!is_file($scanPath)) {
    fwrite(STDERR, "docs/wp-scan.md not found\n");
    exit(1);
}

$scan     = new ScanImport($scanPath);
$sections = $scan->sections();
$rows     = UrlMap::rows();

/**
 * Slugs whose content lives under a different path in the scan.
 * Per docs/url-map.csv: /about/ is rebuilt from the richer /about2/, and
 * /faq/ shares one byte-identical section with /faq2/.
 */
$aliases = [
    'about' => 'about2',
    'faq'   => 'faq2',
];

/** Titles for the consolidated pages, whose scan sections carry the old "2" names. */
$titleOverrides = [
    'about' => 'About',
    'faq'   => 'Frequently Asked Questions',
];

/** Generic Elementor thumbnail names — real filenames, no editorial value. */
$isGenericImage = static fn (string $file): bool => (bool) preg_match('/^\d{1,3}\.(jpe?g|png|webp)$/i', $file);

$written = 0;
$skipped = 0;
$gaps    = [];

/** Categories referenced by the URL map, so /category/<slug>/ has a home. */
$categories = [];
foreach ($rows as $row) {
    if ($row['type'] === 'category' && $row['action'] === 'keep') {
        $slug = basename(rtrim($row['old_path'], '/'));
        $categories[$slug] = $row['title'] !== '' ? $row['title'] : ucfirst($slug);
    }
}

$writeFile = static function (string $path, string $contents) use ($force, &$written, &$skipped, $say): void {
    if (is_file($path) && !$force) {
        $skipped++;
        return;
    }
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents($path, $contents);
    $written++;
    $say('  + ' . substr($path, strlen(ttp_root()) + 1));
};

// ---------------------------------------------------------------------------
// Categories
// ---------------------------------------------------------------------------
foreach ($categories as $slug => $name) {
    $data = [
        'slug'             => $slug,
        'name'             => $name,
        'description'      => '',
        'meta_title'       => '',
        'meta_description' => '',
    ];
    $writeFile($root . "/content/category/{$slug}.md", FrontMatter::render($data, ''));
}

// ---------------------------------------------------------------------------
// Content items
// ---------------------------------------------------------------------------
$lorem = null;

foreach ($rows as $row) {
    if ($row['action'] !== 'keep' || $row['new_type'] === '') {
        continue;
    }
    $type = $row['new_type'];
    if (!in_array($type, ['post', 'page', 'tour', 'service'], true)) {
        continue;   // hubs, blog index, sitemap, robots and feed are routes, not content files
    }

    $slug       = trim($row['old_path'], '/');
    $sourceSlug = $aliases[$slug] ?? $slug;
    $section    = $sections[$sourceSlug] ?? '';
    $title = $titleOverrides[$slug] ?? ($section !== '' ? ScanImport::title($section) : '');
    if ($title === '') {
        $title = $row['title'] !== '' ? $row['title'] : ucwords(str_replace('-', ' ', $slug));
    }

    $data = [
        'type'   => $type,
        'title'  => $title,
        'status' => 'published',
    ];

    $publishedAt = $section !== '' ? ScanImport::publishedAt($section) : '';
    $data['published_at'] = $publishedAt !== '' ? $publishedAt : '2025-07-24T00:00:00Z';

    if ($type === 'post') {
        $catName = ScanImport::category($section);
        $catSlug = $catName !== '' ? Str::slug($catName) : 'uncategorized';
        if ($catSlug !== '' && !isset($categories[$catSlug])) {
            // Categories the URL map 301s away (uncategorized) stay unset.
            $catSlug = '';
        }
        $data['category'] = $catSlug;

        $tags = array_map(static fn (string $t): string => Str::slug($t), ScanImport::tags($section));
        $data['tags'] = array_values(array_filter($tags));
    }

    // The live site's media could not be downloaded (see docs/content-gaps.md), so
    // the old filename is recorded for reference only — never as a live image path.
    $cover = $section !== '' ? ScanImport::coverImage($section) : null;
    if ($cover !== null && !$isGenericImage($cover['file'])) {
        $data['legacy_cover']     = $cover['file'];
        $data['legacy_cover_alt'] = $cover['alt'];
    }

    $body = $section !== '' ? ScanImport::bodyBlock($section) : '';

    if ($type === 'tour' || $type === 'service') {
        $headings = ScanImport::headings($section);
        $parsed   = TourParser::parse($body, $headings, $title);

        $data['tagline']         = $parsed['tagline'];
        $data['excerpt']         = mb_substr(trim((string) preg_split('/\n\n/', $parsed['lead'])[0] ?? ''), 0, 300);
        $data['cta_text']        = $parsed['cta_text'] !== '' ? $parsed['cta_text'] : 'Ask for a quote';
        $data['itinerary_label'] = $parsed['itinerary_label'];
        $data['hook']            = $parsed['hook'];
        $data['solution']        = $parsed['solution'];
        $data['itinerary']       = $parsed['itinerary'];
        $data['why']             = $parsed['why'];
        $data['practical']       = $parsed['practical'];
        $data['faq']             = $parsed['faq'];
        $data['closing']         = $parsed['closing'];

        foreach ($parsed['practical'] as $fact) {
            $label = strtolower($fact['label']);
            foreach (['duration', 'departure', 'transport', 'requirements'] as $key) {
                if ($label === $key && !isset($data[$key])) {
                    $data[$key] = $fact['value'];
                }
            }
        }
        $data['price_usd'] = null;   // plan §1.8: no invented prices

        $bodyMd = $parsed['lead'];
        if ($parsed['leftover'] !== []) {
            $bodyMd .= "\n\n" . implode("\n\n", $parsed['leftover']);
        }
        if ($parsed['hook'] === '' && $parsed['solution'] === '') {
            // No heading list in the scan (e.g. /yerba-mate-tour/, a known content
            // bug on the live site): keep the copy whole rather than lose it.
            $bodyMd = ScanImport::headify($body, ScanImport::headings($section), $title);
        }
        $body = trim($bodyMd);

        foreach ($parsed['gaps'] as $gap) {
            $gaps[] = "/{$slug}/ — {$gap}";
        }
    } else {
        // Posts and pages keep a Markdown body. Posts are Lorem Ipsum on the live
        // site; they are carried across verbatim and replaced in phase S4.
        if ($type === 'post') {
            if ($lorem === null) {
                $lorem = '';
                // `[^\n]*`, not `.*`: with the /s modifier a dot would swallow the
                // rest of the scan and paste it into all 32 post bodies.
                if (preg_match('/## Common Placeholder Body Copy[^\n]*\n\n((?:>[^\n]*\n)+)/', file_get_contents($scanPath) ?: '', $m)) {
                    $lorem = trim((string) preg_replace('/^> ?/m', '', $m[1]));
                }
            }
            $bodyText = $body !== '' ? $body : $lorem;
            $bodyText = (string) preg_replace('/^> ?/m', '', $bodyText);
            $bodyText = trim((string) preg_replace('/^Giving You The Best Services Experiences\s*$/m', '', $bodyText));
            $body = $bodyText;
            $data['placeholder'] = true;
            $gaps[] = "/{$slug}/ — body is Lorem Ipsum on the live site; phase S4 writes the real post";
        }
        if ($type === 'page') {
            $body = ScanImport::headify($body, ScanImport::headings($section), $title);
        }
        $body = trim($body);
    }

    if (!isset($data['excerpt'])) {
        $data['excerpt'] = '';
    }
    $data['meta_title']       = '';
    $data['meta_description'] = '';
    $data['source']           = 'wp-scan';

    $writeFile($root . "/content/{$type}/{$slug}.md", FrontMatter::render($data, $body));
}

$say(sprintf('scan-import: %d file(s) written, %d left untouched (use --force to overwrite)', $written, $skipped));

if ($gaps !== []) {
    $report = "# Content gaps carried over from the WordPress scan\n\n"
        . "Generated by `bin/scan-import.php`. Each line is content the live site did not\n"
        . "expose to the scan, or placeholder copy that phase S4 must replace.\n\n"
        . implode("\n", array_map(static fn (string $g): string => "- {$g}", $gaps)) . "\n";
    file_put_contents($root . '/docs/content-gaps.md', $report);
    $say('scan-import: wrote docs/content-gaps.md (' . count($gaps) . ' items)');
}
