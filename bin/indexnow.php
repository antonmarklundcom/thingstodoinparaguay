<?php
declare(strict_types=1);

/**
 * Submit every sitemap URL to IndexNow. Run once after launch or a big content push:
 *   php bin/indexnow.php
 * Needs INDEXNOW_KEY and a non-dev APP_ENV.
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

use Ttp\IndexNow;
use Ttp\Sitemap;

if (!IndexNow::enabled()) {
    fwrite(STDERR, "indexnow: set INDEXNOW_KEY (8-128 lowercase letters, digits, dashes) and APP_ENV=production first\n");
    exit(1);
}
$sent = IndexNow::submitPaths(array_keys(Sitemap::entries()));
echo "indexnow: submitted {$sent} URL(s)\n";
exit($sent > 0 ? 0 : 1);
