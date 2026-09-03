<?php
declare(strict_types=1);

namespace Ttp\Forms;

use Ttp\Mailchimp;
use Ttp\Repo\SubscriberRepo;

/**
 * The footer newsletter form (plan §6.1). It appears on every page, so it is
 * deliberately lighter than ContactForm: a honeypot only, no time-trap —
 * a per-render timing token here would force every page out of the HTML
 * cache (src/Cache.php), which is not worth it for a low-value-target form.
 */
final class NewsletterForm
{
    public const HONEYPOT_FIELD = 'company';

    public static function isHoneypotTripped(array $post): bool
    {
        return trim((string) ($post[self::HONEYPOT_FIELD] ?? '')) !== '';
    }

    /** @return array{errors: array<int,string>, email:string} */
    public static function validate(array $post): array
    {
        $email  = trim((string) ($post['email'] ?? ''));
        $errors = [];
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'a valid email address';
        }
        return ['errors' => $errors, 'email' => $email];
    }

    /** @return array{ok:bool, errors:array<int,string>} */
    public static function submit(array $post, string $source = 'footer'): array
    {
        if (self::isHoneypotTripped($post)) {
            return ['ok' => true, 'errors' => []];
        }

        $data = self::validate($post);
        if ($data['errors'] !== []) {
            return ['ok' => false, 'errors' => $data['errors']];
        }

        if (SubscriberRepo::create($data['email'], $source)) {
            Mailchimp::subscribe($data['email']);
        }

        return ['ok' => true, 'errors' => []];
    }
}
