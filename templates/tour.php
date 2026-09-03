<?php
/**
 * Tours and services share this template — plan §1.5. The structured sections
 * come from `tour_details`; each renders only when it has content, so the
 * gaps S4 is going to fill do not leave empty headings behind.
 *
 * @var array<string,mixed>            $item
 * @var array<string,mixed>|null       $details
 * @var array<string,mixed>|null       $cover
 * @var bool                           $isTour
 * @var array<int,array<string,mixed>> $related
 */

use Ttp\Markdown;
use Ttp\Router;
use Ttp\View;

$details   ??= [];
$itinerary   = $details['itinerary'] ?? [];
$why         = $details['why'] ?? [];
$practical   = $details['practical'] ?? [];
$faq         = $details['faq'] ?? [];
$price       = $details['price_usd'] ?? null;
$ctaText     = trim((string) ($details['cta_text'] ?? '')) ?: 'Ask for a quote';
$label       = trim((string) ($details['itinerary_label'] ?? '')) ?: 'What the day looks like';

// Facts worth showing above the fold, on top of whatever the practical list has.
$facts = [];
foreach (['duration' => 'Duration', 'departure' => 'Departure', 'transport' => 'Transport', 'requirements' => 'Requirements'] as $key => $name) {
    $value = trim((string) ($details[$key] ?? ''));
    if ($value !== '') {
        $facts[$name] = $value;
    }
}
$priceLabel = ($price !== null && (float) $price > 0)
    ? 'USD ' . number_format((float) $price, 0)
    : 'Ask for a quote';
$facts['Price'] = $priceLabel;
?>
<article class="tour tour--<?= View::e((string) $item['type']) ?> container">
    <header class="tour-header">
        <h1><?= View::e((string) $item['title']) ?></h1>
        <?php if (trim((string) ($details['tagline'] ?? '')) !== ''): ?>
            <p class="tagline"><?= View::e((string) $details['tagline']) ?></p>
        <?php endif; ?>
        <?php if ($cover !== null): ?>
            <?= View::image($cover, 'tour-cover', true) ?>
        <?php else: ?>
            <div class="cover-placeholder"><span><?= $isTour ? 'Tour' : 'Service' ?> &middot; <?= View::e((string) $item['title']) ?></span></div>
        <?php endif; ?>
        <?php if (trim((string) $item['excerpt']) !== ''): ?>
            <p class="lede"><?= View::e((string) $item['excerpt']) ?></p>
        <?php endif; ?>
        <p class="cta"><a class="button button--primary" href="/contact/?about=<?= View::e(urlencode((string) $item['slug'])) ?>"><?= View::e($ctaText) ?></a></p>
    </header>

    <aside class="facts" aria-labelledby="facts-heading">
        <h2 id="facts-heading">At a glance</h2>
        <dl>
            <?php foreach ($facts as $name => $value): ?>
                <dt><?= View::e((string) $name) ?></dt>
                <dd><?= View::e((string) $value) ?></dd>
            <?php endforeach; ?>
        </dl>
    </aside>

    <?php if (trim((string) ($details['hook_md'] ?? '')) !== ''): ?>
        <section class="tour-hook prose"><?= Markdown::toHtml((string) $details['hook_md']) ?></section>
    <?php endif; ?>

    <?php if (trim((string) ($details['solution_md'] ?? '')) !== ''): ?>
        <section class="tour-solution prose"><?= Markdown::toHtml((string) $details['solution_md']) ?></section>
    <?php endif; ?>

    <?php if ($itinerary !== []): ?>
        <section class="itinerary" aria-labelledby="itinerary-heading">
            <h2 id="itinerary-heading"><?= View::e($label) ?></h2>
            <ol>
                <?php foreach ($itinerary as $step): ?>
                    <li>
                        <h3><?= View::e((string) ($step['title'] ?? $step['name'] ?? '')) ?></h3>
                        <?php if (trim((string) ($step['body'] ?? '')) !== ''): ?>
                            <div class="prose"><?= Markdown::toHtml((string) $step['body']) ?></div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endif; ?>

    <?php if ($why !== []): ?>
        <section class="why" aria-labelledby="why-heading">
            <h2 id="why-heading">Why book this with us</h2>
            <ul>
                <?php foreach ($why as $reason): ?>
                    <li>
                        <h3><?= View::e((string) ($reason['title'] ?? '')) ?></h3>
                        <?php if (trim((string) ($reason['body'] ?? '')) !== ''): ?>
                            <div class="prose"><?= Markdown::toHtml((string) $reason['body']) ?></div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if ($practical !== []): ?>
        <section class="practical" aria-labelledby="practical-heading">
            <h2 id="practical-heading">Practical information</h2>
            <dl>
                <?php foreach ($practical as $fact): ?>
                    <dt><?= View::e((string) ($fact['label'] ?? '')) ?></dt>
                    <dd><?= View::e((string) ($fact['value'] ?? '')) ?></dd>
                <?php endforeach; ?>
            </dl>
        </section>
    <?php endif; ?>

    <?php if (trim((string) $item['body_md']) !== ''): ?>
        <section class="prose"><?= $item['body_html'] ?></section>
    <?php endif; ?>

    <?= View::partial('faq', ['faq' => is_array($faq) ? $faq : []]) ?>

    <?php if (trim((string) ($details['closing_md'] ?? '')) !== ''): ?>
        <section class="tour-closing prose"><?= Markdown::toHtml((string) $details['closing_md']) ?></section>
    <?php endif; ?>

    <footer class="tour-cta">
        <h2>Book <?= View::e((string) $item['title']) ?></h2>
        <p>
            Tell us your dates and how many people are coming and we will send a price and a plan.
        </p>
        <p><a class="button button--primary" href="/contact/?about=<?= View::e(urlencode((string) $item['slug'])) ?>"><?= View::e($ctaText) ?></a></p>
    </footer>
</article>

<?php if ($related !== []): ?>
    <div class="container">
        <section class="related" aria-labelledby="related-heading">
            <h2 id="related-heading">Other <?= $isTour ? 'tours' : 'services' ?></h2>
            <div class="grid">
                <?php foreach ($related as $other): ?>
                    <?= View::partial('card', ['item' => $other]) ?>
                <?php endforeach; ?>
            </div>
            <p><a href="<?= View::e($isTour ? Router::TOURS_PATH : Router::SERVICES_PATH) ?>">See all <?= $isTour ? 'tours' : 'services' ?></a></p>
        </section>
    </div>
<?php endif; ?>

<div class="sticky-cta" role="complementary" aria-label="Quick quote">
    <div>
        <strong><?= View::e((string) $item['title']) ?></strong>
        <span class="price"><?= View::e($priceLabel) ?></span>
    </div>
    <a class="button button--primary button--sm" href="/contact/?about=<?= View::e(urlencode((string) $item['slug'])) ?>"><?= View::e($ctaText) ?></a>
</div>
