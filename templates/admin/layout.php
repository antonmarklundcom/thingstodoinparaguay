<?php
/**
 * The admin shell. Deliberately plain: one narrow column on a phone, a sidebar
 * from 60rem up. Phase S3 does not touch this file.
 *
 * @var string $title
 * @var string $content
 * @var array<int,array{label:string,path:string,current:bool}> $nav
 * @var array<int,array{type:string,message:string}> $flash
 * @var array<string,mixed>|null $user
 * @var string $csrf
 */

use Ttp\Admin\AdminView;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= AdminView::e($title) ?> — Admin</title>
    <link rel="stylesheet" href="/assets/admin/admin.css">
</head>
<body class="admin">
<a class="skip-link" href="#main">Skip to content</a>

<header class="bar">
    <a class="bar-brand" href="/admin/">Things to do in Paraguay</a>
    <?php if ($user !== null): ?>
        <button class="bar-toggle" type="button" aria-expanded="false" aria-controls="admin-nav" data-nav-toggle>Menu</button>
        <form class="bar-out" method="post" action="/admin/logout/">
            <input type="hidden" name="_csrf" value="<?= AdminView::e($csrf) ?>">
            <button type="submit" class="link">Sign out</button>
        </form>
    <?php endif; ?>
</header>

<div class="shell">
    <?php if ($user !== null): ?>
        <nav id="admin-nav" class="side" aria-label="Admin sections">
            <ul>
                <?php foreach ($nav as $link): ?>
                    <li>
                        <a href="<?= AdminView::e($link['path']) ?>"<?= $link['current'] ? ' aria-current="page"' : '' ?>>
                            <?= AdminView::e($link['label']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="side-foot">
                <a href="/" target="_blank" rel="noopener">View the site ↗</a><br>
                <a href="/admin/backup/">Download backup</a>
            </p>
        </nav>
    <?php endif; ?>

    <main id="main" class="main">
        <?php foreach ($flash as $message): ?>
            <p class="flash flash-<?= AdminView::e($message['type']) ?>" role="status"><?= AdminView::e($message['message']) ?></p>
        <?php endforeach; ?>
        <?= $content ?>
    </main>
</div>

<script src="/assets/admin/admin.js" defer></script>
</body>
</html>
