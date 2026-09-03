<?php
declare(strict_types=1);

namespace Ttp\Admin;

use Ttp\Cache;
use Ttp\Db;
use Ttp\Markdown;
use Ttp\Repo\CategoryRepo;
use Ttp\Repo\ContentRepo;
use Ttp\Repo\RedirectRepo;
use Ttp\Router;
use Ttp\SeoScore;
use Ttp\Str;

/**
 * Every write the admin makes to content_items goes through here (plan §5.2).
 *
 * The three things it guarantees, which is why the controllers stay thin:
 *   • a row saved in the panel is marked `source = 'admin'`, so bin/seed.php
 *     leaves it alone for ever after;
 *   • renaming the slug of a *published* item leaves a 301 behind, so the URL
 *     Google already knows keeps working (plan §1.4);
 *   • anything that changes what a visitor would see drops the affected pages
 *     from the HTML cache, sitemap and feed included.
 */
final class ContentWriter
{
    public const TYPES    = ['post', 'page', 'tour', 'service'];
    public const STATUSES = ['draft', 'published', 'scheduled'];

    /**
     * Slugs the router resolves before it ever looks at content, so an item using
     * one would be unreachable. `tours`, `services` and `blog` are deliberately
     * absent: /services/ is a real page whose copy is the type index's intro.
     */
    public const RESERVED_SLUGS = ['admin', 'category', 'assets', 'media', 'feed', 'sitemap', 'robots'];

    /**
     * Create or update an item.
     *
     * @param array<string,mixed> $input raw form input; every value is normalised here
     * @return array{ok:bool,errors:array<string,string>,id:int,item:array<string,mixed>|null,notices:array<int,string>}
     */
    public static function save(array $input, ?int $id = null): array
    {
        $errors  = [];
        $notices = [];

        $existing = $id !== null && $id > 0 ? Db::one('SELECT * FROM content_items WHERE id = ?', [$id]) : null;
        if ($id !== null && $id > 0 && $existing === null) {
            return ['ok' => false, 'errors' => ['id' => 'That item no longer exists.'], 'id' => 0, 'item' => null, 'notices' => []];
        }

        $type = (string) ($input['type'] ?? ($existing['type'] ?? 'post'));
        if (!in_array($type, self::TYPES, true)) {
            $errors['type'] = 'Pick one of: ' . implode(', ', self::TYPES) . '.';
            $type = 'post';
        }

        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = 'A title is required.';
        }

