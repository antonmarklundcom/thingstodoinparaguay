<?php
/**
 * One content item in a list. Headings start at H3 so the page keeps a single
 * H1 and a sane outline whichever list the card appears in. A cover image is
 * shown when the item has one; otherwise a type-tinted placeholder panel
 * keeps every card the same shape (plan §6.1's "consistent placeholder
 * system" — no media exists yet, S4 fills real covers in via the admin).
 *
 * @var array<string,mixed> $item
 * @var bool                $meta  Show the published date and reading time.
 */

use Ttp\Markdown;
use Ttp\Repo\MediaRepo;
use Ttp\View;

$meta ??= false;
$summary = trim((string) $item['excerpt']);
if ($summary === '') {
    $summary = Markdown::truncate(Markdown::toText((string) $item['body_md']), 160);
}
$cover = MediaRepo::find(empty($item['cover_media_id']) ? null : (int) $item['cover_media_id']);

$icons = [
    'tour'    => '<path d="M12 2 3 7v10l9 5 9-5V7l-9-5Zm0 2.3 6.9 3.8L12 12l-6.9-3.9L12 4.3ZM5 9.2l6 3.4v7.1l-6-3.4V9.2Zm8 10.5v-7.1l6-3.4v7.1l-6 3.4Z"/>',
    'service' => '<path d="M9 3a2 2 0 0 0-2 2v1H4a2 2 0 0 0-2 2v3h20V8a2 2 0 0 0-2-2h-3V5a2 2 0 0 0-2-2H9Zm0 3V5h6v1H9ZM2 12v7a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-7h-8v2h-4v-2H2Z"/>',
    'post'    => '<path d="M6 2h9l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm8 1.5V8h4.5L14 3.5ZM7 12h10v1.5H7V12Zm0 3.5h10V17H7v-1.5ZM7 9h6v1.5H7V9Z"/>',
    'page'    => '<path d="M6 2h9l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm8 1.5V8h4.5L14 3.5Z"/>',
];
$icon = $icons[(string) $item['type']] ?? $icons['page'];
?>
<article class="card card--<?= View::e((string) $item['type']) ?>">
    <div class="card-media<?= $cover === null ? ' card-media--placeholder' : '' ?>">
        <?php if ($cover !== null): ?>
            <?= View::image($cover, '', false, '(min-width: 60rem) 20rem, 90vw') ?>
        <?php else: ?>
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><?= $icon ?></svg>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (!empty($item['category_name']) && !$meta): ?>
            <p class="card-eyebrow"><?= View::e((string) $item['category_name']) ?></p>
        <?php endif; ?>
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
                    &middot; <?= View::e((string) $item['category_name']) ?>
                <?php endif; ?>
                <?php if ((int) $item['word_count'] > 0): ?>
                    &middot; <?= View::readingTime((int) $item['word_count']) ?> min read
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
</article>
