<?php
declare(strict_types=1);

/**
 * SQLite -> content/ — the backup direction (plan §1.2, §5.1).
 *
 * Everything created or edited in the admin lives only in SQLite; this dumps it
 * back to Markdown with front matter so it is versioned in git like the seed
 * content. The output is exactly what bin/seed.php reads, so export → seed is a
 * round trip.
 *
 * Usage: php bin/export.php [--db=path] [--out=dir] [--quiet]
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

use Ttp\Db;
use Ttp\FrontMatter;

$opts  = getopt('', ['db::', 'out::', 'quiet']);
$quiet = isset($opts['quiet']);
if (!empty($opts['db'])) {
    Db::use((string) $opts['db']);
}
$out = !empty($opts['out']) ? rtrim((string) $opts['out'], '/') : ttp_root() . '/content';

$say = static function (string $m) use ($quiet): void {
    if (!$quiet) {
        echo $m, "\n";
    }
};

if (!Db::exists() || !Db::hasTable('content_items')) {
    fwrite(STDERR, "export: no database at " . Db::path() . " — run bin/migrate.php first\n");
    exit(1);
}

$write = static function (string $file, string $contents) use (&$written, $say): void {
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    if (is_file($file) && (string) file_get_contents($file) === $contents) {
        return;                     // byte-identical: leave the mtime alone
    }
    file_put_contents($file, $contents);
    $written++;
    $say('  + ' . $file);
};
$written = 0;

// ---------------------------------------------------------------------------
// Categories
// ---------------------------------------------------------------------------
foreach (Db::all('SELECT * FROM categories ORDER BY slug') as $row) {
    $write($out . '/category/' . $row['slug'] . '.md', FrontMatter::render([
        'slug'             => (string) $row['slug'],
        'name'             => (string) $row['name'],
        'description'      => (string) $row['description'],
        'meta_title'       => (string) $row['meta_title'],
        'meta_description' => (string) $row['meta_description'],
        'sort_order'       => (int) $row['sort_order'],
    ], ''));
}

// ---------------------------------------------------------------------------
// Content items
// ---------------------------------------------------------------------------
$items = Db::all(
    'SELECT i.*, c.slug AS category_slug
     FROM content_items i LEFT JOIN categories c ON c.id = i.category_id
     ORDER BY i.type, i.slug'
);

foreach ($items as $item) {
    $type = (string) $item['type'];
    $data = [
        'type'         => $type,
        'title'        => (string) $item['title'],
        'status'       => (string) $item['status'],
        'published_at' => (string) ($item['published_at'] ?? ''),
        'updated_at'   => (string) $item['updated_at'],
    ];

    if ($type === 'post') {
        $data['category'] = (string) ($item['category_slug'] ?? '');
        $data['tags'] = array_column(
            Db::all(
                'SELECT t.slug FROM tags t JOIN item_tags it ON it.tag_id = t.id
                 WHERE it.item_id = ? ORDER BY t.slug',
                [(int) $item['id']]
            ),
            'slug'
        );
    }

    if (in_array($type, ['tour', 'service'], true)) {
        $details = Db::one('SELECT * FROM tour_details WHERE item_id = ?', [(int) $item['id']]);
        if ($details !== null) {
            $decode = static function (mixed $json): array {
                $v = json_decode((string) $json, true);
                return is_array($v) ? $v : [];
            };
            $data['tagline']         = (string) $details['tagline'];
            $data['cta_text']        = (string) $details['cta_text'];
            $data['itinerary_label'] = (string) $details['itinerary_label'];
            $data['hook']            = (string) $details['hook_md'];
            $data['solution']        = (string) $details['solution_md'];
            $data['itinerary']       = $decode($details['itinerary_json']);
            $data['why']             = $decode($details['why_json']);
            $data['practical']       = $decode($details['practical_json']);
            $data['faq']             = $decode($details['faq_json']);
            $data['closing']         = (string) $details['closing_md'];
            $data['price_usd']       = $details['price_usd'] === null ? null : (float) $details['price_usd'];
            foreach (['duration', 'departure', 'transport', 'requirements'] as $key) {
                if ((string) $details[$key] !== '') {
                    $data[$key] = (string) $details[$key];
                }
            }
        }
    }

    $data['excerpt']          = (string) $item['excerpt'];
    $data['meta_title']       = (string) $item['meta_title'];
    $data['meta_description'] = (string) $item['meta_description'];
    if ((int) $item['noindex'] === 1) {
        $data['noindex'] = true;
    }
    if ((int) $item['sort_order'] !== 0) {
        $data['sort_order'] = (int) $item['sort_order'];
    }
    $data['source'] = (string) $item['source'];

    $write($out . '/' . $type . '/' . $item['slug'] . '.md', FrontMatter::render($data, (string) $item['body_md']));
}

$say(sprintf('export: %d file(s) written to %s (%d items, from %s)', $written, $out, count($items), Db::path()));
