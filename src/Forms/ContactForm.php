<?php
declare(strict_types=1);

namespace Ttp\Forms;

use Ttp\Mailer;
use Ttp\Repo\LeadRepo;
use Ttp\Seo;
use Ttp\VenderCrm;

/**
 * The contact/quote form (plan §6.1): server-side validation, a honeypot and
 * a time-trap. `validate()`, `isHoneypotTripped()` and `isTooFast()` are pure
 * so tests/forms.test.php can exercise them without a database; `submit()` is
 * the only method that does I/O (SQLite, email, VenderCRM).
 */
final class ContactForm
{
    public const MIN_SECONDS = 3;
    public const HONEYPOT_FIELD = 'company';
    public const TIMESTAMP_FIELD = '_ts';

    public static function isHoneypotTripped(array $post): bool
    {
        return trim((string) ($post[self::HONEYPOT_FIELD] ?? '')) !== '';
    }

    /** True when the form was submitted implausibly fast for a human. */
    public static function isTooFast(array $post, ?int $now = null): bool
    {
        $now ??= time();
        $ts = (int) ($post[self::TIMESTAMP_FIELD] ?? 0);
        if ($ts <= 0) {
            return true;
        }
        return ($now - $ts) < self::MIN_SECONDS;
    }

    /** @return array{errors: array<int,string>, name:string, email:string, phone:string, message:string} */
    public static function validate(array $post): array
    {
        $name    = trim((string) ($post['name'] ?? ''));
        $email   = trim((string) ($post['email'] ?? ''));
        $phone   = trim((string) ($post['phone'] ?? ''));
        $message = trim((string) ($post['message'] ?? ''));

        $errors = [];
        if ($name === '') {
            $errors[] = 'your name';
        }
        if ($phone === '') {
            $errors[] = 'a phone or WhatsApp number';
        }
        if ($message === '') {
            $errors[] = 'a short message';
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'a valid email address';
        }

        return compact('errors', 'name', 'email', 'phone', 'message');
    }

    /**
     * The fast, synchronous half: honeypot/time-trap, validation, and the
     * SQLite write — everything the visitor's redirect must wait for.
     *
     * @return array{ok:bool, errors:array<int,string>, leadId:?int, data:?array{name:string,email:string,phone:string,message:string}}
     */
    public static function submit(array $post, string $pagePath): array
    {
        // A tripped honeypot or an implausibly fast submit is treated as spam
        // and silently "succeeds" — nothing is stored, nothing is emailed,
        // and the bot sees the same thank-you response a human would.
        if (self::isHoneypotTripped($post) || self::isTooFast($post)) {
            return ['ok' => true, 'errors' => [], 'leadId' => null, 'data' => null];
        }

        $data = self::validate($post);
        if ($data['errors'] !== []) {
            return ['ok' => false, 'errors' => $data['errors'], 'leadId' => null, 'data' => null];
        }

        $leadId = LeadRepo::create($data['name'], $data['email'], $data['phone'], $data['message'], $pagePath);

        return ['ok' => true, 'errors' => [], 'leadId' => $leadId, 'data' => $data];
    }

    /**
     * The slow, network-bound half: the notification email and the VenderCRM
     * push. The lead is already safely in SQLite by the time this runs, so
     * the caller (public/forms/contact.php) sends the visitor's redirect
     * first and calls this afterwards — via `fastcgi_finish_request()` where
     * the host supports it, the browser never waits on it.
     *
     * @param array{name:string,email:string,phone:string,message:string} $data
     */
    public static function notify(array $data, string $pagePath): void
    {
        Mailer::send(
            (string) ttp_config()['lead_email'],
            'New enquiry — ' . $data['name'],
            self::emailBody($data, $pagePath),
            $data['email']
        );

        VenderCrm::push($data['name'], $data['email'], $data['phone'], $data['message'], Seo::url($pagePath));
    }

    /** @param array{name:string,email:string,phone:string,message:string} $data */
    private static function emailBody(array $data, string $pagePath): string
    {
        return "New enquiry from " . Seo::url($pagePath) . "\n\n"
             . "Name:  {$data['name']}\n"
             . "Email: " . ($data['email'] !== '' ? $data['email'] : '(not given)') . "\n"
             . "Phone: {$data['phone']}\n\n"
             . "{$data['message']}\n";
    }
}
