<?php
declare(strict_types=1);

/**
 * Contact/quote form handler (plan §6.1). A real file under public/, so
 * .htaccess's rewrite condition (`REQUEST_FILENAME !-f`) never sends this
 * request through src/Router.php — the router's hard limit (plan §4.7) stays
 * untouched while a POST still gets a working endpoint.
 */

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

use Ttp\Db;
use Ttp\Forms\ContactForm;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: /contact/');
    exit;
}

if (!Db::exists()) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "The site database has not been created yet.\n";
    exit;
}

$result = ContactForm::submit($_POST, '/contact/');

$target = '/contact/?sent=' . ($result['ok'] ? '1' : '0');
if (!$result['ok'] && $result['errors'] !== []) {
    $target .= '&missing=' . rawurlencode(implode(',', $result['errors']));
}

header('Location: ' . $target, true, 303);

// The lead is already stored; the email + VenderCRM push are network calls
// the visitor should never wait on. fastcgi_finish_request() (available
// under PHP-FPM) flushes the redirect to the browser now and keeps this
// script running after it — under a SAPI without it (e.g. LiteSpeed's
// LSAPI) this falls back to sending them synchronously, same as before.
if ($result['data'] !== null) {
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    ContactForm::notify($result['data'], '/contact/');
}
exit;
