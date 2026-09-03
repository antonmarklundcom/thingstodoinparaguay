<?php
declare(strict_types=1);

namespace Ttp;

/**
 * The SEO layer (plan §1.9). Every route builds one of these; the layout emits it.
 *
 * Responsibilities: title, meta description, canonical, robots, Open Graph,
 * Twitter cards, pagination rel links and the JSON-LD `@graph`.
 */
final class Seo
{
    public const TITLE_SUFFIX = ' | Things to do in Paraguay';
    public const TITLE_MAX    = 60;
    public const DESC_MAX     = 155;

    /** Bare page title, without the brand suffix. */
    public string $title = '';
    public string $description = '';
    public string $canonicalPath = '/';
    public string $ogType = 'website';
    public ?string $ogImage = null;
    public ?string $ogImageAlt = null;
    public bool $noindex = false;

    /** @var array<int,array{name:string,path:?string}> Home is prepended by render(). */
    public array $breadcrumbs = [];

    /** Extra JSON-LD nodes (BlogPosting, TouristTrip, FAQPage …). @var array<int,array<string,mixed>> */
    public array $graphs = [];

    public ?string $prevPath = null;
    public ?string $nextPath = null;
    public ?string $publishedAt = null;
    public ?string $modifiedAt = null;

    public static function make(string $title, string $description, string $canonicalPath): self
    {
        $seo = new self();
        $seo->title         = $title;
        $seo->description   = self::trimDescription($description);
        $seo->canonicalPath = $canonicalPath;
        return $seo;
    }

    // -----------------------------------------------------------------------
    // Derived values
    // -----------------------------------------------------------------------

    public static function siteUrl(): string
    {
        return rtrim(ttp_config()['site_url'], '/');
    }

