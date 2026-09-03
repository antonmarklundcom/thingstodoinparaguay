<?php
declare(strict_types=1);

namespace Ttp;

/**
 * Converts docs/wp-scan.md — the verbatim scan of the live WordPress site — into
 * the content model. This is the fallback path for bin/wp-export.php when the WP
 * REST API is unreachable (plan §5.1); it is deterministic and re-runnable.
 *
 * The scan is prose, so extraction is best-effort by design: every field it cannot
 * establish is left empty rather than invented, and `scan_gaps` records what was
 * missing so phase S4 knows where to look.
 */
final class ScanImport
{
    /** @var array<int,string> */
    private array $lines;

    public function __construct(private string $scanPath)
    {
        $text = (string) file_get_contents($scanPath);
        $this->lines = preg_split('/\R/', str_replace("\r\n", "\n", $text)) ?: [];
    }

    /**
     * Every `##` section that names a path, as slug => raw section text.
     * @return array<string,string>
     */
    public function sections(): array
    {
        $out = [];
        $current = null;
        $buf = [];
        foreach ($this->lines as $line) {
            if (preg_match('~^##\s+(?:\d+\.\s*)?(/[a-z0-9\-/]*/)~i', $line, $m)) {
                if ($current !== null) {
                    $out[$current] = implode("\n", $buf);
                }
                $current = trim($m[1], '/');
                if ($current === '') {
                    $current = '__root__';
                }
                $buf = [$line];
                continue;
            }
            // "## 14 & 15. /faq2/ and /faq/" and "## 10. / (site root ...)"
            if (preg_match('~^##\s+\d+(?:\s*&\s*\d+)?\.\s+/\s*\(site root~i', $line)) {
                if ($current !== null) {
                    $out[$current] = implode("\n", $buf);
                }
                $current = '__root__';
                $buf = [$line];
                continue;
            }
            if ($current !== null) {
                $buf[] = $line;
            }
        }
        if ($current !== null) {
            $out[$current] = implode("\n", $buf);
        }
        return $out;
    }

    /** The first fenced code block of a section — the verbatim body copy. */
    public static function bodyBlock(string $section): string
    {
        if (!preg_match('/```\n(.*?)\n```/s', $section, $m)) {
            return '';
        }
        return trim($m[1]);
    }

