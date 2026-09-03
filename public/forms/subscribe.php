<?php
declare(strict_types=1);

/**
 * Newsletter form handler (plan §6.1) — same rationale as contact.php: a
 * real file under public/ so it never touches src/Router.php.
 */

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

use Ttp\Db;
use Ttp\Forms\NewsletterForm;
use Ttp\Router;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: /');
    exit;
}

$redirectTo = (string) ($_POST['redirect_to'] ?? '/');
$redirectTo = Router::normalise($redirectTo);
if ($redirectTo === '' || $redirectTo[0] !== '/' || str_starts_with($redirectTo, '//')) {
    $redirectTo = '/';
}

if (!Db::exists()) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "The site database has not been created yet.\n";
    exit;
}

$result = NewsletterForm::submit($_POST);

$target = $redirectTo . '?subscribed=' . ($result['ok'] ? '1' : '0');
header('Location: ' . $target, true, 303);
exit;
