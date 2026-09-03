<?php
declare(strict_types=1);

namespace Ttp;

/**
 * A deliberately small YAML subset — enough for content front matter, no more.
 *
 * Supported: nested maps by indentation, sequences of scalars, sequences of maps,
 * block scalars (`|` and `>`), quoted and bare scalars, inline `[]`/`{}` empties,
 * `#` comments on their own line, and null/bool/int/float coercion.
 *
 * Not supported (and not needed): anchors, aliases, multi-document files, flow
 * collections with content, complex keys, tags. A front-matter block that starts
 * with `{` is parsed as JSON instead — see FrontMatter::parse().
 */
final class Yaml
{
    /** @return array<string,mixed> */
    public static function parse(string $text): array
    {
        $lines = preg_split('/\R/', $text) ?: [];
        $i = 0;
        $out = self::parseBlock($lines, $i, 0);
        return is_array($out) ? $out : [];
    }

    /**
     * @param array<int,string> $lines
     * @return array<string,mixed>|array<int,mixed>
     */
    private static function parseBlock(array $lines, int &$i, int $indent): array
    {
        $map  = [];
        $list = [];

        while ($i < count($lines)) {
            $raw = $lines[$i];
            if (trim($raw) === '' || str_starts_with(ltrim($raw), '#')) {
                $i++;
                continue;
            }

            $cur = self::indentOf($raw);
            if ($cur < $indent) {
                break;
            }
            // A deeper line at this point belongs to a construct we already consumed.
            if ($cur > $indent) {
                $i++;
                continue;
            }

            $line = trim($raw);

            // --- sequence item -------------------------------------------------
            if ($line === '-' || str_starts_with($line, '- ')) {
                $rest = trim(substr($line, 1));
                $i++;

                if ($rest === '') {
                    $child = self::parseBlock($lines, $i, self::nextIndent($lines, $i, $indent));
                    $list[] = $child;
                    continue;
                }

                // "- key: value" starts a map whose remaining keys are indented under it.
                if (self::isKeyLine($rest)) {
                    $itemIndent = $cur + 2;
                    $sub = self::splitKey($rest);
                    $item = [];
                    self::assign($item, $sub[0], $sub[1], $lines, $i, $itemIndent);
                    $more = self::parseBlock($lines, $i, $itemIndent);
                    if (is_array($more)) {
                        $item = array_merge($item, $more);
                    }
                    $list[] = $item;
                    continue;
                }

                $list[] = self::scalar($rest);
                continue;
            }

            // --- mapping entry -------------------------------------------------
            if (!self::isKeyLine($line)) {
                $i++;
                continue;
            }

            [$key, $value] = self::splitKey($line);
            $i++;
            self::assign($map, $key, $value, $lines, $i, $indent);
        }

        if ($list !== []) {
            return $list;
        }
        return $map;
    }

    /**
     * @param array<string,mixed> $target
     * @param array<int,string>   $lines
     */
    private static function assign(array &$target, string $key, string $value, array $lines, int &$i, int $indent): void
    {
        if ($value === '|' || $value === '>' || $value === '|-' || $value === '>-') {
            $target[$key] = self::blockScalar($lines, $i, $indent, $value[0] === '>');
            return;
        }
        if ($value !== '') {
            $target[$key] = self::scalar($value);
            return;
        }
        // Empty value: either a nested block, or a genuine null.
        $childIndent = self::nextIndent($lines, $i, $indent);
        if ($childIndent > $indent) {
            $target[$key] = self::parseBlock($lines, $i, $childIndent);
            return;
        }
        $target[$key] = null;
    }

    /** @param array<int,string> $lines */
    private static function blockScalar(array $lines, int &$i, int $indent, bool $folded): string
    {
        $buf    = [];
        $inner  = null;
        while ($i < count($lines)) {
            $raw = $lines[$i];
            if (trim($raw) === '') {
                $buf[] = '';
                $i++;
                continue;
            }
            $cur = self::indentOf($raw);
            if ($cur <= $indent) {
                break;
            }
            $inner ??= $cur;
            $buf[] = substr($raw, min($inner, $cur));
            $i++;
        }
        while ($buf !== [] && end($buf) === '') {
            array_pop($buf);
        }
        if (!$folded) {
            return implode("\n", $buf);
        }
        // Folded: blank lines stay paragraph breaks, others join with a space.
        $out = '';
        foreach ($buf as $n => $l) {
            if ($l === '') {
                $out .= "\n\n";
            } else {
                $out .= ($n > 0 && !str_ends_with($out, "\n") && $out !== '' ? ' ' : '') . $l;
            }
        }
        return trim($out);
    }

