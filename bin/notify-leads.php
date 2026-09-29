<?php
declare(strict_types=1);

/**
 * Sends the notification email and the VenderCRM push for every lead that has
 * not been forwarded yet, then marks it forwarded. Put it on cron (every 5
 * minutes) and set NOTIFY_QUEUE=1 in .env so the contact form never waits on
 * SMTP or the CRM (deploy/README.md, KNOWN-ISSUES [s3]). Safe to run without
 * the queue mode too: it only picks up leads whose inline notification failed.
 *
 * A lead is marked forwarded only when the email went out. If SMTP is down the
 * lead stays pending and is retried on the next run. The CRM push has no
 * return value; it retries implicitly because its idempotency key repeats for
 * the same phone within the hour.
 *
 * Usage: php bin/notify-leads.php [--db=path] [--quiet]
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

use Ttp\Db;
use Ttp\Forms\ContactForm;
use Ttp\Repo\LeadRepo;

$opts  = getopt('', ['db::', 'quiet']);
$quiet = isset($opts['quiet']);
if (!empty($opts['db'])) {
    Db::use((string) $opts['db']);
}

if (!Db::exists() || !Db::hasTable('leads')) {
    fwrite(STDERR, "notify-leads: no database at " . Db::path() . " — run bin/migrate.php first\n");
    exit(1);
}

$sent   = 0;
$failed = 0;
foreach (LeadRepo::pending() as $lead) {
    $ok = ContactForm::notify(
        [
            'name'    => (string) $lead['name'],
            'email'   => (string) $lead['email'],
            'phone'   => (string) $lead['phone'],
            'message' => (string) $lead['message'],
        ],
        (string) ($lead['page_path'] ?: '/contact/')
    );
    if ($ok) {
        LeadRepo::markForwarded((int) $lead['id']);
        $sent++;
    } else {
        $failed++;
    }
}

if (!$quiet || $sent > 0 || $failed > 0) {
    printf("notify-leads: %d sent, %d still pending\n", $sent, $failed);
}
exit($failed > 0 ? 1 : 0);
