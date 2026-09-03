<?php
/**
 * FAQ accordion — native <details>/<summary>, so it opens/closes with no JS
 * and stays crawlable/expandable by assistive tech and search engines alike.
 * A question whose answer is still missing (plan §5.1 fallback import,
 * filled in by S4) renders as an open question rather than an empty answer,
 * so the gap is visible instead of silently swallowed.
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
    <ul class="faq-list">
        <?php foreach ($faq as $i => $row): ?>
            <?php $answer = trim((string) ($row['a'] ?? '')); ?>
            <li class="faq-item">
                <details<?= $i === 0 ? ' open' : '' ?>>
                    <summary><?= View::e((string) $row['q']) ?></summary>
                    <div class="faq-answer">
                        <?php if ($answer !== ''): ?>
                            <?= Markdown::toHtml($answer) ?>
                        <?php else: ?>
                            <p><a href="/contact/">Ask us this question</a> — we answer within a day.</p>
                        <?php endif; ?>
                    </div>
                </details>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
