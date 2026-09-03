<?php
declare(strict_types=1);

/**
 * Empties the HTML page cache, or just the paths you name.
 *
 * Usage:
 *   php bin/cache-clear.php                 clear everything
 *   php bin/cache-clear.php /about/ /blog/  clear those paths only
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

use Ttp\Cache;

$paths = array_values(array_filter(array_slice($argv, 1), static fn (string $a): bool => !str_starts_with($a, '--')));

if ($paths === []) {
    $n = Cache::flush();
    echo "cache-clear: removed {$n} cached page(s) from " . Cache::dir() . "\n";
    exit(0);
}

$n = Cache::forgetPaths($paths);
echo "cache-clear: removed {$n} of " . count($paths) . " named path(s)\n";
