<?php
declare(strict_types=1);

namespace Ttp;

use Ttp\Repo\CategoryRepo;
use Ttp\Repo\ContentRepo;
use Ttp\Repo\MediaRepo;
use Ttp\Repo\RedirectRepo;

/**
 * The URL contract (plan §1.4), in code. Resolution order:
 *
 *   1. path normalisation + trailing-slash canonicalisation (301)
 *   2. the `redirects` table (301 / 410) — docs/url-map.csv is seeded into it
 *   3. machine routes: /sitemap.xml, /robots.txt, /feed.xml
 *   4. fixed routes: /, /blog/, /blog/page/N/, /category/<slug>/,
 *      /tours/, /services/, /tourist-attractions-paraguay/
 *   5. content by slug — posts, pages, tours and services are all flat
 *   6. 404
 */
final class Router
{
    public const BLOG_PATH        = '/blog/';
    public const TOURS_PATH       = '/tours/';
    public const SERVICES_PATH    = '/services/';
    public const ATTRACTIONS_PATH = '/tourist-attractions-paraguay/';

    public static function dispatch(string $method, string $uri): Response
    {
        $path  = self::normalise($uri);
        $query = (string) (parse_url($uri, PHP_URL_QUERY) ?? '');

        // 1. Trailing-slash canonicalisation. Anything that looks like a file
        //    (a dot in the last segment) is left alone: /robots.txt, /wp-login.php.
        $canonical = self::canonicalPath($path);
        if ($canonical !== $path) {
            return Response::redirect($canonical . ($query !== '' ? '?' . $query : ''), 301);
        }

        // 2. The redirects table.
        $redirect = RedirectRepo::find($path);
        if ($redirect !== null) {
            RedirectRepo::hit($path);
            if ($redirect['status'] === 410) {
                return self::gone($path);
            }
            $to = $redirect['to_path'] !== '' ? $redirect['to_path'] : '/';
            return Response::redirect($to, 301);
        }

        // 3. Machine-readable routes.
        switch ($path) {
            case '/sitemap.xml':
                return Response::text(Sitemap::xml(), 'application/xml; charset=UTF-8');
            case '/robots.txt':
                return Response::text(Sitemap::robots(), 'text/plain; charset=UTF-8');
            case '/feed.xml':
                return Response::text(Feed::xml(), 'application/rss+xml; charset=UTF-8');
        }

        // 4. Fixed routes.
        if ($path === '/') {
            return self::home();
        }
        if ($path === self::BLOG_PATH) {
            return self::blog(1);
        }
        if (preg_match('#^/blog/page/(\d+)/$#', $path, $m) === 1) {
            $page = (int) $m[1];
            if ($page < 2) {
                return Response::redirect(self::BLOG_PATH, 301);
            }
            return self::blog($page);
        }
        if (preg_match('#^/category/([a-z0-9-]+)/$#', $path, $m) === 1) {
            return self::category($m[1], 1);
        }
        if (preg_match('#^/category/([a-z0-9-]+)/page/(\d+)/$#', $path, $m) === 1) {
            return self::category($m[1], max(1, (int) $m[2]));
        }
        if ($path === self::TOURS_PATH) {
            return self::typeIndex('tour');
        }
        if ($path === self::SERVICES_PATH) {
            return self::typeIndex('service');
        }
        if ($path === self::ATTRACTIONS_PATH) {
            return self::attractions();
        }

        // 5. Content by slug.
        if (preg_match('#^/([a-z0-9][a-z0-9-]*)/$#i', $path, $m) === 1) {
            $item = ContentRepo::findBySlug(strtolower($m[1]));
            if ($item !== null) {
                return self::item($item);
            }
        }

        return self::notFound($path);
    }

    // -----------------------------------------------------------------------
    // Path handling
    // -----------------------------------------------------------------------

