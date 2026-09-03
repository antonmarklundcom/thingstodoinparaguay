<?php
declare(strict_types=1);

namespace Ttp\Admin;

use Ttp\Str;

/**
 * A per-session CSRF token. Every mutating admin request carries it in a hidden
 * `_csrf` field (or an `X-CSRF-Token` header for the editor's fetch calls) and
 * Admin\App rejects the request with 403 when it does not match.
 */
final class Csrf
{
    public const FIELD  = '_csrf';
    public const HEADER = 'HTTP_X_CSRF_TOKEN';

    public static function token(): string
    {
        $token = (string) Session::get('csrf', '');
        if ($token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set('csrf', $token);
        }
        return $token;
    }

    public static function check(?string $candidate): bool
    {
        $token = (string) Session::get('csrf', '');
        if ($token === '' || $candidate === null || $candidate === '') {
            return false;
        }
        return hash_equals($token, $candidate);
    }

    /** The token a request presented, from the form field or the fetch header. */
    public static function fromRequest(array $post, array $server): ?string
    {
        $value = $post[self::FIELD] ?? $server[self::HEADER] ?? null;
        return is_string($value) ? $value : null;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . Str::e(self::token()) . '">';
    }

    /** A fresh token — used after logout so the next login form is not replayable. */
    public static function rotate(): void
    {
        Session::forget('csrf');
    }
}