    /** @param array<int,string> $lines */
    private static function nextIndent(array $lines, int $i, int $fallback): int
    {
        while ($i < count($lines)) {
            $raw = $lines[$i];
            if (trim($raw) === '' || str_starts_with(ltrim($raw), '#')) {
                $i++;
                continue;
            }
            return self::indentOf($raw);
        }
        return $fallback;
    }

    private static function indentOf(string $line): int
    {
        return strlen($line) - strlen(ltrim($line, ' '));
    }

    private static function isKeyLine(string $line): bool
    {
        return (bool) preg_match('/^(?:"[^"]*"|\'[^\']*\'|[^:#]+):(?:\s|$)/', $line);
    }

    /** @return array{0:string,1:string} */
    private static function splitKey(string $line): array
    {
        $pos = 0;
        if ($line[0] === '"' || $line[0] === "'") {
            $q = $line[0];
            $end = strpos($line, $q, 1);
            $pos = $end === false ? 0 : $end + 1;
        }
        $colon = strpos($line, ':', $pos);
        $key   = trim(substr($line, 0, (int) $colon));
        $key   = trim($key, "\"'");
        $value = trim(substr($line, (int) $colon + 1));
        return [$key, $value];
    }

    private static function scalar(string $v): mixed
    {
        $v = trim($v);
        if ($v === '' || $v === '~' || strcasecmp($v, 'null') === 0) {
            return null;
        }
        if ($v === '[]') {
            return [];
        }
        if ($v === '{}') {
            return [];
        }
        $len = strlen($v);
        if ($len > 1 && $v[0] === '"' && $v[$len - 1] === '"') {
            return stripcslashes(substr($v, 1, -1));
        }
        if ($len > 1 && $v[0] === "'" && $v[$len - 1] === "'") {
            return str_replace("''", "'", substr($v, 1, -1));
        }
        if (strcasecmp($v, 'true') === 0)  { return true; }
        if (strcasecmp($v, 'false') === 0) { return false; }
        if (preg_match('/^-?\d+$/', $v))   { return (int) $v; }
        if (preg_match('/^-?\d*\.\d+$/', $v)) { return (float) $v; }
        // Strip a trailing comment only when it is clearly one (space + #).
        if (preg_match('/^(.*?)\s+#\s.*$/', $v, $m)) {
            return trim($m[1]);
        }
        return $v;
    }

    // -----------------------------------------------------------------------
    // Dumping (used by bin/export.php)
    // -----------------------------------------------------------------------

    public static function dump(mixed $data, int $indent = 0): string
    {
        $pad = str_repeat(' ', $indent);
        if (!is_array($data)) {
            return $pad . self::dumpScalar($data) . "\n";
        }
        if ($data === []) {
            return $pad . "[]\n";
        }

        $out = '';
        if (array_is_list($data)) {
            foreach ($data as $item) {
                if (is_array($item) && $item !== []) {
                    $block = self::dump($item, $indent + 2);
                    $out .= $pad . '- ' . substr($block, $indent + 2);
                } else {
                    $out .= $pad . '- ' . self::dumpScalar($item) . "\n";
                }
            }
            return $out;
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if ($value === []) {
                    $out .= $pad . $key . ": []\n";
                } else {
                    $out .= $pad . $key . ":\n" . self::dump($value, $indent + 2);
                }
                continue;
            }
            if (is_string($value) && str_contains($value, "\n")) {
                $out .= $pad . $key . ": |\n";
                foreach (preg_split('/\R/', rtrim($value)) ?: [] as $l) {
                    $out .= $l === '' ? "\n" : $pad . '  ' . $l . "\n";
                }
                continue;
            }
            $out .= $pad . $key . ': ' . self::dumpScalar($value) . "\n";
        }
        return $out;
    }

    private static function dumpScalar(mixed $v): string
    {
        if ($v === null)  { return 'null'; }
        if ($v === true)  { return 'true'; }
        if ($v === false) { return 'false'; }
        if (is_int($v) || is_float($v)) { return (string) $v; }
        $s = (string) $v;
        if ($s === '' || preg_match('/^[\s]|[\s]$|^[-?:,\[\]{}#&*!|>\'"%@`]|:\s|\s#|^(true|false|null|~)$/i', $s)
            || preg_match('/^-?\d+(\.\d+)?$/', $s)) {
            return '"' . addcslashes($s, "\"\\") . '"';
        }
        return $s;
    }
}
