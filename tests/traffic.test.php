<?php
declare(strict_types=1);

/**
 * Traffic features that need their own environment (IndexNow key, Bing tag, analytics,
 * a seeded DB), so each check runs a small script in a subprocess.
 */

/** @param array<string,string> $env @return string stdout of the script */
function ttp_traffic_run(string $php, array $env): string
{
    $db = ttp_temp_db();
    $env += ['APP_ENV' => 'production', 'CACHE_TTL' => '0', 'SITE_URL' => 'https://example.test'];
    foreach ($env as $k => $v) {
        putenv("{$k}={$v}");
    }
    ttp_run_script('migrate.php', ['--db=' . $db, '--quiet']);
    ttp_run_script('seed.php', ['--db=' . $db, '--quiet']);
    $file = dirname($db) . '/run.php';
    file_put_contents($file, '<?php require ' . var_export(ttp_root() . '/src/bootstrap.php', true) . ';Ttp\Db::use(' . var_export($db, true) . ');' . $php);
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $out);
    foreach (array_keys($env) as $k) {
        putenv($k);
    }
    return implode("\n", $out);
}

test('IndexNow key file answers with the key, and only when a valid key is set', function (): void {
    $out = ttp_traffic_run(
        '$r = Ttp\Router::dispatch("GET", "/abcd1234efgh5678.txt"); echo $r->status, "|", $r->body;',
        ['INDEXNOW_KEY' => 'abcd1234efgh5678']
    );
    assert_same('200|abcd1234efgh5678', $out);
    $out = ttp_traffic_run('$r = Ttp\Router::dispatch("GET", "/abcd1234efgh5678.txt"); echo $r->status;', []);
    assert_same('404', $out, 'no key configured means no key file');
});

test('Bing verification and analytics tags render only when configured', function (): void {
    $script = '$h = Ttp\Router::dispatch("GET", "/")->body;'
        . 'echo (int) str_contains($h, "msvalidate.01"), (int) str_contains($h, "data-domain=\"example.test\""), (int) str_contains($h, "googletagmanager");';
    assert_same('110', ttp_traffic_run($script, [
        'BING_VERIFY' => 'ABC123', 'ANALYTICS_SRC' => 'https://plausible.io/js/script.js', 'ANALYTICS_DOMAIN' => 'example.test',
    ]));
    assert_same('000', ttp_traffic_run($script, []));
});

test('place posts carry a TouristAttraction, general guides do not', function (): void {
    $script = 'foreach (["/caacupe/", "/cost-of-living-paraguay/"] as $p) { echo (int) str_contains(Ttp\Router::dispatch("GET", $p)->body, "TouristAttraction"); }';
    assert_same('10', ttp_traffic_run($script, []));
});
