<?php
declare(strict_types=1);

namespace Ttp\Admin;

/**
 * The admin session.
 *
 * Web requests get a real PHP session with a hardened cookie: HttpOnly, SameSite,
 * `Secure` over HTTPS, and a path of /admin so the cookie never travels with a
 * public (cacheable) page request. Under the CLI — bin/ scripts and the test
 * runner — the same API is backed by a plain array, so admin behaviour can be
 * tested without an HTTP server.
 */
final class Session
{
    public const COOKIE_NAME = 'ttp_admin';
    public const COOKIE_PATH = '/admin';

    /** @var array<string,mixed>|null Non-null puts the class in array mode. */
    private static ?array $array = null;
    private static bool $started = false;

    /**
     * Array-backed mode: used by the CLI and by tests/admin.test.php.
     * @param array<string,mixed> $data
     */
    public static function useArray(array $data = []): void
    {
        self::$array   = $data;
        self::$started = true;
    }

    public static function start(): void
    {
        if (self::$started) {
            return;
        }
        if (PHP_SAPI === 'cli') {
            self::useArray();
            return;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name(self::COOKIE_NAME);
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => self::COOKIE_PATH,
                'domain'   => '',
                'secure'   => self::isHttps(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            // Refuse a session id the server never issued (session fixation).
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            session_start();
        }
        self::$started = true;
    }

    public static function isHttps(): bool
    {
        if (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        // Hostinger terminates TLS in front of PHP.
        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        if (self::$array !== null) {
            return self::$array[$key] ?? $default;
        }
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        if (self::$array !== null) {
            self::$array[$key] = $value;
            return;
        }
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        self::start();
        if (self::$array !== null) {
            unset(self::$array[$key]);
            return;
        }
        unset($_SESSION[$key]);
    }

    /** New session id, same data — called right after a successful login. */
    public static function regenerate(): void
    {
        self::start();
        if (self::$array === null && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        self::start();
        if (self::$array !== null) {
            self::$array = [];
            return;
        }
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (!headers_sent()) {
                setcookie(self::COOKIE_NAME, '', [
                    'expires'  => time() - 3600,
                    'path'     => self::COOKIE_PATH,
                    'secure'   => self::isHttps(),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }
            session_destroy();
        }
        self::$started = false;
    }

    /** Test helper: forget everything this class remembers between cases. */
    public static function reset(): void
    {
        self::$array   = null;
        self::$started = false;
    }
}
