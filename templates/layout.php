<?php
/**
 * The one page shell. Phase S3 re-skin (plan §6.1); the landmark structure
 * (skip link, header/nav, single main, footer) and the SEO head are the
 * contract from O1/O2 and must survive.
 *
 * @var Ttp\Seo $seo
 * @var string  $content
 */

use Ttp\Repo\SettingsRepo;
use Ttp\Router;
use Ttp\Seo;
use Ttp\View;

$config   = ttp_config();

$siteName = SettingsRepo::get('site_name', (string) $config['site_name']);
$address  = SettingsRepo::get('address', (string) $config['address']);
$phone    = SettingsRepo::get('phone', (string) $config['phone']);
$email    = SettingsRepo::get('email', (string) $config['email']);
$ga4Id    = SettingsRepo::get('ga4_id', (string) $config['ga4_id']);
$whatsapp = preg_replace('/\D+/', '', SettingsRepo::get('whatsapp', (string) $config['whatsapp'])) ?: '';

$social = json_decode(SettingsRepo::get('social_json', '{}'), true);
$social = is_array($social) ? array_filter($social, static fn ($v): bool => trim((string) $v) !== '') : [];
$socialIcons = [
    'instagram' => '<path d="M12 7.2A4.8 4.8 0 1 0 12 16.8 4.8 4.8 0 0 0 12 7.2Zm0 7.92a3.12 3.12 0 1 1 0-6.24 3.12 3.12 0 0 1 0 6.24ZM18.4 2H5.6A3.6 3.6 0 0 0 2 5.6v12.8A3.6 3.6 0 0 0 5.6 22h12.8a3.6 3.6 0 0 0 3.6-3.6V5.6A3.6 3.6 0 0 0 18.4 2Zm1.9 16.4a1.9 1.9 0 0 1-1.9 1.9H5.6a1.9 1.9 0 0 1-1.9-1.9V5.6a1.9 1.9 0 0 1 1.9-1.9h12.8a1.9 1.9 0 0 1 1.9 1.9v12.8ZM17.4 6a1.1 1.1 0 1 0 0 2.2 1.1 1.1 0 0 0 0-2.2Z"/>',
    'facebook'  => '<path d="M13.5 22v-8.4h2.8l.4-3.3h-3.2V8.1c0-.95.27-1.6 1.63-1.6h1.74V3.55C16.5 3.5 15.53 3.4 14.4 3.4c-2.36 0-3.98 1.44-3.98 4.08v2.82H7.6v3.3h2.82V22h3.08Z"/>',
    'twitter'   => '<path d="M20.9 6.9c-.62.28-1.28.46-1.97.55.71-.42 1.25-1.1 1.5-1.9-.66.4-1.4.68-2.18.84A3.4 3.4 0 0 0 12.4 9.4c0 .27.03.53.09.78-2.83-.14-5.34-1.5-7.02-3.56a3.4 3.4 0 0 0 1.05 4.55c-.55-.02-1.07-.17-1.53-.42v.04a3.4 3.4 0 0 0 2.73 3.34c-.5.14-1.04.16-1.55.06a3.41 3.41 0 0 0 3.18 2.37A6.83 6.83 0 0 1 3 18.13a9.63 9.63 0 0 0 5.22 1.53c6.27 0 9.7-5.2 9.7-9.7l-.01-.44c.67-.48 1.24-1.08 1.7-1.76-.6.27-1.25.46-1.94.55Z"/>',
    'youtube'   => '<path d="M21.6 7.7a2.7 2.7 0 0 0-1.9-1.9C18 5.3 12 5.3 12 5.3s-6 0-7.7.5A2.7 2.7 0 0 0 2.4 7.7 28 28 0 0 0 1.9 12a28 28 0 0 0 .5 4.3 2.7 2.7 0 0 0 1.9 1.9c1.7.5 7.7.5 7.7.5s6 0 7.7-.5a2.7 2.7 0 0 0 1.9-1.9 28 28 0 0 0 .5-4.3 28 28 0 0 0-.5-4.3ZM9.9 15.1V8.9l5.4 3.1-5.4 3.1Z"/>',
    'tiktok'    => '<path d="M16.6 2h-3.2v13.3a2.9 2.9 0 1 1-2.05-2.78v-3.28A6.1 6.1 0 1 0 16.6 15.3V8.9a7.3 7.3 0 0 0 4.2 1.34V7.03a4 4 0 0 1-4.2-3.02V2Z"/>',
];

$nav = [
    ['label' => 'Tours',       'path' => Router::TOURS_PATH],
    ['label' => 'Services',    'path' => Router::SERVICES_PATH],
    ['label' => 'Attractions', 'path' => Router::ATTRACTIONS_PATH],
    ['label' => 'Blog',        'path' => Router::BLOG_PATH],
    ['label' => 'About',       'path' => '/about/'],
    ['label' => 'FAQ',         'path' => '/faq/'],
];

