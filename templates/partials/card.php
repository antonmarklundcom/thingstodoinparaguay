<?php
/**
 * One content item in a list. Headings start at H3 so the page keeps a single
 * H1 and a sane outline whichever list the card appears in.
 *
 * @var array<string,mixed> $item
 * @var bool                $meta  Show the published date and reading time.
 */

use Ttp\Markdown;
use Ttp\View;

$meta ??= false;
$summary = trim((string) $item['excerpt']);
if ($summary === '') {
    $summary = Markdown::truncate(Markdown::toText((string) $item['body_md']), 160);
}
?>
<article class="card card--<?= View::e((string) $item['type']) ?>">
    <h3><a href="<?= View::e((string) $item['path']) ?>"><?= View::e((string) $item['title']) ?></a></h3>
    <?php if ($summary !== ''): ?>
        <p><?= View::e($summary) ?></p>
    <?php endif; ?>
    <?php if ($meta): ?>
        <p class="card-meta">
            <?php if (!empty($item['published_at'])): ?>
                <time datetime="<?= View::e(View::isoDate((string) $item['published_at'])) ?>"><?= View::e(View::date((string) $item['published_at'])) ?></time>
            <?php endif; ?>
            <?php if (!empty($item['category_name'])): ?>
                &middot; <a href="/category/<?= View::e((string) $item['category_slug']) ?>/"><?= View::e((string) $item['category_name']) ?></a>
            <?php endif; ?>
            <?php if ((int) $item['word_count'] > 0): ?>
                &middot; <?= View::readingTime((int) $item['word_count']) ?> min read
            <?php endif; ?>
        </p>
    <?php endif; ?>
</article>
