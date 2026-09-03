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

use Ttp\Seo;
use Ttp\View;

$shareUrl   = Seo::url((string) $item['path']);
$shareTitle = (string) $item['title'];
?>
<article class="post container">
    <header class="post-header">
        <h1><?= View::e($shareTitle) ?></h1>
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
        <?php if ($cover !== null): ?>
            <?= View::image($cover, 'post-cover', true) ?>
        <?php endif; ?>
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

    <nav class="share-links" aria-label="Share this article">
        <a href="https://wa.me/?text=<?= rawurlencode($shareTitle . ' — ' . $shareUrl) ?>" rel="noopener" target="_blank">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Z"/></svg> WhatsApp
        </a>
        <a href="https://twitter.com/intent/tweet?text=<?= rawurlencode($shareTitle) ?>&url=<?= rawurlencode($shareUrl) ?>" rel="noopener" target="_blank">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.9 6.9c-.62.28-1.28.46-1.97.55.71-.42 1.25-1.1 1.5-1.9-.66.4-1.4.68-2.18.84A3.4 3.4 0 0 0 12.4 9.4c0 .27.03.53.09.78-2.83-.14-5.34-1.5-7.02-3.56a3.4 3.4 0 0 0 1.05 4.55c-.55-.02-1.07-.17-1.53-.42v.04a3.4 3.4 0 0 0 2.73 3.34 3.4 3.4 0 0 1-1.55.06 3.41 3.41 0 0 0 3.18 2.37A6.83 6.83 0 0 1 3 18.13a9.63 9.63 0 0 0 5.22 1.53c6.27 0 9.7-5.2 9.7-9.7l-.01-.44c.67-.48 1.24-1.08 1.7-1.76-.6.27-1.25.46-1.94.55Z"/></svg> Tweet
        </a>
        <a href="mailto:?subject=<?= rawurlencode($shareTitle) ?>&body=<?= rawurlencode($shareUrl) ?>">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm0 2v.01L12 12l8-5.99V6H4Zm16 2.24-7.4 5.55a1 1 0 0 1-1.2 0L4 8.24V18h16V8.24Z"/></svg> Email
        </a>
    </nav>

    <footer class="post-footer">
        <h2>About the authors</h2>
        <p>
            Written by the team behind Things to do in Paraguay — Anton Marklund (Marketing Director)
            and Yanina Alvarez (Photographer), based in Asunción.
            <a href="/about/">More about us</a>.
        </p>
        <p><a class="button button--primary button--sm" href="/contact/">Ask us a question about this trip</a></p>
    </footer>
</article>

<div class="container">
<?php if ($neighbours['prev'] !== null || $neighbours['next'] !== null): ?>
    <nav class="post-nav" aria-label="More articles">
        <ul>
            <?php if ($neighbours['prev'] !== null): ?>
                <li>Previous<br><a rel="prev" href="<?= View::e((string) $neighbours['prev']['path']) ?>"><?= View::e((string) $neighbours['prev']['title']) ?></a></li>
            <?php endif; ?>
            <?php if ($neighbours['next'] !== null): ?>
                <li>Next<br><a rel="next" href="<?= View::e((string) $neighbours['next']['path']) ?>"><?= View::e((string) $neighbours['next']['title']) ?></a></li>
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
</div>
