<?php
/** @var array<int,array<string,mixed>> $leads @var int $total */
use Ttp\Admin\AdminView;
?>
<h1>Leads</h1>
<p class="hint"><?= $total ?> in total<?= $total > 500 ? ' — the 500 most recent are shown' : '' ?>. <a href="/admin/leads/export.csv">Download all as CSV</a>.</p>

<?php if ($leads === []): ?>
    <p class="hint">No enquiries yet. The contact form arrives in phase S3.</p>
<?php else: ?>
    <div class="scroller">
        <table>
            <thead><tr><th>When</th><th>Name</th><th>Email</th><th>Phone</th><th>Message</th><th>Page</th><th>Sent on</th></tr></thead>
            <tbody>
            <?php foreach ($leads as $row): ?>
                <tr>
                    <td><?= AdminView::dateTime((string) $row['created_at']) ?></td>
                    <td><?= AdminView::e((string) $row['name']) ?></td>
                    <td><a href="mailto:<?= AdminView::e((string) $row['email']) ?>"><?= AdminView::e((string) $row['email']) ?></a></td>
                    <td><?= AdminView::e((string) $row['phone']) ?></td>
                    <td class="wrap"><?= AdminView::e((string) $row['message']) ?></td>
                    <td class="mono"><?= AdminView::e((string) $row['page_path']) ?></td>
                    <td><?= ((int) $row['forwarded']) === 1 ? 'yes' : 'no' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
