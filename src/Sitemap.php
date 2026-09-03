<?php
declare(strict_types=1);

namespace Ttp;

use Ttp\Repo\CategoryRepo;
use Ttp\Repo\ContentRepo;
use Ttp\Repo\MediaRepo;

/**
 * /sitemap.xml and /robots.txt.
 *
 * The sitemap lists exactly the canonical URLs the site serves with a 200:
 * the fixed routes, every published non-noindex content item and every
 * category. Paginated archives are deliberately left out — they are crawlable
 * through rel=next and add nothing to the index.
 */
final class Sitemap
{
    /**
     * Canonical path => ['lastmod' => ISO-8601|null, 'images' => [path, …]].
     *
     * @return array<string,array{lastmod:?string,images:array<int,string>}>
     */
    public static function entries(): array
    {
        $items   = ContentRepo::allPublished();
        $updated = null;
        foreach ($items as $item) {
            $stamp = (string) $item['updated_at'];
            if ($updated === null || $stamp > $updated) {
                $updated = $stamp;
            }
        }

        $entries = [];
        foreach ([
            '/',
            Router::BLOG_PATH,
            Router::TOURS_PATH,
            Router::SERVICES_PATH,
            Router::ATTRACTIONS_PATH,
        ] as $path) {
            $entries[$path] = ['lastmod' => $updated, 'images' => []];
        }

        foreach ($items as $item) {
            if ((int) $item['noindex'] === 1) {
                continue;
            }
            $images = [];
            foreach (['cover_media_id', 'og_image_media_id'] as $key) {
                $media = MediaRepo::find($item[$key] === null ? null : (int) $item[$key]);
                if ($media !== null && !in_array((string) $media['path'], $images, true)) {
                    $images[] = (string) $media['path'];
                }
            }
            $entries[(string) $item['path']] = [
                'lastmod' => (string) $item['updated_at'],
                'images'  => $images,
            ];
        }

        foreach (CategoryRepo::all() as $category) {
            $path = CategoryRepo::path($category);
            $entries[$path] ??= ['lastmod' => $updated, 'images' => []];
        }

        return $entries;
    }

    /** @return array<int,string> */
    public static function paths(): array
    {
        return array_keys(self::entries());
    }

    public static function xml(): string
    {
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
             . ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        foreach (self::entries() as $path => $entry) {
            $out .= "  <url>\n";
            $out .= '    <loc>' . Str::e(Seo::url($path)) . "</loc>\n";
            $lastmod = View::isoDate($entry['lastmod']);
            if ($lastmod !== '') {
                $out .= '    <lastmod>' . $lastmod . "</lastmod>\n";
            }
            $out .= '    <changefreq>' . ($path === '/' ? 'daily' : 'weekly') . "</changefreq>\n";
            $out .= '    <priority>' . ($path === '/' ? '1.0' : '0.7') . "</priority>\n";
            foreach ($entry['images'] as $image) {
                $out .= "    <image:image>\n";
                $out .= '      <image:loc>' . Str::e(Seo::url($image)) . "</image:loc>\n";
                $out .= "    </image:image>\n";
            }
            $out .= "  </url>\n";
        }

        return $out . "</urlset>\n";
    }

    public static function robots(): string
    {
        return implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin/',
            'Disallow: /*?*',
            '',
            'Sitemap: ' . Seo::url('/sitemap.xml'),
            '',
        ]);
    }
}
