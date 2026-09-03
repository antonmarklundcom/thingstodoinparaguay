<?php
declare(strict_types=1);

/**
 * Prints the SEO score (src/SeoScore.php — the same class the admin editor uses)
 * for every published item, worst first.
 *
 * Usage:
 *   php bin/seo-audit.php                    score every published item
 *   php bin/seo-audit.php --all              include drafts and scheduled items
 *   php bin/seo-audit.php --type=post
 *   php bin/seo-audit.php --details          list the failing checks per item
 *   php bin/seo-audit.php --min=80           exit 1 if anything scores below 80
 *   php bin/seo-audit.php --strict           --min=80 plus "no Lorem Ipsum anywhere"
 *   php bin/seo-audit.php --write            store each score in content_items.seo_score
 *
 * `--strict` is phase S4's exit gate (plan §6.2). It fails today on purpose: the
 * 33 imported posts still carry the old site's Lorem Ipsum.
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

use Ttp\Db;
use Ttp\Repo\ContentRepo;
use Ttp\SeoScore;

$opts = getopt('', ['db::', 'type::', 'min::', 'all', 'details', 'strict', 'write', 'quiet']);
if (!empty($opts['db'])) {
    Db::use((string) $opts['db']);
}
$strict  = isset($opts['strict']);
$min     = isset($opts['min']) ? (int) $opts['min'] : ($strict ? 80 : 0);
$details = isset($opts['details']);
$quiet   = isset($opts['quiet']);

if (!Db::exists() || !Db::hasTable('content_items')) {
    fwrite(STDERR, "seo-audit: no database at " . Db::path() . " — run bin/migrate.php && bin/seed.php\n");
    exit(1);
}

$where  = isset($opts['all']) ? '1 = 1' : "status = 'published'";
$params = [];
if (!empty($opts['type'])) {
    $where   .= ' AND type = ?';
    $params[] = (string) $opts['type'];
}

$rows = Db::all(
    "SELECT id FROM content_items WHERE {$where} ORDER BY type, slug",
    $params
);

$results = [];
$lorem   = [];
foreach ($rows as $row) {
    $item = ContentRepo::findById((int) $row['id']);
    if ($item === null) {
        continue;
    }
    $itemDetails = in_array((string) $item['type'], ['tour', 'service'], true)
        ? ContentRepo::details((int) $item['id'])
        : null;
    $result = SeoScore::forItem($item, $itemDetails);

    if (isset($opts['write'])) {
        Db::run('UPDATE content_items SET seo_score = ? WHERE id = ?', [$result->score, (int) $item['id']]);
    }
    foreach ($result->checks as $check) {
        if ($check['id'] === 'no_lorem' && !$check['passed']) {
            $lorem[] = (string) $item['path'];
        }
    }
    $results[] = ['item' => $item, 'result' => $result];
}

usort($results, static fn (array $a, array $b): int => $a['result']->score <=> $b['result']->score);

$total = 0;
foreach ($results as $entry) {
    /** @var SeoScore $result */
    $result = $entry['result'];
    $item   = $entry['item'];
    $total += $result->score;

    if (!$quiet) {
        printf(
            "%3d  %-11s %-12s %-48s %d words\n",
            $result->score,
            $result->grade(),
            $item['type'],
            (string) $item['path'],
            $result->wordCount
        );
        if ($details) {
            foreach ($result->failing() as $check) {
                // Pad by characters, not bytes: several labels contain an en dash.
                $label = (string) $check['label'];
                $pad   = str_repeat(' ', max(1, 38 - mb_strlen($label)));
                echo '       - ' . $label . $pad . $check['advice'] . "\n";
            }
        }
    }
}

$count   = count($results);
$average = $count === 0 ? 0 : (int) round($total / $count);
$below   = array_values(array_filter($results, static fn (array $e): bool => $e['result']->score < $min));

printf("\nseo-audit: %d item(s), average %d/100", $count, $average);
if ($min > 0) {
    printf(", %d below %d", count($below), $min);
}
printf(", %d with Lorem Ipsum\n", count($lorem));

$failed = false;
if ($min > 0 && $below !== []) {
    echo "\nBelow the " . $min . "-point bar:\n";
    foreach ($below as $entry) {
        printf("  ✗ %3d  %s\n", $entry['result']->score, (string) $entry['item']['path']);
    }
    $failed = true;
}
if ($strict && $lorem !== []) {
    echo "\nStill contains Lorem Ipsum:\n";
    foreach ($lorem as $path) {
        echo "  ✗ {$path}\n";
    }
    $failed = true;
}

exit($failed ? 1 : 0);
