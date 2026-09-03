<?php
declare(strict_types=1);

namespace Ttp;

final class Str
{
    public static function slug(string $text): string
    {
        $t = self::deaccent($text);
        $t = strtolower($t);
        $t = (string) preg_replace('/[^a-z0-9]+/', '-', $t);
        return trim($t, '-');
    }

    public static function deaccent(string $text): string
    {
        $map = [
            'á'=>'a','à'=>'a','â'=>'a','ä'=>'a','ã'=>'a','å'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','ó'=>'o','ò'=>'o','ô'=>'o','ö'=>'o','õ'=>'o','ú'=>'u',
            'ù'=>'u','û'=>'u','ü'=>'u','ñ'=>'n','ç'=>'c','ã'=>'a','ý'=>'y','ÿ'=>'y','ẽ'=>'e','ĩ'=>'i',
            'ũ'=>'u','ỹ'=>'y','–'=>'-','—'=>'-','’'=>"'",'‘'=>"'",'“'=>'"','”'=>'"','…'=>'...',
        ];
        $lower = mb_strtolower($text, 'UTF-8');
        $out = strtr($lower, $map);
        // Preserve original casing where the character was untouched.
        return $out === $lower ? $text : $out;
    }

    public static function e(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
