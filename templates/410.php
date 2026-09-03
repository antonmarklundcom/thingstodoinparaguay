<?php
/**
 * 410 Gone — the junk URLs in docs/url-map.csv (old test pages, WordPress
 * endpoints). Deliberately not a 404: these are never coming back and we want
 * search engines to drop them quickly.
 *
 * @var string $path
 */

use Ttp\Router;
use Ttp\View;
?>
<div class="container">
    <h1>This page has been removed</h1>

    <p>
        <code><?= View::e($path) ?></code> was part of the old WordPress site and no longer exists.
        It will not come back.
    </p>

    <p class="hero__actions">
        <a class="button button--primary" href="/">Start page</a>
        <a class="button button--ghost" href="<?= View::e(Router::TOURS_PATH) ?>">Tours and day trips</a>
        <a class="button button--ghost" href="<?= View::e(Router::BLOG_PATH) ?>">Blog</a>
        <a class="button button--ghost" href="/contact/">Contact us</a>
    </p>
</div>
