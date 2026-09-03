<?php
/**
 * @var array<string,mixed>            $item
 * @var string                         $bodyHtml
 * @var array<int,array{id:string,text:string}> $toc
 * @var array<string,mixed>|null       $category
 * @var array<int,array{slug:string,name:string}> $tags
 * @var array<int,array<string,mixed>> $related
 * @var array{prev:?array<string,mixed>,next:?array<string,mixed>} $neighbours
 * @var array<string,mixed>|null       $cover
 */

use Ttp\View;
?>
<article class="post">
    <header class="post-header">
        <h1><?= View::e((string) $item['title']) ?></h1>
        <p class="post-meta">
            <?php if (!empty($item['published_at'])): ?>
                Published <time datetime="<?= View::e(View::isoDate((string) $item['published_at'])) ?>"><?= View::e(View::date((string) $item['published_at'])) ?></time>
            <?php endif; ?>
            <?php if ($category !== null): ?>
                in <a href="/category/<?= View::e((string) $category['slug']) ?>/"><?= View::e((string) $category['name']) ?></a>
            <?php endif; ?>
            <?php if ((int) $item['word_count'] > 0): ?>
                &middot; <?= View::readingTime((int) $item['word_count']) ?> min read
            <?php endif; ?>
        </p>
        <?= View::image($cover, 'post-cover', true) ?>
    </header>

    <?php if ($toc !== []): ?>
        <nav class="toc" aria-labelledby="toc-heading">
            <h2 id="toc-heading">On this page</h2>
            <ol>
                <?php foreach ($toc as $entry): ?>
                    <li><a href="#<?= View::e($entry['id']) ?>"><?= View::e($entry['text']) ?></a></li>
                <?php endforeach; ?>
            </ol>
        </nav>
    <?php endif; ?>

    <div class="prose">
        <?= $bodyHtml ?>
    </div>

    <?php if ($tags !== []): ?>
        <p class="tags">Tagged:
            <?php foreach ($tags as $i => $tag): ?><?= $i > 0 ? ', ' : '' ?><?= View::e($tag['name']) ?><?php endforeach; ?>
        </p>
    <?php endif; ?>

    <footer class="post-footer">
        <h2>About the authors</h2>
        <p>
            Written by the team behind Things to do in Paraguay — Anton Marklund (Marketing Director)
            and Yanina Alvarez (Photographer), based in Asunción.
            <a href="/about/">More about us</a>.
        </p>
        <p><a href="/contact/">Ask us a question about this trip</a>.</p>
    </footer>
</article>

<?php if ($neighbours['prev'] !== null || $neighbours['next'] !== null): ?>
    <nav class="post-nav" aria-label="More articles">
        <ul>
            <?php if ($neighbours['prev'] !== null): ?>
                <li>Previous: <a rel="prev" href="<?= View::e((string) $neighbours['prev']['path']) ?>"><?= View::e((string) $neighbours['prev']['title']) ?></a></li>
            <?php endif; ?>
            <?php if ($neighbours['next'] !== null): ?>
                <li>Next: <a rel="next" href="<?= View::e((string) $neighbours['next']['path']) ?>"><?= View::e((string) $neighbours['next']['title']) ?></a></li>
            <?php endif; ?>
        </ul>
    </nav>
<?php endif; ?>

<?php if ($related !== []): ?>
    <section class="related" aria-labelledby="related-heading">
        <h2 id="related-heading">Related reading</h2>
        <div class="grid">
            <?php foreach ($related as $other): ?>
                <?= View::partial('card', ['item' => $other, 'meta' => true]) ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
