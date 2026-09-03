<?php
declare(strict_types=1);

namespace Ttp\Admin;

use Ttp\Db;

/**
 * Single-admin login (plan §5.2). Passwords are bcrypt; failures are recorded in
 * `login_attempts` and throttled per email *and* per IP, so neither a known email
 * nor a single host can be hammered.
 *
 * A password is never compared before the throttle is consulted, and the failure
 * message never says whether the email exists.
 */
final class Auth
{
    /** Failures allowed inside the window before the next attempt is refused. */
    public const MAX_ATTEMPTS    = 5;
    /** How far back failures are counted, and how long a lockout lasts. */
    public const WINDOW_SECONDS  = 900;
    /** A session older than this must log in again, even if it stayed active. */
    public const SESSION_MAX_AGE = 43200;   // 12 h
    /** …and one left idle this long is dropped. */
    public const SESSION_IDLE    = 7200;    // 2 h

    public const ERROR_CREDENTIALS = 'credentials';
    public const ERROR_LOCKED      = 'locked';

    /** bcrypt of a throwaway random string; see attempt(). */
    private const DUMMY_HASH = '$2y$12$O9SDIC1QGqfMz1LwUoVf2unGUf8EfWk.5fgAXDOOubVN7b5X/gih2';

    public static function createUser(string $email, string $password, string $name = ''): int
    {
        $email = self::normaliseEmail($email);
        $hash  = password_hash($password, PASSWORD_BCRYPT);
        Db::run(
            'INSERT INTO users (email, password_hash, name) VALUES (?, ?, ?)
             ON CONFLICT(email) DO UPDATE SET password_hash = excluded.password_hash, name = excluded.name',
            [$email, $hash, $name]
        );
        return (int) Db::value('SELECT id FROM users WHERE email = ?', [$email]);
    }

    /** @return array<string,mixed>|null */
    public static function findByEmail(string $email): ?array
    {
        return Db::one('SELECT * FROM users WHERE email = ?', [self::normaliseEmail($email)]);
    }

    public static function userCount(): int
    {
        return (int) Db::value('SELECT COUNT(*) FROM users');
    }

    /**
     * Why a password check may not even be attempted: too many recent failures for
     * this email or from this IP. Returns the number of seconds still to wait, or 0.
     */
    public static function lockoutSeconds(string $email, string $ip): int
    {
        $email  = self::normaliseEmail($email);
        $cutoff = gmdate('c', time() - self::WINDOW_SECONDS);

        $newest = 0;
        foreach ([['email', $email], ['ip', $ip]] as [$column, $value]) {
            if ($value === '') {
                continue;
            }
            $rows = Db::all(
                "SELECT created_at FROM login_attempts
                 WHERE {$column} = ? AND successful = 0 AND created_at >= ?
                 ORDER BY created_at DESC LIMIT " . self::MAX_ATTEMPTS,
                [$value, $cutoff]
            );
            if (count($rows) < self::MAX_ATTEMPTS) {
                continue;
            }
            $oldest = (int) strtotime((string) $rows[count($rows) - 1]['created_at']);
            $newest = max($newest, $oldest + self::WINDOW_SECONDS);
        }

        return $newest === 0 ? 0 : max(0, $newest - time());
    }

