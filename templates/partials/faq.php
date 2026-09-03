<?php
/**
 * FAQ list. Questions whose answer is still missing (plan §5.1 fallback import,
 * filled in by S4) render as an open question rather than an empty answer, so
 * the gap is visible instead of silently swallowed.
 *
 * @var array<int,array<string,mixed>> $faq
 */

use Ttp\Markdown;
use Ttp\View;

$faq = array_values(array_filter($faq, static fn ($row): bool => trim((string) ($row['q'] ?? '')) !== ''));
if ($faq === []) {
    return;
}
?>
<section class="faq" aria-labelledby="faq-heading">
    <h2 id="faq-heading">Frequently asked questions</h2>
    <dl>
        <?php foreach ($faq as $row): ?>
            <?php $answer = trim((string) ($row['a'] ?? '')); ?>
            <dt><?= View::e((string) $row['q']) ?></dt>
            <dd>
                <?php if ($answer !== ''): ?>
                    <?= Markdown::toHtml($answer) ?>
                <?php else: ?>
                    <p><a href="/contact/">Ask us this question</a> — we answer within a day.</p>
                <?php endif; ?>
            </dd>
        <?php endforeach; ?>
    </dl>
</section>
