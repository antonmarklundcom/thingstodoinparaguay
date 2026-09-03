<?php
/**
 * @var array<string,mixed>            $item
 * @var string                         $bodyHtml
 * @var array<int,array{id:string,text:string}> $toc
 * @var array<string,mixed>|null       $cover
 */

use Ttp\View;
?>
<article class="page">
    <header>
        <h1><?= View::e((string) $item['title']) ?></h1>
        <?= View::image($cover, 'page-cover', true) ?>
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

    <footer class="page-footer">
        <h2>Questions?</h2>
        <p><a href="/contact/">Send us a message</a> and we will get back to you.</p>
    </footer>
</article>
