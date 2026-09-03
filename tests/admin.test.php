<?php
declare(strict_types=1);

/**
 * Phase O2's exit criteria, as tests (plan §5.2): login fail/success/lockout,
 * CSRF rejection, create → publish → slug change → 301, the SEO score against a
 * known fixture, and a 3000 px JPEG producing three WebP sizes.
 *
 * These drive Ttp\Admin\App directly rather than over HTTP: the class takes its
 * request arrays as parameters for exactly that reason, and the session is
 * array-backed under the CLI (src/Admin/Session.php).
 */

use Ttp\Admin\App;
use Ttp\Admin\Auth;
use Ttp\Admin\ContentWriter;
use Ttp\Admin\Csrf;
use Ttp\Admin\Session;
use Ttp\Cache;
use Ttp\Db;
use Ttp\Repo\ContentRepo;
use Ttp\Repo\RedirectRepo;
use Ttp\Response;
use Ttp\SeoScore;
use Ttp\Uploader;

/** A throwaway install: schema applied, one admin account, nothing seeded. */
function ttp_admin_env(): string
{
    $dir = sys_get_temp_dir() . '/ttp-admin-test-' . bin2hex(random_bytes(4));
    mkdir($dir . '/cache', 0775, true);
    mkdir($dir . '/media', 0775, true);

    Db::use($dir . '/site.sqlite');
    Db::conn()->exec((string) file_get_contents(ttp_root() . '/db/schema.sql'));
    Cache::use($dir . '/cache', 3600);
    Session::reset();
    Session::useArray();

    Auth::createUser('anton@example.com', 'correcthorse123', 'Anton');
    return $dir;
}

function ttp_admin_cleanup(string $dir): void
{
    Cache::reset();
    Session::reset();
    Db::use(ttp_config()['db_path']);

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) {
        $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
    }
    @rmdir($dir);
}

/** Sign in the way the panel does, so the session carries a real user. */
function ttp_admin_login(): void
{
    $result = Auth::attempt('anton@example.com', 'correcthorse123', '10.0.0.1');
    assert_true($result['ok'], 'the fixture login should succeed');
}

/** @return array<string,mixed> a POST body carrying the session's CSRF token */
function ttp_admin_post(array $fields): array
{
    return $fields + [Csrf::FIELD => Csrf::token()];
}

// ---------------------------------------------------------------------------
// Login
// ---------------------------------------------------------------------------

test('login fails on a wrong password and says nothing about the account', function (): void {
    $dir = ttp_admin_env();
    try {
        $result = Auth::attempt('anton@example.com', 'not-the-password', '10.0.0.1');
        assert_true(!$result['ok'], 'a wrong password must not sign anyone in');
        assert_same(Auth::ERROR_CREDENTIALS, $result['error']);
        assert_true(!Auth::check(), 'no session should exist after a failure');

        // An unknown address fails identically — no account enumeration.
        $unknown = Auth::attempt('nobody@example.com', 'not-the-password', '10.0.0.1');
        assert_same(Auth::ERROR_CREDENTIALS, $unknown['error']);
    } finally {
        ttp_admin_cleanup($dir);
    }
});

test('login succeeds with the right password and records the sign-in', function (): void {
    $dir = ttp_admin_env();
    try {
        $result = Auth::attempt('Anton@Example.com', 'correcthorse123', '10.0.0.1');
        assert_true($result['ok'], 'the right password should sign in');
        assert_true(Auth::check(), 'the session should now carry the user');
        assert_same('anton@example.com', (string) Auth::user()['email']);
        assert_true((string) Db::value('SELECT last_login_at FROM users WHERE id = 1') !== '', 'last_login_at is set');

        // The password is stored as a bcrypt hash, never in the clear.
        $hash = (string) Db::value('SELECT password_hash FROM users WHERE id = 1');
        assert_true(str_starts_with($hash, '$2y$'), 'passwords must be bcrypt');
        assert_true(!str_contains($hash, 'correcthorse123'), 'the password must not be recoverable');
    } finally {
        ttp_admin_cleanup($dir);
    }
});

