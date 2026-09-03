<?php
declare(strict_types=1);

/**
 * Front controller. Everything that is not a real file under public/ lands here
 * (see .htaccess, and the PHP built-in server shim at the bottom of this file).
 */

// `php -S localhost:8080 -t public public/index.php` uses this file as the router
// script: hand real files (assets, media) back to the built-in server unchanged.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '');
    if ($file !== __DIR__ . '/' && is_file($file)) {
        return false;
    }
}

require_once dirname(__DIR__) . '/src/bootstrap.php';

use Ttp\Cache;
use Ttp\Db;
use Ttp\Response;
use Ttp\Router;

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$uri    = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$path   = Router::normalise($uri);
$query  = (string) ($_SERVER['QUERY_STRING'] ?? '');

// A missing database means the site was deployed without running bin/migrate.php.
if (!Db::exists()) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Retry-After: 120');
    echo "The site database has not been created yet.\n"
       . "Run: php bin/migrate.php && php bin/seed.php\n";
    exit;
}

$useCache = Cache::cacheable($method, $path, $query);
if ($useCache) {
    $cached = Cache::get($path);
    if ($cached !== null) {
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: public, max-age=600');
        header('X-Ttp-Cache: hit');
        echo $cached;
        exit;
    }
}

$response = Router::dispatch($method, $uri);

if ($response->status === 200 && !isset($response->headers['Cache-Control'])) {
    $response->headers['Cache-Control'] = 'public, max-age=600';
}
if ($useCache) {
    $response->headers['X-Ttp-Cache'] = 'miss';
}

if ($useCache && $response->cacheable && $response->status === 200) {
    Cache::put($path, $response->body);
}

$response->send();
