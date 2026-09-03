<?php
declare(strict_types=1);

/**
 * The contact and newsletter forms (plan §6.1 exit criteria): honeypot,
 * time-trap, server-side validation, and that a real submission actually
 * lands a row in SQLite. Mailer/VenderCrm/Mailchimp are network calls that
 * no-op with no configuration (config/config.php, no `.env` in CI), so
 * `submit()` can be exercised directly against a throwaway database.
 */

use Ttp\Cache;
use Ttp\Db;
use Ttp\Forms\ContactForm;
use Ttp\Forms\NewsletterForm;

/** A throwaway install: schema applied, nothing seeded. */
function ttp_forms_env(): string
{
    $dir = sys_get_temp_dir() . '/ttp-forms-test-' . bin2hex(random_bytes(4));
    mkdir($dir . '/cache', 0775, true);

    Db::use($dir . '/site.sqlite');
    Db::conn()->exec((string) file_get_contents(ttp_root() . '/db/schema.sql'));
    Cache::use($dir . '/cache', 3600);
    return $dir;
}

function ttp_forms_cleanup(string $dir): void
{
    Cache::reset();
    Db::use(ttp_config()['db_path']);

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) {
        $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
    }
    @rmdir($dir);
}

// ---------------------------------------------------------------------------
// Pure logic — no database needed.
// ---------------------------------------------------------------------------

test('the contact honeypot trips on any non-empty value', function (): void {
    assert_true(!ContactForm::isHoneypotTripped(['company' => '']));
    assert_true(!ContactForm::isHoneypotTripped([]));
    assert_true(ContactForm::isHoneypotTripped(['company' => 'Acme SEO Services']));
});

test('the contact time-trap rejects a submit faster than the minimum', function (): void {
    $now = 1_000_000;
    assert_true(ContactForm::isTooFast(['_ts' => (string) $now], $now), 'zero elapsed seconds is too fast');
    assert_true(ContactForm::isTooFast(['_ts' => (string) ($now - 2)], $now), 'two seconds is too fast');
    assert_true(!ContactForm::isTooFast(['_ts' => (string) ($now - 3)], $now), 'the minimum itself is accepted');
    assert_true(!ContactForm::isTooFast(['_ts' => (string) ($now - 30)], $now));
    assert_true(ContactForm::isTooFast([], $now), 'a missing token looks automated');
});

test('contact validation requires name, phone and message', function (): void {
    $result = ContactForm::validate([]);
    assert_true(in_array('your name', $result['errors'], true));
    assert_true(in_array('a phone or WhatsApp number', $result['errors'], true));
    assert_true(in_array('a short message', $result['errors'], true));
});

test('contact validation accepts an empty email but rejects a malformed one', function (): void {
    $ok = ContactForm::validate(['name' => 'A', 'phone' => '+595981123456', 'message' => 'Hi']);
    assert_same([], $ok['errors']);

    $bad = ContactForm::validate(['name' => 'A', 'phone' => '+595981123456', 'message' => 'Hi', 'email' => 'not-an-email']);
    assert_true(in_array('a valid email address', $bad['errors'], true));
});

test('newsletter validation requires a well-formed email', function (): void {
    assert_true(NewsletterForm::validate([])['errors'] !== []);
    assert_true(NewsletterForm::validate(['email' => 'nope'])['errors'] !== []);
    assert_same([], NewsletterForm::validate(['email' => 'reader@example.com'])['errors']);
});

// ---------------------------------------------------------------------------
// submit() — real database.
// ---------------------------------------------------------------------------

test('a tripped honeypot on the contact form stores nothing but still reports success', function (): void {
    $dir = ttp_forms_env();
    try {
        $result = ContactForm::submit([
            'name' => 'Bot', 'phone' => '123', 'message' => 'buy links', 'company' => 'spam co',
            '_ts' => (string) (time() - 10),
        ], '/contact/');
        assert_true($result['ok']);
        assert_same(null, $result['leadId']);
        assert_same(0, (int) Db::value('SELECT COUNT(*) FROM leads'));
    } finally {
        ttp_forms_cleanup($dir);
    }
});

test('a too-fast contact submission stores nothing but still reports success', function (): void {
    $dir = ttp_forms_env();
    try {
        $result = ContactForm::submit([
            'name' => 'Fast Bot', 'phone' => '123', 'message' => 'hello', '_ts' => (string) time(),
        ], '/contact/');
        assert_true($result['ok']);
        assert_same(0, (int) Db::value('SELECT COUNT(*) FROM leads'));
    } finally {
        ttp_forms_cleanup($dir);
    }
});

test('an incomplete contact submission is rejected with no row written', function (): void {
    $dir = ttp_forms_env();
    try {
        $result = ContactForm::submit(['name' => '', 'message' => '', '_ts' => (string) (time() - 10)], '/contact/');
        assert_true(!$result['ok']);
        assert_true($result['errors'] !== []);
        assert_same(0, (int) Db::value('SELECT COUNT(*) FROM leads'));
    } finally {
        ttp_forms_cleanup($dir);
    }
});

test('a valid contact submission is stored with the page it came from', function (): void {
    $dir = ttp_forms_env();
    try {
        $result = ContactForm::submit([
            'name'    => 'Maria Gomez',
            'email'   => 'maria@example.com',
            'phone'   => '+595981123456',
            'message' => 'Interested in the Jesuit missions tour for 4 people in October.',
            '_ts'     => (string) (time() - 10),
        ], '/jesuit-missions-tour/');

        assert_true($result['ok']);
        assert_true($result['leadId'] !== null);

        $row = Db::one('SELECT * FROM leads WHERE id = ?', [$result['leadId']]);
        assert_same('Maria Gomez', $row['name']);
        assert_same('maria@example.com', $row['email']);
        assert_same('/jesuit-missions-tour/', $row['page_path']);
        assert_same(0, (int) $row['forwarded']);
    } finally {
        ttp_forms_cleanup($dir);
    }
});

test('the newsletter honeypot stores no subscriber', function (): void {
    $dir = ttp_forms_env();
    try {
        $result = NewsletterForm::submit(['email' => 'reader@example.com', 'company' => 'spam']);
        assert_true($result['ok']);
        assert_same(0, (int) Db::value('SELECT COUNT(*) FROM subscribers'));
    } finally {
        ttp_forms_cleanup($dir);
    }
});

test('a valid newsletter signup is idempotent by email', function (): void {
    $dir = ttp_forms_env();
    try {
        $first = NewsletterForm::submit(['email' => 'reader@example.com'], 'footer');
        assert_true($first['ok']);
        assert_same(1, (int) Db::value('SELECT COUNT(*) FROM subscribers'));

        $second = NewsletterForm::submit(['email' => 'reader@example.com'], 'footer');
        assert_true($second['ok']);
        assert_same(1, (int) Db::value('SELECT COUNT(*) FROM subscribers'), 'the same email must not create a second row');
    } finally {
        ttp_forms_cleanup($dir);
    }
});
