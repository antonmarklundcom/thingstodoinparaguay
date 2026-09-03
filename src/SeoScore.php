<?php
declare(strict_types=1);

namespace Ttp;

/**
 * The SEO score (plan §5.2), 0–100. One class, two callers: the admin editor
 * (live, on every keystroke pause, and again server-side on save) and
 * bin/seo-audit.php, which prints the same score for every published item.
 *
 * Each rule is worth a fixed number of points and reports one line of advice, so
 * a non-technical author can work the checklist from the top down. The weights
 * add up to exactly 100.
 */
final class SeoScore
{
    /** Posts are held to the plan's 600-word bar; other types to a lower one. */
    public const MIN_WORDS_POST  = 600;
    public const MIN_WORDS_OTHER = 300;

    public const TITLE_MIN = 30;
    public const TITLE_MAX = 60;
    public const DESC_MIN  = 70;
    public const DESC_MAX  = 155;

    /** @param array<int,array{id:string,label:string,points:int,earned:int,passed:bool,advice:string}> $checks */
    private function __construct(
        public readonly int $score,
        public readonly array $checks,
        public readonly int $wordCount,
        public readonly string $focusKeyword,
    ) {
    }

    /** @return array<int,array<string,mixed>> */
    public function failing(): array
    {
        return array_values(array_filter($this->checks, static fn (array $c): bool => !$c['passed']));
    }

    public function grade(): string
    {
        return match (true) {
            $this->score >= 90 => 'excellent',
            $this->score >= 80 => 'good',
            $this->score >= 60 => 'needs work',
            default            => 'poor',
        };
    }

    /** @return array<string,mixed> — what the editor's fetch() call receives. */
    public function toArray(): array
    {
        return [
            'score'      => $this->score,
            'grade'      => $this->grade(),
            'word_count' => $this->wordCount,
            'focus'      => $this->focusKeyword,
            'checks'     => $this->checks,
        ];
    }

