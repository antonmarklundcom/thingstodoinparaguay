<?php
/**
 * /tours/ and /services/. `$intro` is the editable page item behind the index
 * (only /services/ has one today); its body renders above the list.
 *
 * @var string                         $type
 * @var string                         $heading
 * @var array<int,array<string,mixed>> $items
 * @var array<string,mixed>|null       $intro
 * @var string                         $basePath
 */

use Ttp\View;

$isTour = $type === 'tour';
?>
<div class="container container--wide">
    <h1><?= View::e($intro !== null ? (string) $intro['title'] : $heading) ?></h1>

    <?php if ($intro !== null && trim((string) $intro['body_html']) !== ''): ?>
        <div class="prose intro"><?= $intro['body_html'] ?></div>
    <?php else: ?>
        <p class="intro">
            <?php if ($isTour): ?>
                Every trip we run, from a half day in Asunción to the Jesuit missions and Iguazú.
                Prices depend on group size and season — ask and we will quote you.
            <?php else: ?>
                The practical help that makes a stay in Paraguay work: getting from the airport,
                finding somewhere to live, residency paperwork, schools and healthcare.
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if ($items === []): ?>
        <p>Nothing is listed here yet. <a href="/contact/">Ask us what is available</a>.</p>
    <?php else: ?>
        <section aria-labelledby="index-heading">
            <h2 id="index-heading" class="visually-hidden"><?= View::e($heading) ?></h2>
            <div class="grid">
                <?php foreach ($items as $item): ?>
                    <?= View::partial('card', ['item' => $item]) ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<section class="tour-cta" aria-labelledby="index-cta">
    <div class="container">
        <h2 id="index-cta">Not sure which one?</h2>
        <p>
            <a href="/contact/">Tell us what you are after</a> and we will suggest the right
            <?= $isTour ? 'trip' : 'service' ?> — or build something around your dates.
        </p>
    </div>
</section>
