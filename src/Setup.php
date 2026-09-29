<?php
declare(strict_types=1);

namespace Ttp;

use Ttp\Admin\Auth;

/**
 * First-run setup in the browser, for hosts where running bin/*.php over SSH is
 * not practical. Only reachable while the database file does not exist; the front
 * controller hands over to handle() in that case. It does what
 * `bin/migrate.php`, `bin/seed.php`, `bin/create-admin.php` and a hand-written
 * `.env` do, and asks for the hosting account's username as a shared secret so a
 * stranger cannot claim the admin account between deploy and first visit.
 */
final class Setup
{
    public static function handle(string $method, string $path): void
    {
        if ($path !== '/' && $path !== '/setup/') {
            self::redirect('/setup/');
        }

        $error = '';
        $email = '';
        $name  = '';

        if ($method === 'POST') {
            $email  = trim((string) ($_POST['email'] ?? ''));
            $name   = trim((string) ($_POST['name'] ?? ''));
            $pass   = (string) ($_POST['password'] ?? '');
            $repeat = (string) ($_POST['repeat'] ?? '');
            $key    = trim((string) ($_POST['key'] ?? ''));
            $error  = self::validate($email, $pass, $repeat, $key);
            if ($error === '') {
                $error = self::install($email, $name, $pass);
                if ($error === '') {
                    self::redirect('/admin/');
                }
            }
        }

        self::page($error, $email, $name);
    }

    /** @return string error message, or '' when the input is acceptable */
    private static function validate(string $email, string $pass, string $repeat, string $key): string
    {
        if (!hash_equals(strtolower(self::hostUser()), strtolower($key))) {
            return 'The hosting username is not right. It is shown in hPanel under Advanced → SSH Access.';
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return 'That is not a valid email address.';
        }
        $problem = Auth::passwordProblem($pass);
        if ($problem !== null) {
            return $problem;
        }
        if ($pass !== $repeat) {
            return 'The two passwords do not match.';
        }
        return '';
    }

    private static function hostUser(): string
    {
        $user = function_exists('get_current_user') ? (string) get_current_user() : '';
        if ($user === '' && function_exists('posix_getpwuid')) {
            $info = posix_getpwuid(fileowner(ttp_root() . '/config/config.php') ?: 0);
            $user = is_array($info) ? (string) $info['name'] : '';
        }
        return $user !== '' ? $user : "\0";
    }

    /** @return string error message, or '' on success */
    private static function install(string $email, string $name, string $password): string
    {
        @set_time_limit(300);
        $root = ttp_root();

        try {
            self::writeEnv($root);
            foreach (['data', 'cache', 'public/media'] as $dir) {
                if (!is_dir($root . '/' . $dir)) {
                    @mkdir($root . '/' . $dir, 0775, true);
                }
            }

            self::runScripts($root);

            Auth::createUser($email, $password, $name);
            Cache::flush();
        } catch (\Throwable $e) {
            // Leave no half-built database behind, or the site would boot broken.
            Db::use(Db::path());
            if (is_file(Db::path())) {
                @unlink(Db::path());
                @unlink(Db::path() . '-wal');
                @unlink(Db::path() . '-shm');
            }
            error_log('setup failed: ' . $e->getMessage());
            return 'Setup failed: ' . $e->getMessage();
        }

        return '';
    }

    /** Own scope: the CLI scripts reuse names like $name, which must not reach install(). */
    private static function runScripts(string $root): void
    {
        // They print progress; swallow it. Neither calls exit().
        ob_start();
        try {
            require $root . '/bin/migrate.php';
            require $root . '/bin/seed.php';
        } finally {
            ob_end_clean();
        }
    }

    /** Creates .env from the request's own host the first time, when there is none. */
    private static function writeEnv(string $root): void
    {
        $file = $root . '/.env';
        if (is_file($file)) {
            return;
        }
        $https = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $host = preg_replace('/[^A-Za-z0-9.\-:]/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
        $body = "# Written by the first-run setup page.\n"
            . 'SITE_URL=' . ($https ? 'https' : 'http') . '://' . $host . "\n"
            . "APP_ENV=prod\n"
            . "DB_PATH=data/site.sqlite\n";
        @file_put_contents($file, $body);
        @chmod($file, 0640);
    }

    private static function redirect(string $to): never
    {
        header('Location: ' . $to, true, 302);
        exit;
    }

    private static function page(string $error, string $email, string $name): void
    {
        $h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        http_response_code($error === '' ? 200 : 422);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="robots" content="noindex"><title>Site setup</title>'
            . '<style>body{font:16px/1.5 system-ui,sans-serif;max-width:30rem;margin:3rem auto;padding:0 1rem;color:#1b2b24}'
            . 'label{display:block;margin:1rem 0 .25rem;font-weight:600}'
            . 'input{width:100%;padding:.6rem;border:1px solid #99a;border-radius:6px;font:inherit;box-sizing:border-box}'
            . 'button{margin-top:1.5rem;padding:.7rem 1.4rem;font:inherit;font-weight:600;border:0;border-radius:6px;background:#1f6f4a;color:#fff;cursor:pointer}'
            . '.err{background:#fde8e8;border:1px solid #c33;padding:.7rem;border-radius:6px}small{color:#556}</style></head><body>'
            . '<h1>Set up the site</h1>'
            . '<p>The database has not been created yet. This creates it, imports the content and makes your admin login. It takes about a minute.</p>';
        if ($error !== '') {
            echo '<p class="err">' . $h($error) . '</p>';
        }
        echo '<form method="post" action="/setup/" autocomplete="off">'
            . '<label for="key">Hosting username</label>'
            . '<input id="key" name="key" required><small>Proves it is you. Find it in hPanel → Advanced → SSH Access.</small>'
            . '<label for="email">Admin email</label>'
            . '<input id="email" name="email" type="email" required value="' . $h($email) . '">'
            . '<label for="name">Your name (optional)</label>'
            . '<input id="name" name="name" value="' . $h($name) . '">'
            . '<label for="password">Admin password</label>'
            . '<input id="password" name="password" type="password" required minlength="12"><small>At least 12 characters, with letters and numbers.</small>'
            . '<label for="repeat">Repeat password</label>'
            . '<input id="repeat" name="repeat" type="password" required minlength="12">'
            . '<button type="submit">Create the site</button></form></body></html>';
        exit;
    }
}
