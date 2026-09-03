<?php
declare(strict_types=1);

namespace Ttp\Admin;

use RuntimeException;
use Throwable;
use Ttp\Cache;
use Ttp\Db;
use Ttp\Markdown;
use Ttp\Repo\CategoryRepo;
use Ttp\Repo\ContentRepo;
use Ttp\Repo\MediaRepo;
use Ttp\Repo\RedirectRepo;
use Ttp\Repo\SettingsRepo;
use Ttp\Response;
use Ttp\Router;
use Ttp\SeoScore;
use Ttp\Str;
use Ttp\Uploader;

/**
 * The /admin/ panel (plan §5.2).
 *
 * Ttp\Router hands every /admin path here before it canonicalises anything, so a
 * POST is never answered with a redirect. Three rules hold for every route:
 *
 *   • nothing but /admin/login/ is reachable without a session;
 *   • every POST must carry the session's CSRF token or it is refused with 403;
 *   • no admin response is ever cacheable — Ttp\Cache already skips /admin, and
 *     the responses say `no-store` as well.
 */
final class App
{
    public const PREFIX = '/admin';

    /** @var array<string,mixed>|null the current request's POST body */
    private static array $post = [];
    /** @var array<string,mixed> */
    private static array $get = [];
    /** @var array<string,mixed> */
    private static array $files = [];
    /** @var array<string,mixed> */
    private static array $server = [];

