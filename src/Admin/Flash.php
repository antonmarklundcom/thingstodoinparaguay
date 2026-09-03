<?php
declare(strict_types=1);

namespace Ttp\Admin;

/** One-shot messages carried across a redirect. */
final class Flash
{
    public static function add(string $type, string $message): void
    {
        $messages   = (array) Session::get('flash', []);
        $messages[] = ['type' => $type, 'message' => $message];
        Session::set('flash', $messages);
    }

    public static function ok(string $message): void
    {
        self::add('ok', $message);
    }

    public static function error(string $message): void
    {
        self::add('error', $message);
    }

    /** @return array<int,array{type:string,message:string}> */
    public static function take(): array
    {
        $messages = (array) Session::get('flash', []);
        Session::forget('flash');
        return $messages;
    }
}
