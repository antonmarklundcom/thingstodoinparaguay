<?php
/**
 * The footer newsletter form — plain HTML POST to /forms/subscribe.php
 * (src/Forms/NewsletterForm.php). Works with no JS.
 *
 * @var string $redirectTo
 */

use Ttp\View;

$subscribed = $_GET['subscribed'] ?? null;
?>
<?php if ($subscribed === '1'): ?>
    <p class="form-note form-note--success">You're on the list — thanks!</p>
<?php elseif ($subscribed === '0'): ?>
    <p class="form-note form-note--error">That didn't look like a valid email — try again?</p>
<?php endif; ?>
<form class="newsletter-form" method="post" action="/forms/subscribe.php">
    <label class="visually-hidden" for="newsletter-email">Email address</label>
    <input id="newsletter-email" name="email" type="email" placeholder="you@example.com" required autocomplete="email">
    <input type="hidden" name="redirect_to" value="<?= View::e($redirectTo) ?>">
    <input class="hp-field" type="text" name="company" tabindex="-1" autocomplete="off" aria-hidden="true">
    <button type="submit" class="button button--primary button--sm">Subscribe</button>
</form>
