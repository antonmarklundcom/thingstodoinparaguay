<?php
/**
 * @var array<string,mixed>            $item
 * @var string                         $bodyHtml
 * @var array<int,array{id:string,text:string}> $toc
 * @var array<string,mixed>|null       $cover
 */

use Ttp\View;

$isContact = (string) $item['slug'] === 'contact';
?>
<article class="page container">
    <header>
        <h1><?= View::e((string) $item['title']) ?></h1>
        <?php if ($cover !== null): ?>
            <?= View::image($cover, 'post-cover', true) ?>
        <?php endif; ?>
    </header>

    <?php if ($toc !== [] && !$isContact): ?>
        <nav class="toc" aria-labelledby="toc-heading">
            <h2 id="toc-heading">On this page</h2>
            <ol>
                <?php foreach ($toc as $entry): ?>
                    <li><a href="#<?= View::e($entry['id']) ?>"><?= View::e($entry['text']) ?></a></li>
                <?php endforeach; ?>
            </ol>
        </nav>
    <?php endif; ?>

    <?php if ($isContact): ?>
        <p class="intro">Tell us your dates and what you want to see — we reply within a day, in English, Spanish or Swedish.</p>
        <?= View::partial('contact') ?>
    <?php else: ?>
        <div class="prose">
            <?= $bodyHtml ?>
        </div>
    <?php endif; ?>

    <?php if (!$isContact): ?>
        <footer class="page-footer">
            <h2>Questions?</h2>
            <p><a class="button button--primary" href="/contact/">Send us a message</a></p>
        </footer>
    <?php endif; ?>
</article>