test('too many failures lock the account out, even with the right password', function (): void {
    $dir = ttp_admin_env();
    try {
        for ($i = 0; $i < Auth::MAX_ATTEMPTS; $i++) {
            $result = Auth::attempt('anton@example.com', 'wrong-' . $i, '10.0.0.9');
            assert_same(Auth::ERROR_CREDENTIALS, $result['error'], 'attempt ' . $i . ' should be a plain failure');
        }

        $locked = Auth::attempt('anton@example.com', 'correcthorse123', '10.0.0.9');
        assert_true(!$locked['ok'], 'the right password must not get in during a lockout');
        assert_same(Auth::ERROR_LOCKED, $locked['error']);
        assert_true($locked['retry_after'] > 0, 'the lockout should report how long to wait');
        assert_true(!Auth::check(), 'a locked-out attempt must not create a session');

        // A different IP with a different email is unaffected.
        Db::run('DELETE FROM login_attempts');
        $after = Auth::attempt('anton@example.com', 'correcthorse123', '10.0.0.9');
        assert_true($after['ok'], 'the lockout lifts once the failures are gone');
    } finally {
        ttp_admin_cleanup($dir);
    }
});

test('a locked-out login is refused by the panel with a wait time', function (): void {
    $dir = ttp_admin_env();
    try {
        for ($i = 0; $i < Auth::MAX_ATTEMPTS; $i++) {
            Auth::record('anton@example.com', '10.0.0.5', false);
        }
        $response = App::handle('POST', '/admin/login/', [], ttp_admin_post([
            'email'    => 'anton@example.com',
            'password' => 'correcthorse123',
        ]), [], ['REMOTE_ADDR' => '10.0.0.5']);

        assert_same(401, $response->status);
        assert_contains('Too many failed attempts', $response->body);
    } finally {
        ttp_admin_cleanup($dir);
    }
});

// ---------------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------------

test('a mutating request without the CSRF token is refused with 403', function (): void {
    $dir = ttp_admin_env();
    try {
        ttp_admin_login();

        $response = App::handle('POST', '/admin/content/save/', [], [
            'type'  => 'post',
            'title' => 'Forged',
        ], [], []);

        assert_same(403, $response->status, 'a POST without a token must be refused');
        assert_same(0, (int) Db::value('SELECT COUNT(*) FROM content_items'), 'nothing may be written');

        // A token from someone else's session is refused just the same.
        $wrong = App::handle('POST', '/admin/content/save/', [], [
            'type' => 'post', 'title' => 'Forged', Csrf::FIELD => str_repeat('a', 64),
        ], [], []);
        assert_same(403, $wrong->status);

        // With the real token the same request goes through.
        $ok = App::handle('POST', '/admin/content/save/', [], ttp_admin_post([
            'type' => 'post', 'title' => 'Allowed', 'status' => 'draft', 'body_md' => 'Hello.',
        ]), [], []);
        assert_same(303, $ok->status);
        assert_same(1, (int) Db::value('SELECT COUNT(*) FROM content_items'));
    } finally {
        ttp_admin_cleanup($dir);
    }
});

test('the panel is closed to anyone who is not signed in', function (): void {
    $dir = ttp_admin_env();
    try {
        $get = App::handle('GET', '/admin/content/', [], [], [], []);
        assert_same(303, $get->status, 'a signed-out GET is sent to the login form');
        assert_contains('/admin/login/', $get->headers['Location'] ?? '');

        $post = App::handle('POST', '/admin/content/save/', [], ['title' => 'x'], [], []);
        assert_same(401, $post->status, 'a signed-out POST is refused outright');

        // The login form itself must stay reachable.
        assert_same(200, App::handle('GET', '/admin/login/', [], [], [], [])->status);
    } finally {
        ttp_admin_cleanup($dir);
    }
});