    /**
     * Grade one item.
     *
     * @param array<string,mixed>      $item    a content_items row (or the editor's draft of one)
     * @param array<string,mixed>|null $details the tour_details row for a tour/service
     */
    public static function forItem(array $item, ?array $details = null): self
    {
        $type  = (string) ($item['type'] ?? 'post');
        $focus = self::normalise((string) ($item['focus_keyword'] ?? ''));
        $title = trim((string) ($item['meta_title'] ?? '')) !== ''
            ? (string) $item['meta_title']
            : (string) ($item['title'] ?? '');
        $description = trim((string) ($item['meta_description'] ?? '')) !== ''
            ? (string) $item['meta_description']
            : (string) ($item['excerpt'] ?? '');
        $slug = (string) ($item['slug'] ?? '');

        $markdown = self::document($item, $details);
        $html     = Markdown::toHtml($markdown);
        $text     = Markdown::toText($markdown);
        $words    = $text === '' ? 0 : count(preg_split('/\s+/u', $text) ?: []);
        $headings = self::headings($html, 'h2');
        $intro    = self::firstWords($text, 100);
        $hasCover = ((int) ($item['cover_media_id'] ?? 0)) > 0;

        [$internal, $external] = self::links($html);
        [$images, $withAlt]    = self::images($html);

        $minWords = $type === 'post' ? self::MIN_WORDS_POST : self::MIN_WORDS_OTHER;
        $hasFocus = $focus !== '';

        $checks = [];
        $add = static function (
            string $id,
            string $label,
            int $points,
            bool $passed,
            string $advice
        ) use (&$checks): void {
            $checks[] = [
                'id'     => $id,
                'label'  => $label,
                'points' => $points,
                'earned' => $passed ? $points : 0,
                'passed' => $passed,
                'advice' => $passed ? '' : $advice,
            ];
        };

        // --- Focus keyword (34 points) ---------------------------------------
        $add(
            'focus_in_title',
            'Focus keyword in the title',
            10,
            $hasFocus && self::contains(self::normalise($title), $focus),
            $hasFocus
                ? 'Work “' . $focus . '” into the title, ideally near the start.'
                : 'Set a focus keyword — the phrase you want this page to rank for.'
        );
        $add(
            'focus_in_slug',
            'Focus keyword in the URL',
            8,
            $hasFocus && self::contains(self::normalise(str_replace('-', ' ', $slug)), $focus),
            $hasFocus
                ? 'Put “' . $focus . '” in the slug. Change it only before publishing when you can.'
                : 'Set a focus keyword first.'
        );
        $add(
            'focus_in_intro',
            'Focus keyword in the first 100 words',
            8,
            $hasFocus && self::contains(self::normalise($intro), $focus),
            $hasFocus
                ? 'Mention “' . $focus . '” in the opening paragraph.'
                : 'Set a focus keyword first.'
        );
        $add(
            'focus_in_heading',
            'Focus keyword in a subheading',
            8,
            $hasFocus && self::anyContains($headings, $focus),
            $hasFocus
                ? 'Use “' . $focus . '” in at least one H2.'
                : 'Set a focus keyword first.'
        );

        // --- Title and description (20 points) -------------------------------
        $titleLength = mb_strlen(trim($title));
        $add(
            'title_length',
            'Title is ' . self::TITLE_MIN . '–' . self::TITLE_MAX . ' characters',
            10,
            $titleLength >= self::TITLE_MIN && $titleLength <= self::TITLE_MAX,
            $titleLength < self::TITLE_MIN
                ? 'The title is ' . $titleLength . ' characters — make it more descriptive.'
                : 'The title is ' . $titleLength . ' characters and Google will cut it off. Shorten it.'
        );
        $descLength = mb_strlen(trim($description));
        $add(
            'description_length',
            'Meta description is ' . self::DESC_MIN . '–' . self::DESC_MAX . ' characters',
            10,
            $descLength >= self::DESC_MIN && $descLength <= self::DESC_MAX,
            $descLength < self::DESC_MIN
                ? 'The description is ' . $descLength . ' characters. Say what the page gives the reader.'
                : 'The description is ' . $descLength . ' characters and will be truncated. Trim it.'
        );

        // --- Substance (26 points) -------------------------------------------
        $add(
            'word_count',
            'At least ' . $minWords . ' words',
            12,
            $words >= $minWords,
            'This page has ' . $words . ' words. Add another ' . max(0, $minWords - $words) . '.'
        );
        $add(
            'headings',
            'At least two H2 subheadings',
            8,
            count($headings) >= 2,
            'Break the page up with `## ` subheadings — it has ' . count($headings) . '.'
        );
        $add(
            'no_lorem',
            'No placeholder text',
            6,
            !self::hasLorem($text),
            'Lorem Ipsum is still on this page. Replace it with real copy.'
        );

        // --- Links and images (20 points) ------------------------------------
        $add(
            'internal_links',
            'At least two internal links',
            8,
            $internal >= 2,
            'Link to two other pages on this site — it has ' . $internal . '.'
        );
        $add(
            'external_links',
            'At least one external link',
            4,
            $external >= 1,
            'Link out once to a source worth citing.'
        );
        $add(
            'image_alt',
            'Every image has alt text',
            4,
            $images === 0 || $withAlt === $images,
            ($images - $withAlt) . ' of ' . $images . ' images have no alt text.'
        );
        $add(
            'cover_image',
            'Cover image set',
            4,
            $hasCover,
            'Pick a cover image — it is what social networks and the blog index show.'
        );

        $score = 0;
        foreach ($checks as $check) {
            $score += (int) $check['earned'];
        }

        return new self(min(100, max(0, $score)), $checks, $words, $focus);
    }

    /**
     * The document the rules are applied to. For a post or page that is the
     * Markdown body; for a tour or service it is the body plus the structured
     * sections, laid out with the same headings templates/tour.php renders, so a
     * tour is graded on what a reader actually sees.
     */
    public static function document(array $item, ?array $details = null): string
    {
        $parts = [(string) ($item['body_md'] ?? '')];
        if ($details === null) {
            return trim(implode("\n\n", $parts));
        }

        $rows = static function (mixed $value): array {
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                return is_array($decoded) ? $decoded : [];
            }
            return is_array($value) ? $value : [];
        };

        foreach (['hook_md', 'solution_md'] as $key) {
            $parts[] = (string) ($details[$key] ?? '');
        }

        $itinerary = $rows($details['itinerary'] ?? $details['itinerary_json'] ?? []);
        if ($itinerary !== []) {
            $label   = trim((string) ($details['itinerary_label'] ?? '')) ?: 'What the day looks like';
            $parts[] = '## ' . $label;
            foreach ($itinerary as $step) {
                $parts[] = '### ' . (string) ($step['title'] ?? $step['name'] ?? '');
                $parts[] = (string) ($step['body'] ?? $step['text'] ?? '');
            }
        }

