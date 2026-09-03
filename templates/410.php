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
<h1>This page has been removed</h1>

<p>
    <code><?= View::e($path) ?></code> was part of the old WordPress site and no longer exists.
    It will not come back.
</p>

<ul>
    <li><a href="/">Start page</a></li>
    <li><a href="<?= View::e(Router::TOURS_PATH) ?>">Tours and day trips</a></li>
    <li><a href="<?= View::e(Router::SERVICES_PATH) ?>">Services</a></li>
    <li><a href="<?= View::e(Router::BLOG_PATH) ?>">Blog</a></li>
    <li><a href="/contact/">Contact us</a></li>
</ul>