test('the sign-in redirect never leaves the panel', function (): void {
    $dir = ttp_admin_env();
    try {
        $response = App::handle('POST', '/admin/login/', [], ttp_admin_post([
            'email'    => 'anton@example.com',
            'password' => 'correcthorse123',
            'next'     => 'https://evil.example.com/admin/',
        ]), [], []);

        assert_same(303, $response->status);
        assert_same('/admin/', $response->headers['Location'] ?? '', 'an off-site "next" must be dropped');
    } finally {
        ttp_admin_cleanup($dir);
    }
});

// ---------------------------------------------------------------------------
// Create → publish → rename → 301
// ---------------------------------------------------------------------------

test('create, publish, rename the slug, and the old address 301s', function (): void {
    $dir = ttp_admin_env();
    try {
        ttp_admin_login();

        // 1. Create as a draft. The slug comes from the title.
        $created = App::handle('POST', '/admin/content/save/', [], ttp_admin_post([
            'type'    => 'post',
            'title'   => 'Visiting Salto Cristal in one day',
            'status'  => 'draft',
            'body_md' => "## Getting there\n\nTake the bus.",
        ]), [], []);
        assert_same(303, $created->status);

        $id = (int) Db::value('SELECT id FROM content_items');
        $item = ContentRepo::findById($id);
        assert_same('visiting-salto-cristal-in-one-day', (string) $item['slug']);
        assert_same('draft', (string) $item['status']);
        assert_same('admin', (string) $item['source'], 'the seeder must never overwrite this row again');
        assert_true(ContentRepo::findBySlug('visiting-salto-cristal-in-one-day') === null, 'a draft is not public');

        // 2. Publish it.
        App::handle('POST', '/admin/content/status/', [], ttp_admin_post([
            'id' => $id, 'status' => 'published',
        ]), [], []);
        $item = ContentRepo::findById($id);
        assert_same('published', (string) $item['status']);
        assert_true((string) $item['published_at'] !== '', 'publishing stamps a date');
        assert_true(ContentRepo::findBySlug('visiting-salto-cristal-in-one-day') !== null, 'it is public now');

        // The published page is in the HTML cache…
        Cache::put('/visiting-salto-cristal-in-one-day/', '<html>stale</html>');
        Cache::put('/sitemap.xml', '<urlset>stale</urlset>');

        // 3. Rename the slug of the live page.
        $renamed = App::handle('POST', '/admin/content/save/', [], ttp_admin_post([
            'id'      => $id,
            'type'    => 'post',
            'title'   => 'Visiting Salto Cristal in one day',
            'slug'    => 'salto-cristal-one-day',
            'status'  => 'published',
            'body_md' => "## Getting there\n\nTake the bus.",
        ]), [], []);
        assert_same(303, $renamed->status);
        assert_same('salto-cristal-one-day', (string) ContentRepo::findById($id)['slug']);

        // 4. …and the old address is a 301 to the new one.
        $redirect = RedirectRepo::find('/visiting-salto-cristal-in-one-day/');
        assert_true($redirect !== null, 'renaming a live page must leave a redirect');
        assert_same(301, (int) $redirect['status']);
        assert_same('/salto-cristal-one-day/', (string) $redirect['to_path']);
        assert_same('slug-change', (string) Db::value(
            'SELECT source FROM redirects WHERE from_path = ?',
            ['/visiting-salto-cristal-in-one-day/']
        ));

        // 5. The stale cache entries are gone — the sitemap included.
        assert_true(Cache::get('/visiting-salto-cristal-in-one-day/') === null, 'the old page must leave the cache');
        assert_true(Cache::get('/sitemap.xml') === null, 'the sitemap must be rebuilt');

        // 6. The router honours all of it.
        $response = Ttp\Router::dispatch('GET', '/visiting-salto-cristal-in-one-day/');
        assert_same(301, $response->status);
        assert_same('/salto-cristal-one-day/', $response->headers['Location'] ?? '');
        assert_same(200, Ttp\Router::dispatch('GET', '/salto-cristal-one-day/')->status);
    } finally {
        ttp_admin_cleanup($dir);
    }
});

