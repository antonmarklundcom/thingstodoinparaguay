<?php
declare(strict_types=1);

/**
 * The unit-test runner. No framework: every `tests/*.test.php` file calls
 * `test('name', fn)` and the assert helpers below. CI runs this next to
 * bin/verify.php (which covers the URL contract end to end).
 *
 * Usage: php bin/test.php [filter]
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

/** @var array<int,array{name:string,fn:callable}> */
$GLOBALS['ttp_tests'] = [];

function test(string $name, callable $fn): void
{
    $GLOBALS['ttp_tests'][] = ['name' => $name, 'fn' => $fn];
}

final class AssertionFailed extends RuntimeException
{
}

function assert_same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new AssertionFailed(
            ($message !== '' ? $message . ': ' : '')
            . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)
        );
    }
}

function assert_equals(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected != $actual) {
        throw new AssertionFailed(
            ($message !== '' ? $message . ': ' : '')
            . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)
        );
    }
}

function assert_true(bool $condition, string $message = 'expected true'): void
{
    if (!$condition) {
        throw new AssertionFailed($message);
    }
}

function assert_contains(string $needle, string $haystack, string $message = ''): void
{
    if (!str_contains($haystack, $needle)) {
        throw new AssertionFailed(
            ($message !== '' ? $message . ': ' : '') . "'{$needle}' not found in: "
            . substr($haystack, 0, 400)
        );
    }
}

// ---------------------------------------------------------------------------
$filter = $argv[1] ?? '';
foreach (glob(ttp_root() . '/tests/*.test.php') ?: [] as $file) {
    require_once $file;
}

$passed  = 0;
$failed  = [];
$started = microtime(true);

foreach ($GLOBALS['ttp_tests'] as $case) {
    if ($filter !== '' && !str_contains($case['name'], $filter)) {
        continue;
    }
    try {
        ($case['fn'])();
        $passed++;
    } catch (Throwable $e) {
        $failed[] = $case['name'] . ' — ' . $e->getMessage();
    }
}

printf("test: %d passed, %d failed, %.2fs\n", $passed, count($failed), microtime(true) - $started);

if ($failed !== []) {
    echo "\nFAILED:\n";
    foreach ($failed as $line) {
        echo '  ✗ ' . $line . "\n";
    }
    exit(1);
}
exit(0);
