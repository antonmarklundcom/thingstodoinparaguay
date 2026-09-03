<?php
/**
 * @var int    $page
 * @var int    $pages
 * @var string $basePath  e.g. /blog/ — page N lives at {basePath}page/N/
 */

use Ttp\View;

if ($pages < 2) {
    return;
}
$url = static fn (int $n): string => $n <= 1 ? $basePath : $basePath . 'page/' . $n . '/';
?>
<nav class="pagination" aria-label="Pagination">
    <ul>
        <?php if ($page > 1): ?>
            <li><a rel="prev" href="<?= View::e($url($page - 1)) ?>">Previous</a></li>
        <?php endif; ?>
        <?php for ($n = 1; $n <= $pages; $n++): ?>
            <li>
                <?php if ($n === $page): ?>
                    <span aria-current="page"><?= $n ?></span>
                <?php else: ?>
                    <a href="<?= View::e($url($n)) ?>"><?= $n ?></a>
                <?php endif; ?>
            </li>
        <?php endfor; ?>
        <?php if ($page < $pages): ?>
            <li><a rel="next" href="<?= View::e($url($page + 1)) ?>">Next</a></li>
        <?php endif; ?>
    </ul>
</nav>
