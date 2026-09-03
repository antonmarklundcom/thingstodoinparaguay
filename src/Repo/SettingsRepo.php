<?php
declare(strict_types=1);

namespace Ttp\Repo;

use Ttp\Db;

final class SettingsRepo
{
    /** @var array<string,string>|null */
    private static ?array $cache = null;

    public static function get(string $key, string $default = ''): string
    {
        self::$cache ??= self::load();
        $value = self::$cache[$key] ?? '';
        return $value !== '' ? $value : $default;
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        return self::$cache ??= self::load();
    }

    public static function set(string $key, string $value): void
    {
        Db::run(
            'INSERT INTO settings (key, value) VALUES (?, ?)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value',
            [$key, $value]
        );
        self::$cache = null;
    }

    /** @return array<string,string> */
    private static function load(): array
    {
        if (!Db::exists() || !Db::hasTable('settings')) {
            return [];
        }
        $out = [];
        foreach (Db::all('SELECT key, value FROM settings') as $row) {
            $out[(string) $row['key']] = (string) $row['value'];
        }
        return $out;
    }
}
