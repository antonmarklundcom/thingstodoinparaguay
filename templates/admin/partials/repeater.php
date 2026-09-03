<?php
/**
 * A repeatable group of rows — itinerary steps, reasons, facts, FAQ entries.
 *
 * @var string $name
 * @var string $legend
 * @var array<string,string> $fields    field key => label
 * @var array<int,array<string,mixed>> $rows
 * @var array<string,array{0:string,1:string}> $extra  a single input above the rows
 */
use Ttp\Admin\AdminView;

$extra ??= [];
$blank = [];
foreach (array_keys($fields) as $key) {
    $blank[$key] = '';
}
$all = $rows === [] ? [$blank] : $rows;
?>
<section class="card repeater" data-repeater="<?= AdminView::e($name) ?>">
    <h2><?= AdminView::e($legend) ?></h2>

    <?php foreach ($extra as $key => $spec): ?>
        <label for="<?= AdminView::e($key) ?>"><?= AdminView::e($spec[0]) ?></label>
        <input id="<?= AdminView::e($key) ?>" name="<?= AdminView::e($key) ?>" type="text" value="<?= AdminView::e($spec[1]) ?>">
    <?php endforeach; ?>

    <div data-repeater-rows>
        <?php foreach (array_values($all) as $i => $row): ?>
            <div class="repeater-row" data-repeater-row>
                <?php foreach ($fields as $key => $label): ?>
                    <label>
                        <span><?= AdminView::e($label) ?></span>
                        <?php if ($key === 'body' || $key === 'a'): ?>
                            <textarea name="<?= AdminView::e($name) ?>[<?= $i ?>][<?= AdminView::e($key) ?>]" rows="3"><?= AdminView::e((string) ($row[$key] ?? '')) ?></textarea>
                        <?php else: ?>
                            <input type="text" name="<?= AdminView::e($name) ?>[<?= $i ?>][<?= AdminView::e($key) ?>]" value="<?= AdminView::e((string) ($row[$key] ?? '')) ?>">
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
                <button type="button" class="link danger" data-repeater-remove>Remove</button>
            </div>
        <?php endforeach; ?>
    </div>

    <button type="button" data-repeater-add>Add another</button>
    <p class="hint">Rows left completely blank are dropped when you save.</p>
</section>
