<?php
/**
 * /tourist-attractions-paraguay/ — the hub page. S4 rewrites the copy and
 * curates the grouping; the structure and the linking are set here.
 *
 * @var array<int,array<string,mixed>>                    $tours
 * @var array<string,array<int,array<string,mixed>>>      $grouped
 */

use Ttp\Router;
use Ttp\View;
?>
<h1>Tourist attractions in Paraguay</h1>

<p class="intro">
    Paraguay is small enough to see properly and quiet enough that you will often have a place to
    yourself. Below is what we send people to — the ruins, the parks, the waterfalls and the city —
    with the trips that get you there.
</p>

<?php if ($tours !== []): ?>
    <section aria-labelledby="hub-tours">
        <h2 id="hub-tours">Guided trips to these places</h2>
        <div class="grid">
            <?php foreach ($tours as $item): ?>
                <?= View::partial('card', ['item' => $item]) ?>
            <?php endforeach; ?>
        </div>
        <p><a href="<?= View::e(Router::TOURS_PATH) ?>">All tours and day trips</a></p>
    </section>
<?php endif; ?>

<?php foreach ($grouped as $group => $posts): ?>
    <?php if ($posts === []) { continue; } ?>
    <section aria-labelledby="hub-<?= View::e(\Ttp\Str::slug((string) $group)) ?>">
        <h2 id="hub-<?= View::e(\Ttp\Str::slug((string) $group)) ?>"><?= View::e((string) $group) ?></h2>
        <ul class="link-list">
            <?php foreach ($posts as $post): ?>
                <li><a href="<?= View::e((string) $post['path']) ?>"><?= View::e((string) $post['title']) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endforeach; ?>

<section aria-labelledby="hub-cta">
    <h2 id="hub-cta">Want help putting a route together?</h2>
    <p>
        <a href="/contact/">Send us your dates</a> and we will tell you what fits — and what is worth
        skipping.
    </p>
</section>
