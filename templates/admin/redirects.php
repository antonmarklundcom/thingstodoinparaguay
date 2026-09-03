<?php
/** @var array<int,array<string,mixed>> $redirects @var int $total @var string $q @var string $csrf */
use Ttp\Admin\AdminView;
?>
<h1>Redirects</h1>
<p class="hint">
    <?= $total ?> in total. Rows marked <span class="pill">map</span> come from the old WordPress site
    (<span class="mono">docs/url-map.csv</span>) and hold the URL contract together — leave those alone unless you
    know why. <span class="pill">slug-change</span> rows the panel created for you when a live address changed.
</p>

<section class="card">
    <h2>Add a redirect</h2>
    <form method="post" action="/admin/redirects/save/" class="stack">
        <input type="hidden" name="_csrf" value="<?= AdminView::e($csrf) ?>">

        <label for="from_path">From</label>
        <input id="from_path" name="from_path" type="text" required placeholder="/old-page/">

        <label for="status">What should happen</label>
        <select id="status" name="status">
            <option value="301">301 — send visitors somewhere else</option>
            <option value="410">410 — tell Google it is gone for good</option>
        </select>

        <label for="to_path">To (301 only)</label>
        <input id="to_path" name="to_path" type="text" placeholder="/new-page/">

        <button type="submit" class="primary">Add</button>
    </form>
</section>

<form class="filters" method="get" action="/admin/redirects/">
    <label for="q">Search</label>
    <input id="q" name="q" type="search" value="<?= AdminView::e($q) ?>" placeholder="/old-page/">
    <button type="submit">Filter</button>
</form>

<div class="scroller">
    <table>
        <thead><tr><th>From</th><th>To</th><th>Status</th><th>Source</th><th>Hits</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($redirects as $row): ?>
            <tr>
                <td class="mono"><?= AdminView::e((string) $row['from_path']) ?></td>
                <td class="mono"><?= AdminView::e((string) $row['to_path']) ?: '—' ?></td>
                <td><?= (int) $row['status'] ?></td>
                <td><span class="pill"><?= AdminView::e((string) $row['source']) ?></span></td>
                <td><?= (int) $row['hits'] ?></td>
                <td>
                    <form method="post" action="/admin/redirects/delete/">
                        <input type="hidden" name="_csrf" value="<?= AdminView::e($csrf) ?>">
                        <input type="hidden" name="from_path" value="<?= AdminView::e((string) $row['from_path']) ?>">
                        <button type="submit" class="link danger" onclick="return confirm('Remove this redirect?')">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
