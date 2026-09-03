<?php
declare(strict_types=1);

namespace Ttp;

use Parsedown;

final class Markdown
{
    private static ?Parsedown $parser = null;

    /**
     * Parsedown in safe mode: raw HTML in a Markdown body is escaped, not passed
     * through, and `javascript:`-style URLs are stripped from links and images.
     *
     * Bodies come from the admin editor (plan §5.2) and from content/ files, so
     * this is what keeps a stored cross-site scripting payload out of a rendered
     * page. Nothing on the site needs raw HTML in a body; a template is the place
     * for markup.
     */
    private static function parser(): Parsedown
    {
        if (self::$parser === null) {
            self::$parser = new Parsedown();
            self::$parser->setSafeMode(true);
        }
        return self::$parser;
    }

    public static function toHtml(string $md): string
    {
        if ($md === '') {
            return '';
        }
        return self::parser()->text($md);
    }

    public static function inline(string $md): string
    {
        if ($md === '') {
            return '';
        }
        return self::parser()->line($md);
    }

    /** Plain text of a Markdown string — for excerpts, word counts and meta descriptions. */
    public static function toText(string $md): string
    {
        $text = strip_tags(self::toHtml($md));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    public static function wordCount(string $md): int
    {
        $text = self::toText($md);
        return $text === '' ? 0 : count(preg_split('/\s+/u', $text) ?: []);
    }

    /** Truncate on a word boundary, appending an ellipsis only when text was cut. */
    public static function truncate(string $text, int $limit): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        $cut = mb_substr($text, 0, $limit - 1);
        $sp  = mb_strrpos($cut, ' ');
        if ($sp !== false && $sp > $limit * 0.5) {
            $cut = mb_substr($cut, 0, $sp);
        }
        return rtrim($cut, " ,.;:—-") . '…';
    }
}
