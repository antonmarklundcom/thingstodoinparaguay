<?php
/**
 * @var array<int,array<string,mixed>> $tours
 * @var array<int,array<string,mixed>> $services
 * @var array<int,array<string,mixed>> $posts
 * @var array<int,array<string,mixed>> $categories
 * @var array<string,mixed>            $config
 */

use Ttp\Router;
use Ttp\View;

$homeFaq = [
    ['q' => 'Do you only run group tours, or can you plan something private?', 'a' => 'Both. Every tour on the site can run as a private trip for your group — tell us your dates and headcount and we will quote it.'],
    ['q' => 'How far in advance should I book?', 'a' => 'A few days is usually enough for day trips around Asunción; for the Jesuit missions, Iguazú or anything multi-day, a week or two gives us room to arrange transport properly.'],
    ['q' => 'Do you help with things beyond tours, like moving here?', 'a' => 'Yes — see [our services](' . Router::SERVICES_PATH . '): airport transfers, apartment hunting, residency paperwork, schools and healthcare.'],
    ['q' => 'What languages do you work in?', 'a' => 'English, Spanish and Swedish, both on tours and by email or WhatsApp.'],
];
?>
<section class="hero">
    <div class="hero__inner">
        <p class="hero__eyebrow">Asunción, Paraguay</p>
        <h1>See Paraguay the way people who live here see it</h1>
        <p class="hero__lede">
            Guided tours, day trips and relocation help — planned and run by Anton and Yanina, based in
            Asunción. Tell us what you want to see and we will build the route.
        </p>
        <p class="hero__actions">
            <a class="button button--primary" href="/contact/">Ask us for a quote</a>
            <a class="button button--ghost" href="<?= View::e(Router::TOURS_PATH) ?>">Browse the tours</a>
        </p>
    </div>
</section>

<div class="container container--wide">

<?php if ($tours !== []): ?>
    <section aria-labelledby="home-tours">
        <div class="section-head">
            <h2 id="home-tours">Tours and day trips</h2>
            <a class="see-all" href="<?= View::e(Router::TOURS_PATH) ?>">All tours &rarr;</a>
        </div>
        <div class="grid">
            <?php foreach ($tours as $item): ?>
                <?= View::partial('card', ['item' => $item]) ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($posts !== []): ?>
    <section aria-labelledby="home-posts">
        <div class="section-head">
            <h2 id="home-posts">From the blog</h2>
            <a class="see-all" href="<?= View::e(Router::BLOG_PATH) ?>">All articles &rarr;</a>
        </div>
        <div class="grid">
            <?php foreach ($posts as $item): ?>
                <?= View::partial('card', ['item' => $item, 'meta' => true]) ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section aria-labelledby="home-why">
    <div class="section-head">
        <h2 id="home-why">Why book with us</h2>
    </div>
    <div class="team-grid">
        <div class="team-card">
            <div class="team-avatar" aria-hidden="true">AM</div>
            <div>
                <h3>Anton Marklund</h3>
                <p class="team-role">Marketing Director</p>
                <p>Handles routes, quotes and the practical side of getting around Paraguay — the person you will most likely message first.</p>
            </div>
        </div>
        <div class="team-card">
            <div class="team-avatar" aria-hidden="true">YA</div>
            <div>
                <h3>Yanina Alvarez</h3>
                <p class="team-role">Photographer</p>
                <p>Knows the country's back roads and hidden corners — she plans the itineraries that get you past the obvious stops.</p>
            </div>
        </div>
    </div>
</section>

<?php if ($services !== []): ?>
    <section aria-labelledby="home-services">
        <div class="section-head">
            <h2 id="home-services">Help on the ground</h2>
            <a class="see-all" href="<?= View::e(Router::SERVICES_PATH) ?>">All services &rarr;</a>
        </div>
        <div class="grid">
            <?php foreach ($services as $item): ?>
                <?= View::partial('card', ['item' => $item]) ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($categories !== []): ?>
    <section aria-labelledby="home-categories">
        <h2 id="home-categories">Browse by topic</h2>
        <ul class="link-list">
            <?php foreach ($categories as $category): ?>
                <li>
                    <a href="/category/<?= View::e((string) $category['slug']) ?>/"><?= View::e((string) $category['name']) ?></a>
                    (<?= (int) $category['post_count'] ?>)
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<?= View::partial('faq', ['faq' => $homeFaq]) ?>

</div>

<section class="tour-cta" aria-labelledby="home-contact">
    <div class="container">
        <h2 id="home-contact">Plan your trip with us</h2>
        <p>
            Tell us when you are coming and what you want to see, and we will put together a route and a
            price. We reply in English, Spanish or Swedish.
        </p>
        <p><a class="button button--primary" href="/contact/">Get in touch</a></p>
    </div>
</section>
