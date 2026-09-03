<?php
declare(strict_types=1);

namespace Ttp;

/**
 * Turns one scanned tour/service page (verbatim body copy + its heading list)
 * into the structured shape of `tour_details` (plan §2; scan brief §6):
 * hero, hook, solution, itinerary, why-choose, practical info, FAQ, closing CTA.
 *
 * The 18 pages share one Elementor template but its heading LEVELS are unreliable
 * (the scan documents H1/H2/H3 used inconsistently), so sections are located by
 * their labels and by order, never by level. Anything that cannot be placed lands
 * in `leftover`, and `gaps` records what the scan never captured — nothing is
 * invented and nothing is silently dropped.
 */
final class TourParser
{
    private const SECTION_LABELS = [
        'why'       => '/^why\s+(choose|work|book|us|travel|go)/i',
        'practical' => '/^(practical information|good to know|what you need to know|the details)/i',
        'faq'       => '/^(faq|frequently asked)/i',
    ];

    /**
     * @param array<int,array{level:int,text:string}> $headings
     * @return array<string,mixed>
     */
    public static function parse(string $body, array $headings, string $title): array
    {
        $nodes  = self::nodes($body, $headings, $title);
        $groups = self::groups($nodes);
        $groups = self::redistribute($groups);

        $result = [
            'tagline' => '', 'lead' => '', 'cta_text' => '', 'hook' => '', 'solution' => '',
            'itinerary_label' => '', 'itinerary' => [], 'why' => [], 'practical' => [],
            'faq' => [], 'closing' => '', 'leftover' => [], 'gaps' => [],
        ];

        $n = count($groups);
        $iWhy  = self::indexOf($groups, self::SECTION_LABELS['why']);
        $iPrac = self::indexOf($groups, self::SECTION_LABELS['practical']);
        $iFaq  = self::indexOf($groups, self::SECTION_LABELS['faq']);

        // The itinerary block is the run of sibling items between the narrative
        // sections and "Why choose"/"Practical"/"FAQ" — its label is the heading
        // that introduces it and carries no prose of its own.
        $limit = min(array_filter([$iWhy, $iPrac, $iFaq, $n], static fn ($v) => $v !== null));
        $iItin = null;
        for ($i = 0; $i < $limit; $i++) {
            if ($groups[$i]['heading'] === null || $groups[$i]['paras'] !== []) {
                continue;
            }
            // A label with at least two item groups after it.
            if ($i + 2 < $limit) {
                $iItin = $i;
                break;
            }
        }

        // --- hero ------------------------------------------------------------
        $start = 0;
        if ($groups !== [] && $groups[0]['heading'] === null) {
            $result['lead'] = implode("\n\n", $groups[0]['paras']);
            $start = 1;
        }
        if (isset($groups[$start]) && $groups[$start]['heading'] !== null
            && ($iItin === null || $start < $iItin)
            && mb_strlen($groups[$start]['heading']) <= 90) {
            $result['tagline'] = $groups[$start]['heading'];
            $result['lead'] = trim($result['lead'] . "\n\n" . implode("\n\n", $groups[$start]['paras']));
            $start++;
        }

        // --- narrative (hook + solution) --------------------------------------
        $narrativeEnd = $iItin ?? $limit;
        $narrative = [];
        for ($i = $start; $i < $narrativeEnd; $i++) {
            if ($groups[$i]['heading'] !== null && $groups[$i]['paras'] !== []) {
                $narrative[] = $groups[$i];
            }
        }
        // Three narrative blocks = hero intro + problem + solution; the intro joins the lead.
        while (count($narrative) > 2) {
            $extra = array_shift($narrative);
            $result['lead'] = trim($result['lead'] . "\n\n" . implode("\n\n", $extra['paras']));
        }
        if (isset($narrative[0])) {
            $result['hook'] = self::section($narrative[0]);
        }
        if (isset($narrative[1])) {
            $result['solution'] = self::section($narrative[1]);
        }

        // --- itinerary --------------------------------------------------------
        if ($iItin !== null) {
            $result['itinerary_label'] = $groups[$iItin]['heading'] ?? '';
            for ($i = $iItin + 1; $i < $limit; $i++) {
                if ($groups[$i]['heading'] === null) {
                    continue;
                }
                $result['itinerary'][] = [
                    'title' => $groups[$i]['heading'],
                    'body'  => implode("\n\n", $groups[$i]['paras']),
                ];
            }
        }

        // --- why choose -------------------------------------------------------
        if ($iWhy !== null) {
            $end = self::nextAnchor([$iPrac, $iFaq], $iWhy, $n);
            for ($i = $iWhy + 1; $i < $end; $i++) {
                if ($groups[$i]['heading'] === null) {
                    continue;
                }
                $result['why'][] = [
                    'title' => $groups[$i]['heading'],
                    'body'  => implode("\n\n", $groups[$i]['paras']),
                ];
            }
            if ($result['why'] === [] && $groups[$iWhy]['paras'] !== []) {
                $result['why'][] = ['title' => $groups[$iWhy]['heading'], 'body' => implode("\n\n", $groups[$iWhy]['paras'])];
            }
        }

        // --- practical information --------------------------------------------
        if ($iPrac !== null) {
            $end = self::nextAnchor([$iFaq], $iPrac, $n);
            $paras = $groups[$iPrac]['paras'];
            for ($i = $iPrac + 1; $i < $end; $i++) {
                if ($groups[$i]['heading'] !== null) {
                    $paras[] = $groups[$i]['heading'] . ': ' . implode(' ', $groups[$i]['paras']);
                } else {
                    $paras = array_merge($paras, $groups[$i]['paras']);
                }
            }
            $result['practical'] = self::practical($paras);
        }

        // --- FAQ ----------------------------------------------------------------
        $closingIndex = null;
        if ($iFaq !== null) {
            $paras = $groups[$iFaq]['paras'];
            for ($i = $iFaq + 1; $i < $n; $i++) {
                if ($groups[$i]['heading'] !== null) {
                    $closingIndex = $i;
                    break;
                }
                $paras = array_merge($paras, $groups[$i]['paras']);
            }
            [$faq, $missing] = self::faq($paras);
            $result['faq'] = $faq;
            if ($missing > 0) {
                $result['gaps'][] = "{$missing} FAQ answer(s) were collapsed accordions in the scan and are empty";
            }
        }

        // --- closing CTA ---------------------------------------------------------
        $closingIndex ??= ($iFaq !== null ? null : $n - 1);
        for ($i = $n - 1; $i >= 0; $i--) {
            if ($groups[$i]['heading'] === null || $groups[$i]['paras'] === []) {
                continue;
            }
            if ($iFaq !== null && $i <= $iFaq) {
                break;
            }
            if ($iFaq === null && ($iPrac !== null && $i <= $iPrac)) {
                break;
            }
            $result['closing'] = self::section($groups[$i]);
            $closingIndex = $i;
            break;
        }

        // --- anything the anchors never claimed ------------------------------------
        $claimed = [];
        foreach ([$iWhy, $iPrac, $iFaq, $iItin, $closingIndex] as $idx) {
            if ($idx !== null) {
                $claimed[$idx] = true;
            }
        }
        for ($i = 0; $i < $n; $i++) {
            if (isset($claimed[$i]) || $i < $start) {
                continue;
            }
            if ($iItin !== null && $i > $iItin && $i < $limit) { continue; }
            if ($iWhy !== null && $i > $iWhy && $i < self::nextAnchor([$iPrac, $iFaq], $iWhy, $n)) { continue; }
            if ($iPrac !== null && $i > $iPrac && $i < self::nextAnchor([$iFaq], $iPrac, $n)) { continue; }
            if ($iFaq !== null && $i > $iFaq && ($closingIndex === null || $i < $closingIndex)) { continue; }
            if ($groups[$i]['heading'] === null && $groups[$i]['paras'] === []) { continue; }
            if (in_array(self::section($groups[$i]), [$result['hook'], $result['solution']], true)) { continue; }
            if ($groups[$i]['paras'] === []) { continue; }
            $result['leftover'][] = self::section($groups[$i]);
        }

        // --- CTA text ---------------------------------------------------------------
        foreach ($nodes as $node) {
            if ($node['kind'] === 'cta') {
                $result['cta_text'] = self::titleCase($node['text']);
                break;
            }
        }

        foreach (['hook', 'solution', 'itinerary', 'practical', 'faq', 'closing'] as $key) {
            if ($result[$key] === '' || $result[$key] === []) {
                $result['gaps'][] = "no {$key} section could be located in the scan";
            }
        }

        return $result;
    }