    /**
     * Try to log in. On success the session is regenerated and bound to the user.
     *
     * @return array{ok:bool,error:string,retry_after:int,user:array<string,mixed>|null}
     */
    public static function attempt(string $email, string $password, string $ip = ''): array
    {
        $email = self::normaliseEmail($email);

        $wait = self::lockoutSeconds($email, $ip);
        if ($wait > 0) {
            // Still recorded: a locked-out attacker must not be able to let the
            // window expire while continuing to guess.
            self::record($email, $ip, false);
            return ['ok' => false, 'error' => self::ERROR_LOCKED, 'retry_after' => $wait, 'user' => null];
        }

        $user = self::findByEmail($email);
        $hash = is_array($user) ? (string) $user['password_hash'] : '';
        // Always spend the work of one real bcrypt comparison so a missing account
        // and a wrong password take the same time. DUMMY_HASH is a valid bcrypt
        // digest of a random string nobody knows.
        $valid = password_verify($password, $hash !== '' ? $hash : self::DUMMY_HASH);

        if (!$valid || !is_array($user)) {
            self::record($email, $ip, false);
            return ['ok' => false, 'error' => self::ERROR_CREDENTIALS, 'retry_after' => 0, 'user' => null];
        }

        if (password_needs_rehash($hash, PASSWORD_BCRYPT)) {
            Db::run('UPDATE users SET password_hash = ? WHERE id = ?', [
                password_hash($password, PASSWORD_BCRYPT), (int) $user['id'],
            ]);
        }

        self::record($email, $ip, true);
        self::clearFailures($email, $ip);
        Db::run('UPDATE users SET last_login_at = ? WHERE id = ?', [gmdate('c'), (int) $user['id']]);

        Session::regenerate();
        Csrf::rotate();
        Session::set('user_id', (int) $user['id']);
        Session::set('logged_in_at', time());
        Session::set('last_seen_at', time());

        return ['ok' => true, 'error' => '', 'retry_after' => 0, 'user' => $user];
    }

    /** The signed-in user, or null. Expired sessions are dropped here. */
    public static function user(): ?array
    {
        $id = (int) Session::get('user_id', 0);
        if ($id <= 0) {
            return null;
        }
        $loggedIn = (int) Session::get('logged_in_at', 0);
        $lastSeen = (int) Session::get('last_seen_at', 0);
        $now      = time();
        if ($now - $loggedIn > self::SESSION_MAX_AGE || $now - $lastSeen > self::SESSION_IDLE) {
            self::logout();
            return null;
        }

        $user = Db::one('SELECT * FROM users WHERE id = ?', [$id]);
        if ($user === null) {
            self::logout();
            return null;
        }
        Session::set('last_seen_at', $now);
        return $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function logout(): void
    {
        Session::forget('user_id');
        Session::forget('logged_in_at');
        Session::forget('last_seen_at');
        Csrf::rotate();
    }

    public static function record(string $email, string $ip, bool $successful): void
    {
        Db::run(
            'INSERT INTO login_attempts (ip, email, successful, created_at) VALUES (?, ?, ?, ?)',
            [$ip, self::normaliseEmail($email), $successful ? 1 : 0, gmdate('c')]
        );
    }

    public static function clearFailures(string $email, string $ip): void
    {
        Db::run(
            'DELETE FROM login_attempts WHERE successful = 0 AND email = ?',
            [self::normaliseEmail($email)]
        );
        if ($ip !== '') {
            Db::run('DELETE FROM login_attempts WHERE successful = 0 AND ip = ?', [$ip]);
        }
    }

    /** Drop attempt rows older than a day; called opportunistically by the panel. */
    public static function pruneAttempts(): void
    {
        Db::run('DELETE FROM login_attempts WHERE created_at < ?', [gmdate('c', time() - 86400)]);
    }

    /** null when the password is acceptable, otherwise why it is not. */
    public static function passwordProblem(string $password): ?string
    {
        if (strlen($password) < 12) {
            return 'Use at least 12 characters.';
        }
        if (preg_match('/[a-zA-Z]/', $password) !== 1 || preg_match('/\d/', $password) !== 1) {
            return 'Use at least one letter and one number.';
        }
        return null;
    }

    public static function normaliseEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    /** The client address, trusting Hostinger's proxy header only for its first hop. */
    public static function clientIp(array $server): string
    {
        $forwarded = (string) ($server['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($forwarded !== '') {
            $first = trim(explode(',', $forwarded)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP) !== false) {
                return $first;
            }
        }
        $remote = (string) ($server['REMOTE_ADDR'] ?? '');
        return filter_var($remote, FILTER_VALIDATE_IP) !== false ? $remote : '';
    }
}
