<?php
/**
 * The editor. Five steps to a published post, in the order they appear:
 * 1 title  2 body  3 cover image  4 focus keyword + search snippet  5 Publish.
 *
 * @var array<string,mixed>|null $item
 * @var string $type
 * @var array<string,mixed> $draft
 * @var array<string,mixed>|null $details
 * @var array<int,array<string,mixed>> $categories
 * @var array<int,array{slug:string,name:string}> $tags
 * @var array<int,array<string,mixed>> $media
 * @var array<string,mixed>|null $cover
 * @var Ttp\SeoScore $score
 * @var array<string,string> $errors
 * @var string $csrf
 */

use Ttp\Admin\AdminView;
use Ttp\Admin\ContentWriter;

$structured = in_array($type, ['tour', 'service'], true);
$id         = (int) ($draft['id'] ?? 0);
$value      = static fn (string $key, string $default = ''): string => (string) ($draft[$key] ?? $default);
$rowsOf     = static function (mixed $raw): array {
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
    return is_array($raw) ? $raw : [];
};

// Repeatable sections come either from the saved tour_details row or, after a
// validation failure, straight back from the submitted form.
$sections = [
    'itinerary' => $rowsOf($draft['itinerary'] ?? ($details['itinerary'] ?? [])),
    'why'       => $rowsOf($draft['why']       ?? ($details['why'] ?? [])),
    'practical' => $rowsOf($draft['practical'] ?? ($details['practical'] ?? [])),
    'faq'       => $rowsOf($draft['faq']       ?? ($details['faq'] ?? [])),
];
$detail = static fn (string $key, string $default = ''): string =>
    (string) ($draft[$key] ?? ($details[$key . '_md'] ?? ($details[$key] ?? $default)));
