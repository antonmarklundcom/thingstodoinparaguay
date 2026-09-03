<?php
declare(strict_types=1);

namespace Ttp;

/**
 * Full-page HTML cache (plan §1.9). Keyed by request path only — query strings
 * and anything under /admin/ or any non-GET request bypass it entirely, so the
 * O2 panel and the S3 forms can never be served stale.
 */
final class Cache
{
    private static ?string $dirOverride = null;
    private static ?int $ttlOverride = null;

    /** Point the cache somewhere else. Used by tests and by bin/verify.php. */
    public static function use(string $dir, ?int $ttl = null): void
    {
        self::$dirOverride = rtrim($dir, '/');
        self::$ttlOverride = $ttl;
    }

    public static function reset(): void
    {
        self::$dirOverride = null;
        self::$ttlOverride = null;
    }

    public static function dir(): string
    {
        return self::$dirOverride ?? rtrim(ttp_config()['cache_dir'], '/');
    }

    public static function ttl(): int
    {
        return self::$ttlOverride ?? (int) ttp_config()['cache_ttl'];
    }

    public static function enabled(): bool
    {
        return self::ttl() > 0;
    }

    public static function cacheable(string $method, string $path, string $query): bool
    {
        if (!self::enabled() || $method !== 'GET' || $query !== '') {
            return false;
        }
        return !str_starts_with($path, '/admin');
    }

    public static function file(string $path): string
    {
        return self::dir() . '/' . sha1($path) . '.html';
    }

    /** The cached body, or null when there is no fresh entry. */
    public static function get(string $path): ?string
    {
        $file = self::file($path);
        if (!is_file($file)) {
            return null;
        }
        if (time() - (int) filemtime($file) > self::ttl()) {
            @unlink($file);
            return null;
        }
        $body = file_get_contents($file);
        return $body === false ? null : $body;
    }

    public static function put(string $path, string $body): void
    {
        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }
        $file = self::file($path);
        $tmp  = $file . '.' . getmypid() . '.tmp';
        if (file_put_contents($tmp, $body, LOCK_EX) === false) {
            return;
        }
        @rename($tmp, $file);
    }

    /** Drop one path. Returns true when something was removed. */
    public static function forget(string $path): bool
    {
        $file = self::file($path);
        return is_file($file) && @unlink($file);
    }

    /** Drop several paths at once (used after a publish in O2). */
    public static function forgetPaths(array $paths): int
    {
        $n = 0;
        foreach ($paths as $path) {
            if (self::forget((string) $path)) {
                $n++;
            }
        }
        return $n;
    }

    /** Empty the whole cache. Returns the number of files removed. */
    public static function flush(): int
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            return 0;
        }
        $n = 0;
        foreach ((array) glob($dir . '/*.html') as $file) {
            if (is_string($file) && @unlink($file)) {
                $n++;
            }
        }
        foreach ((array) glob($dir . '/*.tmp') as $file) {
            if (is_string($file)) {
                @unlink($file);
            }
        }
        return $n;
    }
}
