<?php
/**
 * The /contact/ page body: a plain-HTML POST form (works with no JS) plus a
 * WhatsApp shortcut and an address card that links out to Google Maps rather
 * than embedding a map iframe — no third-party request, nothing to affect
 * the Lighthouse performance budget (plan §6.1).
 */

use Ttp\Repo\SettingsRepo;
use Ttp\View;

$config   = ttp_config();

$address  = SettingsRepo::get('address', (string) $config['address']);
$phone    = SettingsRepo::get('phone', (string) $config['phone']);
$email    = SettingsRepo::get('email', (string) $config['email']);
$whatsapp = preg_replace('/\D+/', '', SettingsRepo::get('whatsapp', (string) $config['whatsapp'])) ?: '';

$sent    = $_GET['sent'] ?? null;
$missing = trim((string) ($_GET['missing'] ?? ''));
$about   = trim((string) ($_GET['about'] ?? ''));
?>
<div class="contact-grid">
    <div class="form-card">
        <?php if ($sent === '1'): ?>
            <p class="form-note form-note--success">Thanks — we read every message and reply within a day.</p>
        <?php elseif ($sent === '0'): ?>
            <p class="form-note form-note--error">
                Almost — please add <?= View::e($missing !== '' ? str_replace(',', ', ', $missing) : 'the missing details') ?> and send it again.
            </p>
        <?php endif; ?>

        <form method="post" action="/forms/contact.php">
            <div class="field">
                <label for="contact-name">Name</label>
                <input id="contact-name" name="name" type="text" autocomplete="name" required>
            </div>
            <div class="field">
                <label for="contact-phone">Phone or WhatsApp</label>
                <input id="contact-phone" name="phone" type="tel" inputmode="tel" placeholder="+595 9XX XXX XXX" autocomplete="tel" required>
            </div>
            <div class="field">
                <label for="contact-email">Email <span class="hint">(optional)</span></label>
                <input id="contact-email" name="email" type="email" autocomplete="email">
            </div>
            <div class="field">
                <label for="contact-message">What are you planning?</label>
                <textarea id="contact-message" name="message" rows="5" required><?= $about !== '' ? View::e('I would like to ask about: ' . str_replace('-', ' ', $about) . '. ') : '' ?></textarea>
                <p class="hint">Dates, group size, anything you already have in mind.</p>
            </div>

            <input class="hp-field" type="text" name="company" tabindex="-1" autocomplete="off" aria-hidden="true">
            <input type="hidden" name="_ts" value="<?= time() ?>">

            <button type="submit" class="button button--primary button--block">Send message</button>
        </form>
    </div>

    <div class="contact-side">
        <?php if ($whatsapp !== ''): ?>
            <a class="map-card" href="https://wa.me/<?= View::e($whatsapp) ?>" rel="noopener" target="_blank">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.1a8.1 8.1 0 0 1-4.1-1.1l-.3-.2-2.9.8.8-2.8-.2-.3A8.1 8.1 0 1 1 12 20.1Z"/></svg>
                <span>
                    <strong>Message us on WhatsApp</strong>
                    <span>Usually the fastest way to reach us</span>
                </span>
            </a>
        <?php endif; ?>

        <a class="map-card" href="https://www.google.com/maps/search/?api=1&query=<?= rawurlencode($address) ?>" rel="noopener" target="_blank">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 22s7-6.2 7-12.3A7 7 0 0 0 5 9.7C5 15.8 12 22 12 22Zm0-9.3a3 3 0 1 1 0-6 3 3 0 0 1 0 6Z"/></svg>
            <span>
                <strong>Find us</strong>
                <span><?= View::e($address) ?></span>
            </span>
        </a>

        <div class="info-card">
            <h2 class="info-card__heading">Other ways to reach us</h2>
            <p>
                <a href="tel:<?= View::e(preg_replace('/\s+/', '', $phone)) ?>"><?= View::e($phone) ?></a><br>
                <a href="mailto:<?= View::e($email) ?>"><?= View::e($email) ?></a>
            </p>
        </div>
    </div>
</div>
