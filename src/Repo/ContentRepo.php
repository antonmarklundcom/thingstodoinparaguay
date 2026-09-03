<?php
declare(strict_types=1);

namespace Ttp\Repo;

use Ttp\Db;

/**
 * Read access to content_items (+ tour_details). Phase S3 templates must go
 * through this class rather than touching SQL (plan §6.1).
 */
final class ContentRepo
{
    public const POSTS_PER_PAGE = 9;

    private const SELECT = 'SELECT i.*, c.slug AS category_slug, c.name AS category_name
                            FROM content_items i
                            LEFT JOIN categories c ON c.id = i.category_id';

    /** @return array<string,mixed>|null */
    public static function findBySlug(string $slug, bool $publishedOnly = true): ?array
    {
        $sql = self::SELECT . ' WHERE i.slug = ?';
        if ($publishedOnly) {
            $sql .= " AND i.status = 'published'";
        }
        $row = Db::one($sql, [$slug]);
        return $row === null ? null : self::hydrate($row);
    }

    /** @return array<string,mixed>|null */
    public static function findById(int $id): ?array
    {
        $row = Db::one(self::SELECT . ' WHERE i.id = ?', [$id]);
        return $row === null ? null : self::hydrate($row);
    }

    /** @return array<int,array<string,mixed>> */
    public static function published(string $type, int $limit = 0, int $offset = 0): array
    {
        $sql = self::SELECT . " WHERE i.type = ? AND i.status = 'published'
                                ORDER BY i.sort_order ASC, i.published_at DESC, i.id DESC";
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit . ' OFFSET ' . max($offset, 0);
        }
        return array_map([self::class, 'hydrate'], Db::all($sql, [$type]));
    }

    /** Every published item of any type — used by the sitemap. @return array<int,array<string,mixed>> */
    public static function allPublished(): array
    {
        $sql = self::SELECT . " WHERE i.status = 'published'
                                ORDER BY i.type ASC, i.published_at DESC, i.id DESC";
        return array_map([self::class, 'hydrate'], Db::all($sql));
    }

    /** @return array<int,array<string,mixed>> */
    public static function postsPage(int $page, int $perPage = self::POSTS_PER_PAGE): array
    {
        return self::published('post', $perPage, ($page - 1) * $perPage);
    }

    public static function countPosts(?int $categoryId = null): int
    {
        if ($categoryId === null) {
            return (int) Db::value("SELECT COUNT(*) FROM content_items WHERE type='post' AND status='published'");
        }
        return (int) Db::value(
            "SELECT COUNT(*) FROM content_items WHERE type='post' AND status='published' AND category_id = ?",
            [$categoryId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function postsByCategory(int $categoryId, int $page = 1, int $perPage = self::POSTS_PER_PAGE): array
    {
        $sql = self::SELECT . " WHERE i.type='post' AND i.status='published' AND i.category_id = ?
                                ORDER BY i.published_at DESC, i.id DESC
                                LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage);
        return array_map([self::class, 'hydrate'], Db::all($sql, [$categoryId]));
    }

    /** Other published posts sharing this one's category, newest first. @return array<int,array<string,mixed>> */
    public static function related(array $item, int $limit = 3): array
    {
        if (empty($item['category_id'])) {
            $sql = self::SELECT . " WHERE i.type = ? AND i.status='published' AND i.id <> ?
                                    ORDER BY i.published_at DESC LIMIT {$limit}";
            return array_map([self::class, 'hydrate'], Db::all($sql, [$item['type'], $item['id']]));
        }
        $sql = self::SELECT . " WHERE i.type = ? AND i.status='published' AND i.id <> ? AND i.category_id = ?
                                ORDER BY i.published_at DESC LIMIT {$limit}";
        return array_map([self::class, 'hydrate'], Db::all($sql, [$item['type'], $item['id'], $item['category_id']]));
    }

    /** @return array{prev:?array<string,mixed>,next:?array<string,mixed>} */
    public static function neighbours(array $item): array
    {
        $prev = Db::one(
            self::SELECT . " WHERE i.type = ? AND i.status='published' AND i.published_at < ?
                             ORDER BY i.published_at DESC LIMIT 1",
            [$item['type'], $item['published_at']]
        );
        $next = Db::one(
            self::SELECT . " WHERE i.type = ? AND i.status='published' AND i.published_at > ?
                             ORDER BY i.published_at ASC LIMIT 1",
            [$item['type'], $item['published_at']]
        );
        return [
            'prev' => $prev === null ? null : self::hydrate($prev),
            'next' => $next === null ? null : self::hydrate($next),
        ];
    }

    /** @return array<int,array{slug:string,name:string}> */
    public static function tagsFor(int $itemId): array
    {
        return Db::all(
            'SELECT t.slug, t.name FROM tags t
             JOIN item_tags it ON it.tag_id = t.id
             WHERE it.item_id = ? ORDER BY t.name',
            [$itemId]
        );
    }

    /** @return array<string,mixed>|null */
    public static function details(int $itemId): ?array
    {
        $row = Db::one('SELECT * FROM tour_details WHERE item_id = ?', [$itemId]);
        if ($row === null) {
            return null;
        }
        foreach (['itinerary_json', 'why_json', 'practical_json', 'faq_json'] as $key) {
            $decoded = json_decode((string) $row[$key], true);
            $row[substr($key, 0, -5)] = is_array($decoded) ? $decoded : [];
        }
        return $row;
    }

    /** The public path for an item. Posts, pages, tours and services are all flat. */
    public static function path(array $item): string
    {
        return '/' . trim((string) $item['slug'], '/') . '/';
    }

    /** @return array<string,mixed> */
    private static function hydrate(array $row): array
    {
        $row['id']      = (int) $row['id'];
        $row['noindex'] = (int) $row['noindex'];
        $row['path']    = self::path($row);
        return $row;
    }
}