test('renaming twice keeps the oldest address pointing at the live page', function (): void {
    $dir = ttp_admin_env();
    try {
        $first = ContentWriter::save([
            'type' => 'post', 'title' => 'First name', 'slug' => 'first-name',
            'status' => 'published', 'body_md' => 'Body.',
        ]);
        ContentWriter::save([
            'type' => 'post', 'title' => 'First name', 'slug' => 'second-name',
            'status' => 'published', 'body_md' => 'Body.',
        ], $first['id']);
        ContentWriter::save([
            'type' => 'post', 'title' => 'First name', 'slug' => 'third-name',
            'status' => 'published', 'body_md' => 'Body.',
        ], $first['id']);

        assert_same('/third-name/', (string) RedirectRepo::find('/first-name/')['to_path'], 'no redirect chain');
        assert_same('/third-name/', (string) RedirectRepo::find('/second-name/')['to_path']);
    } finally {
        ttp_admin_cleanup($dir);
    }
});

test('a draft never steals a URL the old site still redirects', function (): void {
    $dir = ttp_admin_env();
    try {
        RedirectRepo::upsert('/home/', '/', 301, 'map');

        ContentWriter::save([
            'type' => 'page', 'title' => 'Home draft', 'slug' => 'home',
            'status' => 'draft', 'body_md' => 'Draft.',
        ]);
        assert_true(RedirectRepo::find('/home/') !== null, 'a draft must leave the URL map alone');

        // Publishing at that address is a deliberate act and does take it over.
        $id = (int) Db::value('SELECT id FROM content_items');
        ContentWriter::save([
            'type' => 'page', 'title' => 'Home draft', 'slug' => 'home',
            'status' => 'published', 'body_md' => 'Live.',
        ], $id);
        assert_true(RedirectRepo::find('/home/') === null, 'a published page must not sit behind a redirect');
    } finally {
        ttp_admin_cleanup($dir);
    }
});

test('deleting a published item leaves its address redirecting, not 404ing', function (): void {
    $dir = ttp_admin_env();
    try {
        $saved = ContentWriter::save([
            'type' => 'tour', 'title' => 'A tour', 'slug' => 'a-tour',
            'status' => 'published', 'body_md' => 'Body.',
        ]);
        ContentWriter::delete($saved['id']);

        $redirect = RedirectRepo::find('/a-tour/');
        assert_true($redirect !== null, 'the address must survive the delete');
        assert_same('/tours/', (string) $redirect['to_path']);
    } finally {
        ttp_admin_cleanup($dir);
    }
});

test('a scheduled item goes live once its time passes', function (): void {
    $dir = ttp_admin_env();
    try {
        $saved = ContentWriter::save([
            'type' => 'post', 'title' => 'Later', 'slug' => 'later', 'status' => 'scheduled',
            'published_at' => gmdate('c', time() + 3600), 'body_md' => 'Body.',
        ]);
        assert_same('scheduled', (string) Db::value('SELECT status FROM content_items WHERE id = ?', [$saved['id']]));
        assert_same([], ContentWriter::publishDue(), 'nothing is due yet');

        Db::run('UPDATE content_items SET published_at = ? WHERE id = ?', [gmdate('c', time() - 60), $saved['id']]);
        assert_same(['/later/'], ContentWriter::publishDue());
        assert_same('published', (string) Db::value('SELECT status FROM content_items WHERE id = ?', [$saved['id']]));
    } finally {
        ttp_admin_cleanup($dir);
    }
});

// ---------------------------------------------------------------------------
// SEO score
// ---------------------------------------------------------------------------

