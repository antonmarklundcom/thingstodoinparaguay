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
?>
<h1>Things to do in Paraguay</h1>

<section class="intro">
    <p>
        We are Anton Marklund and Yanina Alvarez, and we run guided tours, day trips and relocation
        services out of Asunción. This site is the practical guide we wished existed when we arrived:
        what is worth seeing, what it actually takes to get there, and how to arrange it.
    </p>
    <p><a href="/contact/">Ask us for a quote</a> or <a href="<?= View::e(Router::TOURS_PATH) ?>">browse the tours</a>.</p>
</section>

<?php if ($tours !== []): ?>
    <section aria-labelledby="home-tours">
        <h2 id="home-tours">Tours and day trips</h2>
        <div class="grid">
            <?php foreach ($tours as $item): ?>
                <?= View::partial('card', ['item' => $item]) ?>
            <?php endforeach; ?>
        </div>
        <p><a href="<?= View::e(Router::TOURS_PATH) ?>">All tours</a></p>
    </section>
<?php endif; ?>

<?php if ($services !== []): ?>
    <section aria-labelledby="home-services">
        <h2 id="home-services">Help on the ground</h2>
        <div class="grid">
            <?php foreach ($services as $item): ?>
                <?= View::partial('card', ['item' => $item]) ?>
            <?php endforeach; ?>
        </div>
        <p><a href="<?= View::e(Router::SERVICES_PATH) ?>">All services</a></p>
    </section>
<?php endif; ?>

<?php if ($posts !== []): ?>
    <section aria-labelledby="home-posts">
        <h2 id="home-posts">From the blog</h2>
        <div class="grid">
            <?php foreach ($posts as $item): ?>
                <?= View::partial('card', ['item' => $item, 'meta' => true]) ?>
            <?php endforeach; ?>
        </div>
        <p><a href="<?= View::e(Router::BLOG_PATH) ?>">All articles</a></p>
    </section>
<?php endif; ?>

<?php if ($categories !== []): ?>
    <section aria-labelledby="home-categories">
        <h2 id="home-categories">Browse by topic</h2>
        <ul>
            <?php foreach ($categories as $category): ?>
                <li>
                    <a href="/category/<?= View::e((string) $category['slug']) ?>/"><?= View::e((string) $category['name']) ?></a>
                    (<?= (int) $category['post_count'] ?>)
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<section aria-labelledby="home-contact">
    <h2 id="home-contact">Plan your trip with us</h2>
    <p>
        Tell us when you are coming and what you want to see, and we will put together a route and a
        price. <a href="/contact/">Get in touch</a> — we reply in English, Spanish or Swedish.
    </p>
</section>
