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

    /**
     * Leads whose notification (email + VenderCRM) has not gone out yet, oldest first.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function pending(int $limit = 50): array
    {
        return Db::all('SELECT * FROM leads WHERE forwarded = 0 ORDER BY id ASC LIMIT ' . max(1, $limit));
    }

    public static function markForwarded(int $id): void
    {
        Db::run('UPDATE leads SET forwarded = 1 WHERE id = ?', [$id]);
    }
}