test('the SEO score grades a known fixture exactly', function (): void {
    // A post that passes every rule except the two it cannot: it is 40 words long
    // and has no cover image. 100 − 12 (word count) − 4 (cover) = 84.
    $body = "Salto Cristal is the waterfall everyone asks about, and this guide covers it.\n\n"
          . "## Getting to Salto Cristal\n\n"
          . "Drive south, then take the turn-off. See [our tour](/salto-cristal-tour/) or the "
          . "[blog](/blog/), and check [Wikipedia](https://en.wikipedia.org/wiki/Paraguay) for background.\n\n"
          . "## What to bring\n\n"
          . "Sturdy shoes and water.";

    $item = [
        'type'             => 'post',
        'slug'             => 'salto-cristal-guide',
        'title'            => 'Salto Cristal: the waterfall guide',
        'focus_keyword'    => 'salto cristal',
        'meta_title'       => 'Salto Cristal waterfall — how to visit in a day',
        'meta_description' => 'How to reach Salto Cristal from Asuncion, what the walk is like, '
                            . 'what it costs and when to go for the best water.',
        'body_md'          => $body,
        'cover_media_id'   => null,
    ];

    $score  = SeoScore::forItem($item);
    $failed = array_column($score->failing(), 'id');
    sort($failed);

    assert_same(['cover_image', 'word_count'], $failed, 'only the two unmeetable rules should fail');
    assert_same(84, $score->score, 'the fixture must score exactly 84');
    assert_same('good', $score->grade());

    // Every rule is reported, and the weights add up to 100.
    assert_same(13, count($score->checks));
    assert_same(100, array_sum(array_column($score->checks, 'points')));
});

test('the SEO score fails the things it is supposed to fail', function (): void {
    // An empty draft only earns the two rules that are vacuously true — it has no
    // placeholder text and no images without alt. Everything of substance fails.
    $empty  = SeoScore::forItem(['type' => 'post', 'slug' => '', 'title' => '', 'body_md' => '']);
    $passed = array_column(array_filter($empty->checks, static fn (array $c): bool => $c['passed']), 'id');
    sort($passed);
    assert_same(['image_alt', 'no_lorem'], $passed);
    assert_same(10, $empty->score);
    assert_same('poor', $empty->grade());

    $lorem = SeoScore::forItem([
        'type' => 'post', 'slug' => 'x', 'title' => 'X',
        'body_md' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
    ]);
    $failed = array_column($lorem->failing(), 'id');
    assert_true(in_array('no_lorem', $failed, true), 'placeholder copy must be caught');

    // A keyword must match as a whole phrase, not as a fragment.
    $partial = SeoScore::forItem([
        'type' => 'post', 'slug' => 'asuncionista-life', 'title' => 'Asuncionista life',
        'focus_keyword' => 'asuncion', 'body_md' => 'About asuncionistas.',
    ]);
    assert_true(
        in_array('focus_in_title', array_column($partial->failing(), 'id'), true),
        '“asuncion” must not match inside “asuncionista”'
    );

    // An image without alt text costs the image rule.
    $noAlt = SeoScore::forItem([
        'type' => 'post', 'slug' => 'x', 'title' => 'X',
        'body_md' => 'Text with ![](/media/x.jpg) an undescribed image.',
    ]);
    assert_true(in_array('image_alt', array_column($noAlt->failing(), 'id'), true));
});

test('a tour is graded on its structured sections, not just its body', function (): void {
    $details = [
        'hook_md'    => 'Getting to the Jesuit ruins on your own is a long day of guesswork.',
        'itinerary'  => [['title' => 'Pick-up in Asuncion', 'body' => 'We collect you at seven.']],
        'faq'        => [['q' => 'How long is the drive?', 'a' => 'About four hours each way.']],
        'closing_md' => 'Ask us for the next departure.',
    ];
    $item = ['type' => 'tour', 'slug' => 'jesuit-ruins-tour', 'title' => 'Jesuit ruins tour', 'body_md' => ''];

    $withSections = SeoScore::forItem($item, $details);
    $bodyOnly     = SeoScore::forItem($item);

    assert_true($withSections->wordCount > $bodyOnly->wordCount, 'the sections count towards the word count');
    assert_true(
        !in_array('headings', array_column($withSections->failing(), 'id'), true),
        'the itinerary and FAQ headings count as H2s'
    );
});

