<?php
declare(strict_types=1);

namespace Ttp;

/**
 * Plain PHP templates. `View::render()` renders a page template into
 * `templates/layout.php`; `View::partial()` includes a fragment inline.
 *
 * Templates receive their variables as locals plus `$seo` (a Ttp\Seo).
 * Phase S3 re-skins these files; the markup contract (one <h1>, landmarks,
 * breadcrumbs) is set here and must survive.
 */
final class View
{
    public static function dir(): string
    {
        return ttp_root() . '/templates';
    }

    /** @param array<string,mixed> $vars */
    public static function render(string $template, array $vars, Seo $seo): string
    {
        $content = self::capture($template, $vars + ['seo' => $seo]);
        return self::capture('layout', $vars + ['seo' => $seo, 'content' => $content]);
    }

    /** @param array<string,mixed> $vars */
    public static function partial(string $name, array $vars = []): string
    {
        return self::capture('partials/' . $name, $vars);
    }

    /** @param array<string,mixed> $vars */
    private static function capture(string $template, array $vars): string
    {
        $file = self::dir() . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("template not found: {$template}");
        }
        extract($vars, EXTR_SKIP);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }

    // -----------------------------------------------------------------------
    // Helpers used by templates
    // -----------------------------------------------------------------------

    public static function e(?string $s): string
    {
        return Str::e($s);
    }

    /** "24 July 2025" — never a locale-dependent format. */
    public static function date(?string $iso): string
    {
        if ($iso === null || trim($iso) === '') {
            return '';
        }
        $ts = strtotime($iso);
        return $ts === false ? '' : gmdate('j F Y', $ts);
    }

    public static function isoDate(?string $iso): string
    {
        if ($iso === null || trim($iso) === '') {
            return '';
        }
        $ts = strtotime($iso);
        return $ts === false ? '' : gmdate('c', $ts);
    }

    public static function readingTime(int $words): int
    {
        return max(1, (int) ceil($words / 220));
    }

    /**
     * Table of contents from the rendered body: the H2s, in order.
     * Adds `id` attributes to the HTML so the links resolve.
     *
     * @return array{0:string,1:array<int,array{id:string,text:string}>}
     */
    public static function withToc(string $html): array
    {
        $toc  = [];
        $used = [];
        $out  = (string) preg_replace_callback(
            '/<h2(?![^>]*\bid=)([^>]*)>(.*?)<\/h2>/is',
            static function (array $m) use (&$toc, &$used): string {
                $text = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $id   = Str::slug($text);
                if ($id === '') {
                    $id = 'section';
                }
                $base = $id;
                $n    = 2;
                while (isset($used[$id])) {
                    $id = $base . '-' . $n++;
                }
                $used[$id] = true;
                $toc[] = ['id' => $id, 'text' => $text];
                return '<h2 id="' . Str::e($id) . '"' . $m[1] . '>' . $m[2] . '</h2>';
            },
            $html
        );
        return [$out, $toc];
    }

    /**
     * A media row rendered as a responsive image, or '' when there is no image.
     *
     * An upload made through the admin carries the 400/800/1600 variants
     * src/Uploader.php generated, so it renders as a <picture> that offers WebP
     * first and falls back to the original format. Anything without variants
     * (seeded or hand-inserted rows) renders as a plain <img>.
     */
    public static function image(
        ?array $media,
        string $class = '',
        bool $eager = false,
        string $sizes = '(min-width: 60rem) 60rem, 100vw'
    ): string {
        if ($media === null || empty($media['path'])) {
            return '';
        }

        $img = '<img src="' . Str::e((string) $media['path']) . '"'
             . ' alt="' . Str::e((string) ($media['alt'] ?? '')) . '"';
        if ($class !== '') {
            $img .= ' class="' . Str::e($class) . '"';
        }
        if (!empty($media['width']) && !empty($media['height'])) {
            $img .= ' width="' . (int) $media['width'] . '" height="' . (int) $media['height'] . '"';
        }

        $variants = self::variants($media);
        $fallback = self::srcset($variants, 'original');
        if ($fallback !== '') {
            $img .= ' srcset="' . Str::e($fallback) . '" sizes="' . Str::e($sizes) . '"';
        }

        $img .= $eager
            ? ' loading="eager" fetchpriority="high" decoding="async">'
            : ' loading="lazy" decoding="async">';

        $webp = self::srcset($variants, 'webp');
        if ($webp === '') {
            return $img;
        }
        return '<picture><source type="image/webp" srcset="' . Str::e($webp) . '"'
             . ' sizes="' . Str::e($sizes) . '">' . $img . '</picture>';
    }

    /**
     * The variants src/Uploader.php stored for a media row, smallest first.
     *
     * @return array<int,array{width:int,height:int,webp:string,original:string}>
     */
    public static function variants(?array $media): array
    {
        if ($media === null) {
            return [];
        }
        $decoded = json_decode((string) ($media['sizes_json'] ?? '[]'), true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $size) {
            if (is_array($size) && !empty($size['width']) && !empty($size['original'])) {
                $out[] = $size;
            }
        }
        usort($out, static fn (array $a, array $b): int => (int) $a['width'] <=> (int) $b['width']);
        return $out;
    }

    /** @param array<int,array<string,mixed>> $variants */
    public static function srcset(array $variants, string $key): string
    {
        $parts = [];
        foreach ($variants as $variant) {
            $url = (string) ($variant[$key] ?? '');
            if ($url !== '') {
                $parts[] = $url . ' ' . (int) $variant['width'] . 'w';
            }
        }
        return count($parts) > 1 ? implode(', ', $parts) : '';
    }
}