?>
<form method="post" action="/admin/content/save/" class="editor" id="editor" data-score-url="/admin/api/score/" data-preview-url="/admin/api/preview/">
    <input type="hidden" name="_csrf" value="<?= AdminView::e($csrf) ?>">
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="type" value="<?= AdminView::e($type) ?>">

    <div class="editor-head">
        <h1><?= $id === 0 ? 'New ' . AdminView::e($type) : 'Edit ' . AdminView::e($type) ?></h1>
        <div class="editor-head-actions">
            <?php if ($id > 0): ?>
                <a class="button" href="/admin/content/preview/?id=<?= $id ?>" target="_blank" rel="noopener">Preview</a>
            <?php endif; ?>
            <button type="submit" class="primary">Save</button>
        </div>
    </div>

    <div class="editor-grid">
        <div class="editor-main">

            <!-- 1. Title ------------------------------------------------->
            <section class="card">
                <h2><span class="step">1</span> Title and address</h2>

                <label for="title">Title</label>
                <input id="title" name="title" type="text" required maxlength="180"
                       value="<?= AdminView::e($value('title')) ?>"
                       data-slug-source data-count-for="counter-title">
                <?php if (isset($errors['title'])): ?><p class="field-error"><?= AdminView::e($errors['title']) ?></p><?php endif; ?>
                <p class="hint"><span id="counter-title">0</span> characters. Aim for 30–60 including the site name.</p>

                <label for="slug">Address (slug)</label>
                <div class="slug-row">
                    <span class="mono">/</span>
                    <input id="slug" name="slug" type="text" pattern="[a-z0-9][a-z0-9-]*"
                           value="<?= AdminView::e($value('slug')) ?>" data-slug-target
                           data-locked="<?= $id > 0 && $value('status') === 'published' ? '1' : '0' ?>">
                    <span class="mono">/</span>
                </div>
                <?php if (isset($errors['slug'])): ?><p class="field-error"><?= AdminView::e($errors['slug']) ?></p><?php endif; ?>
                <?php if ($id > 0 && $value('status') === 'published'): ?>
                    <p class="hint warn">This page is live. Changing the address leaves a permanent redirect behind so the old link keeps working — but only change it if you must.</p>
                <?php else: ?>
                    <p class="hint">Filled in from the title. Lower-case letters, numbers and hyphens.</p>
                <?php endif; ?>
            </section>

            <!-- 2. Body -------------------------------------------------->
            <section class="card">
                <h2><span class="step">2</span> <?= $structured ? 'Introduction' : 'The article' ?></h2>

                <?php if ($structured): ?>
                    <label for="hook">Hook — the problem the traveller has</label>
                    <textarea id="hook" name="hook" rows="4" data-md><?= AdminView::e($detail('hook')) ?></textarea>

                    <label for="solution">What you offer</label>
                    <textarea id="solution" name="solution" rows="4" data-md><?= AdminView::e($detail('solution')) ?></textarea>
                <?php endif; ?>

                <label for="body_md"><?= $structured ? 'Extra copy (optional)' : 'Body' ?></label>
                <?= AdminView::partial('md-toolbar', ['target' => 'body_md']) ?>
                <textarea id="body_md" name="body_md" rows="<?= $structured ? 10 : 24 ?>" data-md data-body><?= AdminView::e($value('body_md')) ?></textarea>
                <div class="md-preview" id="body_md-preview" hidden></div>
                <p class="hint">Markdown: <code>## </code> for a subheading, <code>**bold**</code>, <code>[text](/about/)</code> for a link.</p>
            </section>

            <?php if ($structured): ?>
                <?= AdminView::partial('repeater', [
                    'name'   => 'itinerary',
                    'legend' => 'What the day looks like',
                    'fields' => ['title' => 'Step', 'body' => 'What happens'],
                    'rows'   => $sections['itinerary'],
                    'extra'  => ['itinerary_label' => ['Section heading', $detail('itinerary_label')]],
                ]) ?>
                <?= AdminView::partial('repeater', [
                    'name'   => 'why',
                    'legend' => 'Why book this with us',
                    'fields' => ['title' => 'Reason', 'body' => 'Detail'],
                    'rows'   => $sections['why'],
                ]) ?>
                <?= AdminView::partial('repeater', [
                    'name'   => 'practical',
                    'legend' => 'Practical information',
                    'fields' => ['label' => 'Label', 'value' => 'Value'],
                    'rows'   => $sections['practical'],
                ]) ?>
                <?= AdminView::partial('repeater', [
                    'name'   => 'faq',
                    'legend' => 'Frequently asked questions',
                    'fields' => ['q' => 'Question', 'a' => 'Answer'],
                    'rows'   => $sections['faq'],
                ]) ?>

                <section class="card">
                    <h2>Closing</h2>
                    <label for="closing">Closing paragraph</label>
                    <textarea id="closing" name="closing" rows="4" data-md><?= AdminView::e($detail('closing')) ?></textarea>

                    <label for="cta_text">Button text</label>
                    <input id="cta_text" name="cta_text" type="text" value="<?= AdminView::e($detail('cta_text')) ?>" placeholder="Ask for a quote">
                </section>

                <section class="card">
                    <h2>The facts</h2>
                    <div class="pair">
                        <div>
                            <label for="price_usd">Price in USD</label>
                            <input id="price_usd" name="price_usd" type="number" min="0" step="1"
                                   value="<?= AdminView::e((string) ($draft['price_usd'] ?? ($details['price_usd'] ?? ''))) ?>">
                            <p class="hint">Leave empty for “Ask for a quote”. Never invent a price.</p>
                        </div>
                        <div>
                            <label for="duration">Duration</label>
                            <input id="duration" name="duration" type="text" value="<?= AdminView::e($detail('duration')) ?>">
                        </div>
                        <div>
                            <label for="departure">Departure</label>
                            <input id="departure" name="departure" type="text" value="<?= AdminView::e($detail('departure')) ?>">
                        </div>
                        <div>
                            <label for="transport">Transport</label>
                            <input id="transport" name="transport" type="text" value="<?= AdminView::e($detail('transport')) ?>">
                        </div>
                        <div>
                            <label for="requirements">Requirements</label>
                            <input id="requirements" name="requirements" type="text" value="<?= AdminView::e($detail('requirements')) ?>">
                        </div>
                        <div>
                            <label for="tagline">Tagline</label>
                            <input id="tagline" name="tagline" type="text" value="<?= AdminView::e($detail('tagline')) ?>">
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <!-- 4. Search appearance ------------------------------------->
            <section class="card">
                <h2><span class="step">4</span> How it looks in Google</h2>

                <label for="focus_keyword">Focus keyword</label>
                <input id="focus_keyword" name="focus_keyword" type="text"
                       value="<?= AdminView::e($value('focus_keyword')) ?>"
                       placeholder="salto cristal tour">
                <p class="hint">The phrase you want this page to be found for. One phrase, the way a visitor would type it.</p>

                <label for="meta_title">Search title</label>
                <input id="meta_title" name="meta_title" type="text" maxlength="120"
                       value="<?= AdminView::e($value('meta_title')) ?>" data-count-for="counter-meta-title">
                <p class="hint"><span id="counter-meta-title">0</span>/60 characters. Empty means the title above is used.</p>

                <label for="meta_description">Search description</label>
                <textarea id="meta_description" name="meta_description" rows="3" maxlength="320"
                          data-count-for="counter-meta-desc"><?= AdminView::e($value('meta_description')) ?></textarea>
                <p class="hint"><span id="counter-meta-desc">0</span>/155 characters. Empty means the summary below is used.</p>

                <label for="excerpt">Summary</label>
                <textarea id="excerpt" name="excerpt" rows="2"><?= AdminView::e($value('excerpt')) ?></textarea>
                <p class="hint">Shown on the blog index and used as the fallback description.</p>
            </section>
        </div>

        <aside class="editor-side">
            <!-- 5. Publish ------------------------------------------------>
            <section class="card">
                <h2><span class="step">5</span> Publish</h2>

                <label for="status">Status</label>
                <select id="status" name="status" data-status>
                    <?php foreach (ContentWriter::STATUSES as $s): ?>
                        <option value="<?= AdminView::e($s) ?>"<?= $value('status', 'draft') === $s ? ' selected' : '' ?>><?= AdminView::e($s) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['status'])): ?><p class="field-error"><?= AdminView::e($errors['status']) ?></p><?php endif; ?>

                <label for="published_at">Publish date (UTC)</label>
                <input id="published_at" name="published_at" type="datetime-local"
                       value="<?= AdminView::e(AdminView::dateTimeLocal($value('published_at'))) ?>">
                <?php if (isset($errors['published_at'])): ?><p class="field-error"><?= AdminView::e($errors['published_at']) ?></p><?php endif; ?>
                <p class="hint">Set a future date and choose “scheduled” to publish later.</p>

                <button type="submit" class="primary wide">Save</button>

                <?php if ($id > 0): ?>
                    <p class="danger-zone">
                        <a href="/admin/content/edit/?id=<?= $id ?>">Reload</a> ·
                        <button type="submit" form="delete-<?= $id ?>" class="link danger"
                                onclick="return confirm('Delete this permanently? Its address will 301 to the index instead.')">Delete</button>
                    </p>
                <?php endif; ?>
            </section>

            <!-- SEO score ------------------------------------------------>
            <section class="card" id="seo-panel" data-score="<?= $score->score ?>">
                <h2>SEO score</h2>
                <p class="score-big"><span data-score-value><?= $score->score ?></span><span class="score-max">/100</span></p>
                <p class="hint" data-score-grade><?= AdminView::e($score->grade()) ?> · <?= $score->wordCount ?> words</p>
                <ul class="checks" data-score-list>
                    <?php foreach ($score->checks as $check): ?>
                        <li class="<?= $check['passed'] ? 'pass' : 'fail' ?>">
                            <strong><?= AdminView::e((string) $check['label']) ?></strong>
                            <?php if (!$check['passed']): ?><span><?= AdminView::e((string) $check['advice']) ?></span><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <!-- 3. Cover image ------------------------------------------->
            <section class="card">
                <h2><span class="step">3</span> Cover image</h2>
                <div class="cover-preview" data-cover-preview>
                    <?php if ($cover !== null): ?>
                        <img src="<?= AdminView::e((string) $cover['path']) ?>" alt="<?= AdminView::e((string) $cover['alt']) ?>" width="200">
                    <?php endif; ?>
                </div>
                <label for="cover_media_id">Choose from the library</label>
                <select id="cover_media_id" name="cover_media_id" data-cover-select>
                    <option value="">— none —</option>
                    <?php foreach ($media as $row): ?>
                        <option value="<?= (int) $row['id'] ?>"
                                data-path="<?= AdminView::e((string) $row['path']) ?>"
                            <?= (int) ($draft['cover_media_id'] ?? 0) === (int) $row['id'] ? ' selected' : '' ?>>
                            <?= AdminView::e((string) $row['filename']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="hint"><a href="/admin/media/?back=<?= rawurlencode('/admin/content/' . ($id > 0 ? 'edit/?id=' . $id : 'new/?type=' . $type)) ?>">Upload a new image</a> — you will come back here.</p>

                <label for="og_image_media_id">Social share image (optional)</label>
                <select id="og_image_media_id" name="og_image_media_id">
                    <option value="">— same as the cover —</option>
                    <?php foreach ($media as $row): ?>
                        <option value="<?= (int) $row['id'] ?>"<?= (int) ($draft['og_image_media_id'] ?? 0) === (int) $row['id'] ? ' selected' : '' ?>>
                            <?= AdminView::e((string) $row['filename']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </section>

            <?php if ($type === 'post'): ?>
                <section class="card">
                    <h2>Filing</h2>
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id">
                        <option value="">— none —</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>"<?= (int) ($draft['category_id'] ?? 0) === (int) $category['id'] ? ' selected' : '' ?>>
                                <?= AdminView::e((string) $category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="tags">Tags</label>
                    <input id="tags" name="tags" type="text"
                           value="<?= AdminView::e(is_string($draft['tags'] ?? null) ? (string) $draft['tags'] : implode(', ', array_column($tags, 'name'))) ?>">
                    <p class="hint">Comma separated. Tags are stored but have no archive pages.</p>
                </section>
            <?php endif; ?>

            <section class="card">
                <h2>Advanced</h2>
                <label class="check">
                    <input type="checkbox" name="noindex" value="1"<?= (int) ($draft['noindex'] ?? 0) === 1 ? ' checked' : '' ?>>
                    Keep this page out of Google
                </label>

                <label for="canonical_override">Canonical URL override</label>
                <input id="canonical_override" name="canonical_override" type="text"
                       value="<?= AdminView::e($value('canonical_override')) ?>" placeholder="/another-page/">

                <label for="sort_order">Sort order</label>
                <input id="sort_order" name="sort_order" type="number" value="<?= (int) ($draft['sort_order'] ?? 0) ?>">
                <p class="hint">Lower comes first on the tours and services indexes.</p>
            </section>
        </aside>
    </div>
</form>

<?php if ($id > 0): ?>
    <form id="delete-<?= $id ?>" method="post" action="/admin/content/delete/" hidden>
        <input type="hidden" name="_csrf" value="<?= AdminView::e($csrf) ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
    </form>
<?php endif; ?>