test('the editor score endpoint returns the same numbers as the class', function (): void {
    $dir = ttp_admin_env();
    try {
        ttp_admin_login();
        $fields = ttp_admin_post([
            'type' => 'post', 'slug' => 'salto-cristal', 'title' => 'Salto Cristal guide',
            'focus_keyword' => 'salto cristal', 'body_md' => '## Salto Cristal basics',
        ]);

        $response = App::handle('POST', '/admin/api/score/', [], $fields, [], []);
        assert_same(200, $response->status);

        $data = json_decode($response->body, true);
        assert_true(is_array($data), 'the endpoint returns JSON');
        assert_same(SeoScore::forItem($fields)->score, (int) $data['score']);
        assert_same(13, count($data['checks']));
    } finally {
        ttp_admin_cleanup($dir);
    }
});

// ---------------------------------------------------------------------------
// Media
// ---------------------------------------------------------------------------

test('a 3000px JPEG upload produces three WebP sizes and three fallbacks', function (): void {
    $dir = ttp_admin_env();
    try {
        $source = $dir . '/source.jpg';
        $image  = imagecreatetruecolor(3000, 2000);
        for ($x = 0; $x < 3000; $x += 60) {
            imagefilledrectangle($image, $x, 0, $x + 30, 2000, imagecolorallocate($image, $x % 255, 90, 160));
        }
        imagejpeg($image, $source, 88);
        imagedestroy($image);

        $media = Uploader::store($source, 'Salto Cristal — big.JPG', 'The waterfall from below', $dir . '/media', '/media');
        $sizes = Uploader::sizes($media);

        assert_same(3, count($sizes), 'three widths for a 3000 px source');
        assert_same([400, 800, 1600], array_column($sizes, 'width'));

        $webp = 0;
        foreach ($sizes as $size) {
            $webpFile = $dir . '/media' . substr((string) $size['webp'], strlen('/media'));
            $origFile = $dir . '/media' . substr((string) $size['original'], strlen('/media'));

            assert_true(is_file($webpFile), 'missing ' . $size['webp']);
            assert_true(is_file($origFile), 'missing ' . $size['original']);
            assert_same('image/webp', Uploader::detectMime($webpFile), $size['webp'] . ' must really be WebP');
            assert_same('image/jpeg', Uploader::detectMime($origFile), $size['original'] . ' must keep its format');

            $info = getimagesize($webpFile);
            assert_same((int) $size['width'], (int) $info[0], 'the WebP is the width it claims');
            $webp++;
        }
        assert_same(3, $webp, 'exactly three WebP sizes');

        // The row points at the largest fallback and carries the alt text.
        assert_same(1600, (int) $media['width']);
        assert_same('image/jpeg', (string) $media['mime']);
        assert_same('The waterfall from below', (string) $media['alt']);
        assert_contains('/media/', (string) $media['path']);

        // The filename is a slug of what the browser sent — never the raw name.
        assert_true(
            preg_match('#/salto-cristal-big-1600\.jpg$#', (string) $media['path']) === 1,
            'unexpected stored path: ' . $media['path']
        );
    } finally {
        ttp_admin_cleanup($dir);
    }
});

test('an image smaller than 400px is never upscaled', function (): void {
    $dir = ttp_admin_env();
    try {
        $source = $dir . '/small.png';
        $image  = imagecreatetruecolor(300, 200);
        imagefilledrectangle($image, 0, 0, 300, 200, imagecolorallocate($image, 20, 120, 90));
        imagepng($image, $source);
        imagedestroy($image);

        $media = Uploader::store($source, 'small.png', 'A small graphic', $dir . '/media', '/media');
        $sizes = Uploader::sizes($media);

        assert_same(1, count($sizes), 'one variant at the native width');
        assert_same(300, (int) $sizes[0]['width']);
        assert_same('image/png', (string) $media['mime']);
    } finally {
        ttp_admin_cleanup($dir);
    }
});

