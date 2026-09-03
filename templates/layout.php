<?php
/**
 * The one page shell. Phase S3 re-skins this file; the landmark structure
 * (skip link, header/nav, single main, footer) and the SEO head are the
 * contract and must survive.
 *
 * @var Ttp\Seo $seo
 * @var string  $content
 */

use Ttp\Router;
use Ttp\Seo;
use Ttp\View;

$config = ttp_config();
$nav = [
    ['label' => 'Tours',       'path' => Router::TOURS_PATH],
    ['label' => 'Services',    'path' => Router::SERVICES_PATH],
    ['label' => 'Attractions', 'path' => Router::ATTRACTIONS_PATH],
    ['label' => 'Blog',        'path' => Router::BLOG_PATH],
    ['label' => 'About',       'path' => '/about/'],
    ['label' => 'FAQ',         'path' => '/faq/'],
    ['label' => 'Contact',     'path' => '/contact/'],
];
$whatsapp = preg_replace('/\D+/', '', (string) $config['whatsapp']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $seo->renderHead() ?>

    <link rel="stylesheet" href="/assets/site.css">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <p class="brand"><a href="/"><?= View::e($config['site_name']) ?></a></p>
    <nav aria-label="Primary">
        <ul>
            <?php foreach ($nav as $link): ?>
                <?php $current = $seo->canonicalPath === $link['path']; ?>
                <li><a href="<?= View::e($link['path']) ?>"<?= $current ? ' aria-current="page"' : '' ?>><?= View::e($link['label']) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </nav>
</header>

<?php if ($seo->breadcrumbs !== []): ?>
    <?= View::partial('breadcrumbs', ['breadcrumbs' => $seo->breadcrumbs]) ?>
<?php endif; ?>

<main id="main">
<?= $content ?>
</main>

<footer class="site-footer">
    <h2>Things to do in Paraguay</h2>
    <address>
        <?= View::e($config['address']) ?><br>
        <a href="tel:<?= View::e(preg_replace('/\s+/', '', (string) $config['phone'])) ?>"><?= View::e($config['phone']) ?></a><br>
        <a href="mailto:<?= View::e($config['email']) ?>"><?= View::e($config['email']) ?></a>
    </address>
    <?php if ($whatsapp !== ''): ?>
        <p><a class="whatsapp" href="https://wa.me/<?= View::e($whatsapp) ?>" rel="noopener">Message us on WhatsApp</a></p>
    <?php endif; ?>
    <nav aria-label="Footer">
        <ul>
            <?php foreach ($nav as $link): ?>
                <li><a href="<?= View::e($link['path']) ?>"><?= View::e($link['label']) ?></a></li>
            <?php endforeach; ?>
            <li><a href="<?= View::e(Seo::url('/feed.xml')) ?>">RSS feed</a></li>
        </ul>
    </nav>
    <p class="copyright">&copy; <?= gmdate('Y') ?> <?= View::e($config['site_name']) ?>. Asunción, Paraguay.</p>
</footer>
</body>
</html>
