<?php
/** @var int $score */
$band = $score >= 90 ? 'a' : ($score >= 80 ? 'b' : ($score >= 60 ? 'c' : 'd'));
?>
<span class="score score-<?= $band ?>" title="SEO score"><?= (int) $score ?></span>
