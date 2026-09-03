<?php
/** @var array<int,array<string,mixed>> $categories @var array<string,mixed>|null $edit @var string $csrf */
use Ttp\Admin\AdminView;
?>
<h1>Categories</h1>

<section class="card">
    <h2><?= $edit === null ? 'Add a category' : 'Edit ' . AdminView::e((string) $edit['name']) ?></h2>
    <form method="post" action="/admin/categories/save/" class="stack">
        <input type="hidden" name="_csrf" value="<?= AdminView::e($csrf) ?>">
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">

        <label for="name">Name</label>
        <input id="name" name="name" type="text" required value="<?= AdminView::e((string) ($edit['name'] ?? '')) ?>">

        <label for="slug">Address — /category/<span class="mono">slug</span>/</label>
        <input id="slug" name="slug" type="text" value="<?= AdminView::e((string) ($edit['slug'] ?? '')) ?>">

        <label for="description">Description</label>
        <textarea id="description" name="description" rows="3"><?= AdminView::e((string) ($edit['description'] ?? '')) ?></textarea>

        <label for="meta_title">Search title</label>
        <input id="meta_title" name="meta_title" type="text" value="<?= AdminView::e((string) ($edit['meta_title'] ?? '')) ?>">

        <label for="meta_description">Search description</label>
        <textarea id="meta_description" name="meta_description" rows="2"><?= AdminView::e((string) ($edit['meta_description'] ?? '')) ?></textarea>

        <label for="sort_order">Sort order</label>
        <input id="sort_order" name="sort_order" type="number" value="<?= (int) ($edit['sort_order'] ?? 0) ?>">

        <button type="submit" class="primary"><?= $edit === null ? 'Add' : 'Save' ?></button>
        <?php if ($edit !== null): ?><a href="/admin/categories/">Cancel</a><?php endif; ?>
    </form>
</section>

<div class="scroller">
    <table>
        <thead><tr><th>Name</th><th>Address</th><th>Posts</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($categories as $row): ?>
            <tr>
                <td><?= AdminView::e((string) $row['name']) ?></td>
                <td class="mono">/category/<?= AdminView::e((string) $row['slug']) ?>/</td>
                <td><?= (int) $row['post_count'] ?></td>
                <td class="row-actions">
                    <a href="/admin/categories/?edit=<?= (int) $row['id'] ?>">Edit</a>
                    <form method="post" action="/admin/categories/delete/">
                        <input type="hidden" name="_csrf" value="<?= AdminView::e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                        <button type="submit" class="link danger"
                                onclick="return confirm('Delete this category? Its posts stay, and the archive address will redirect to the blog.')">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