    public static function url(string $path): string
    {
        if (preg_match('~^https?://~i', $path) === 1) {
            return $path;               // an absolute canonical override
        }
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }
        return self::siteUrl() . $path;
    }

    /**
     * `{title} | Things to do in Paraguay`, kept under 60 characters where the
     * title allows it — an over-long title is worse than a missing suffix.
     */
    public function documentTitle(): string
    {
        $title = trim($this->title);
        if ($title === '') {
            return ttp_config()['site_name'];
        }
        $full = $title . self::TITLE_SUFFIX;
        if (mb_strlen($full) <= self::TITLE_MAX) {
            return $full;
        }
        if (mb_strlen($title) <= self::TITLE_MAX) {
            return $title;
        }
        return Markdown::truncate($title, self::TITLE_MAX);
    }

    public static function trimDescription(string $text): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        return $text === '' ? '' : Markdown::truncate($text, self::DESC_MAX);
    }

    public function canonicalUrl(): string
    {
        return self::url($this->canonicalPath);
    }

    // -----------------------------------------------------------------------
    // JSON-LD
    // -----------------------------------------------------------------------

    /** @return array<string,mixed> */
    public static function websiteNode(): array
    {
        $c = ttp_config();
        return [
            '@type'      => 'WebSite',
            '@id'        => self::siteUrl() . '/#website',
            'url'        => self::siteUrl() . '/',
            'name'       => $c['site_name'],
            'description'=> $c['tagline'],
            'inLanguage' => 'en',
            'publisher'  => ['@id' => self::siteUrl() . '/#organization'],
        ];
    }

    /** @return array<string,mixed> */
    public static function organizationNode(): array
    {
        $c = ttp_config();
        return [
            '@type'     => 'Organization',
            '@id'       => self::siteUrl() . '/#organization',
            'name'      => $c['site_name'],
            'url'       => self::siteUrl() . '/',
            'email'     => $c['email'],
            'telephone' => $c['phone'],
            'address'   => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => 'Edificio Skytower',
                'addressLocality' => 'Asunción',
                'addressCountry'  => 'PY',
            ],
        ];
    }

    /** @return array<string,mixed>|null */
    public function breadcrumbNode(): ?array
    {
        if ($this->breadcrumbs === []) {
            return null;
        }
        $trail = array_merge([['name' => 'Home', 'path' => '/']], $this->breadcrumbs);
        $items = [];
        foreach (array_values($trail) as $i => $crumb) {
            $node = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $crumb['name'],
            ];
            if (!empty($crumb['path'])) {
                $node['item'] = self::url($crumb['path']);
            }
            $items[] = $node;
        }
        return [
            '@type'           => 'BreadcrumbList',
            '@id'             => $this->canonicalUrl() . '#breadcrumb',
            'itemListElement' => $items,
        ];
    }

    /**
     * FAQPage from `[{q, a}, …]`. Entries whose answer the scan never captured
     * are dropped — an empty acceptedAnswer is invalid structured data.
     *
     * @param  array<int,array<string,mixed>> $faq
     * @return array<string,mixed>|null
     */
    public static function faqNode(array $faq, string $canonicalUrl): ?array
    {
        $entities = [];
        foreach ($faq as $row) {
            $q = trim((string) ($row['q'] ?? ''));
            $a = trim((string) ($row['a'] ?? ''));
            if ($q === '' || $a === '') {
                continue;
            }
            $entities[] = [
                '@type'          => 'Question',
                'name'           => $q,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
            ];
        }
        if ($entities === []) {
            return null;
        }
        return [
            '@type'      => 'FAQPage',
            '@id'        => $canonicalUrl . '#faq',
            'mainEntity' => $entities,
        ];
    }

    /** The whole `@graph`, ready to encode. @return array<string,mixed> */
    public function graph(): array
    {
        $nodes = [self::websiteNode(), self::organizationNode()];
        $crumbs = $this->breadcrumbNode();
        if ($crumbs !== null) {
            $nodes[] = $crumbs;
        }
        foreach ($this->graphs as $node) {
            if ($node !== null && $node !== []) {
                $nodes[] = $node;
            }
        }
        return ['@context' => 'https://schema.org', '@graph' => $nodes];
    }

    public function jsonLd(): string
    {
        return (string) json_encode(
            $this->graph(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );
    }

    // -----------------------------------------------------------------------
    // Head markup
    // -----------------------------------------------------------------------

    public function renderHead(): string
    {
        $c    = ttp_config();
        $e    = static fn (?string $s): string => Str::e($s);
        $out  = [];
        $out[] = '<title>' . $e($this->documentTitle()) . '</title>';

        if ($this->description !== '') {
            $out[] = '<meta name="description" content="' . $e($this->description) . '">';
        }
        $out[] = '<link rel="canonical" href="' . $e($this->canonicalUrl()) . '">';
        $out[] = '<meta name="robots" content="'
            . ($this->noindex ? 'noindex, follow' : 'index, follow, max-image-preview:large')
            . '">';

        if ($this->prevPath !== null) {
            $out[] = '<link rel="prev" href="' . $e(self::url($this->prevPath)) . '">';
        }
        if ($this->nextPath !== null) {
            $out[] = '<link rel="next" href="' . $e(self::url($this->nextPath)) . '">';
        }

        // Open Graph
        $out[] = '<meta property="og:type" content="' . $e($this->ogType) . '">';
        $out[] = '<meta property="og:site_name" content="' . $e($c['site_name']) . '">';
        $out[] = '<meta property="og:locale" content="en_US">';
        $out[] = '<meta property="og:title" content="' . $e($this->documentTitle()) . '">';
        if ($this->description !== '') {
            $out[] = '<meta property="og:description" content="' . $e($this->description) . '">';
        }
        $out[] = '<meta property="og:url" content="' . $e($this->canonicalUrl()) . '">';

        $image = $this->ogImage ?? '/assets/og-default.png';
        $out[] = '<meta property="og:image" content="' . $e(self::url($image)) . '">';
        $out[] = '<meta property="og:image:alt" content="' . $e($this->ogImageAlt ?? $this->documentTitle()) . '">';

        if ($this->ogType === 'article') {
            if ($this->publishedAt !== null) {
                $out[] = '<meta property="article:published_time" content="' . $e($this->publishedAt) . '">';
            }
            if ($this->modifiedAt !== null) {
                $out[] = '<meta property="article:modified_time" content="' . $e($this->modifiedAt) . '">';
            }
        }

        // Twitter
        $out[] = '<meta name="twitter:card" content="summary_large_image">';
        $out[] = '<meta name="twitter:title" content="' . $e($this->documentTitle()) . '">';
        if ($this->description !== '') {
            $out[] = '<meta name="twitter:description" content="' . $e($this->description) . '">';
        }
        $out[] = '<meta name="twitter:image" content="' . $e(self::url($image)) . '">';

        $out[] = '<link rel="alternate" type="application/rss+xml" title="'
            . $e($c['site_name']) . '" href="' . $e(self::url('/feed.xml')) . '">';

        $out[] = '<script type="application/ld+json">' . $this->jsonLd() . '</script>';

        return implode("\n    ", $out);
    }
}