    /**
     * The `**Headings**:` bullet list, flattened to distinct heading texts.
     * @return array<int,array{level:int,text:string}>
     */
    public static function headings(string $section): array
    {
        if (!preg_match('/^\*\*Headings\*?\*?:?\*?\*?(.*)$/mi', $section, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }
        $rest = substr($section, $m[0][1]);
        $lines = preg_split('/\R/', $rest) ?: [];
        array_shift($lines);
        $inline = trim($m[1][0]);

        $chunks = [];
        if ($inline !== '') {
            $chunks = array_merge($chunks, preg_split('/\s*;\s*|\s+·\s+/u', $inline) ?: []);
        }
        foreach ($lines as $line) {
            $t = trim($line);
            if ($t === '') {
                if ($chunks !== []) {
                    break;
                }
                continue;
            }
            if (!str_starts_with($t, '- ')) {
                break;
            }
            $chunks = array_merge($chunks, preg_split('/\s*;\s*|\s+·\s+|\s+\/\s+(?=H\d\s*:)/u', substr($t, 2)) ?: []);
        }

        $out = [];
        foreach ($chunks as $chunk) {
            if (!preg_match('/^H(\d)\s*(?:\(.*?\))?\s*:?\s+(.+)$/', trim($chunk), $mm)) {
                continue;
            }
            foreach (preg_split('~\s+/\s+~', $mm[2]) ?: [] as $text) {
                $text = trim($text);
                // Drop footer widget headings, they are chrome, not content.
                if ($text === '' || in_array($text, ['Page', 'Important Link', 'Our Newsletter'], true)) {
                    continue;
                }
                $out[] = ['level' => (int) $mm[1], 'text' => $text];
            }
        }
        return $out;
    }

    public static function field(string $section, string $label): string
    {
        $pattern = '/^\*\*' . preg_quote($label, '/') . '\*?\*?:?\*?\*?:?\s*(.*)$/mi';
        if (!preg_match($pattern, $section, $m)) {
            return '';
        }
        return trim($m[1]);
    }

    public static function title(string $section): string
    {
        $t = self::field($section, 'Title');
        $t = preg_replace('/\s*[–\-]\s*thingstodoinparaguay\.com\s*$/u', '', $t) ?? $t;
        $t = trim($t, " *");
        if (stripos($t, 'none') === 0 || str_contains($t, 'no title tag')) {
            return '';
        }
        return $t;
    }

    /** Category name mentioned in the section, normalised. */
    public static function category(string $section): string
    {
        if (preg_match('/Category:?\s*"([^"]+)"/i', $section, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/categor(?:y|ised as)\s+"([^"]+)"/i', $section, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    /** @return array<int,string> */
    public static function tags(string $section): array
    {
        $raw = self::field($section, 'Tags');
        if ($raw === '' || stripos($raw, 'none') === 0) {
            return [];
        }
        $raw = preg_replace('/\s*\(.*?\)\s*/', '', $raw) ?? $raw;
        $out = [];
        foreach (explode(',', $raw) as $tag) {
            $tag = trim($tag, " .*");
            if ($tag !== '' && stripos($tag, 'none') !== 0) {
                $out[] = $tag;
            }
        }
        return $out;
    }

    /** ISO timestamp from a byline like `03:07:50 July 24, 2025`. */
    public static function publishedAt(string $section): string
    {
        if (!preg_match('/(\d{2}):(\d{2}):(\d{2})\s+([A-Z][a-z]+)\s+(\d{1,2}),\s*(\d{4})/', $section, $m)) {
            return '';
        }
        $ts = strtotime("{$m[4]} {$m[5]} {$m[6]} {$m[1]}:{$m[2]}:{$m[3]} UTC");
        return $ts === false ? '' : gmdate('Y-m-d\TH:i:s\Z', $ts);
    }

    /** @return array{file:string,alt:string}|null */
    public static function coverImage(string $section): ?array
    {
        $raw = self::field($section, 'Images w/ alt');
        if ($raw === '' || stripos($raw, 'none') === 0) {
            return null;
        }
        if (!preg_match('/`?([A-Za-z0-9_\-.]+\.(?:jpg|jpeg|png|webp))`?/i', $raw, $m)) {
            return null;
        }
        $file = $m[1];
        if (preg_match('/alt\s*=\s*"([^"]*)"/i', $raw, $am) && trim($am[1]) !== '') {
            return ['file' => $file, 'alt' => trim($am[1])];
        }
        return ['file' => $file, 'alt' => ''];
    }

    /**
     * Promotes the lines of a scanned body that the heading list identifies as
     * headings to Markdown `##`, so a flat scrape gains real document structure.
     *
     * @param array<int,array{level:int,text:string}> $headings
     */
    public static function headify(string $body, array $headings, string $title): string
    {
        if ($body === '') {
            return '';
        }
        $index = [];
        foreach ($headings as $h) {
            $index[self::normalise($h['text'])] = true;
        }

        $out = [];
        $seenTitle = false;
        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = rtrim($line);
            if (trim($line) === '') {
                $out[] = '';
                continue;
            }
            // Elementor breadcrumb line, e.g. "Home / About Us".
            if ($out === [] && preg_match('~^Home\s*/\s*[A-Za-z ]{2,40}$~', trim($line))) {
                continue;
            }
            $key = self::normalise($line);
            if (!$seenTitle && $key === self::normalise($title)) {
                $seenTitle = true;
                continue;
            }
            $out[] = isset($index[$key]) ? '## ' . trim($line) : trim($line);
        }

        $md = implode("\n\n", array_filter($out, static fn (string $l): bool => $l !== ''));
        return trim((string) preg_replace('/\n{3,}/', "\n\n", $md));
    }

    private static function normalise(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        return (string) preg_replace('/[^a-z0-9]+/u', '', $s);
    }
}
