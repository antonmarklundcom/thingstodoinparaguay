<?php
/** @var array<int,array<string,mixed>> $tags @var string $csrf */
use Ttp\Admin\AdminView;
?>
<h1>Tags</h1>
<p class="hint">Tags are stored on posts but have no archive pages (plan §1.4) — they are there for later curation.</p>

<?php if ($tags === []): ?>
    <p class="hint">No tags yet.</p>
<?php else: ?>
<div class="scroller">
    <table>
        <thead><tr><th>Name</th><th>Slug</th><th>Used on</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($tags as $row): ?>
            <tr>
                <td>
                    <form method="post" action="/admin/tags/save/" class="inline-form">
                        <input type="hidden" name="_csrf" value="<?= AdminView::e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                        <label class="sr-only" for="tag-<?= (int) $row['id'] ?>">Tag name</label>
                        <input id="tag-<?= (int) $row['id'] ?>" name="name" type="text" value="<?= AdminView::e((string) $row['name']) ?>" required>
                        <button type="submit" class="link">Rename</button>
                    </form>
                </td>
                <td class="mono"><?= AdminView::e((string) $row['slug']) ?></td>
                <td><?= (int) $row['use_count'] ?> post(s)</td>
                <td>
                    <form method="post" action="/admin/tags/delete/">
                        <input type="hidden" name="_csrf" value="<?= AdminView::e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                        <button type="submit" class="link danger" onclick="return confirm('Delete this tag?')">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
