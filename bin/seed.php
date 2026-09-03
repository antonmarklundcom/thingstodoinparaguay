<?php
declare(strict_types=1);

/**
 * content/ + docs/url-map.csv  ->  SQLite.
 *
 * Idempotent by slug. An item whose row was edited in the admin (source =
 * 'admin') is never overwritten — the admin always wins, which is what makes
 * it safe to keep running this on a live install (plan §5.1).
 *
 * Usage: php bin/seed.php [--db=path] [--force] [--quiet]
 *   --force  re-import even when the file's hash matches the stored one
 *            (still refuses to clobber admin-edited rows)
 *   --content=dir  read from another content tree (bin/test.php round-trips
 *                  bin/export.php's output through here)
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

use Ttp\Db;
use Ttp\FrontMatter;
use Ttp\Markdown;
use Ttp\Repo\RedirectRepo;
use Ttp\SeoScore;
use Ttp\Str;
use Ttp\UrlMap;

$opts  = getopt('', ['db::', 'content::', 'force', 'quiet']);
$quiet = isset($opts['quiet']);
$force = isset($opts['force']);
if (!empty($opts['db'])) {
    Db::use((string) $opts['db']);
}

$say = static function (string $m) use ($quiet): void {
    if (!$quiet) {
        echo $m, "\n";
    }
};

if (!Db::exists() || !Db::hasTable('content_items')) {
    $say('seed: schema missing, applying db/schema.sql first');
    Db::conn()->exec((string) file_get_contents(ttp_root() . '/db/schema.sql'));
}

$root       = ttp_root();
$contentDir = !empty($opts['content']) ? rtrim((string) $opts['content'], '/') : $root . '/content';
$now        = gmdate('c');

// ---------------------------------------------------------------------------
// Settings — defaults only; an existing value is never overwritten.
// ---------------------------------------------------------------------------
$config = ttp_config();
$defaults = [
    'site_name'   => $config['site_name'],
    'tagline'     => $config['tagline'],
    'address'     => $config['address'],
    'phone'       => $config['phone'],
    'email'       => $config['email'],
    'whatsapp'    => $config['whatsapp'],
    'ga4_id'      => '',
    'social_json' => '{}',
];
foreach ($defaults as $key => $value) {
    Db::run('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO NOTHING', [$key, $value]);
}

// ---------------------------------------------------------------------------
// Categories
// ---------------------------------------------------------------------------
$categoryIds = [];
$categoryFiles = glob($contentDir . '/category/*.md') ?: [];
sort($categoryFiles);
foreach ($categoryFiles as $i => $file) {
    [$fm] = FrontMatter::parse((string) file_get_contents($file));
    $slug = (string) ($fm['slug'] ?? basename($file, '.md'));
    Db::run(
        'INSERT INTO categories (slug, name, description, meta_title, meta_description, sort_order)
         VALUES (?, ?, ?, ?, ?, ?)
         ON CONFLICT(slug) DO UPDATE SET
            name = excluded.name,
            description = excluded.description,
            meta_title = excluded.meta_title,
            meta_description = excluded.meta_description,
            sort_order = excluded.sort_order',
        [
            $slug,
            (string) ($fm['name'] ?? ucfirst($slug)),
            (string) ($fm['description'] ?? ''),
            (string) ($fm['meta_title'] ?? ''),
            (string) ($fm['meta_description'] ?? ''),
            (int) ($fm['sort_order'] ?? $i),
        ]
    );
    $categoryIds[$slug] = (int) Db::value('SELECT id FROM categories WHERE slug = ?', [$slug]);
}
$say(sprintf('seed: %d categories', count($categoryIds)));

// ---------------------------------------------------------------------------
// Content items
// ---------------------------------------------------------------------------
$tagCache = [];
$tagId = static function (string $name) use (&$tagCache): int {
    $slug = Str::slug($name);
    if (isset($tagCache[$slug])) {
        return $tagCache[$slug];
    }
    Db::run('INSERT INTO tags (slug, name) VALUES (?, ?) ON CONFLICT(slug) DO UPDATE SET name = excluded.name', [$slug, $name]);
    return $tagCache[$slug] = (int) Db::value('SELECT id FROM tags WHERE slug = ?', [$slug]);
};

$imported = 0;
$skipped  = 0;
$locked   = 0;

foreach (['post', 'page', 'tour', 'service'] as $type) {
    $files = glob($contentDir . '/' . $type . '/*.md') ?: [];
    sort($files);

    foreach ($files as $file) {
        $raw  = (string) file_get_contents($file);
        $hash = hash('sha256', $raw);
        [$fm, $body] = FrontMatter::parse($raw);
        $slug = (string) ($fm['slug'] ?? basename($file, '.md'));

        $existing = Db::one('SELECT id, source, content_hash FROM content_items WHERE slug = ?', [$slug]);
        if ($existing !== null && (string) $existing['source'] === 'admin') {
            $locked++;
            continue;
        }
        if ($existing !== null && !$force && (string) $existing['content_hash'] === $hash) {
            $skipped++;
            continue;
        }

        $isStructured = in_array($type, ['tour', 'service'], true);

        // Word count covers the structured sections too, otherwise every tour
        // would look like a 20-word page to the O2 SEO score.
        $fullText = $body;
        if ($isStructured) {
            foreach (['hook', 'solution', 'closing'] as $key) {
                $fullText .= "\n\n" . (string) ($fm[$key] ?? '');
            }
            foreach (['itinerary', 'why', 'practical', 'faq'] as $key) {
                foreach ((array) ($fm[$key] ?? []) as $row) {
                    $fullText .= "\n\n" . implode(' ', array_map('strval', (array) $row));
                }
            }
        }

        $excerpt = trim((string) ($fm['excerpt'] ?? ''));
        if ($excerpt === '') {
            $excerpt = Markdown::truncate(Markdown::toText($fullText), 155);
        }

        $publishedAt = (string) ($fm['published_at'] ?? '');
        $updatedAt   = (string) ($fm['updated_at'] ?? ($publishedAt !== '' ? $publishedAt : $now));

        $categorySlug = trim((string) ($fm['category'] ?? ''));
        $categoryId   = $categorySlug !== '' ? ($categoryIds[$categorySlug] ?? null) : null;

        $fields = [
            'type'               => $type,
            'title'              => (string) ($fm['title'] ?? ucfirst(str_replace('-', ' ', $slug))),
            'status'             => in_array((string) ($fm['status'] ?? 'published'), ['draft', 'published', 'scheduled'], true)
                                    ? (string) ($fm['status'] ?? 'published') : 'published',
            'published_at'       => $publishedAt !== '' ? $publishedAt : null,
            'updated_at'         => $updatedAt,
            'excerpt'            => $excerpt,
            'body_md'            => $body,
            'body_html'          => Markdown::toHtml($body),
            'category_id'        => $categoryId,
            'meta_title'         => (string) ($fm['meta_title'] ?? ''),
            'meta_description'   => (string) ($fm['meta_description'] ?? ''),
            'canonical_override' => (string) ($fm['canonical'] ?? ''),
            'noindex'            => (int) (bool) ($fm['noindex'] ?? false),
            'focus_keyword'      => (string) ($fm['focus_keyword'] ?? ''),
            'word_count'         => Markdown::wordCount($fullText),
            'sort_order'         => (int) ($fm['sort_order'] ?? 0),
            'source'             => 'seed',
            'content_hash'       => $hash,
        ];

        // The same score the admin editor shows, so `bin/seo-audit.php` is
        // meaningful straight after a seed without a separate --write pass.
        $scoreDetails = $isStructured ? [
            'hook_md'         => (string) ($fm['hook'] ?? ''),
            'solution_md'     => (string) ($fm['solution'] ?? ''),
            'closing_md'      => (string) ($fm['closing'] ?? ''),
            'itinerary_label' => (string) ($fm['itinerary_label'] ?? ''),
            'itinerary'       => (array) ($fm['itinerary'] ?? []),
            'why'             => (array) ($fm['why'] ?? []),
            'practical'       => (array) ($fm['practical'] ?? []),
            'faq'             => (array) ($fm['faq'] ?? []),
        ] : null;
        $fields['seo_score'] = SeoScore::forItem($fields + ['slug' => $slug], $scoreDetails)->score;

        if ($existing === null) {
            $cols = implode(', ', array_keys($fields));
            $ph   = implode(', ', array_fill(0, count($fields), '?'));
            Db::run("INSERT INTO content_items (slug, {$cols}) VALUES (?, {$ph})", array_merge([$slug], array_values($fields)));
            $itemId = Db::lastId();
        } else {
            $set = implode(', ', array_map(static fn (string $c): string => $c . ' = ?', array_keys($fields)));
            Db::run("UPDATE content_items SET {$set} WHERE id = ?", array_merge(array_values($fields), [(int) $existing['id']]));
            $itemId = (int) $existing['id'];
        }

        // Tags
        Db::run('DELETE FROM item_tags WHERE item_id = ?', [$itemId]);
        foreach ((array) ($fm['tags'] ?? []) as $tag) {
            $name = trim((string) $tag);
            if ($name === '') {
                continue;
            }
            Db::run('INSERT OR IGNORE INTO item_tags (item_id, tag_id) VALUES (?, ?)', [$itemId, $tagId($name)]);
        }

        // Structured tour/service fields
        if ($isStructured) {
            $json = static function (mixed $v): string {
                return (string) json_encode(is_array($v) ? array_values($v) : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            };
            $price = $fm['price_usd'] ?? null;
            $price = ($price === null || $price === '' || (float) $price <= 0) ? null : (float) $price;

            Db::run(
                'INSERT INTO tour_details
                    (item_id, hook_md, solution_md, itinerary_json, why_json, practical_json, faq_json,
                     price_usd, duration, departure, transport, requirements, cta_text,
                     tagline, itinerary_label, closing_md)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON CONFLICT(item_id) DO UPDATE SET
                    hook_md = excluded.hook_md, solution_md = excluded.solution_md,
                    itinerary_json = excluded.itinerary_json, why_json = excluded.why_json,
                    practical_json = excluded.practical_json, faq_json = excluded.faq_json,
                    price_usd = excluded.price_usd, duration = excluded.duration,
                    departure = excluded.departure, transport = excluded.transport,
                    requirements = excluded.requirements, cta_text = excluded.cta_text,
                    tagline = excluded.tagline, itinerary_label = excluded.itinerary_label,
                    closing_md = excluded.closing_md',
                [
                    $itemId,
                    (string) ($fm['hook'] ?? ''),
                    (string) ($fm['solution'] ?? ''),
                    $json($fm['itinerary'] ?? []),
                    $json($fm['why'] ?? []),
                    $json($fm['practical'] ?? []),
                    $json($fm['faq'] ?? []),
                    $price,
                    (string) ($fm['duration'] ?? ''),
                    (string) ($fm['departure'] ?? ''),
                    (string) ($fm['transport'] ?? ''),
                    (string) ($fm['requirements'] ?? ''),
                    (string) ($fm['cta_text'] ?? ''),
                    (string) ($fm['tagline'] ?? ''),
                    (string) ($fm['itinerary_label'] ?? ''),
                    (string) ($fm['closing'] ?? ''),
                ]
            );
        }

        $imported++;
    }
}
$say(sprintf('seed: %d items imported, %d unchanged, %d admin-owned (left alone)', $imported, $skipped, $locked));

// ---------------------------------------------------------------------------
// Redirects — docs/url-map.csv is the contract (plan §1.4).
// Only the 301 and 410 rows become redirects; `keep` rows are served by the
// router and must never shadow real content.
// ---------------------------------------------------------------------------
$wanted = [];
foreach (UrlMap::rows() as $row) {
    if ($row['action'] === '301') {
        $wanted[$row['old_path']] = ['to' => $row['target'] !== '' ? $row['target'] : '/', 'status' => 301];
    } elseif ($row['action'] === '410') {
        $wanted[$row['old_path']] = ['to' => '', 'status' => 410];
    }
}
foreach ($wanted as $from => $spec) {
    RedirectRepo::upsert($from, $spec['to'], $spec['status'], 'map');
}

// Drop map-sourced rows that are no longer in the CSV. Rows the admin or a slug
// change created (source != 'map') are left alone.
$removed = 0;
foreach (Db::all("SELECT from_path FROM redirects WHERE source = 'map'") as $row) {
    if (!isset($wanted[(string) $row['from_path']])) {
        Db::run('DELETE FROM redirects WHERE from_path = ?', [(string) $row['from_path']]);
        $removed++;
    }
}
$say(sprintf('seed: %d redirects from the URL map (%d stale removed)', count($wanted), $removed));

$say('seed: done, database at ' . Db::path());