    public static function handle(
        string $method,
        string $path,
        ?array $get = null,
        ?array $post = null,
        ?array $files = null,
        ?array $server = null
    ): Response {
        self::$get    = $get    ?? $_GET;
        self::$post   = $post   ?? $_POST;
        self::$files  = $files  ?? $_FILES;
        self::$server = $server ?? $_SERVER;

        $method = strtoupper($method);
        $route  = '/' . trim(strtolower($path), '/') . '/';
        $route  = str_replace('//', '/', $route);
        if (str_ends_with($route, '.csv/')) {
            $route = substr($route, 0, -1);
        }

        Session::start();

        // Public route: the login form itself.
        if ($route === '/admin/login/') {
            return $method === 'POST' ? self::doLogin() : self::loginForm();
        }

        if (!Auth::check()) {
            if ($method === 'GET') {
                $next = $route === '/admin/' ? '' : '?next=' . rawurlencode($path);
                return self::to('/admin/login/' . $next);
            }
            return self::deny(401, 'Your session has expired. Sign in again.');
        }

        if ($method === 'POST' && !Csrf::check(Csrf::fromRequest(self::$post, self::$server))) {
            return self::deny(403, 'That form expired before it was submitted. Open the page again and retry.');
        }

        // A scheduled item goes live on the first admin request after its time.
        ContentWriter::publishDue();

        try {
            return match (true) {
                $route === '/admin/'                    => self::dashboard(),
                $route === '/admin/logout/'             => self::doLogout($method),

                $route === '/admin/content/'            => self::contentList(),
                $route === '/admin/content/new/'        => self::editor(null),
                $route === '/admin/content/edit/'       => self::editor(self::intParam('id')),
                $route === '/admin/content/save/'       => self::contentSave($method),
                $route === '/admin/content/status/'     => self::contentStatus($method),
                $route === '/admin/content/delete/'     => self::contentDelete($method),
                $route === '/admin/content/preview/'    => self::preview(),

                $route === '/admin/media/'              => self::mediaList(),
                $route === '/admin/media/upload/'       => self::mediaUpload($method),
                $route === '/admin/media/alt/'          => self::mediaAlt($method),
                $route === '/admin/media/delete/'       => self::mediaDelete($method),

                $route === '/admin/categories/'         => self::categoryList(),
                $route === '/admin/categories/save/'    => self::categorySave($method),
                $route === '/admin/categories/delete/'  => self::categoryDelete($method),

                $route === '/admin/tags/'               => self::tagList(),
                $route === '/admin/tags/save/'          => self::tagSave($method),
                $route === '/admin/tags/delete/'        => self::tagDelete($method),

                $route === '/admin/redirects/'          => self::redirectList(),
                $route === '/admin/redirects/save/'     => self::redirectSave($method),
                $route === '/admin/redirects/delete/'   => self::redirectDelete($method),

                $route === '/admin/settings/'           => self::settings(),
                $route === '/admin/settings/save/'      => self::settingsSave($method),

                $route === '/admin/leads/'              => self::leads(),
                $route === '/admin/leads/export.csv'    => self::leadsCsv(),
                $route === '/admin/subscribers/'        => self::subscribers(),
                $route === '/admin/subscribers/export.csv' => self::subscribersCsv(),

                $route === '/admin/backup/'             => self::backup(),
                $route === '/admin/cache/clear/'        => self::cacheClear($method),
                $route === '/admin/api/score/'          => self::apiScore($method),
                $route === '/admin/api/preview/'        => self::apiPreview($method),

                default => self::notFound(),
            };
        } catch (Throwable $e) {
            error_log('[admin] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            // The message can carry a file path or a fragment of SQL. It goes to
            // the error log, where it is useful; the page says only what to do.
            $detail = ttp_config()['env'] === 'dev'
                ? $e->getMessage()
                : 'Nothing was saved. The details are in the server error log.';
            return self::page('error', ['title' => 'Something went wrong', 'detail' => $detail], 500);
        }
    }

    // -----------------------------------------------------------------------
    // Login
    // -----------------------------------------------------------------------

    private static function loginForm(string $error = '', string $email = ''): Response
    {
        if (Auth::check()) {
            return self::to('/admin/');
        }
        return self::page('login', [
            'title'    => 'Sign in',
            'error'    => $error,
            'email'    => $email,
            'next'     => self::safeNext((string) (self::$get['next'] ?? '')),
            'noAdmins' => Auth::userCount() === 0,
        ], $error === '' ? 200 : 401);
    }

    private static function doLogin(): Response
    {
        // The login form is the one POST without a session yet, so its token is
        // checked here rather than in the shared guard above.
        if (!Csrf::check(Csrf::fromRequest(self::$post, self::$server))) {
            return self::loginForm('That form expired. Try again.');
        }

        $email    = (string) (self::$post['email'] ?? '');
        $password = (string) (self::$post['password'] ?? '');
        $result   = Auth::attempt($email, $password, Auth::clientIp(self::$server));

        if (!$result['ok']) {
            if ($result['error'] === Auth::ERROR_LOCKED) {
                $minutes = max(1, (int) ceil($result['retry_after'] / 60));
                return self::loginForm(
                    'Too many failed attempts. Try again in ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's') . '.',
                    $email
                );
            }
            return self::loginForm('That email and password do not match.', $email);
        }

        Auth::pruneAttempts();
        Flash::ok('Signed in.');
        return self::to(self::safeNext((string) (self::$post['next'] ?? '')) ?: '/admin/');
    }

    private static function doLogout(string $method): Response
    {
        if ($method !== 'POST') {
            return self::to('/admin/');
        }
        Auth::logout();
        Session::destroy();
        return self::to('/admin/login/');
    }

    /** Only ever redirect back into the panel — never to an attacker's URL. */
    private static function safeNext(string $next): string
    {
        $next = trim($next);
        if ($next === '' || !str_starts_with($next, '/admin') || str_starts_with($next, '//')) {
            return '';
        }
        return (string) preg_replace('/[^\x21-\x7E]/', '', $next);
    }

    // -----------------------------------------------------------------------
    // Dashboard
    // -----------------------------------------------------------------------

    private static function dashboard(): Response
    {
        $counts = [];
        foreach (ContentWriter::TYPES as $type) {
            $counts[$type] = [
                'published' => (int) Db::value("SELECT COUNT(*) FROM content_items WHERE type = ? AND status = 'published'", [$type]),
                'draft'     => (int) Db::value("SELECT COUNT(*) FROM content_items WHERE type = ? AND status = 'draft'", [$type]),
                'scheduled' => (int) Db::value("SELECT COUNT(*) FROM content_items WHERE type = ? AND status = 'scheduled'", [$type]),
            ];
        }

        return self::page('dashboard', [
            'title'      => 'Dashboard',
            'counts'     => $counts,
            'recent'     => Db::all('SELECT id, type, slug, title, status, seo_score, updated_at FROM content_items ORDER BY updated_at DESC LIMIT 8'),
            'weakest'    => Db::all("SELECT id, type, slug, title, seo_score FROM content_items WHERE status = 'published' ORDER BY seo_score ASC LIMIT 5"),
            'scheduled'  => Db::all("SELECT id, slug, title, published_at FROM content_items WHERE status = 'scheduled' ORDER BY published_at ASC LIMIT 5"),
            'leadCount'  => (int) Db::value('SELECT COUNT(*) FROM leads'),
            'subCount'   => (int) Db::value('SELECT COUNT(*) FROM subscribers'),
            'mediaCount' => (int) Db::value('SELECT COUNT(*) FROM media'),
            'cacheCount' => count((array) glob(Cache::dir() . '/*.html')),
        ]);
    }

    // -----------------------------------------------------------------------
    // Content
    // -----------------------------------------------------------------------

    private static function contentList(): Response
    {
        $type   = (string) (self::$get['type'] ?? '');
        $status = (string) (self::$get['status'] ?? '');
        $query  = trim((string) (self::$get['q'] ?? ''));

        $where  = ['1 = 1'];
        $params = [];
        if (in_array($type, ContentWriter::TYPES, true)) {
            $where[]  = 'i.type = ?';
            $params[] = $type;
        }
        if (in_array($status, ContentWriter::STATUSES, true)) {
            $where[]  = 'i.status = ?';
            $params[] = $status;
        }
        if ($query !== '') {
            $where[]  = '(i.title LIKE ? OR i.slug LIKE ?)';
            $params[] = '%' . $query . '%';
            $params[] = '%' . $query . '%';
        }

        $items = Db::all(
            'SELECT i.id, i.type, i.slug, i.title, i.status, i.seo_score, i.word_count, i.updated_at,
                    i.published_at, c.name AS category_name
             FROM content_items i LEFT JOIN categories c ON c.id = i.category_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY i.updated_at DESC',
            $params
        );

        return self::page('content/list', [
            'title'  => 'Content',
            'items'  => $items,
            'type'   => $type,
            'status' => $status,
            'q'      => $query,
        ]);
    }

    private static function editor(?int $id): Response
    {
        $item    = null;
        $details = null;
        if ($id !== null && $id > 0) {
            $item = Db::one('SELECT * FROM content_items WHERE id = ?', [$id]);
            if ($item === null) {
                Flash::error('That item no longer exists.');
                return self::to('/admin/content/');
            }
            $details = ContentRepo::details($id);
        }

        $type = $item !== null
            ? (string) $item['type']
            : (in_array((string) (self::$get['type'] ?? ''), ContentWriter::TYPES, true)
                ? (string) self::$get['type']
                : 'post');

        $draft = self::draftFor($item, $details, $type);

        return self::page('content/editor', [
            'title'      => $item === null ? 'New ' . $type : 'Edit ' . $item['title'],
            'item'       => $item,
            'type'       => $type,
            'draft'      => $draft,
            'details'    => $details,
            'categories' => CategoryRepo::all(),
            'tags'       => $item === null ? [] : ContentRepo::tagsFor((int) $item['id']),
            'media'      => self::mediaChoices($draft['cover_media_id'], $draft['og_image_media_id']),
            'cover'      => MediaRepo::find($item === null ? null : self::intOrNull($item['cover_media_id'])),
            'score'      => SeoScore::forItem($draft, $details),
            'errors'     => [],
        ]);
    }

    /** @return array<string,mixed> the shape both the editor form and SeoScore read */
    private static function draftFor(?array $item, ?array $details, string $type): array
    {
        return [
            'id'                 => $item === null ? 0 : (int) $item['id'],
            'type'               => $type,
            'slug'               => (string) ($item['slug'] ?? ''),
            'title'              => (string) ($item['title'] ?? ''),
            'status'             => (string) ($item['status'] ?? 'draft'),
            'published_at'       => (string) ($item['published_at'] ?? ''),
            'excerpt'            => (string) ($item['excerpt'] ?? ''),
            'body_md'            => (string) ($item['body_md'] ?? ''),
            'meta_title'         => (string) ($item['meta_title'] ?? ''),
            'meta_description'   => (string) ($item['meta_description'] ?? ''),
            'focus_keyword'      => (string) ($item['focus_keyword'] ?? ''),
            'canonical_override' => (string) ($item['canonical_override'] ?? ''),
            'noindex'            => (int) ($item['noindex'] ?? 0),
            'category_id'        => self::intOrNull($item['category_id'] ?? null),
            'cover_media_id'     => self::intOrNull($item['cover_media_id'] ?? null),
            'og_image_media_id'  => self::intOrNull($item['og_image_media_id'] ?? null),
            'sort_order'         => (int) ($item['sort_order'] ?? 0),
        ];
    }

    /**
     * The image library for the editor's two <select>s: the most recent uploads,
     * plus whichever rows this item already points at. Without that second part a
     * cover chosen long ago would be missing from the list and saving would
     * silently clear it.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function mediaChoices(?int ...$selected): array
    {
        $rows = Db::all('SELECT * FROM media ORDER BY id DESC LIMIT 200');
        $have = array_map(static fn (array $row): int => (int) $row['id'], $rows);

        foreach ($selected as $id) {
            if ($id === null || in_array($id, $have, true)) {
                continue;
            }
            $row = MediaRepo::find($id);
            if ($row !== null) {
                array_unshift($rows, $row);
                $have[] = $id;
            }
        }
        return $rows;
    }

    private static function contentSave(string $method): Response
    {
        if ($method !== 'POST') {
            return self::to('/admin/content/');
        }
        $id     = self::intParam('id', self::$post);
        $result = ContentWriter::save(self::$post, $id);

        if (!$result['ok']) {
            $type    = (string) (self::$post['type'] ?? 'post');
            $item    = $id !== null && $id > 0 ? Db::one('SELECT * FROM content_items WHERE id = ?', [$id]) : null;
            $details = $id !== null && $id > 0 ? ContentRepo::details($id) : null;
            $draft   = array_merge(self::draftFor($item, $details, $type), self::$post);

            Flash::error('Nothing was saved — see the highlighted fields.');
            return self::page('content/editor', [
                'title'      => $item === null ? 'New ' . $type : 'Edit ' . (string) $item['title'],
                'item'       => $item,
                'type'       => $type,
                'draft'      => $draft,
                'details'    => $details,
                'categories' => CategoryRepo::all(),
                'tags'       => $id === null ? [] : ContentRepo::tagsFor($id),
                'media'      => Db::all('SELECT * FROM media ORDER BY id DESC LIMIT 60'),
                'cover'      => MediaRepo::find(self::intOrNull(self::$post['cover_media_id'] ?? null)),
                'score'      => SeoScore::forItem($draft, $details),
                'errors'     => $result['errors'],
            ], 422);
        }

        foreach ($result['notices'] as $notice) {
            Flash::ok($notice);
        }
        $item = $result['item'];
        Flash::ok(
            'Saved “' . (string) ($item['title'] ?? '') . '”'
            . ((string) ($item['status'] ?? '') === 'published' ? ' and it is live.' : ' as a ' . (string) ($item['status'] ?? '') . '.')
        );
        return self::to('/admin/content/edit/?id=' . $result['id']);
    }

    private static function contentStatus(string $method): Response
    {
        if ($method !== 'POST') {
            return self::to('/admin/content/');
        }
        $id     = (int) self::intParam('id', self::$post);
        $status = (string) (self::$post['status'] ?? '');
        if (ContentWriter::setStatus($id, $status)) {
            Flash::ok($status === 'published' ? 'Published.' : 'Moved back to ' . $status . '.');
        } else {
            Flash::error('That status change was not possible.');
        }
        return self::to(self::backTo('/admin/content/'));
    }

    private static function contentDelete(string $method): Response
    {
        if ($method !== 'POST') {
            return self::to('/admin/content/');
        }
        $id = (int) self::intParam('id', self::$post);
        if (ContentWriter::delete($id)) {
            Flash::ok('Deleted. Its old address now redirects instead of 404ing.');
        } else {
            Flash::error('That item no longer exists.');
        }
        return self::to('/admin/content/');
    }

    /** Renders a draft through the real public template, behind the login. */
    private static function preview(): Response
    {
        $id   = (int) self::intParam('id');
        $item = ContentRepo::findById($id);
        if ($item === null) {
            return self::notFound();
        }
        // Never let a preview be indexed, whatever the item says.
        $item['noindex'] = 1;

        $response = Router::renderItem($item);
        $response->cacheable = false;
        $response->headers['Cache-Control'] = 'no-store, private';
        $response->headers['X-Robots-Tag']  = 'noindex, nofollow';
        return $response;
    }

    // -----------------------------------------------------------------------
    // Media
    // -----------------------------------------------------------------------

    private static function mediaList(): Response
    {
        return self::page('media/list', [
            'title' => 'Media',
            'media' => Db::all('SELECT * FROM media ORDER BY id DESC'),
            'usage' => self::mediaUsage(),
            'limit' => Uploader::MAX_BYTES,
            'back'  => self::safeNext((string) (self::$get['back'] ?? '')),
        ]);
    }

    /** @return array<int,int> media id => how many items use it */
    private static function mediaUsage(): array
    {
        $usage = [];
        foreach (Db::all(
            'SELECT cover_media_id AS id, COUNT(*) AS n FROM content_items WHERE cover_media_id IS NOT NULL GROUP BY cover_media_id
             UNION ALL
             SELECT og_image_media_id AS id, COUNT(*) AS n FROM content_items WHERE og_image_media_id IS NOT NULL GROUP BY og_image_media_id'
        ) as $row) {
            $id = (int) $row['id'];
            $usage[$id] = ($usage[$id] ?? 0) + (int) $row['n'];
        }
        return $usage;
    }

    private static function mediaUpload(string $method): Response
    {
        if ($method !== 'POST') {
            return self::to('/admin/media/');
        }
        $file = self::$files['file'] ?? null;
        $back = self::backTo('/admin/media/');

        if (!is_array($file)) {
            Flash::error('Choose a file first.');
            return self::to($back);
        }
        $problem = Uploader::uploadErrorMessage((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE));
        if ($problem !== null) {
            Flash::error($problem);
            return self::to($back);
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        // In a real request the file must be one PHP itself received.
        if (PHP_SAPI !== 'cli' && !is_uploaded_file($tmp)) {
            Flash::error('That upload could not be verified. Try again.');
            return self::to($back);
        }

        $alt = trim((string) (self::$post['alt'] ?? ''));
        if ($alt === '') {
            Flash::error('Describe the image first — alt text is required, and it is what a screen reader reads out.');
            return self::to($back);
        }

        try {
            $media = Uploader::store($tmp, (string) ($file['name'] ?? 'image'), $alt);
        } catch (RuntimeException $e) {
            Flash::error($e->getMessage());
            return self::to($back);
        }

        Flash::ok('Uploaded ' . (string) $media['filename'] . ' in ' . count(Uploader::sizes($media)) . ' sizes.');
        return self::to($back . (str_contains($back, '?') ? '&' : '?') . 'uploaded=' . (int) $media['id']);
    }

    private static function mediaAlt(string $method): Response
    {
        if ($method !== 'POST') {
            return self::to('/admin/media/');
        }
        $id  = (int) self::intParam('id', self::$post);
        $alt = trim((string) (self::$post['alt'] ?? ''));
        if ($alt === '') {
            Flash::error('Alt text cannot be empty.');
        } else {
            Db::run('UPDATE media SET alt = ? WHERE id = ?', [$alt, $id]);
            Cache::flush();
            Flash::ok('Alt text updated.');
        }
        return self::to('/admin/media/');
    }

    private static function mediaDelete(string $method): Response
    {
        if ($method !== 'POST') {
            return self::to('/admin/media/');
        }
        $media = MediaRepo::find((int) self::intParam('id', self::$post));
        if ($media === null) {
            Flash::error('That image is already gone.');
            return self::to('/admin/media/');
        }
        $removed = Uploader::delete($media);
        Cache::flush();
        Flash::ok('Deleted ' . (string) $media['filename'] . ' (' . $removed . ' files).');
        return self::to('/admin/media/');
    }

    // -----------------------------------------------------------------------
    // Categories and tags
    // -----------------------------------------------------------------------

    private static function categoryList(): Response
    {
        return self::page('taxonomy/categories', [
            'title'      => 'Categories',
            'categories' => Db::all(
                "SELECT c.*, (SELECT COUNT(*) FROM content_items i WHERE i.category_id = c.id AND i.type = 'post') AS post_count
                 FROM categories c ORDER BY c.sort_order, c.name"
            ),
            'edit'       => Db::one('SELECT * FROM categories WHERE id = ?', [(int) self::intParam('edit')]),
        ]);
    }

    private static function categorySave(string $method): Response
    {
        if ($method !== 'POST') {
            return self::to('/admin/categories/');
        }
        $id   = (int) self::intParam('id', self::$post);
        $name = trim((string) (self::$post['name'] ?? ''));
        $slug = Str::slug((string) (self::$post['slug'] ?? '')) ?: Str::slug($name);

        if ($name === '' || $slug === '') {
            Flash::error('A category needs a name.');
            return self::to('/admin/categories/');
        }
        $clash = Db::one('SELECT id FROM categories WHERE slug = ? AND id <> ?', [$slug, $id]);
        if ($clash !== null) {
            Flash::error('Another category already uses /category/' . $slug . '/.');
            return self::to('/admin/categories/');
        }

        $fields = [
            'slug'             => $slug,
            'name'             => $name,
            'description'      => trim((string) (self::$post['description'] ?? '')),
            'meta_title'       => trim((string) (self::$post['meta_title'] ?? '')),
            'meta_description' => trim((string) (self::$post['meta_description'] ?? '')),
            'sort_order'       => (int) (self::$post['sort_order'] ?? 0),
        ];

        if ($id > 0) {
            $existing = Db::one('SELECT * FROM categories WHERE id = ?', [$id]);
            if ($existing === null) {
                Flash::error('That category no longer exists.');
                return self::to('/admin/categories/');
            }
            $set = implode(', ', array_map(static fn (string $c): string => $c . ' = ?', array_keys($fields)));
            Db::run("UPDATE categories SET {$set} WHERE id = ?", array_merge(array_values($fields), [$id]));
            if ((string) $existing['slug'] !== $slug) {
                RedirectRepo::upsert('/category/' . $existing['slug'] . '/', '/category/' . $slug . '/', 301, 'slug-change');
                Flash::ok('The old /category/' . $existing['slug'] . '/ address now redirects.');
            }
        } else {
            $columns = implode(', ', array_keys($fields));
            $holders = implode(', ', array_fill(0, count($fields), '?'));
            Db::run("INSERT INTO categories ({$columns}) VALUES ({$holders})", array_values($fields));
        }

        Cache::flush();
        Flash::ok('Category saved.');
        return self::to('/admin/categories/');
    }

    private static function categoryDelete(string $method): Response
    {
        if ($method !== 'POST') {
            return self::to('/admin/categories/');
        }
        $id  = (int) self::intParam('id', self::$post);
        $row = Db::one('SELECT * FROM categories WHERE id = ?', [$id]);
        if ($row === null) {
            Flash::error('That category is already gone.');
            return self::to('/admin/categories/');
        }
        $used = (int) Db::value('SELECT COUNT(*) FROM content_items WHERE category_id = ?', [$id]);
        // The posts survive; the archive URL must not simply 404.
        Db::run('UPDATE content_items SET category_id = NULL WHERE category_id = ?', [$id]);
        Db::run('DELETE FROM categories WHERE id = ?', [$id]);
        RedirectRepo::upsert('/category/' . $row['slug'] . '/', Router::BLOG_PATH, 301, 'slug-change');
        Cache::flush();
        Flash::ok('Category deleted; ' . $used . ' post(s) kept and the archive now redirects to the blog.');
        return self::to('/admin/categories/');
    }

    private static function tagList(): Response
    {
        return self::page('taxonomy/tags', [
            'title' => 'Tags',
            'tags'  => Db::all(
                'SELECT t.*, (SELECT COUNT(*) FROM item_tags it WHERE it.tag_id = t.id) AS use_count
                 FROM tags t ORDER BY t.name'
            ),
        ]);
    }

    private static function tagSave(string $method): Response
    {
        if ($method !== 'POST') {
            return self::to('/admin/tags/');
        }
        $id   = (int) self::intParam('id', self::$post);
        $name = trim((string) (self::$post['name'] ?? ''));
        $slug = Str::slug($name);
        if ($id <= 0 || $name === '' || $slug === '') {
            Flash::error('A tag needs a name.');
            return self::to('/admin/tags/');
        }
        if (Db::one('SELECT id FROM tags WHERE slug = ? AND id <> ?', [$slug, $id]) !== null) {
            Flash::error('Another tag already uses that name.');
            return self::to('/admin/tags/');
        }
        Db::run('UPDATE tags SET name = ?, slug = ? WHERE id = ?', [$name, $slug, $id]);
        Flash::ok('Tag renamed.');
        return self::to('/admin/tags/');
    }

    private static function tagDelete(string $method): Response
    {
        if ($method !== 'POST') {
            return self::to('/admin/tags/');
        }
        Db::run('DELETE FROM tags WHERE id = ?', [(int) self::intParam('id', self::$post)]);
        Flash::ok('Tag deleted.');
        return self::to('/admin/tags/');
    }

    // -----------------------------------------------------------------------
    // Redirects
    // -----------------------------------------------------------------------

    private static function redirectList(): Response
    {
        $query  = trim((string) (self::$get['q'] ?? ''));
        $params = [];
        $where  = '1 = 1';
        if ($query !== '') {
            $where    = '(from_path LIKE ? OR to_path LIKE ?)';
            $params[] = '%' . $query . '%';
            $params[] = '%' . $query . '%';
        }
        return self::page('redirects', [
            'title'     => 'Redirects',
            'redirects' => Db::all(
                "SELECT * FROM redirects WHERE {$where} ORDER BY hits DESC, from_path ASC LIMIT 500",
                $params
            ),
            'total'     => (int) Db::value('SELECT COUNT(*) FROM redirects'),
            'q'         => $query,
        ]);
    }

    private static function redirectSave(string $method): Response
    {
        if ($method !== 'POST') {
            return self::to('/admin/redirects/');
        }
        $from   = self::normalisePath((string) (self::$post['from_path'] ?? ''));
        $status = (int) (self::$post['status'] ?? 301);
        $to     = $status === 410 ? '' : self::normalisePath((string) (self::$post['to_path'] ?? ''));

        if ($from === '' || $from === '/') {
            Flash::error('The "from" address must be a path such as /old-page/.');
            return self::to('/admin/redirects/');
        }
        if (!in_array($status, [301, 410], true)) {
            $status = 301;
        }
        if ($status === 301 && $to === '') {
            Flash::error('A 301 needs somewhere to point.');
            return self::to('/admin/redirects/');
        }
        if ($from === $to) {
            Flash::error('That redirect points at itself.');
            return self::to('/admin/redirects/');
        }
        if (Db::value('SELECT 1 FROM content_items WHERE slug = ?', [trim($from, '/')]) !== null) {
            Flash::error('A page already lives at ' . $from . '. A redirect there would hide it.');
            return self::to('/admin/redirects/');
        }

        RedirectRepo::upsert($from, $to, $status, 'manual');
        Cache::forgetPaths([$from, $to]);
        Flash::ok($status === 410 ? $from . ' now returns 410 Gone.' : $from . ' now redirects to ' . $to . '.');
        return self::to('/admin/redirects/');
    }

    private static function redirectDelete(string $method): Response
    {
        if ($method !== 'POST') {
            return self::to('/admin/redirects/');
        }
        $from = (string) (self::$post['from_path'] ?? '');
        $row  = RedirectRepo::find($from);
        if ($row === null) {
            Flash::error('That redirect is already gone.');
            return self::to('/admin/redirects/');
        }
        Db::run('DELETE FROM redirects WHERE from_path = ?', [$from]);
        Cache::forgetPaths([$from]);
        Flash::ok('Removed the redirect from ' . $from . '. Check docs/url-map.csv if it came from the old site.');
        return self::to('/admin/redirects/');
    }

    private static function normalisePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        // Accept a pasted absolute URL and keep only its path.
        if (preg_match('~^https?://~i', $path) === 1) {
            $path = (string) (parse_url($path, PHP_URL_PATH) ?? '/');
        }
        // A backslash is not a path separator here, but some browsers normalise
        // `/\evil.com` into a protocol-relative URL — so a redirect target can
        // never contain one.
        $path = str_replace('\\', '/', $path);
        $path = '/' . ltrim($path, '/');
        $path = (string) preg_replace('#/{2,}#', '/', $path);
        return Router::canonicalPath($path);
    }

    // -----------------------------------------------------------------------
    // Settings, leads, subscribers, tools
    // -----------------------------------------------------------------------

    private static function settings(): Response
    {
        return self::page('settings', [
            'title'    => 'Settings',
            'settings' => SettingsRepo::all(),
            'config'   => ttp_config(),
        ]);
    }

    private static function settingsSave(string $method): Response
    {
        if ($method !== 'POST') {
            return self::to('/admin/settings/');
        }
        $allowed = ['site_name', 'tagline', 'address', 'phone', 'email', 'whatsapp', 'ga4_id', 'social_json'];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, self::$post)) {
                continue;
            }
            $value = trim((string) self::$post[$key]);
            if ($key === 'whatsapp') {
                $value = (string) preg_replace('/\D+/', '', $value);
            }
            if ($key === 'social_json' && $value !== '' && json_decode($value, true) === null) {
                Flash::error('Social links must be valid JSON — nothing else was saved.');
                return self::to('/admin/settings/');
            }
            SettingsRepo::set($key, $value);
        }
        Cache::flush();
        Flash::ok('Settings saved and the page cache cleared.');
        return self::to('/admin/settings/');
    }

    private static function leads(): Response
    {
        return self::page('leads', [
            'title' => 'Leads',
            'leads' => Db::all('SELECT * FROM leads ORDER BY created_at DESC LIMIT 500'),
            'total' => (int) Db::value('SELECT COUNT(*) FROM leads'),
        ]);
    }

    private static function leadsCsv(): Response
    {
        return self::csv('leads', ['id', 'created_at', 'name', 'email', 'phone', 'message', 'page_path', 'forwarded'],
            Db::all('SELECT * FROM leads ORDER BY created_at DESC'));
    }

    private static function subscribers(): Response
    {
        return self::page('subscribers', [
            'title'       => 'Subscribers',
            'subscribers' => Db::all('SELECT * FROM subscribers ORDER BY created_at DESC LIMIT 500'),
            'total'       => (int) Db::value('SELECT COUNT(*) FROM subscribers'),
        ]);
    }

    private static function subscribersCsv(): Response
    {
        return self::csv('subscribers', ['id', 'created_at', 'email', 'source'],
            Db::all('SELECT * FROM subscribers ORDER BY created_at DESC'));
    }

    /** @param array<int,string> $columns @param array<int,array<string,mixed>> $rows */
    private static function csv(string $name, array $columns, array $rows): Response
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return self::deny(500, 'Could not build the export.');
        }
        fputcsv($handle, $columns);
        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $column) {
                $line[] = self::csvSafe((string) ($row[$column] ?? ''));
            }
            fputcsv($handle, $line);
        }
        rewind($handle);
        $body = (string) stream_get_contents($handle);
        fclose($handle);

        return new Response(200, $body, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $name . '-' . gmdate('Y-m-d') . '.csv"',
            'Cache-Control'       => 'no-store, private',
        ], false);
    }

    /**
     * Leads and subscribers are written by strangers on the public site. A value
     * beginning with =, +, - or @ is a formula to Excel, Numbers and Sheets, so
     * it is prefixed with an apostrophe: the cell still reads the same, but it is
     * text rather than something the spreadsheet runs when Anton opens the export.
     */
    public static function csvSafe(string $value): string
    {
        if ($value === '') {
            return $value;
        }
        return str_contains("=+-@\t\r", $value[0]) ? "'" . $value : $value;
    }

    private static function backup(): Response
    {
        try {
            [$filename, $zip] = Backup::build();
        } catch (RuntimeException $e) {
            Flash::error($e->getMessage());
            return self::to('/admin/settings/');
        }

        return new Response(200, $zip, [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => (string) strlen($zip),
            'Cache-Control'       => 'no-store, private',
        ], false);
    }

    private static function cacheClear(string $method): Response
    {
        if ($method !== 'POST') {
            return self::to('/admin/');
        }
        Flash::ok('Cleared ' . Cache::flush() . ' cached page(s).');
        return self::to(self::backTo('/admin/'));
    }

    // -----------------------------------------------------------------------
    // JSON used by the editor
    // -----------------------------------------------------------------------

    private static function apiScore(string $method): Response
    {
        if ($method !== 'POST') {
            return self::json(['error' => 'POST only'], 405);
        }
        $details = in_array((string) (self::$post['type'] ?? ''), ['tour', 'service'], true)
            ? [
                'hook_md'         => (string) (self::$post['hook'] ?? ''),
                'solution_md'     => (string) (self::$post['solution'] ?? ''),
                'closing_md'      => (string) (self::$post['closing'] ?? ''),
                'itinerary_label' => (string) (self::$post['itinerary_label'] ?? ''),
                'itinerary'       => (array) (self::$post['itinerary'] ?? []),
                'why'             => (array) (self::$post['why'] ?? []),
                'practical'       => (array) (self::$post['practical'] ?? []),
                'faq'             => (array) (self::$post['faq'] ?? []),
            ]
            : null;

        return self::json(SeoScore::forItem(self::$post, $details)->toArray());
    }

    private static function apiPreview(string $method): Response
    {
        if ($method !== 'POST') {
            return self::json(['error' => 'POST only'], 405);
        }
        // Markdown is rendered in safe mode (src/Markdown.php), so the preview
        // shows exactly what the public page would, escaping included.
        return self::json(['html' => Markdown::toHtml((string) (self::$post['body_md'] ?? ''))]);
    }

    // -----------------------------------------------------------------------
    // Plumbing
    // -----------------------------------------------------------------------

    /** @param array<string,mixed> $vars */
    private static function page(string $template, array $vars, int $status = 200): Response
    {
        $response = Response::html(AdminView::render($template, $vars), $status);
        return self::seal($response);
    }

    private static function json(array $data, int $status = 200): Response
    {
        $response = new Response(
            $status,
            (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ['Content-Type' => 'application/json; charset=UTF-8'],
            false
        );
        return self::seal($response);
    }

    private static function to(string $path): Response
    {
        // 303: the browser must follow a POST with a GET.
        return self::seal(new Response(303, '', ['Location' => $path], false));
    }

    private static function deny(int $status, string $message): Response
    {
        return self::page('error', ['title' => $status === 403 ? 'Refused' : 'Sign in again', 'detail' => $message], $status);
    }

    private static function notFound(): Response
    {
        return self::page('error', ['title' => 'No such page', 'detail' => 'That admin address does not exist.'], 404);
    }

    /** Nothing under /admin is cached, indexed or framed. */
    private static function seal(Response $response): Response
    {
        $response->cacheable = false;
        $response->headers['Cache-Control'] = 'no-store, private';
        $response->headers['X-Robots-Tag']  = 'noindex, nofollow';
        $response->headers['Referrer-Policy'] = 'same-origin';
        $response->headers['X-Frame-Options'] = 'DENY';
        $response->headers['X-Content-Type-Options'] = 'nosniff';
        return $response;
    }

    /** A `back` field, accepted only when it stays inside the panel. */
    private static function backTo(string $fallback): string
    {
        $back = self::safeNext((string) (self::$post['back'] ?? self::$get['back'] ?? ''));
        return $back !== '' ? $back : $fallback;
    }

    private static function intParam(string $name, ?array $source = null): ?int
    {
        $source ??= self::$get;
        $value = $source[$name] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        return (int) $value;
    }

    private static function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }
}