        $why = $rows($details['why'] ?? $details['why_json'] ?? []);
        if ($why !== []) {
            $parts[] = '## Why book this with us';
            foreach ($why as $reason) {
                $parts[] = '### ' . (string) ($reason['title'] ?? '');
                $parts[] = (string) ($reason['body'] ?? $reason['text'] ?? '');
            }
        }

        $practical = $rows($details['practical'] ?? $details['practical_json'] ?? []);
        if ($practical !== []) {
            $parts[] = '## Practical information';
            foreach ($practical as $fact) {
                $parts[] = '- **' . (string) ($fact['label'] ?? '') . '** ' . (string) ($fact['value'] ?? '');
            }
        }

        $faq = $rows($details['faq'] ?? $details['faq_json'] ?? []);
        if ($faq !== []) {
            $parts[] = '## Frequently asked questions';
            foreach ($faq as $entry) {
                $parts[] = '### ' . (string) ($entry['q'] ?? $entry['question'] ?? '');
                $parts[] = (string) ($entry['a'] ?? $entry['answer'] ?? '');
            }
        }

        $parts[] = (string) ($details['closing_md'] ?? '');

        return trim(implode("\n\n", array_filter(array_map('trim', $parts), static fn (string $p): bool => $p !== '')));
    }

    // -----------------------------------------------------------------------
    // Rule helpers
    // -----------------------------------------------------------------------

    /** Lower-case, unaccented, punctuation collapsed to single spaces. */
    public static function normalise(string $text): string
    {
        $text = Str::deaccent($text);
        $text = mb_strtolower($text, 'UTF-8');
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** Whole-phrase match, so "asuncion" does not match "asuncionista". */
    private static function contains(string $haystack, string $needle): bool
    {
        if ($needle === '' || $haystack === '') {
            return false;
        }
        return str_contains(' ' . $haystack . ' ', ' ' . $needle . ' ');
    }

    /** @param array<int,string> $strings */
    private static function anyContains(array $strings, string $needle): bool
    {
        foreach ($strings as $string) {
            if (self::contains(self::normalise($string), $needle)) {
                return true;
            }
        }
        return false;
    }

    /** @return array<int,string> */
    private static function headings(string $html, string $tag): array
    {
        $out = [];
        if (preg_match_all('#<' . $tag . '\b[^>]*>(.*?)</' . $tag . '>#is', $html, $m) >= 1) {
            foreach ($m[1] as $inner) {
                $text = trim(html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($text !== '') {
                    $out[] = $text;
                }
            }
        }
        return $out;
    }

    /** @return array{0:int,1:int} internal links, external links */
    private static function links(string $html): array
    {
        $internal = 0;
        $external = 0;
        $host     = (string) (parse_url((string) ttp_config()['site_url'], PHP_URL_HOST) ?? '');
        preg_match_all('/<a\b[^>]*href="([^"]*)"/i', $html, $m);
        foreach ($m[1] ?? [] as $href) {
            $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($href === '' || str_starts_with($href, '#')) {
                continue;
            }
            if (preg_match('~^(mailto|tel|javascript):~i', $href) === 1) {
                continue;
            }
            $linkHost = (string) (parse_url($href, PHP_URL_HOST) ?? '');
            if ($linkHost === '' || ($host !== '' && strcasecmp($linkHost, $host) === 0)) {
                $internal++;
            } else {
                $external++;
            }
        }
        return [$internal, $external];
    }

    /** @return array{0:int,1:int} images found, images carrying non-empty alt text */
    private static function images(string $html): array
    {
        preg_match_all('/<img\b[^>]*>/i', $html, $m);
        $total   = count($m[0] ?? []);
        $withAlt = 0;
        foreach ($m[0] ?? [] as $tag) {
            if (preg_match('/\balt="([^"]*)"/i', $tag, $alt) === 1 && trim($alt[1]) !== '') {
                $withAlt++;
            }
        }
        return [$total, $withAlt];
    }

    public static function hasLorem(string $text): bool
    {
        return preg_match('/\b(lorem ipsum|dolor sit amet|consectetur adipiscing)\b/i', $text) === 1;
    }

    private static function firstWords(string $text, int $count): string
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        return implode(' ', array_slice($words, 0, $count));
    }
}
