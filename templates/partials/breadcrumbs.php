<?php
/** @var array<int,array{name:string,path:?string}> $breadcrumbs */

use Ttp\View;

$trail = array_merge([['name' => 'Home', 'path' => '/']], $breadcrumbs);
$last  = count($trail) - 1;
?>
<nav class="breadcrumbs" aria-label="Breadcrumb">
    <ol>
        <?php foreach ($trail as $i => $crumb): ?>
            <li>
                <?php if (!empty($crumb['path']) && $i !== $last): ?>
                    <a href="<?= View::e((string) $crumb['path']) ?>"><?= View::e((string) $crumb['name']) ?></a>
                <?php else: ?>
                    <span aria-current="page"><?= View::e((string) $crumb['name']) ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