    public static function normalise(string $uri): string
    {
        // Strip query and fragment by hand: parse_url() reads a leading "//" as a
        // protocol-relative host, which would swallow the path entirely.
        $path = (string) preg_replace('/[?#].*$/s', '', $uri);
        if (preg_match('~^[a-z][a-z0-9+.\-]*://~i', $path) === 1) {
            $path = (string) (parse_url($path, PHP_URL_PATH) ?? '/');
        }
        $path = rawurldecode($path);
        $path = (string) preg_replace('/[\x00-\x1F\x7F]/', '', $path);
        $path = (string) preg_replace('#/{2,}#', '/', $path);
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }
        return $path;
    }

    /**
     * Adds the trailing slash unless the last segment looks like a filename, and
     * lower-cases the path — every URL in the contract is lower-case, so serving
     * /About/ as well as /about/ would be duplicate content.
     */
    public static function canonicalPath(string $path): string
    {
        $path = mb_strtolower($path, 'UTF-8');
        if ($path === '/' || str_ends_with($path, '/')) {
            return $path;
        }
        $last = substr($path, (int) strrpos($path, '/') + 1);
        return str_contains($last, '.') ? $path : $path . '/';
    }

    // -----------------------------------------------------------------------
    // Routes
    // -----------------------------------------------------------------------

    private static function home(): Response
    {
        $c    = ttp_config();
        $seo  = Seo::make(
            'Things to do in Paraguay — tours, day trips and relocation help',
            'Guided tours, day trips and relocation services in Paraguay, run by Anton and Yanina in Asunción. '
            . 'Plan your visit with people who live here.',
            '/'
        );
        $seo->graphs[] = [
            '@type'      => 'WebPage',
            '@id'        => Seo::url('/') . '#webpage',
            'url'        => Seo::url('/'),
            'name'       => $seo->documentTitle(),
            'description'=> $seo->description,
            'isPartOf'   => ['@id' => Seo::siteUrl() . '/#website'],
            'about'      => ['@id' => Seo::siteUrl() . '/#organization'],
        ];

        return Response::html(View::render('home', [
            'tours'      => ContentRepo::published('tour', 6),
            'services'   => ContentRepo::published('service', 6),
            'posts'      => ContentRepo::published('post', 6),
            'categories' => CategoryRepo::withPosts(),
            'config'     => $c,
        ], $seo));
    }

    private static function blog(int $page): Response
    {
        $perPage = ContentRepo::POSTS_PER_PAGE;
        $total   = ContentRepo::countPosts();
        $pages   = max(1, (int) ceil($total / $perPage));
        if ($page > $pages) {
            return self::notFound(self::BLOG_PATH . 'page/' . $page . '/');
        }

        $path = $page === 1 ? self::BLOG_PATH : self::BLOG_PATH . 'page/' . $page . '/';
        $seo  = Seo::make(
            $page === 1 ? 'Paraguay travel blog' : 'Paraguay travel blog — page ' . $page,
            'Guides, city write-ups and practical advice about travelling, working and living in Paraguay.',
            $path
        );
        $seo->breadcrumbs = [['name' => 'Blog', 'path' => self::BLOG_PATH]];
        $seo->graphs[]    = self::collectionNode($seo, ContentRepo::postsPage($page, $perPage));
        if ($page > 1) {
            $seo->prevPath = $page === 2 ? self::BLOG_PATH : self::BLOG_PATH . 'page/' . ($page - 1) . '/';
        }
        if ($page < $pages) {
            $seo->nextPath = self::BLOG_PATH . 'page/' . ($page + 1) . '/';
        }

        return Response::html(View::render('blog', [
            'posts'      => ContentRepo::postsPage($page, $perPage),
            'page'       => $page,
            'pages'      => $pages,
            'total'      => $total,
            'basePath'   => self::BLOG_PATH,
            'categories' => CategoryRepo::withPosts(),
        ], $seo));
    }

    private static function category(string $slug, int $page): Response
    {
        $category = CategoryRepo::findBySlug($slug);
        if ($category === null) {
            return self::notFound('/category/' . $slug . '/');
        }

        $perPage = ContentRepo::POSTS_PER_PAGE;
        $total   = ContentRepo::countPosts((int) $category['id']);
        $pages   = max(1, (int) ceil($total / $perPage));
        if ($page > $pages) {
            return self::notFound('/category/' . $slug . '/page/' . $page . '/');
        }

        $base  = CategoryRepo::path($category);
        $path  = $page === 1 ? $base : $base . 'page/' . $page . '/';
        $name  = (string) $category['name'];
        $desc  = (string) ($category['meta_description'] ?: $category['description']);
        if ($desc === '') {
            $desc = 'Articles about ' . $name . ' in Paraguay — guides, tips and places worth your time.';
        }

        $seo = Seo::make(
            (string) ($category['meta_title'] ?: $name . ' in Paraguay'),
            $desc,
            $path
        );
        $seo->breadcrumbs = [
            ['name' => 'Blog', 'path' => self::BLOG_PATH],
            ['name' => $name, 'path' => $base],
        ];
        $posts = ContentRepo::postsByCategory((int) $category['id'], $page, $perPage);
        $seo->graphs[] = self::collectionNode($seo, $posts);
        if ($page > 1) {
            $seo->prevPath = $page === 2 ? $base : $base . 'page/' . ($page - 1) . '/';
        }
        if ($page < $pages) {
            $seo->nextPath = $base . 'page/' . ($page + 1) . '/';
        }

        return Response::html(View::render('category', [
            'category'   => $category,
            'posts'      => $posts,
            'page'       => $page,
            'pages'      => $pages,
            'total'      => $total,
            'basePath'   => $base,
            'categories' => CategoryRepo::withPosts(),
        ], $seo));
    }

    /** /tours/ and /services/ — the two type indexes. */
    private static function typeIndex(string $type): Response
    {
        $isTour = $type === 'tour';
        $path   = $isTour ? self::TOURS_PATH : self::SERVICES_PATH;
        $items  = ContentRepo::published($type);

        // /services/ also exists as an editable page; use its copy as the intro.
        $intro = ContentRepo::findBySlug(trim($path, '/'));

        $title = $isTour
            ? 'Tours and day trips in Paraguay'
            : 'Relocation and travel services in Paraguay';
        $desc  = $isTour
            ? 'Every guided tour and day trip we run in Paraguay — Asunción, the Jesuit missions, Itaipú, '
              . 'waterfalls, birding and more.'
            : 'Practical help on the ground in Paraguay: airport transfers, private drivers, apartment '
              . 'hunting, residency, schools and healthcare.';

        if ($intro !== null) {
            $title = (string) ($intro['meta_title'] ?: $intro['title'] ?: $title);
            if (trim((string) $intro['meta_description']) !== '') {
                $desc = (string) $intro['meta_description'];
            } elseif (trim((string) $intro['excerpt']) !== '') {
                $desc = (string) $intro['excerpt'];
            }
        }

        $seo = Seo::make($title, $desc, $path);
        $seo->breadcrumbs = [['name' => $isTour ? 'Tours' : 'Services', 'path' => $path]];
        $seo->graphs[]    = self::collectionNode($seo, $items);

        return Response::html(View::render('type-index', [
            'type'     => $type,
            'heading'  => $isTour ? 'Tours and day trips' : 'Services',
            'items'    => $items,
            'intro'    => $intro,
            'basePath' => $path,
        ], $seo));
    }

    private static function attractions(): Response
    {
        $seo = Seo::make(
            'Tourist attractions in Paraguay',
            'The places worth travelling for in Paraguay — national parks, waterfalls, the Jesuit missions, '
            . 'Asunción and the Chaco — with the tours that get you there.',
            self::ATTRACTIONS_PATH
        );
        $seo->breadcrumbs = [['name' => 'Tourist attractions', 'path' => self::ATTRACTIONS_PATH]];

        $tours = ContentRepo::published('tour');
        $posts = ContentRepo::published('post');
        $seo->graphs[] = self::collectionNode($seo, array_merge($tours, $posts));

        $grouped = [];
        foreach ($posts as $post) {
            $key = (string) ($post['category_name'] ?? '');
            $grouped[$key === '' ? 'More from the blog' : $key][] = $post;
        }
        ksort($grouped);

        return Response::html(View::render('attractions', [
            'tours'   => $tours,
            'grouped' => $grouped,
        ], $seo));
    }

    /** A single post, page, tour or service. */
    private static function item(array $item): Response
    {
        $type = (string) $item['type'];
        $path = (string) $item['path'];

        $description = (string) $item['meta_description'];
        if ($description === '') {
            $description = (string) $item['excerpt'];
        }
        if (trim($description) === '') {
            $description = Markdown::toText((string) $item['body_md']);
        }

        $seo = Seo::make(
            (string) ($item['meta_title'] ?: $item['title']),
            $description,
            $path
        );
        $override = trim((string) $item['canonical_override']);
        if ($override !== '') {
            $seo->canonicalPath = $override;
        }
        $seo->noindex     = ((int) $item['noindex']) === 1;
        $seo->publishedAt = View::isoDate((string) $item['published_at']);
        $seo->modifiedAt  = View::isoDate((string) $item['updated_at']);

        $cover = MediaRepo::find($item['cover_media_id'] === null ? null : (int) $item['cover_media_id']);
        $og    = MediaRepo::find($item['og_image_media_id'] === null ? null : (int) $item['og_image_media_id'])
                 ?? $cover;
        if ($og !== null) {
            $seo->ogImage    = (string) $og['path'];
            $seo->ogImageAlt = (string) ($og['alt'] ?: $item['title']);
        }

        $details = in_array($type, ['tour', 'service'], true) ? ContentRepo::details((int) $item['id']) : null;

        switch ($type) {
            case 'post':
                return self::post($item, $seo, $cover);
            case 'tour':
            case 'service':
                return self::tour($item, $seo, $details, $cover);
            default:
                return self::page($item, $seo, $cover);
        }
    }

    private static function post(array $item, Seo $seo, ?array $cover): Response
    {
        $seo->ogType = 'article';
        $category    = empty($item['category_slug'])
            ? null
            : CategoryRepo::findBySlug((string) $item['category_slug']);

        $seo->breadcrumbs = [['name' => 'Blog', 'path' => self::BLOG_PATH]];
        if ($category !== null) {
            $seo->breadcrumbs[] = ['name' => (string) $category['name'], 'path' => CategoryRepo::path($category)];
        }
        $seo->breadcrumbs[] = ['name' => (string) $item['title'], 'path' => null];

        $node = [
            '@type'            => 'BlogPosting',
            '@id'              => $seo->canonicalUrl() . '#article',
            'mainEntityOfPage' => ['@id' => $seo->canonicalUrl()],
            'headline'         => Markdown::truncate((string) $item['title'], 110),
            'description'      => $seo->description,
            'inLanguage'       => 'en',
            'datePublished'    => $seo->publishedAt,
            'dateModified'     => $seo->modifiedAt,
            'author'           => ['@id' => Seo::siteUrl() . '/#organization'],
            'publisher'        => ['@id' => Seo::siteUrl() . '/#organization'],
            'wordCount'        => (int) $item['word_count'],
        ];
        if ($cover !== null) {
            $node['image'] = Seo::url((string) $cover['path']);
        }
        if ($category !== null) {
            $node['articleSection'] = (string) $category['name'];
        }
        $seo->graphs[] = $node;

        [$html, $toc] = View::withToc((string) $item['body_html']);

        return Response::html(View::render('post', [
            'item'       => $item,
            'bodyHtml'   => $html,
            'toc'        => count($toc) >= 4 ? $toc : [],
            'category'   => $category,
            'tags'       => ContentRepo::tagsFor((int) $item['id']),
            'related'    => ContentRepo::related($item, 3),
            'neighbours' => ContentRepo::neighbours($item),
            'cover'      => $cover,
        ], $seo));
    }

    private static function tour(array $item, Seo $seo, ?array $details, ?array $cover): Response
    {
        $isTour = $item['type'] === 'tour';
        $seo->breadcrumbs = [
            ['name' => $isTour ? 'Tours' : 'Services', 'path' => $isTour ? self::TOURS_PATH : self::SERVICES_PATH],
            ['name' => (string) $item['title'], 'path' => null],
        ];

        $node = [
            '@type'       => $isTour ? 'TouristTrip' : 'Service',
            '@id'         => $seo->canonicalUrl() . ($isTour ? '#trip' : '#service'),
            'name'        => (string) $item['title'],
            'description' => $seo->description,
            'url'         => $seo->canonicalUrl(),
            'provider'    => ['@id' => Seo::siteUrl() . '/#organization'],
        ];
        if ($cover !== null) {
            $node['image'] = Seo::url((string) $cover['path']);
        }
        if ($isTour) {
            $node['touristType'] = 'Leisure';
            $node['itinerary']   = ['@type' => 'ItemList', 'itemListElement' => []];
            foreach (array_values($details['itinerary'] ?? []) as $i => $step) {
                $name = trim((string) ($step['title'] ?? $step['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $node['itinerary']['itemListElement'][] = [
                    '@type'    => 'ListItem',
                    'position' => $i + 1,
                    'name'     => $name,
                ];
            }
            if ($node['itinerary']['itemListElement'] === []) {
                unset($node['itinerary']);
            }
        } else {
            $node['serviceType'] = (string) $item['title'];
            $node['areaServed']  = ['@type' => 'Country', 'name' => 'Paraguay'];
        }
        if ($details !== null && $details['price_usd'] !== null && (float) $details['price_usd'] > 0) {
            $node['offers'] = [
                '@type'         => 'Offer',
                'price'         => (string) (float) $details['price_usd'],
                'priceCurrency' => 'USD',
                'url'           => $seo->canonicalUrl(),
                'availability'  => 'https://schema.org/InStock',
            ];
        }
        $seo->graphs[] = $node;

        $faq = $details['faq'] ?? [];
        $faqNode = Seo::faqNode(is_array($faq) ? $faq : [], $seo->canonicalUrl());
        if ($faqNode !== null) {
            $seo->graphs[] = $faqNode;
        }

        return Response::html(View::render('tour', [
            'item'    => $item,
            'details' => $details,
            'cover'   => $cover,
            'isTour'  => $isTour,
            'related' => ContentRepo::related($item, 3),
        ], $seo));
    }

    private static function page(array $item, Seo $seo, ?array $cover): Response
    {
        $seo->breadcrumbs = [['name' => (string) $item['title'], 'path' => null]];
        $seo->graphs[]    = [
            '@type'       => 'WebPage',
            '@id'         => $seo->canonicalUrl() . '#webpage',
            'url'         => $seo->canonicalUrl(),
            'name'        => $seo->documentTitle(),
            'description' => $seo->description,
            'isPartOf'    => ['@id' => Seo::siteUrl() . '/#website'],
            'dateModified'=> $seo->modifiedAt,
        ];

        [$html, $toc] = View::withToc((string) $item['body_html']);

        return Response::html(View::render('page', [
            'item'     => $item,
            'bodyHtml' => $html,
            'toc'      => count($toc) >= 4 ? $toc : [],
            'cover'    => $cover,
        ], $seo));
    }

    // -----------------------------------------------------------------------
    // Error routes
    // -----------------------------------------------------------------------

    private static function notFound(string $path): Response
    {
        $seo = Seo::make('Page not found', 'That page does not exist on Things to do in Paraguay.', $path);
        $seo->noindex     = true;
        $seo->breadcrumbs = [['name' => 'Page not found', 'path' => null]];

        $response = Response::html(View::render('404', [
            'path'  => $path,
            'tours' => ContentRepo::published('tour', 4),
            'posts' => ContentRepo::published('post', 4),
        ], $seo), 404);
        $response->cacheable = false;
        $response->headers['Cache-Control'] = 'no-store';
        return $response;
    }

    /** 410 Gone — the junk URLs in docs/url-map.csv. */
    private static function gone(string $path): Response
    {
        $seo = Seo::make('Page removed', 'This page has been permanently removed.', $path);
        $seo->noindex     = true;
        $seo->breadcrumbs = [['name' => 'Page removed', 'path' => null]];

        $response = Response::html(View::render('410', ['path' => $path], $seo), 410);
        $response->cacheable = false;
        $response->headers['Cache-Control'] = 'no-store';
        return $response;
    }

    /** @param array<int,array<string,mixed>> $items */
    private static function collectionNode(Seo $seo, array $items): array
    {
        $elements = [];
        foreach (array_values($items) as $i => $item) {
            $elements[] = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'url'      => Seo::url((string) $item['path']),
                'name'     => (string) $item['title'],
            ];
        }
        return [
            '@type'       => 'CollectionPage',
            '@id'         => $seo->canonicalUrl() . '#webpage',
            'url'         => $seo->canonicalUrl(),
            'name'        => $seo->documentTitle(),
            'description' => $seo->description,
            'isPartOf'    => ['@id' => Seo::siteUrl() . '/#website'],
            'mainEntity'  => ['@type' => 'ItemList', 'itemListElement' => $elements],
        ];
    }
}