test('uploads are validated by what the file is, not what it claims', function (): void {
    $dir = ttp_admin_env();
    try {
        $fake = $dir . '/payload.jpg';
        file_put_contents($fake, "<?php echo 'pwned'; ?>\n");

        $threw = false;
        try {
            Uploader::store($fake, 'payload.jpg', 'Nothing', $dir . '/media', '/media');
        } catch (RuntimeException $e) {
            $threw = true;
            assert_contains('images can be uploaded', $e->getMessage());
        }
        assert_true($threw, 'a PHP file named .jpg must be refused');
        assert_same(0, (int) Db::value('SELECT COUNT(*) FROM media'), 'nothing may be recorded');

        // An oversized file is refused before GD ever opens it.
        $big = $dir . '/big.jpg';
        file_put_contents($big, str_repeat('x', Uploader::MAX_BYTES + 1));
        $threwBig = false;
        try {
            Uploader::store($big, 'big.jpg', 'Nothing', $dir . '/media', '/media');
        } catch (RuntimeException $e) {
            $threwBig = true;
            assert_contains('limit is', $e->getMessage());
        }
        assert_true($threwBig, 'an oversized upload must be refused');
    } finally {
        ttp_admin_cleanup($dir);
    }
});

test('deleting an image removes every size it generated', function (): void {
    $dir = ttp_admin_env();
    try {
        $source = $dir . '/wide.jpg';
        $image  = imagecreatetruecolor(1200, 800);
        imagejpeg($image, $source, 80);
        imagedestroy($image);

        $media = Uploader::store($source, 'wide.jpg', 'Wide', $dir . '/media', '/media');
        $saved = ContentWriter::save([
            'type' => 'post', 'title' => 'Uses the image', 'status' => 'draft',
            'body_md' => 'Body.', 'cover_media_id' => (int) $media['id'],
        ]);
        assert_same((int) $media['id'], (int) Db::value('SELECT cover_media_id FROM content_items WHERE id = ?', [$saved['id']]));

        $removed = Uploader::delete($media, $dir . '/media', '/media');
        assert_same(4, $removed, 'two widths in two formats');
        assert_same(0, (int) Db::value('SELECT COUNT(*) FROM media'));
        assert_true(
            Db::value('SELECT cover_media_id FROM content_items WHERE id = ?', [$saved['id']]) === null,
            'the item must not point at a deleted image'
        );
    } finally {
        ttp_admin_cleanup($dir);
    }
});

// ---------------------------------------------------------------------------
// Markdown safety
// ---------------------------------------------------------------------------

test('markup in a body is escaped, never rendered', function (): void {
    $dir = ttp_admin_env();
    try {
        $saved = ContentWriter::save([
            'type' => 'post', 'title' => 'Stored payload', 'status' => 'published',
            'body_md' => "Hello <script>alert(1)</script> and <img src=x onerror=alert(1)>.\n\n"
                       . '[click](javascript:alert(1))',
        ]);

        $html = (string) Db::value('SELECT body_html FROM content_items WHERE id = ?', [$saved['id']]);
        assert_true(!str_contains($html, '<script'), 'a script tag must never survive');
        assert_true(!str_contains($html, '<img'), 'an author-supplied tag must never become markup');
        assert_true(!str_contains($html, 'href="javascript:'), 'a javascript: link must be defused');
        assert_contains('&lt;script&gt;', $html, 'it is escaped, not silently dropped');
        assert_contains('&lt;img src=x onerror=alert(1)&gt;', $html, 'the whole tag is shown as text');
    } finally {
        ttp_admin_cleanup($dir);
    }
});

// ---------------------------------------------------------------------------
// Validation
// ---------------------------------------------------------------------------

