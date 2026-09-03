<?php
/** @var string $title @var string $detail */
use Ttp\Admin\AdminView;
?>
<div class="card card-narrow">
    <h1><?= AdminView::e($title) ?></h1>
    <p><?= AdminView::e($detail) ?></p>
    <p><a href="/admin/">Back to the dashboard</a></p>
</div>
