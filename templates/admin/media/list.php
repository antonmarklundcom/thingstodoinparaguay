<?php
/**
 * @var array<int,array<string,mixed>> $media
 * @var array<int,int> $usage
 * @var int $limit
 * @var string $back   where to return after an upload (already validated)
 * @var string $csrf
 */
use Ttp\Admin\AdminView;
use Ttp\Uploader;
?>
<h1>Media</h1>

<section class="card">
    <h2>Upload an image</h2>
    <form method="post" action="/admin/media/upload/" enctype="multipart/form-data" class="stack">
        <input type="hidden" name="_csrf" value="<?= AdminView::e($csrf) ?>">
        <?php if ($back !== ''): ?><input type="hidden" name="back" value="<?= AdminView::e($back) ?>"><?php endif; ?>

        <label for="file">Image file</label>
        <input id="file" name="file" type="file" accept="image/jpeg,image/png,image/webp,image/gif" required>

        <label for="alt">Describe the picture (alt text, required)</label>
        <input id="alt" name="alt" type="text" required maxlength="200"
               placeholder="Salto Cristal waterfall seen from the pool below">

        <button type="submit" class="primary">Upload</button>
    </form>
    <p class="hint">
        JPEG, PNG, WebP or GIF, up to <?= (int) round($limit / 1048576) ?> MB. Upload the biggest version you have:
        the site stores <?= implode(', ', Uploader::WIDTHS) ?> px copies in WebP and in the original format, and picks
        the right one per screen. Images are never enlarged.
    </p>
</section>

<?php if ($media === []): ?>
    <p class="hint">Nothing uploaded yet.</p>
<?php else: ?>
    <div class="media-grid">
        <?php foreach ($media as $row): ?>
            <?php $used = (int) ($usage[(int) $row['id']] ?? 0); ?>
            <figure class="media-card">
                <img src="<?= AdminView::e((string) $row['path']) ?>" alt="<?= AdminView::e((string) $row['alt']) ?>" loading="lazy">
                <figcaption>
                    <span class="mono"><?= AdminView::e((string) $row['filename']) ?></span>
                    <span class="hint"><?= (int) $row['width'] ?>×<?= (int) $row['height'] ?> ·
                        <?= count(json_decode((string) $row['sizes_json'], true) ?: []) ?> sizes ·
                        <?= $used > 0 ? 'used on ' . $used . ' page(s)' : 'unused' ?></span>

                    <form method="post" action="/admin/media/alt/" class="inline-form">
                        <input type="hidden" name="_csrf" value="<?= AdminView::e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                        <label class="sr-only" for="alt-<?= (int) $row['id'] ?>">Alt text</label>
                        <input id="alt-<?= (int) $row['id'] ?>" name="alt" type="text" value="<?= AdminView::e((string) $row['alt']) ?>" required>
                        <button type="submit" class="link">Save alt</button>
                    </form>

                    <form method="post" action="/admin/media/delete/">
                        <input type="hidden" name="_csrf" value="<?= AdminView::e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                        <button type="submit" class="link danger"
                                onclick="return confirm('Delete this image and every size of it?')">Delete</button>
                    </form>
                </figcaption>
            </figure>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
