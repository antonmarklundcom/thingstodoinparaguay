<?php
declare(strict_types=1);

namespace Ttp;

/**
 * Lead push to VenderCRM (skill: vendercrm-lead-capture). No-op unless both
 * `VENDERCRM_ENDPOINT` and `VENDERCRM_API_KEY` are set and the lead has a
 * phone number — phone is the contact identity on the CRM side. Never blocks
 * or throws: the lead is already stored in SQLite by the caller.
 */
final class VenderCrm
{
    public static function push(string $name, string $email, string $phone, string $message, string $pageUrl): void
    {
        $config   = ttp_config()['vendercrm'];
        $endpoint = rtrim((string) $config['endpoint'], '/');
        $key      = trim((string) $config['api_key']);
        $phone    = trim($phone);

        if ($endpoint === '' || $key === '' || $phone === '') {
            return;
        }

        $idempotencyKey = hash('sha256', $phone . '|' . gmdate('Y-m-d-H'));
        $payload = array_filter(
            [
                'phone'           => $phone,
                'name'            => $name,
                'email'           => $email,
                'message'         => $message,
                'source'          => 'site:thingstodoinparaguay',
                'page_url'        => $pageUrl,
                'idempotency_key' => $idempotencyKey,
            ],
            static fn ($v): bool => $v !== null && $v !== ''
        );

        try {
            $ch = curl_init($endpoint . '/api/v1/leads');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'X-Api-Key: ' . $key,
                ],
                CURLOPT_POSTFIELDS => (string) json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);
            $response = curl_exec($ch);
            $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error    = curl_error($ch);
            curl_close($ch);

            if ($status !== 200 && $status !== 201) {
                error_log(sprintf('VenderCrm: push failed [%d] %s %s', $status, (string) $response, $error));
            }
        } catch (\Throwable $e) {
            error_log('VenderCrm: push threw — ' . $e->getMessage());
        }
    }
}
