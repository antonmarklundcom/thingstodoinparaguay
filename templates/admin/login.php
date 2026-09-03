<?php
/** @var string $error @var string $email @var string $next @var bool $noAdmins @var string $csrf */
use Ttp\Admin\AdminView;
?>
<div class="card card-narrow">
    <h1>Sign in</h1>

    <?php if ($noAdmins): ?>
        <p class="note">
            There is no admin account yet. Create one over SSH with
            <code>php bin/create-admin.php</code>, then sign in here.
        </p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p class="flash flash-error" role="alert"><?= AdminView::e($error) ?></p>
    <?php endif; ?>

    <form method="post" action="/admin/login/" class="stack">
        <input type="hidden" name="_csrf" value="<?= AdminView::e($csrf) ?>">
        <input type="hidden" name="next" value="<?= AdminView::e($next) ?>">

        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="username" required
               value="<?= AdminView::e($email) ?>" autofocus>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>

        <button type="submit" class="primary">Sign in</button>
    </form>
</div>
