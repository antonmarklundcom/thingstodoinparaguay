<?php
declare(strict_types=1);

/**
 * Creates (or re-passwords) the admin account the /admin/ panel logs in with.
 *
 * Usage:
 *   php bin/create-admin.php                                  prompt for everything
 *   php bin/create-admin.php --email=you@example.com --name=Anton
 *   php bin/create-admin.php --email=… --password=…           non-interactive
 *   php bin/create-admin.php --list                           show existing accounts
 *
 * Re-running with an existing email replaces that account's password, so this is
 * also the "I forgot my password" tool. Passwords are stored as bcrypt hashes and
 * are never written to the shell history when you let the script prompt for them.
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

use Ttp\Admin\Auth;
use Ttp\Db;

$opts = getopt('', ['db::', 'email::', 'password::', 'name::', 'list', 'force']);
if (!empty($opts['db'])) {
    Db::use((string) $opts['db']);
}

if (!Db::exists() || !Db::hasTable('users')) {
    fwrite(STDERR, "create-admin: no database at " . Db::path() . " — run bin/migrate.php first\n");
    exit(1);
}

if (isset($opts['list'])) {
    $rows = Db::all('SELECT email, name, last_login_at FROM users ORDER BY email');
    if ($rows === []) {
        echo "No admin accounts yet. Run this script without --list to create one.\n";
        exit(0);
    }
    foreach ($rows as $row) {
        printf("  %-40s %-20s last login: %s\n", $row['email'], $row['name'], $row['last_login_at'] ?: 'never');
    }
    exit(0);
}

/** Read one line from the terminal, optionally without echoing it. */
$prompt = static function (string $label, bool $hidden = false): string {
    fwrite(STDOUT, $label);
    if ($hidden && DIRECTORY_SEPARATOR !== '\\' && is_readable('/dev/tty')) {
        // `stty -echo` only works on a real terminal; fall back to a visible prompt.
        $before = trim((string) shell_exec('stty -g 2>/dev/null'));
        if ($before !== '') {
            shell_exec('stty -echo 2>/dev/null');
        }
        $value = (string) fgets(STDIN);
        if ($before !== '') {
            shell_exec('stty ' . escapeshellarg($before) . ' 2>/dev/null');
        }
        fwrite(STDOUT, "\n");
        return trim($value);
    }
    return trim((string) fgets(STDIN));
};

$email = trim((string) ($opts['email'] ?? ''));
while ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    if ($email !== '') {
        fwrite(STDOUT, "  That is not a valid email address.\n");
    }
    $email = $prompt('Admin email: ');
    if ($email === '' && !stream_isatty(STDIN)) {
        fwrite(STDERR, "create-admin: --email is required when there is no terminal\n");
        exit(1);
    }
}

$name = trim((string) ($opts['name'] ?? ''));
if ($name === '' && stream_isatty(STDIN)) {
    $name = $prompt('Display name (optional): ');
}

$password = (string) ($opts['password'] ?? '');
if ($password === '') {
    while (true) {
        $password = $prompt('Password (min 12 chars, letters + numbers): ', true);
        $problem  = Auth::passwordProblem($password);
        if ($problem !== null) {
            fwrite(STDOUT, '  ' . $problem . "\n");
            continue;
        }
        if ($prompt('Repeat password: ', true) !== $password) {
            fwrite(STDOUT, "  The two passwords do not match.\n");
            continue;
        }
        break;
    }
} else {
    $problem = Auth::passwordProblem($password);
    if ($problem !== null && !isset($opts['force'])) {
        fwrite(STDERR, "create-admin: weak password — {$problem} (pass --force to accept it anyway)\n");
        exit(1);
    }
}

$existing = Auth::findByEmail($email);
$id       = Auth::createUser($email, $password, $name);

printf(
    "create-admin: %s %s (id %d) in %s\n",
    $existing === null ? 'created' : 'updated',
    Auth::normaliseEmail($email),
    $id,
    Db::path()
);
echo "Sign in at " . rtrim((string) ttp_config()['site_url'], '/') . "/admin/\n";
exit(0);
