<?php
declare(strict_types=1);

namespace Ttp\Repo;

use Ttp\Db;

/** Writes for the `leads` table — the public contact/quote form (plan §6.1). */
final class LeadRepo
{
    public static function create(string $name, string $email, string $phone, string $message, string $pagePath): int
    {
        Db::run(
            'INSERT INTO leads (name, email, phone, message, page_path, created_at, forwarded)
             VALUES (?, ?, ?, ?, ?, ?, 0)',
            [$name, $email, $phone, $message, $pagePath, gmdate('c')]
        );
        return Db::lastId();
    }

    public static function markForwarded(int $id): void
    {
        Db::run('UPDATE leads SET forwarded = 1 WHERE id = ?', [$id]);
    }
}