test('the editor refuses a duplicate, reserved or malformed slug', function (): void {
    $dir = ttp_admin_env();
    try {
        ContentWriter::save(['type' => 'post', 'title' => 'Taken', 'slug' => 'taken', 'status' => 'draft', 'body_md' => 'x']);

        $duplicate = ContentWriter::save(['type' => 'post', 'title' => 'Also taken', 'slug' => 'taken', 'status' => 'draft']);
        assert_true(!$duplicate['ok']);
        assert_contains('/taken/', $duplicate['errors']['slug'] ?? '');

        $reserved = ContentWriter::save(['type' => 'page', 'title' => 'Admin', 'slug' => 'admin', 'status' => 'draft']);
        assert_true(!$reserved['ok'], '/admin/ belongs to the panel');

        $noTitle = ContentWriter::save(['type' => 'post', 'title' => '   ', 'status' => 'draft']);
        assert_true(!$noTitle['ok']);
        assert_true(isset($noTitle['errors']['title']));

        assert_same(1, (int) Db::value('SELECT COUNT(*) FROM content_items'), 'no failed save may write a row');
    } finally {
        ttp_admin_cleanup($dir);
    }
});

test('every admin response is uncacheable and unindexable', function (): void {
    $dir = ttp_admin_env();
    try {
        ttp_admin_login();
        foreach (['/admin/', '/admin/content/', '/admin/media/', '/admin/settings/'] as $path) {
            $response = App::handle('GET', $path, [], [], [], []);
            assert_same(200, $response->status, $path);
            assert_true(!$response->cacheable, $path . ' must never be cached');
            assert_contains('no-store', $response->headers['Cache-Control'] ?? '', $path);
            assert_contains('noindex', $response->headers['X-Robots-Tag'] ?? '', $path);
        }
        assert_true(!Cache::cacheable('GET', '/admin/', ''), 'the page cache skips /admin outright');
    } finally {
        ttp_admin_cleanup($dir);
    }
});

test('a CSV export cannot smuggle a spreadsheet formula out of a public form', function (): void {
    $dir = ttp_admin_env();
    try {
        ttp_admin_login();
        Db::run(
            'INSERT INTO leads (name, email, phone, message, page_path, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            ['=HYPERLINK("http://evil.example","Click")', 'a@b.test', '+595 1', 'Hi', '/contact/', gmdate('c')]
        );

        $response = App::handle('GET', '/admin/leads/export.csv', [], [], [], []);
        assert_same(200, $response->status);
        assert_contains('text/csv', $response->headers['Content-Type'] ?? '');
        assert_contains('attachment;', $response->headers['Content-Disposition'] ?? '');

        // The name is still readable, but Excel will treat it as text.
        assert_contains("'=HYPERLINK", $response->body);
        assert_true(
            preg_match('/(^|,)"?=HYPERLINK/m', $response->body) !== 1,
            'no cell may start a formula'
        );

        assert_same('Anton', App::csvSafe('Anton'), 'ordinary values are untouched');
        assert_same('', App::csvSafe(''));
    } finally {
        ttp_admin_cleanup($dir);
    }
});

test('a redirect target can never become an off-site address', function (): void {
    $dir = ttp_admin_env();
    try {
        ttp_admin_login();

        foreach ([
            // A dot in the last segment means the router treats it as a file, so
            // no trailing slash is added — it is still a path on this site.
            '/\\evil.example'       => '/evil.example',
            '//evil.example/path'   => '/evil.example/path/',
            'https://evil.example/x' => '/x/',
            'no-leading-slash'      => '/no-leading-slash/',
        ] as $entered => $expected) {
            App::handle('POST', '/admin/redirects/save/', [], ttp_admin_post([
                'from_path' => '/from-' . md5($entered) . '/',
                'status'    => 301,
                'to_path'   => $entered,
            ]), [], []);

            $stored = RedirectRepo::find('/from-' . md5($entered) . '/');
            assert_true($stored !== null, 'the redirect should have been stored for ' . $entered);
            assert_same($expected, (string) $stored['to_path'], 'target for ' . $entered);
            assert_true(
                str_starts_with((string) $stored['to_path'], '/')
                && !str_starts_with((string) $stored['to_path'], '//'),
                'a target must stay a same-site path: ' . $stored['to_path']
            );
        }
    } finally {
        ttp_admin_cleanup($dir);
    }
});
