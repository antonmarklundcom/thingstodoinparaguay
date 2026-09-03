<?php
declare(strict_types=1);

namespace Ttp\Repo;

use Ttp\Db;

final class MediaRepo
{
    /** @return array<string,mixed>|null */
    public static function find(?int $id): ?array
    {
        if ($id === null || $id <= 0) {
            return null;
        }
        return Db::one('SELECT * FROM media WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public static function findByPath(string $path): ?array
    {
        return Db::one('SELECT * FROM media WHERE path = ?', [$path]);
    }

    public static function upsert(string $path, string $alt = '', string $mime = '', ?int $width = null, ?int $height = null): int
    {
        $existing = self::findByPath($path);
        if ($existing !== null) {
            if ($alt !== '') {
                Db::run('UPDATE media SET alt = ? WHERE id = ?', [$alt, (int) $existing['id']]);
            }
            return (int) $existing['id'];
        }
        Db::run(
            'INSERT INTO media (filename, path, width, height, alt, mime, sizes_json, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [basename($path), $path, $width, $height, $alt, $mime, '[]', gmdate('c')]
        );
        return Db::lastId();
    }
}
