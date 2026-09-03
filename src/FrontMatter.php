<?php
declare(strict_types=1);

namespace Ttp;

/**
 * `---` delimited front matter followed by a Markdown body.
 * YAML-lite by default; a block starting with `{` is read as JSON.
 */
final class FrontMatter
{
    /** @return array{0:array<string,mixed>,1:string} */
    public static function parse(string $raw): array
    {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $raw = str_replace("\r\n", "\n", $raw);

        if (!str_starts_with($raw, "---\n")) {
            return [[], trim($raw)];
        }
        $end = strpos($raw, "\n---", 3);
        if ($end === false) {
            return [[], trim($raw)];
        }
        $block = substr($raw, 4, $end - 3);
        $body  = ltrim(substr($raw, $end + 4), "\n");

        $trimmed = trim($block);
        if ($trimmed !== '' && $trimmed[0] === '{') {
            $data = json_decode($trimmed, true);
            return [is_array($data) ? $data : [], trim($body)];
        }
        return [Yaml::parse($block), trim($body)];
    }

    /** @param array<string,mixed> $data */
    public static function render(array $data, string $body): string
    {
        return "---\n" . Yaml::dump($data) . "---\n\n" . trim($body) . "\n";
    }
}