$cssFile = ttp_root() . '/public/assets/site.css';
$css     = is_file($cssFile) ? (string) file_get_contents($cssFile) : '';
$jsFile  = ttp_root() . '/public/assets/site.js';
// A cache-busting query string stands in for hashed filenames (plan §6.1) —
// no build step, and a redeploy still invalidates the year-long asset cache
// .htaccess sets for /assets/*.js.
$jsVer   = is_file($jsFile) ? (string) filemtime($jsFile) : '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $seo->renderHead() ?>

    <link rel="icon" href="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2032%2032%22%3E%3Crect%20width%3D%2232%22%20height%3D%2232%22%20rx%3D%228%22%20fill%3D%22%2316407a%22%2F%3E%3Cpath%20d%3D%22M9%2022V10h4.8c3%200%204.9%201.7%204.9%204.4%200%202.8-2%204.5-5%204.5h-2v3.1H9Zm1.7-4.6h2.2c1.9%200%203-.9%203-2.6%200-1.6-1.1-2.5-3-2.5h-2.2v5.1Z%22%20fill%3D%22%23fff%22%2F%3E%3C%2Fsvg%3E">
    <?php if ($css !== ''): ?>
        <style><?= $css ?></style>
    <?php else: ?>
        <link rel="stylesheet" href="/assets/site.css">
    <?php endif; ?>

    <?php if ($ga4Id !== ''): ?>
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?= View::e($ga4Id) ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '<?= View::e($ga4Id) ?>');
        </script>
    <?php endif; ?>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <div class="site-header__bar">
        <p class="brand">
            <a href="/">
                <svg class="mark" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 22s7-6.2 7-12.3A7 7 0 0 0 5 9.7C5 15.8 12 22 12 22Z" fill="currentColor"/>
                    <circle cx="12" cy="9.5" r="2.6" fill="var(--paper)"/>
                </svg>
                <?= View::e($siteName) ?>
            </a>
        </p>

        <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="primary-nav">
            <svg class="icon-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
            <svg class="icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 5l14 14M19 5 5 19"/></svg>
            <span class="visually-hidden">Menu</span>
        </button>

        <nav class="primary-nav" id="primary-nav" aria-label="Primary">
            <ul>
                <?php foreach ($nav as $link): ?>
                    <?php $current = $seo->canonicalPath === $link['path']; ?>
                    <li><a href="<?= View::e($link['path']) ?>"<?= $current ? ' aria-current="page"' : '' ?>><?= View::e($link['label']) ?></a></li>
                <?php endforeach; ?>
                <li class="nav-cta"><a class="button button--primary button--sm" href="/contact/">Ask for a quote</a></li>
            </ul>
        </nav>
    </div>
</header>

<?php if ($seo->breadcrumbs !== []): ?>
    <?= View::partial('breadcrumbs', ['breadcrumbs' => $seo->breadcrumbs]) ?>
<?php endif; ?>

<main id="main">
<?= $content ?>
</main>

<?php if ($whatsapp !== ''): ?>
    <a class="whatsapp-float" href="https://wa.me/<?= View::e($whatsapp) ?>?text=<?= rawurlencode('Hi! I have a question about a trip in Paraguay.') ?>" rel="noopener" target="_blank" aria-label="Message us on WhatsApp">
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.1a8.1 8.1 0 0 1-4.1-1.1l-.3-.2-2.9.8.8-2.8-.2-.3A8.1 8.1 0 1 1 12 20.1Zm4.4-6c-.2-.1-1.4-.7-1.6-.8-.2-.1-.4-.1-.6.1-.2.2-.6.8-.8 1-.1.2-.3.2-.5.1-.2-.1-1-.4-2-1.2-.7-.6-1.2-1.4-1.4-1.6-.1-.2 0-.4.1-.5l.4-.5c.1-.1.2-.3.2-.4.1-.2 0-.3 0-.5l-.7-1.6c-.2-.4-.4-.4-.6-.4h-.5c-.2 0-.5.1-.7.3-.2.2-.9.9-.9 2.2s1 2.6 1.1 2.7c.1.2 1.9 3 4.7 4.1.7.3 1.2.4 1.6.5.7.2 1.3.2 1.8.1.5-.1 1.4-.6 1.6-1.1.2-.5.2-1 .1-1.1-.1-.1-.2-.2-.4-.3Z"/></svg>
        <span aria-hidden="true">WhatsApp us</span>
    </a>
<?php endif; ?>

<footer class="site-footer">
    <div class="footer-grid">
        <div class="footer-brand">
            <h2><?= View::e($siteName) ?></h2>
            <address>
                <?= View::e($address) ?><br>
                <a href="tel:<?= View::e(preg_replace('/\s+/', '', $phone)) ?>"><?= View::e($phone) ?></a><br>
                <a href="mailto:<?= View::e($email) ?>"><?= View::e($email) ?></a>
            </address>
            <?php if ($social !== []): ?>
                <p class="social-links">
                    <?php foreach ($social as $key => $url): ?>
                        <?php if (!isset($socialIcons[$key])) { continue; } ?>
                        <a href="<?= View::e((string) $url) ?>" rel="noopener" aria-label="<?= View::e(ucfirst((string) $key)) ?>">
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><?= $socialIcons[$key] ?></svg>
                        </a>
                    <?php endforeach; ?>
                </p>
            <?php endif; ?>
        </div>

        <nav class="footer-nav" aria-label="Footer">
            <h3>Explore</h3>
            <ul>
                <?php foreach ($nav as $link): ?>
                    <li><a href="<?= View::e($link['path']) ?>"><?= View::e($link['label']) ?></a></li>
                <?php endforeach; ?>
                <li><a href="/contact/">Contact</a></li>
                <li><a href="<?= View::e(Seo::url('/feed.xml')) ?>">RSS feed</a></li>
            </ul>
        </nav>

        <div class="footer-newsletter">
            <h3>Trip ideas, monthly</h3>
            <p>Short and infrequent — new tours, events and travel tips for Paraguay.</p>
            <?= View::partial('newsletter-form', ['redirectTo' => $seo->canonicalPath]) ?>
        </div>
    </div>
    <p class="footer-bottom">&copy; <?= gmdate('Y') ?> <?= View::e($siteName) ?>. Asunción, Paraguay.</p>
</footer>

<script src="/assets/site.js?v=<?= View::e($jsVer) ?>" defer></script>
</body>
</html>
