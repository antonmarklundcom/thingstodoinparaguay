<?php
declare(strict_types=1);

namespace Ttp\Repo;

use Ttp\Db;

final class CategoryRepo
{
    /** @return array<string,mixed>|null */
    public static function findBySlug(string $slug): ?array
    {
        return Db::one('SELECT * FROM categories WHERE slug = ?', [$slug]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return Db::all('SELECT * FROM categories ORDER BY sort_order ASC, name ASC');
    }

    /** Categories that actually have published posts — the only ones worth listing. */
    public static function withPosts(): array
    {
        return Db::all(
            "SELECT c.*, COUNT(i.id) AS post_count
             FROM categories c
             JOIN content_items i ON i.category_id = c.id AND i.type='post' AND i.status='published'
             GROUP BY c.id ORDER BY c.sort_order ASC, c.name ASC"
        );
    }

    public static function path(array $category): string
    {
        return '/category/' . $category['slug'] . '/';
    }
}
