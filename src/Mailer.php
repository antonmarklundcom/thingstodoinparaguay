<?php
declare(strict_types=1);

namespace Ttp;

/**
 * Lead notification email (plan §6.1): SMTP when `SMTP_HOST` is set, else
 * PHP's `mail()`. Never throws — a delivery failure is logged and the lead
 * stays safely in SQLite either way (src/Forms/ContactForm.php stores it
 * first), so a broken mail server never loses an enquiry.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $body, string $replyTo = ''): bool
    {
        $to = trim($to);
        if ($to === '') {
            return false;
        }

        $smtp = ttp_config()['smtp'];
        if (trim((string) $smtp['host']) !== '') {
            try {
                if (self::sendSmtp($smtp, $to, $subject, $body, $replyTo)) {
                    return true;
                }
            } catch (\Throwable $e) {
                error_log('Mailer: SMTP send threw — ' . $e->getMessage());
            }
            error_log('Mailer: SMTP send failed, falling back to mail()');
        }

        return self::sendPhpMail($to, $subject, $body, $replyTo);
    }

    private static function sendPhpMail(string $to, string $subject, string $body, string $replyTo): bool
    {
        $from = self::fromAddress();
        $headers = "From: {$from}\r\n" . "Content-Type: text/plain; charset=UTF-8\r\n";
        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL) !== false) {
            $headers .= "Reply-To: {$replyTo}\r\n";
        }
        $ok = @mail($to, self::encodeSubject($subject), $body, $headers);
        if (!$ok) {
            error_log('Mailer: mail() returned false for ' . $to);
        }
        return $ok;
    }

    /** @param array{host:string,port:int,user:string,pass:string} $smtp */
    private static function sendSmtp(array $smtp, string $to, string $subject, string $body, string $replyTo): bool
    {
        $host = (string) $smtp['host'];
        $port = (int) $smtp['port'];
        $user = (string) $smtp['user'];
        $pass = (string) $smtp['pass'];

        $address = ($port === 465 ? 'ssl://' : '') . $host . ':' . $port;
        $fp = @stream_socket_client($address, $errno, $errstr, 10);
        if ($fp === false) {
            error_log("Mailer: could not connect to {$host}:{$port} — {$errstr}");
            return false;
        }
        stream_set_timeout($fp, 10);

        $read = static function () use ($fp): string {
            $data = '';
            while (($line = fgets($fp, 515)) !== false) {
                $data .= $line;
                if (!isset($line[3]) || $line[3] !== '-') {
                    break;
                }
            }
            return $data;
        };
        $write = static function (string $cmd) use ($fp): void {
            fwrite($fp, $cmd . "\r\n");
        };
        $ehlo = static function () use ($fp, $write, $read, $host): void {
            $write('EHLO ' . (parse_url((string) ttp_config()['site_url'], PHP_URL_HOST) ?: $host));
            $read();
        };

        if (!str_starts_with($read(), '220')) {
            fclose($fp);
            return false;
        }
        $ehlo();

        if ($port === 587) {
            $write('STARTTLS');
            if (!str_starts_with($read(), '220')) {
                fclose($fp);
                return false;
            }
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($fp);
                return false;
            }
            $ehlo();
        }

        if ($user !== '') {
            $write('AUTH LOGIN');
            $read();
            $write(base64_encode($user));
            $read();
            $write(base64_encode($pass));
            if (!str_starts_with($read(), '235')) {
                fclose($fp);
                return false;
            }
        }

        $write('MAIL FROM:<' . self::fromAddressOnly() . '>');
        if (!str_starts_with($read(), '250')) {
            fclose($fp);
            return false;
        }
        $write('RCPT TO:<' . $to . '>');
        if (!str_starts_with($read(), '250')) {
            fclose($fp);
            return false;
        }
        $write('DATA');
        if (!str_starts_with($read(), '354')) {
            fclose($fp);
            return false;
        }

        $headers = self::dataHeaders($to, $subject, $replyTo);
        $stuffed = preg_replace('/^\./m', '..', str_replace("\n", "\r\n", str_replace("\r\n", "\n", $body)));
        $write($headers . "\r\n" . $stuffed . "\r\n.");
        $ok = str_starts_with($read(), '250');

        $write('QUIT');
        fclose($fp);
        return $ok;
    }

    private static function dataHeaders(string $to, string $subject, string $replyTo): string
    {
        $lines = [
            'From: ' . self::fromAddress(),
            'To: <' . $to . '>',
            'Subject: ' . self::encodeSubject($subject),
            'Date: ' . date('r'),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];
        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL) !== false) {
            $lines[] = 'Reply-To: ' . $replyTo;
        }
        return implode("\r\n", $lines);
    }

    private static function encodeSubject(string $subject): string
    {
        return '=?UTF-8?B?' . base64_encode($subject) . '?=';
    }

    private static function fromAddress(): string
    {
        $c = ttp_config();
        return '"' . str_replace(['"', "\r", "\n"], '', (string) $c['site_name']) . '" <' . self::fromAddressOnly() . '>';
    }

    private static function fromAddressOnly(): string
    {
        $smtpUser = trim((string) ttp_config()['smtp']['user']);
        if ($smtpUser !== '' && filter_var($smtpUser, FILTER_VALIDATE_EMAIL) !== false) {
            return $smtpUser;
        }
        return (string) ttp_config()['lead_email'];
    }
}