        $slug = Str::slug((string) ($input['slug'] ?? ''));
        if ($slug === '') {
            $slug = Str::slug($title);
        }
        if ($slug === '') {
            $errors['slug'] = 'A slug is required — it is the page address.';
        } elseif (preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug) !== 1) {
            $errors['slug'] = 'Use lower-case letters, numbers and hyphens only.';
        } elseif (in_array($slug, self::RESERVED_SLUGS, true)) {
            $errors['slug'] = '“' . $slug . '” is reserved by the site itself. Pick another.';
        } else {
            $clash = Db::one('SELECT id FROM content_items WHERE slug = ? AND id <> ?', [$slug, (int) ($existing['id'] ?? 0)]);
            if ($clash !== null) {
                $errors['slug'] = 'Another item already uses /' . $slug . '/.';
            }
        }

        $status = (string) ($input['status'] ?? ($existing['status'] ?? 'draft'));
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'draft';
        }

        $publishedAt = self::normaliseDate((string) ($input['published_at'] ?? ($existing['published_at'] ?? '')));
        if ($status === 'published' && $publishedAt === null) {
            $publishedAt = gmdate('c');
        }
        if ($status === 'scheduled') {
            if ($publishedAt === null) {
                $errors['published_at'] = 'A scheduled item needs a date and time to go live.';
            } elseif (strtotime($publishedAt) <= time()) {
                // A date in the past is not a schedule — it is a publish.
                $status    = 'published';
                $notices[] = 'The publish date had already passed, so the item was published straight away.';
            }
        }

        $categoryId = self::intOrNull($input['category_id'] ?? ($existing['category_id'] ?? null));
        if ($categoryId !== null && Db::value('SELECT 1 FROM categories WHERE id = ?', [$categoryId]) === null) {
            $categoryId = null;
        }
        $coverId = self::mediaIdOrNull($input['cover_media_id'] ?? ($existing['cover_media_id'] ?? null));
        $ogId    = self::mediaIdOrNull($input['og_image_media_id'] ?? ($existing['og_image_media_id'] ?? null));

        $bodyMd  = self::normaliseText((string) ($input['body_md'] ?? ($existing['body_md'] ?? '')));
        $details = in_array($type, ['tour', 'service'], true) ? self::detailsFromInput($input, $existing) : null;

        $excerpt = trim((string) ($input['excerpt'] ?? ''));
        $graded  = SeoScore::document(['body_md' => $bodyMd], $details);
        if ($excerpt === '') {
            $excerpt = Markdown::truncate(Markdown::toText($graded), 155);
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors, 'id' => (int) ($existing['id'] ?? 0), 'item' => null, 'notices' => $notices];
        }

        $draft = [
            'type'             => $type,
            'slug'             => $slug,
            'title'            => $title,
            'meta_title'       => trim((string) ($input['meta_title'] ?? '')),
            'meta_description' => trim((string) ($input['meta_description'] ?? '')),
            'excerpt'          => $excerpt,
            'body_md'          => $bodyMd,
            'focus_keyword'    => trim((string) ($input['focus_keyword'] ?? '')),
            'cover_media_id'   => $coverId,
        ];
        $score = SeoScore::forItem($draft, $details);

        $fields = $draft + [
            'status'             => $status,
            'published_at'       => $publishedAt,
            'updated_at'         => gmdate('c'),
            'body_html'          => Markdown::toHtml($bodyMd),
            'category_id'        => $type === 'post' ? $categoryId : null,
            'canonical_override' => trim((string) ($input['canonical_override'] ?? '')),
            'noindex'            => !empty($input['noindex']) ? 1 : 0,
            'og_image_media_id'  => $ogId,
            'seo_score'          => $score->score,
            'word_count'         => $score->wordCount,
            'sort_order'         => (int) ($input['sort_order'] ?? ($existing['sort_order'] ?? 0)),
            'source'             => 'admin',
            'content_hash'       => '',
        ];

        $wasPublished = $existing !== null && (string) $existing['status'] === 'published';
        $oldSlug      = $existing === null ? '' : (string) $existing['slug'];
        $stalePaths   = $existing === null ? [] : self::affectedPaths($existing);

        Db::conn()->beginTransaction();
        try {
            if ($existing === null) {
                $columns = implode(', ', array_keys($fields));
                $holders = implode(', ', array_fill(0, count($fields), '?'));
                Db::run("INSERT INTO content_items ({$columns}) VALUES ({$holders})", array_values($fields));
                $itemId = Db::lastId();
            } else {
                $itemId = (int) $existing['id'];
                $set    = implode(', ', array_map(static fn (string $c): string => $c . ' = ?', array_keys($fields)));
                Db::run("UPDATE content_items SET {$set} WHERE id = ?", array_merge(array_values($fields), [$itemId]));
            }

            self::saveTags($itemId, $input['tags'] ?? null, $type);
            if ($details !== null) {
                self::saveDetails($itemId, $details);
            }

            // The URL contract: a published page that changes address leaves a 301.
            if ($wasPublished && $oldSlug !== '' && $oldSlug !== $slug) {
                RedirectRepo::upsert('/' . $oldSlug . '/', '/' . $slug . '/', 301, 'slug-change');
                self::retargetRedirects('/' . $oldSlug . '/', '/' . $slug . '/');
                $notices[] = 'Anyone using the old address /' . $oldSlug . '/ is now redirected to /' . $slug . '/.';
            }
            // …and a live page must not sit behind a redirect from the old site,
            // which the router would answer first. Drafts leave the URL map alone:
            // an unpublished slug has no claim on an address that still 301s.
            $shadow = $status === 'published' ? RedirectRepo::find('/' . $slug . '/') : null;
            if ($shadow !== null) {
                Db::run('DELETE FROM redirects WHERE from_path = ?', ['/' . $slug . '/']);
                $notices[] = 'A redirect from /' . $slug . '/ was removed so this page can be reached.';
            }

            Db::conn()->commit();
        } catch (\Throwable $e) {
            Db::conn()->rollBack();
            throw $e;
        }

        $item = ContentRepo::findById($itemId);
        self::invalidate(array_merge($stalePaths, $item === null ? [] : self::affectedPaths($item)));

        return ['ok' => true, 'errors' => [], 'id' => $itemId, 'item' => $item, 'notices' => $notices];
    }

    /** Publish (or unpublish) without going through the whole form. */
    public static function setStatus(int $id, string $status): bool
    {
        if (!in_array($status, self::STATUSES, true)) {
            return false;
        }
        $item = Db::one('SELECT * FROM content_items WHERE id = ?', [$id]);
        if ($item === null) {
            return false;
        }
        $publishedAt = $item['published_at'];
        if ($status === 'published' && ($publishedAt === null || (string) $publishedAt === '')) {
            $publishedAt = gmdate('c');
        }
        Db::run(
            "UPDATE content_items SET status = ?, published_at = ?, updated_at = ?, source = 'admin' WHERE id = ?",
            [$status, $publishedAt, gmdate('c'), $id]
        );
        self::invalidate(self::affectedPaths($item));
        return true;
    }

    public static function delete(int $id): bool
    {
        $item = Db::one('SELECT * FROM content_items WHERE id = ?', [$id]);
        if ($item === null) {
            return false;
        }
        $paths = self::affectedPaths($item);
        // A published URL never simply disappears: it becomes a 301 to its index.
        if ((string) $item['status'] === 'published') {
            RedirectRepo::upsert('/' . $item['slug'] . '/', self::indexPathFor((string) $item['type']), 301, 'slug-change');
        }
        Db::run('DELETE FROM content_items WHERE id = ?', [$id]);
        self::invalidate($paths);
        return true;
    }

    /**
     * Publish everything whose scheduled time has arrived.
     *
     * Called by bin/publish-due.php (put it on cron) and on every admin page load,
     * so a scheduled post goes live even on a host without cron as soon as anyone
     * opens the panel.
     *
     * @return array<int,string> the paths that went live
     */
    public static function publishDue(): array
    {
        $now  = gmdate('c');
        $rows = Db::all(
            "SELECT * FROM content_items
             WHERE status = 'scheduled' AND published_at IS NOT NULL AND published_at <= ?",
            [$now]
        );
        $published = [];
        foreach ($rows as $row) {
            Db::run("UPDATE content_items SET status = 'published', updated_at = ? WHERE id = ?", [$now, (int) $row['id']]);
            $published[] = '/' . $row['slug'] . '/';
            self::invalidate(self::affectedPaths($row));
        }
        return $published;
    }

    /**
     * Every cached page an item appears on: its own URL, the indexes that list it,
     * and the machine files that enumerate it.
     *
     * @return array<int,string>
     */
    public static function affectedPaths(array $item): array
    {
        $paths = [
            '/' . trim((string) $item['slug'], '/') . '/',
            '/',
            '/sitemap.xml',
            '/feed.xml',
            Router::ATTRACTIONS_PATH,
        ];

        $type = (string) $item['type'];
        if ($type === 'post') {
            $paths[] = Router::BLOG_PATH;
            $pages   = (int) ceil(max(1, ContentRepo::countPosts() + 1) / ContentRepo::POSTS_PER_PAGE);
            for ($page = 2; $page <= $pages + 1; $page++) {
                $paths[] = Router::BLOG_PATH . 'page/' . $page . '/';
            }
            $categoryId = self::intOrNull($item['category_id'] ?? null);
            if ($categoryId !== null) {
                $category = Db::one('SELECT * FROM categories WHERE id = ?', [$categoryId]);
                if ($category !== null) {
                    $base    = CategoryRepo::path($category);
                    $paths[] = $base;
                    for ($page = 2; $page <= $pages + 1; $page++) {
                        $paths[] = $base . 'page/' . $page . '/';
                    }
                }
            }
        } elseif ($type === 'tour' || $type === 'service') {
            $paths[] = self::indexPathFor($type);
        }

        return array_values(array_unique($paths));
    }

    /** @param array<int,string> $paths */
    public static function invalidate(array $paths): int
    {
        return Cache::forgetPaths(array_values(array_unique($paths)));
    }

    public static function indexPathFor(string $type): string
    {
        return match ($type) {
            'tour'    => Router::TOURS_PATH,
            'service' => Router::SERVICES_PATH,
            'post'    => Router::BLOG_PATH,
            default   => '/',
        };
    }

    // -----------------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------------

    /**
     * A slug can be renamed twice. When /a/ → /b/ and later /b/ → /c/, the first
     * redirect must follow, otherwise the oldest address dead-ends on a redirect
     * to a 404.
     */
    private static function retargetRedirects(string $from, string $to): void
    {
        Db::run(
            "UPDATE redirects SET to_path = ? WHERE to_path = ? AND from_path <> ? AND status = 301",
            [$to, $from, $from]
        );
    }

    /** @return array<string,mixed> the tour_details shape, ready for saveDetails() */
    private static function detailsFromInput(array $input, ?array $existing): array
    {
        $rows = static function (mixed $value, array $keys): array {
            $out = [];
            foreach ((array) $value as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $clean = [];
                foreach ($keys as $key) {
                    $clean[$key] = self::normaliseText((string) ($row[$key] ?? ''));
                }
                // A row where every field is blank is a leftover empty form row.
                if (implode('', $clean) !== '') {
                    $out[] = $clean;
                }
            }
            return $out;
        };

        $price = $input['price_usd'] ?? null;
        $price = ($price === null || trim((string) $price) === '' || (float) $price <= 0) ? null : (float) $price;

        return [
            'hook_md'         => self::normaliseText((string) ($input['hook'] ?? '')),
            'solution_md'     => self::normaliseText((string) ($input['solution'] ?? '')),
            'closing_md'      => self::normaliseText((string) ($input['closing'] ?? '')),
            'itinerary'       => $rows($input['itinerary'] ?? [], ['title', 'body']),
            'why'             => $rows($input['why'] ?? [], ['title', 'body']),
            'practical'       => $rows($input['practical'] ?? [], ['label', 'value']),
            'faq'             => $rows($input['faq'] ?? [], ['q', 'a']),
            'price_usd'       => $price,
            'duration'        => trim((string) ($input['duration'] ?? '')),
            'departure'       => trim((string) ($input['departure'] ?? '')),
            'transport'       => trim((string) ($input['transport'] ?? '')),
            'requirements'    => trim((string) ($input['requirements'] ?? '')),
            'cta_text'        => trim((string) ($input['cta_text'] ?? '')),
            'tagline'         => trim((string) ($input['tagline'] ?? '')),
            'itinerary_label' => trim((string) ($input['itinerary_label'] ?? '')),
        ];
    }

    private static function saveDetails(int $itemId, array $details): void
    {
        $json = static fn (mixed $v): string => (string) json_encode(
            is_array($v) ? array_values($v) : [],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

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
                $details['hook_md'],
                $details['solution_md'],
                $json($details['itinerary']),
                $json($details['why']),
                $json($details['practical']),
                $json($details['faq']),
                $details['price_usd'],
                $details['duration'],
                $details['departure'],
                $details['transport'],
                $details['requirements'],
                $details['cta_text'],
                $details['tagline'],
                $details['itinerary_label'],
                $details['closing_md'],
            ]
        );
    }

    private static function saveTags(int $itemId, mixed $tags, string $type): void
    {
        if ($type !== 'post') {
            Db::run('DELETE FROM item_tags WHERE item_id = ?', [$itemId]);
            return;
        }
        $names = is_array($tags) ? $tags : (preg_split('/\s*,\s*/', trim((string) $tags)) ?: []);

        Db::run('DELETE FROM item_tags WHERE item_id = ?', [$itemId]);
        foreach ($names as $name) {
            $name = trim((string) $name);
            $slug = Str::slug($name);
            if ($name === '' || $slug === '') {
                continue;
            }
            Db::run(
                'INSERT INTO tags (slug, name) VALUES (?, ?) ON CONFLICT(slug) DO UPDATE SET name = excluded.name',
                [$slug, $name]
            );
            $tagId = (int) Db::value('SELECT id FROM tags WHERE slug = ?', [$slug]);
            Db::run('INSERT OR IGNORE INTO item_tags (item_id, tag_id) VALUES (?, ?)', [$itemId, $tagId]);
        }
    }

    /** `2026-09-03T14:00` from a datetime-local field, or an ISO string, to UTC ISO-8601. */
    public static function normaliseDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);
        return $ts === false ? null : gmdate('c', $ts);
    }

    private static function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === '0') {
            return null;
        }
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }

    private static function mediaIdOrNull(mixed $value): ?int
    {
        $id = self::intOrNull($value);
        if ($id === null) {
            return null;
        }
        return Db::value('SELECT 1 FROM media WHERE id = ?', [$id]) === null ? null : $id;
    }

    /** Normalise line endings and strip control characters, keeping tabs/newlines. */
    private static function normaliseText(string $value): string
    {
        $value = str_replace("\r\n", "\n", $value);
        return trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value));
    }
}
