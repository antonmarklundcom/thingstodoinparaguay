<?php
/** @var array<int,array<string,mixed>> $subscribers @var int $total */
use Ttp\Admin\AdminView;
?>
<h1>Subscribers</h1>
<p class="hint"><?= $total ?> in total<?= $total > 500 ? ' — the 500 most recent are shown' : '' ?>. <a href="/admin/subscribers/export.csv">Download all as CSV</a>.</p>

<?php if ($subscribers === []): ?>
    <p class="hint">Nobody has signed up yet. The newsletter form arrives in phase S3.</p>
<?php else: ?>
    <div class="scroller">
        <table>
            <thead><tr><th>When</th><th>Email</th><th>Source</th></tr></thead>
            <tbody>
            <?php foreach ($subscribers as $row): ?>
                <tr>
                    <td><?= AdminView::dateTime((string) $row['created_at']) ?></td>
                    <td><?= AdminView::e((string) $row['email']) ?></td>
                    <td><?= AdminView::e((string) $row['source']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
