<?php
/**
 * @var array<string,mixed>            $category
 * @var array<int,array<string,mixed>> $posts
 * @var int    $page
 * @var int    $pages
 * @var int    $total
 * @var string $basePath
 * @var array<int,array<string,mixed>> $categories
 */

use Ttp\Markdown;
use Ttp\Router;
use Ttp\View;
?>
<div class="container container--wide">
    <h1><?= View::e((string) $category['name']) ?><?= $page > 1 ? ' — page ' . (int) $page : '' ?></h1>

    <?php if (trim((string) $category['description']) !== ''): ?>
        <div class="archive-intro"><?= Markdown::toHtml((string) $category['description']) ?></div>
    <?php endif; ?>

    <?php if ($posts === []): ?>
        <p>
            Nothing has been published in this category yet.
            <a href="<?= View::e(Router::BLOG_PATH) ?>">Read the rest of the blog</a> instead.
        </p>
    <?php else: ?>
        <h2 class="visually-hidden">Articles in <?= View::e((string) $category['name']) ?></h2>
        <div class="grid post-list">
            <?php foreach ($posts as $item): ?>
                <?= View::partial('card', ['item' => $item, 'meta' => true]) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?= View::partial('pagination', ['page' => $page, 'pages' => $pages, 'basePath' => $basePath]) ?>

    <?php if ($categories !== []): ?>
        <section aria-labelledby="other-categories">
            <h2 id="other-categories">Other topics</h2>
            <ul class="link-list">
                <?php foreach ($categories as $other): ?>
                    <?php if ((string) $other['slug'] === (string) $category['slug']) { continue; } ?>
                    <li><a href="/category/<?= View::e((string) $other['slug']) ?>/"><?= View::e((string) $other['name']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</div>
