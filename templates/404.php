<?php
/**
 * @var string $path
 * @var array<int,array<string,mixed>> $tours
 * @var array<int,array<string,mixed>> $posts
 */

use Ttp\Router;
use Ttp\View;
?>
<div class="container container--wide">
    <h1>That page does not exist</h1>

    <p>
        Nothing lives at <code><?= View::e($path) ?></code>. It may have moved when we rebuilt the site,
        or the link may be wrong. Here is where to go instead.
    </p>

    <p class="hero__actions">
        <a class="button button--primary" href="/">Start page</a>
        <a class="button button--ghost" href="<?= View::e(Router::TOURS_PATH) ?>">Tours and day trips</a>
        <a class="button button--ghost" href="<?= View::e(Router::BLOG_PATH) ?>">Blog</a>
        <a class="button button--ghost" href="/contact/">Contact us</a>
    </p>

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
</div>
