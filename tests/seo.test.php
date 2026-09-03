<?php
declare(strict_types=1);

use Ttp\Markdown;
use Ttp\Seo;
use Ttp\Str;

test('document title keeps the brand suffix when it fits in 60 characters', function (): void {
    $seo = Seo::make('Jesuit Ruins Tour', 'x', '/jesuit-ruins-tour/');
    assert_same('Jesuit Ruins Tour | Things to do in Paraguay', $seo->documentTitle());
    assert_true(mb_strlen($seo->documentTitle()) <= Seo::TITLE_MAX);
});

test('document title drops the suffix rather than overrunning', function (): void {
    $title = 'Best neighborhoods to live in Asuncion: where should you settle down';
    $seo   = Seo::make($title, 'x', '/x/');
    assert_true(!str_contains($seo->documentTitle(), '| Things to do'), 'suffix should have been dropped');
});

test('meta description is trimmed to 155 characters on a word boundary', function (): void {
    $long = str_repeat('Paraguay is a quiet country worth seeing properly. ', 10);
    $seo  = Seo::make('T', $long, '/x/');
    assert_true(mb_strlen($seo->description) <= Seo::DESC_MAX, 'got ' . mb_strlen($seo->description));
    assert_true(str_ends_with($seo->description, '…'), 'a truncated description should be marked as such');
});

test('a short description is left exactly as written', function (): void {
    $seo = Seo::make('T', 'Nine words long, which is well under the cap.', '/x/');
    assert_same('Nine words long, which is well under the cap.', $seo->description);
});

test('FAQ JSON-LD drops questions whose answer is still missing', function (): void {
    $node = Seo::faqNode([
        ['q' => 'Answered?', 'a' => 'Yes.'],
        ['q' => 'Unanswered?', 'a' => ''],
        ['q' => '', 'a' => 'Orphan answer'],
    ], 'https://example.com/x/');
    assert_true($node !== null);
    assert_same(1, count($node['mainEntity']));
    assert_same('Answered?', $node['mainEntity'][0]['name']);
});

test('FAQ JSON-LD is omitted entirely when no answer survives', function (): void {
    assert_same(null, Seo::faqNode([['q' => 'Q', 'a' => '']], 'https://example.com/x/'));
});

test('the JSON-LD graph parses and every node is typed', function (): void {
    $seo = Seo::make('T', 'D', '/about/');
    $seo->breadcrumbs = [['name' => 'About', 'path' => null]];
    $data = json_decode($seo->jsonLd(), true);
    assert_true(is_array($data), 'JSON-LD must parse');
    assert_same('https://schema.org', $data['@context']);
    foreach ($data['@graph'] as $node) {
        assert_true(isset($node['@type']), 'every @graph node needs an @type');
    }
    $types = array_column($data['@graph'], '@type');
    assert_true(in_array('WebSite', $types, true));
    assert_true(in_array('Organization', $types, true));
    assert_true(in_array('BreadcrumbList', $types, true));
});

test('the head block carries the whole SEO layer', function (): void {
    $seo = Seo::make('T', 'D', '/about/');
    $seo->nextPath = '/blog/page/2/';
    $head = $seo->renderHead();
    foreach ([
        '<title>', 'name="description"', 'rel="canonical"', 'name="robots"',
        'property="og:type"', 'property="og:title"', 'property="og:image"',
        'name="twitter:card"', 'application/ld+json', 'rel="next"',
        'type="application/rss+xml"',
    ] as $needle) {
        assert_contains($needle, $head);
    }
});

test('noindex pages say so', function (): void {
    $seo = Seo::make('T', 'D', '/x/');
    $seo->noindex = true;
    assert_contains('content="noindex, follow"', $seo->renderHead());
});

test('head values are escaped', function (): void {
    $seo = Seo::make('Quote " and <script>', 'Ampersand & angle <', '/x/');
    $head = $seo->renderHead();
    assert_true(!str_contains($head, '<script>alert'), 'no raw markup should survive');
    assert_contains('&lt;script&gt;', $head);
});

test('Str::slug and Markdown::truncate behave', function (): void {
    assert_same('asuncion-city-tour', Str::slug('Asunción City Tour'));
    assert_same('jesus-de-tavarangue', Str::slug('Jesús de Tavarangue'));
    assert_same('short', Markdown::truncate('short', 20));
    assert_true(mb_strlen(Markdown::truncate(str_repeat('word ', 40), 30)) <= 30);
});
