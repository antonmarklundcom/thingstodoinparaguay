<?php
declare(strict_types=1);

use Ttp\FrontMatter;
use Ttp\Yaml;

test('Yaml round-trips scalars, lists and nested maps', function (): void {
    $data = [
        'type'      => 'tour',
        'title'     => 'Asunción: "the Mother of Cities"',
        'published' => true,
        'price'     => null,
        'sort'      => 3,
        'tags'      => ['hiking', 'day-trip'],
        'faq'       => [
            ['q' => 'How long is it?', 'a' => 'About 10 hours.'],
            ['q' => 'Is lunch included?', 'a' => ''],
        ],
    ];
    $parsed = Yaml::parse(Yaml::dump($data));
    assert_equals($data, $parsed);
});

test('Yaml round-trips a multi-line block scalar', function (): void {
    $data = ['hook' => "## A heading\n\nA paragraph with a colon: and a dash - inside.\n\nAnother one."];
    $parsed = Yaml::parse(Yaml::dump($data));
    assert_same($data['hook'], rtrim((string) $parsed['hook'], "\n"));
});

test('Yaml keeps an empty list empty rather than turning it into a string', function (): void {
    $parsed = Yaml::parse(Yaml::dump(['itinerary' => [], 'why' => []]));
    assert_same([], $parsed['itinerary']);
    assert_same([], $parsed['why']);
});

test('FrontMatter splits front matter from body and renders it back', function (): void {
    $raw = "---\ntype: post\ntitle: Hello\n---\n\n# Body\n\nText.\n";
    [$fm, $body] = FrontMatter::parse($raw);
    assert_same('post', $fm['type']);
    assert_same('Hello', $fm['title']);
    assert_same("# Body\n\nText.", $body);

    [$fm2, $body2] = FrontMatter::parse(FrontMatter::render($fm, $body));
    assert_equals($fm, $fm2);
    assert_same($body, $body2);
});

test('FrontMatter accepts a JSON block', function (): void {
    [$fm, $body] = FrontMatter::parse("---\n{\"type\":\"page\",\"title\":\"J\"}\n---\n\nBody\n");
    assert_same('page', $fm['type']);
    assert_same('Body', $body);
});

test('FrontMatter treats a body with no front matter as all body', function (): void {
    [$fm, $body] = FrontMatter::parse("Just text.\n");
    assert_same([], $fm);
    assert_same('Just text.', $body);
});

test('every committed content file parses', function (): void {
    $files = glob(ttp_root() . '/content/*/*.md') ?: [];
    assert_true(count($files) > 50, 'expected the seed content to be present, found ' . count($files));
    foreach ($files as $file) {
        [$fm] = FrontMatter::parse((string) file_get_contents($file));
        assert_true($fm !== [], basename($file) . ' has no front matter');
    }
});
