<?php
/** @var array<string,string> $settings @var array<string,mixed> $config @var string $csrf */
use Ttp\Admin\AdminView;

$value = static fn (string $key, string $fallback = ''): string => (string) ($settings[$key] ?? $fallback);
?>
<h1>Settings</h1>

<section class="card">
    <form method="post" action="/admin/settings/save/" class="stack">
        <input type="hidden" name="_csrf" value="<?= AdminView::e($csrf) ?>">

        <label for="site_name">Site name</label>
        <input id="site_name" name="site_name" type="text" value="<?= AdminView::e($value('site_name', (string) $config['site_name'])) ?>">

        <label for="tagline">Tagline</label>
        <input id="tagline" name="tagline" type="text" value="<?= AdminView::e($value('tagline', (string) $config['tagline'])) ?>">

        <label for="address">Address</label>
        <input id="address" name="address" type="text" value="<?= AdminView::e($value('address', (string) $config['address'])) ?>">

        <label for="phone">Phone</label>
        <input id="phone" name="phone" type="text" value="<?= AdminView::e($value('phone', (string) $config['phone'])) ?>">

        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="<?= AdminView::e($value('email', (string) $config['email'])) ?>">

        <label for="whatsapp">WhatsApp number</label>
        <input id="whatsapp" name="whatsapp" type="text" inputmode="numeric"
               value="<?= AdminView::e($value('whatsapp', (string) $config['whatsapp'])) ?>">
        <p class="hint">Digits only, country code first — 595995628862.</p>

        <label for="ga4_id">Google Analytics 4 id</label>
        <input id="ga4_id" name="ga4_id" type="text" value="<?= AdminView::e($value('ga4_id')) ?>" placeholder="G-XXXXXXXXXX">
        <p class="hint">Leave empty and no analytics script is loaded at all.</p>

        <label for="social_json">Social links (JSON)</label>
        <textarea id="social_json" name="social_json" rows="4"><?= AdminView::e($value('social_json', '{}')) ?></textarea>
        <p class="hint">For example <code>{"instagram":"https://instagram.com/…"}</code>.</p>

        <button type="submit" class="primary">Save settings</button>
    </form>
</section>

<section class="card">
    <h2>Backup</h2>
    <p>
        Everything written in this panel lives in the site database. A backup writes it all back out as Markdown
        files — the same files the site was built from — so it can be restored anywhere.
    </p>
    <p><a class="button" href="/admin/backup/">Download backup (.zip)</a></p>
    <p class="hint">The uploaded images are not in the zip; copy <span class="mono">public/media/</span> separately.</p>
</section>

<section class="card">
    <h2>Your account</h2>
    <p>
        Passwords are changed over SSH with <span class="mono">php bin/create-admin.php</span> — run it with the same
        email address and it replaces the password. That is also how you recover a forgotten one.
    </p>
</section>
