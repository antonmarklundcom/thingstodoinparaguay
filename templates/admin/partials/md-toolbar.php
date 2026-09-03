<?php
/** @var string $target */
use Ttp\Admin\AdminView;

$buttons = [
    ['label' => 'H2',      'title' => 'Subheading',   'action' => 'prefix', 'value' => '## '],
    ['label' => 'H3',      'title' => 'Sub-subheading','action' => 'prefix','value' => '### '],
    ['label' => 'B',       'title' => 'Bold',          'action' => 'wrap',  'value' => '**'],
    ['label' => 'I',       'title' => 'Italic',        'action' => 'wrap',  'value' => '*'],
    ['label' => 'Link',    'title' => 'Link',          'action' => 'link',  'value' => ''],
    ['label' => 'List',    'title' => 'Bulleted list', 'action' => 'prefix','value' => '- '],
    ['label' => 'Quote',   'title' => 'Quote',         'action' => 'prefix','value' => '> '],
];
?>
<div class="md-bar" role="toolbar" aria-label="Formatting" data-md-bar="<?= AdminView::e($target) ?>">
    <?php foreach ($buttons as $button): ?>
        <button type="button" title="<?= AdminView::e($button['title']) ?>"
                data-md-action="<?= AdminView::e($button['action']) ?>"
                data-md-value="<?= AdminView::e($button['value']) ?>"><?= AdminView::e($button['label']) ?></button>
    <?php endforeach; ?>
    <button type="button" data-md-action="preview" aria-pressed="false">Preview</button>
</div>
