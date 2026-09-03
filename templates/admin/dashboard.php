<?php
/**
 * @var array<string,array<string,int>> $counts
 * @var array<int,array<string,mixed>> $recent
 * @var array<int,array<string,mixed>> $weakest
 * @var array<int,array<string,mixed>> $scheduled
 * @var int $leadCount @var int $subCount @var int $mediaCount @var int $cacheCount
 * @var string $csrf
 */
use Ttp\Admin\AdminView;
?>
<h1>Dashboard</h1>

<p class="actions">
    <a class="button primary" href="/admin/content/new/?type=post">Write a post</a>
    <a class="button" href="/admin/content/new/?type=tour">Add a tour</a>
    <a class="button" href="/admin/media/">Upload an image</a>
</p>

<div class="tiles">
    <?php foreach ($counts as $type => $count): ?>
        <a class="tile" href="/admin/content/?type=<?= AdminView::e($type) ?>">
            <span class="tile-n"><?= (int) $count['published'] ?></span>
            <span class="tile-label"><?= AdminView::e($type) ?>s published</span>
            <?php if ($count['draft'] + $count['scheduled'] > 0): ?>
                <span class="tile-sub"><?= (int) $count['draft'] ?> draft, <?= (int) $count['scheduled'] ?> scheduled</span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
    <a class="tile" href="/admin/media/">
        <span class="tile-n"><?= $mediaCount ?></span><span class="tile-label">images</span>
    </a>
    <a class="tile" href="/admin/leads/">
        <span class="tile-n"><?= $leadCount ?></span><span class="tile-label">leads</span>
    </a>
    <a class="tile" href="/admin/subscribers/">
        <span class="tile-n"><?= $subCount ?></span><span class="tile-label">subscribers</span>
    </a>
</div>

<?php if ($scheduled !== []): ?>
    <section class="card">
        <h2>Waiting to go live</h2>
        <ul class="plain">
            <?php foreach ($scheduled as $row): ?>
                <li>
                    <a href="/admin/content/edit/?id=<?= (int) $row['id'] ?>"><?= AdminView::e((string) $row['title']) ?></a>
                    — <?= AdminView::dateTime((string) $row['published_at']) ?> UTC
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<section class="card">
    <h2>Recently edited</h2>
    <div class="scroller">
        <table>
            <thead><tr><th>Title</th><th>Type</th><th>Status</th><th>SEO</th><th>Updated</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $row): ?>
                <tr>
                    <td><a href="/admin/content/edit/?id=<?= (int) $row['id'] ?>"><?= AdminView::e((string) $row['title']) ?></a></td>
                    <td><?= AdminView::e((string) $row['type']) ?></td>
                    <td><span class="pill pill-<?= AdminView::e((string) $row['status']) ?>"><?= AdminView::e((string) $row['status']) ?></span></td>
                    <td><?= AdminView::partial('score-badge', ['score' => (int) $row['seo_score']]) ?></td>
                    <td><?= AdminView::dateTime((string) $row['updated_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($weakest !== []): ?>
    <section class="card">
        <h2>Weakest published pages</h2>
        <p class="hint">Open one and work down its SEO checklist — the editor explains every red line.</p>
        <ul class="plain">
            <?php foreach ($weakest as $row): ?>
                <li>
                    <?= AdminView::partial('score-badge', ['score' => (int) $row['seo_score']]) ?>
                    <a href="/admin/content/edit/?id=<?= (int) $row['id'] ?>"><?= AdminView::e((string) $row['title']) ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<section class="card">
    <h2>Maintenance</h2>
    <p>The site keeps <?= $cacheCount ?> page(s) in its HTML cache. Publishing clears what it needs to; this button clears the lot.</p>
    <form method="post" action="/admin/cache/clear/">
        <input type="hidden" name="_csrf" value="<?= AdminView::e($csrf) ?>">
        <input type="hidden" name="back" value="/admin/">
        <button type="submit">Clear the page cache</button>
    </form>
</section>
