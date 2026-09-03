<?php
declare(strict_types=1);

namespace Ttp;

use DOMDocument;
use DOMNode;
use DOMElement;
use DOMText;

/**
 * Small HTML -> Markdown converter for bin/wp-export.php.
 * Handles what WordPress post bodies actually contain; unknown tags degrade to
 * their text content rather than being dropped.
 */
final class HtmlToMarkdown
{
    public static function convert(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        $doc = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="UTF-8">' . '<div id="ttp-root">' . $html . '</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $doc->getElementById('ttp-root');
        $md = $root ? self::node($root) : strip_tags($html);
        $md = (string) preg_replace('/[ \t]+$/m', '', $md);
        $md = (string) preg_replace('/\n{3,}/', "\n\n", $md);
        return trim($md);
    }

    private static function children(DOMNode $node): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= self::node($child);
        }
        return $out;
    }

    private static function node(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return (string) preg_replace('/\s+/', ' ', $node->nodeValue ?? '');
        }
        if (!$node instanceof DOMElement) {
            return '';
        }

        $tag = strtolower($node->nodeName);
        switch ($tag) {
            case 'script':
            case 'style':
            case 'noscript':
                return '';
            case 'h1': case 'h2': case 'h3': case 'h4': case 'h5': case 'h6':
                $level = (int) substr($tag, 1);
                return "\n\n" . str_repeat('#', $level) . ' ' . trim(self::children($node)) . "\n\n";
            case 'p':
                $t = trim(self::children($node));
                return $t === '' ? '' : "\n\n" . $t . "\n\n";
            case 'br':
                return "  \n";
            case 'hr':
                return "\n\n---\n\n";
            case 'strong': case 'b':
                $t = trim(self::children($node));
                return $t === '' ? '' : '**' . $t . '**';
            case 'em': case 'i':
                $t = trim(self::children($node));
                return $t === '' ? '' : '*' . $t . '*';
            case 'code':
                return '`' . trim(self::children($node)) . '`';
            case 'pre':
                return "\n\n```\n" . trim($node->textContent) . "\n```\n\n";
            case 'blockquote':
                $inner = trim(self::children($node));
                $quoted = implode("\n", array_map(
                    static fn (string $l): string => rtrim('> ' . $l),
                    preg_split('/\n/', $inner) ?: []
                ));
                return "\n\n" . $quoted . "\n\n";
            case 'a':
                $href = trim($node->getAttribute('href'));
                $text = trim(self::children($node));
                if ($text === '') {
                    return '';
                }
                return $href === '' ? $text : '[' . $text . '](' . $href . ')';
            case 'img':
                $src = trim($node->getAttribute('src'));
                $alt = trim($node->getAttribute('alt'));
                return $src === '' ? '' : "\n\n![" . $alt . '](' . $src . ")\n\n";
            case 'ul': case 'ol':
                $out = "\n\n";
                $n = 1;
                foreach ($node->childNodes as $li) {
                    if (!$li instanceof DOMElement || strtolower($li->nodeName) !== 'li') {
                        continue;
                    }
                    $marker = $tag === 'ol' ? ($n++ . '. ') : '- ';
                    $text = trim(self::children($li));
                    $text = (string) preg_replace('/\n{2,}/', "\n", $text);
                    $text = str_replace("\n", "\n  ", $text);
                    $out .= $marker . $text . "\n";
                }
                return $out . "\n";
            case 'table':
                return self::table($node);
            default:
                return self::children($node);
        }
    }

    private static function table(DOMElement $table): string
    {
        $rows = [];
        foreach ($table->getElementsByTagName('tr') as $tr) {
            $cells = [];
            foreach ($tr->childNodes as $cell) {
                if ($cell instanceof DOMElement && in_array(strtolower($cell->nodeName), ['td', 'th'], true)) {
                    $cells[] = trim((string) preg_replace('/\s+/', ' ', self::children($cell)));
                }
            }
            if ($cells !== []) {
                $rows[] = $cells;
            }
        }
        if ($rows === []) {
            return '';
        }
        $head = array_shift($rows);
        $out = "\n\n| " . implode(' | ', $head) . " |\n|" . str_repeat(' --- |', count($head)) . "\n";
        foreach ($rows as $r) {
            $r = array_pad($r, count($head), '');
            $out .= '| ' . implode(' | ', array_slice($r, 0, count($head))) . " |\n";
        }
        return $out . "\n";
    }
}
