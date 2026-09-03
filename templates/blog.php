<?php
/**
 * @var array<int,array<string,mixed>> $posts
 * @var int    $page
 * @var int    $pages
 * @var int    $total
 * @var string $basePath
 * @var array<int,array<string,mixed>> $categories
 */

use Ttp\View;
?>
<div class="container container--wide">
    <h1>Paraguay travel blog<?= $page > 1 ? ' — page ' . (int) $page : '' ?></h1>

    <p class="archive-intro">
        <?= (int) $total ?> article<?= $total === 1 ? '' : 's' ?> about travelling, working and living in
        Paraguay — written by people who live here.
    </p>

    <?php if ($categories !== []): ?>
        <nav class="category-nav" aria-label="Categories">
            <ul>
                <?php foreach ($categories as $category): ?>
                    <li><a href="/category/<?= View::e((string) $category['slug']) ?>/"><?= View::e((string) $category['name']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
    <?php endif; ?>

    <?php if ($posts === []): ?>
        <p>No articles have been published yet.</p>
    <?php else: ?>
        <h2 class="visually-hidden">Articles</h2>
        <div class="grid post-list">
            <?php foreach ($posts as $item): ?>
                <?= View::partial('card', ['item' => $item, 'meta' => true]) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?= View::partial('pagination', ['page' => $page, 'pages' => $pages, 'basePath' => $basePath]) ?>
</div>
