<?php
declare(strict_types=1);

namespace Ttp;

use PDO;

final class Db
{
    private static ?PDO $pdo = null;
    private static ?string $path = null;

    public static function path(): string
    {
        return self::$path ?? ttp_config()['db_path'];
    }

    /** Point the connection at another file (used by bin/verify.php and tests). */
    public static function use(string $path): void
    {
        self::$pdo  = null;
        self::$path = $path;
    }

    public static function conn(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $path = self::path();
        $dir  = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');

        return self::$pdo = $pdo;
    }

    public static function exists(): bool
    {
        return is_file(self::path());
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        $st = self::conn()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public static function one(string $sql, array $params = []): ?array
    {
        $st = self::conn()->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $st = self::conn()->prepare($sql);
        $st->execute($params);
        $v = $st->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function run(string $sql, array $params = []): int
    {
        $st = self::conn()->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    public static function lastId(): int
    {
        return (int) self::conn()->lastInsertId();
    }

    public static function hasTable(string $name): bool
    {
        return self::value(
            "SELECT 1 FROM sqlite_master WHERE type='table' AND name = ?",
            [$name]
        ) !== null;
    }
}
