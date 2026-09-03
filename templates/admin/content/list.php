<?php
/**
 * @var array<int,array<string,mixed>> $items
 * @var string $type @var string $status @var string $q @var string $csrf
 */
use Ttp\Admin\AdminView;
use Ttp\Admin\ContentWriter;
?>
<h1>Content</h1>

<p class="actions">
    <?php foreach (ContentWriter::TYPES as $t): ?>
        <a class="button<?= $t === 'post' ? ' primary' : '' ?>" href="/admin/content/new/?type=<?= AdminView::e($t) ?>">New <?= AdminView::e($t) ?></a>
    <?php endforeach; ?>
</p>

<form class="filters" method="get" action="/admin/content/">
    <label for="f-type">Type</label>
    <select id="f-type" name="type">
        <option value="">All</option>
        <?php foreach (ContentWriter::TYPES as $t): ?>
            <option value="<?= AdminView::e($t) ?>"<?= $type === $t ? ' selected' : '' ?>><?= AdminView::e($t) ?></option>
        <?php endforeach; ?>
    </select>

    <label for="f-status">Status</label>
    <select id="f-status" name="status">
        <option value="">All</option>
        <?php foreach (ContentWriter::STATUSES as $s): ?>
            <option value="<?= AdminView::e($s) ?>"<?= $status === $s ? ' selected' : '' ?>><?= AdminView::e($s) ?></option>
        <?php endforeach; ?>
    </select>

    <label for="f-q">Search</label>
    <input id="f-q" name="q" type="search" value="<?= AdminView::e($q) ?>" placeholder="title or slug">

    <button type="submit">Filter</button>
</form>

<p class="hint"><?= count($items) ?> item(s).</p>

<div class="scroller">
    <table>
        <thead>
        <tr><th>Title</th><th>Type</th><th>Status</th><th>SEO</th><th>Words</th><th>Updated</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($items as $row): ?>
            <tr>
                <td>
                    <a href="/admin/content/edit/?id=<?= (int) $row['id'] ?>"><?= AdminView::e((string) $row['title']) ?></a>
                    <span class="mono">/<?= AdminView::e((string) $row['slug']) ?>/</span>
                    <?php if (!empty($row['category_name'])): ?>
                        <span class="pill"><?= AdminView::e((string) $row['category_name']) ?></span>
                    <?php endif; ?>
                </td>
                <td><?= AdminView::e((string) $row['type']) ?></td>
                <td><span class="pill pill-<?= AdminView::e((string) $row['status']) ?>"><?= AdminView::e((string) $row['status']) ?></span></td>
                <td><?= AdminView::partial('score-badge', ['score' => (int) $row['seo_score']]) ?></td>
                <td><?= (int) $row['word_count'] ?></td>
                <td><?= AdminView::dateTime((string) $row['updated_at']) ?></td>
                <td class="row-actions">
                    <?php if ((string) $row['status'] === 'published'): ?>
                        <a href="/<?= AdminView::e((string) $row['slug']) ?>/" target="_blank" rel="noopener">View</a>
                        <form method="post" action="/admin/content/status/">
                            <input type="hidden" name="_csrf" value="<?= AdminView::e($csrf) ?>">
                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                            <input type="hidden" name="status" value="draft">
                            <button type="submit" class="link">Unpublish</button>
                        </form>
                    <?php else: ?>
                        <a href="/admin/content/preview/?id=<?= (int) $row['id'] ?>" target="_blank" rel="noopener">Preview</a>
                        <form method="post" action="/admin/content/status/">
                            <input type="hidden" name="_csrf" value="<?= AdminView::e($csrf) ?>">
                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                            <input type="hidden" name="status" value="published">
                            <button type="submit" class="link">Publish</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
