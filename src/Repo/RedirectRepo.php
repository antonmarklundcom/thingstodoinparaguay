<?php
declare(strict_types=1);

namespace Ttp\Repo;

use Ttp\Db;

final class RedirectRepo
{
    /** @return array{from_path:string,to_path:string,status:int}|null */
    public static function find(string $path): ?array
    {
        $row = Db::one('SELECT from_path, to_path, status FROM redirects WHERE from_path = ?', [$path]);
        if ($row === null) {
            return null;
        }
        $row['status'] = (int) $row['status'];
        return $row;
    }

    public static function hit(string $path): void
    {
        Db::run('UPDATE redirects SET hits = hits + 1 WHERE from_path = ?', [$path]);
    }

    public static function upsert(string $from, string $to, int $status, string $source): void
    {
        Db::run(
            'INSERT INTO redirects (from_path, to_path, status, source, created_at)
             VALUES (?, ?, ?, ?, ?)
             ON CONFLICT(from_path) DO UPDATE SET
                to_path = excluded.to_path, status = excluded.status, source = excluded.source',
            [$from, $to, $status, $source, gmdate('c')]
        );
    }
}
