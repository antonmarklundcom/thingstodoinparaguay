<?php
declare(strict_types=1);

namespace Ttp\Repo;

use Ttp\Db;

/** Writes for the `subscribers` table — the newsletter form (plan §6.1). */
final class SubscriberRepo
{
    public static function exists(string $email): bool
    {
        return Db::value('SELECT 1 FROM subscribers WHERE email = ?', [$email]) !== null;
    }

    /**
     * Idempotent by email. Returns true when a new row was created.
     *
     * `INSERT OR IGNORE` rather than an `exists()` check followed by an
     * insert: two submits for the same email racing each other (a
     * double-click, two open tabs) would otherwise both pass the check and
     * the second `INSERT` would throw on the `email` UNIQUE constraint
     * instead of the request just redirecting like a normal signup.
     */
    public static function create(string $email, string $source): bool
    {
        $affected = Db::run(
            'INSERT OR IGNORE INTO subscribers (email, created_at, source) VALUES (?, ?, ?)',
            [$email, gmdate('c'), $source]
        );
        return $affected > 0;
    }
}
