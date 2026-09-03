<?php
/**
 * @var string $path
 * @var array<int,array<string,mixed>> $tours
 * @var array<int,array<string,mixed>> $posts
 */

use Ttp\Router;
use Ttp\View;
?>
<h1>That page does not exist</h1>

<p>
    Nothing lives at <code><?= View::e($path) ?></code>. It may have moved when we rebuilt the site,
    or the link may be wrong. Here is where to go instead.
</p>

<ul>
    <li><a href="/">Start page</a></li>
    <li><a href="<?= View::e(Router::TOURS_PATH) ?>">Tours and day trips</a></li>
    <li><a href="<?= View::e(Router::SERVICES_PATH) ?>">Services</a></li>
    <li><a href="<?= View::e(Router::BLOG_PATH) ?>">Blog</a></li>
    <li><a href="/contact/">Contact us</a></li>
</ul>

<?php if ($tours !== []): ?>
    <section aria-labelledby="nf-tours">
        <h2 id="nf-tours">Popular tours</h2>
        <div class="grid">
            <?php foreach ($tours as $item): ?>
                <?= View::partial('card', ['item' => $item]) ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($posts !== []): ?>
    <section aria-labelledby="nf-posts">
        <h2 id="nf-posts">Recent articles</h2>
        <div class="grid">
            <?php foreach ($posts as $item): ?>
                <?= View::partial('card', ['item' => $item, 'meta' => true]) ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
