<?php
declare(strict_types=1);

namespace Ttp;

/**
 * Newsletter push (plan §6.1/§7): no-op unless `MAILCHIMP_API_KEY` and
 * `MAILCHIMP_LIST_ID` are set. The subscriber is always stored locally first
 * (src/Forms/NewsletterForm.php), so a missing or failing key never loses a
 * signup.
 */
final class Mailchimp
{
    public static function subscribe(string $email): void
    {
        $config = ttp_config()['mailchimp'];
        $key    = trim((string) $config['api_key']);
        $listId = trim((string) $config['list_id']);

        if ($key === '' || $listId === '' || !str_contains($key, '-')) {
            return;
        }

        $dc  = substr($key, (int) strpos($key, '-') + 1);
        $url = "https://{$dc}.api.mailchimp.com/3.0/lists/{$listId}/members/" . md5(strtolower($email));

        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST  => 'PUT',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_USERPWD        => 'anystring:' . $key,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS     => (string) json_encode([
                    'email_address' => $email,
                    'status_if_new' => 'subscribed',
                    'status'        => 'subscribed',
                ], JSON_UNESCAPED_UNICODE),
            ]);
            $response = curl_exec($ch);
            $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error    = curl_error($ch);
            curl_close($ch);

            if ($status < 200 || $status >= 300) {
                error_log(sprintf('Mailchimp: subscribe failed [%d] %s %s', $status, (string) $response, $error));
            }
        } catch (\Throwable $e) {
            error_log('Mailchimp: subscribe threw — ' . $e->getMessage());
        }
    }
}