    /** @param array<int,?int> $candidates */
    private static function nextAnchor(array $candidates, int $after, int $fallback): int
    {
        $best = $fallback;
        foreach ($candidates as $c) {
            if ($c !== null && $c > $after && $c < $best) {
                $best = $c;
            }
        }
        return $best;
    }

    /** @param array{heading:?string,paras:array<int,string>} $group */
    private static function section(array $group): string
    {
        $heading = $group['heading'] !== null ? '## ' . $group['heading'] . "\n\n" : '';
        return trim($heading . implode("\n\n", $group['paras']));
    }

    /**
     * @param array<int,array{level:int,text:string}> $headings
     * @return array<int,array{kind:string,text:string}>
     */
    private static function nodes(string $body, array $headings, string $title): array
    {
        $index = [];
        foreach ($headings as $h) {
            $index[self::key($h['text'])] = true;
        }

        $nodes = [];
        $seenTitle = false;
        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $k = self::key($line);
            if (!$seenTitle && $k === self::key($title)) {
                $seenTitle = true;
                continue;
            }
            if (isset($index[$k])) {
                $nodes[] = ['kind' => 'h', 'text' => $line];
                continue;
            }
            if (self::isCta($line)) {
                $nodes[] = ['kind' => 'cta', 'text' => $line];
                continue;
            }
            $nodes[] = ['kind' => 'p', 'text' => $line];
        }
        return $nodes;
    }

    /**
     * @param array<int,array{kind:string,text:string}> $nodes
     * @return array<int,array{heading:?string,paras:array<int,string>}>
     */
    private static function groups(array $nodes): array
    {
        $groups = [];
        $current = ['heading' => null, 'paras' => []];
        foreach ($nodes as $node) {
            if ($node['kind'] === 'h') {
                if ($current['heading'] !== null || $current['paras'] !== []) {
                    $groups[] = $current;
                }
                $current = ['heading' => $node['text'], 'paras' => []];
                continue;
            }
            if ($node['kind'] === 'cta') {
                continue;
            }
            $current['paras'][] = $node['text'];
        }
        if ($current['heading'] !== null || $current['paras'] !== []) {
            $groups[] = $current;
        }
        return $groups;
    }

    /**
     * Elementor prints a run of sibling headings first and their paragraphs after.
     * Where the counts line up, hand each heading its own paragraph.
     *
     * @param array<int,array{heading:?string,paras:array<int,string>}> $groups
     * @return array<int,array{heading:?string,paras:array<int,string>}>
     */
    private static function redistribute(array $groups): array
    {
        $n = count($groups);
        for ($i = 0; $i < $n; $i++) {
            if ($groups[$i]['heading'] === null || $groups[$i]['paras'] !== []) {
                continue;
            }
            $run = [];
            for ($j = $i; $j < $n; $j++) {
                if ($groups[$j]['heading'] === null) {
                    break;
                }
                $run[] = $j;
                if ($groups[$j]['paras'] !== []) {
                    break;
                }
            }
            if (count($run) < 3) {
                continue;
            }
            $last = (int) end($run);
            $paras = $groups[$last]['paras'];
            if (count($paras) !== count($run)) {
                continue;
            }
            foreach ($run as $k => $idx) {
                $groups[$idx]['paras'] = [$paras[$k]];
            }
            $i = $last;
        }
        return $groups;
    }

    /** @param array<int,array{heading:?string,paras:array<int,string>}> $groups */
    private static function indexOf(array $groups, string $pattern): ?int
    {
        foreach ($groups as $i => $g) {
            if ($g['heading'] !== null && preg_match($pattern, $g['heading'])) {
                return $i;
            }
        }
        return null;
    }

    /**
     * @param array<int,string> $paras
     * @return array<int,array{label:string,value:string}>
     */
    private static function practical(array $paras): array
    {
        $out = [];
        foreach ($paras as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            if (preg_match('/^([A-Z][A-Za-z \/&\']{1,28}):\s*(.+)$/u', $p, $m)) {
                $out[] = ['label' => trim($m[1]), 'value' => trim($m[2])];
                continue;
            }
            $out[] = ['label' => '', 'value' => $p];
        }
        return $out;
    }

    /**
     * @param array<int,string> $paras
     * @return array{0:array<int,array{q:string,a:string}>,1:int}
     */
    private static function faq(array $paras): array
    {
        $out = [];
        $missing = 0;
        $pending = null;
        foreach ($paras as $p) {
            $p = trim($p);
            if ($p === '' || preg_match('/^(still have a question|contact us directly)/i', $p)) {
                break;
            }
            if (str_ends_with($p, '?') && mb_strlen($p) < 140) {
                if ($pending !== null) {
                    $out[] = ['q' => $pending, 'a' => ''];
                    $missing++;
                }
                $pending = $p;
                continue;
            }
            if ($pending !== null) {
                $out[] = ['q' => $pending, 'a' => $p];
                $pending = null;
            }
        }
        if ($pending !== null) {
            $out[] = ['q' => $pending, 'a' => ''];
            $missing++;
        }
        return [$out, $missing];
    }

    private static function isCta(string $line): bool
    {
        if (mb_strlen($line) > 48 || mb_strlen($line) < 4) {
            return false;
        }
        if (!preg_match('/\p{Lu}/u', $line)) {
            return false;
        }
        return !preg_match('/\p{Ll}/u', $line);
    }

    private static function titleCase(string $s): string
    {
        return mb_convert_case(mb_strtolower(trim($s), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    private static function key(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = str_replace(["\u{2019}", "\u{2018}", "\u{201C}", "\u{201D}", "\u{2013}", "\u{2014}", "\u{2011}"], ["'", "'", '"', '"', '-', '-', '-'], $s);
        return (string) preg_replace('/[^a-z0-9]+/u', '', $s);
    }
}
