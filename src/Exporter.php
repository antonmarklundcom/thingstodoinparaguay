<?php
declare(strict_types=1);

namespace Ttp;

/**
 * SQLite -> content/ — the backup direction (plan §1.2, §5.1).
 *
 * Everything created or edited in the admin lives only in SQLite; this writes it
 * back out as Markdown with front matter so it is versioned in git like the seed
 * content. The output is exactly what bin/seed.php reads, so export → seed is a
 * round trip.
 *
 * bin/export.php is the CLI wrapper; the admin's "Download backup" zips the same
 * output (src/Admin/Backup.php).
 */
final class Exporter
{
    /**
     * @param callable(string):void|null $log called with each file written
     * @return array{files:int,items:int} what was written
     */
    public static function run(string $outDir, ?callable $log = null): array
    {
        $outDir  = rtrim($outDir, '/');
        $written = 0;

        $write = static function (string $file, string $contents) use (&$written, $log): void {
            $dir = dirname($file);
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException('export: cannot create ' . $dir);
            }
            if (is_file($file) && (string) file_get_contents($file) === $contents) {
                return;                     // byte-identical: leave the mtime alone
            }
            if (file_put_contents($file, $contents) === false) {
                throw new \RuntimeException('export: cannot write ' . $file);
            }
            $written++;
            if ($log !== null) {
                $log('  + ' . $file);
            }
        };

        foreach (Db::all('SELECT * FROM categories ORDER BY slug') as $row) {
            $write($outDir . '/category/' . $row['slug'] . '.md', FrontMatter::render([
                'slug'             => (string) $row['slug'],
                'name'             => (string) $row['name'],
                'description'      => (string) $row['description'],
                'meta_title'       => (string) $row['meta_title'],
                'meta_description' => (string) $row['meta_description'],
                'sort_order'       => (int) $row['sort_order'],
            ], ''));
        }

        $items = Db::all(
            'SELECT i.*, c.slug AS category_slug
             FROM content_items i LEFT JOIN categories c ON c.id = i.category_id
             ORDER BY i.type, i.slug'
        );

        foreach ($items as $item) {
            $write(
                $outDir . '/' . $item['type'] . '/' . $item['slug'] . '.md',
                FrontMatter::render(self::frontMatter($item), (string) $item['body_md'])
            );
        }

        return ['files' => $written, 'items' => count($items)];
    }

    /**
     * The cover as a size-less base path ("/media/generated/foo", no "-1280.webp"),
     * the form bin/seed.php reads back. Null when there is no cover or its file
     * does not follow the "<base>-<width>.<ext>" naming both webimg and the
     * admin uploader use.
     *
     * @return array{base:string,alt:string}|null
     */
    private static function coverBase(array $item): ?array
    {
        $media = Repo\MediaRepo::find(empty($item['cover_media_id']) ? null : (int) $item['cover_media_id']);
        if ($media === null || preg_match('#^(/media/.+)-\d+\.[a-z0-9]+$#i', (string) $media['path'], $m) !== 1) {
            return null;
        }
        return ['base' => $m[1], 'alt' => (string) $media['alt']];
    }

    /** @return array<string,mixed> the front matter bin/seed.php reads back */
    private static function frontMatter(array $item): array
    {
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

        $cover = self::coverBase($item);
        if ($cover !== null) {
            $data['cover']     = $cover['base'];
            $data['cover_alt'] = $cover['alt'];
        }

        $data['excerpt']          = (string) $item['excerpt'];
        $data['meta_title']       = (string) $item['meta_title'];
        $data['meta_description'] = (string) $item['meta_description'];
        if (trim((string) ($item['focus_keyword'] ?? '')) !== '') {
            $data['focus_keyword'] = (string) $item['focus_keyword'];
        }
        if ((int) $item['noindex'] === 1) {
            $data['noindex'] = true;
        }
        if ((int) $item['sort_order'] !== 0) {
            $data['sort_order'] = (int) $item['sort_order'];
        }
        $data['source'] = (string) $item['source'];

        return $data;
    }
}
