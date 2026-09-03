<?php
declare(strict_types=1);

/**
 * Reads .env (if present) and returns the resolved configuration array.
 * Environment variables always win over .env so hosting panels can override.
 */

function ttp_root(): string
{
    return dirname(__DIR__);
}

function ttp_load_env(?string $file = null): array
{
    $file ??= ttp_root() . '/.env';
    $vars = [];
    if (is_file($file)) {
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $v = trim($v);
            if (strlen($v) > 1 && ($v[0] === '"' || $v[0] === "'") && $v[strlen($v) - 1] === $v[0]) {
                $v = substr($v, 1, -1);
            }
            $vars[trim($k)] = $v;
        }
    }
    return $vars;
}

function ttp_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $env = ttp_load_env();
    $get = static function (string $key, string $default = '') use ($env): string {
        $v = getenv($key);
        if ($v !== false && $v !== '') {
            return $v;
        }
        return $env[$key] ?? $default;
    };

    $root = ttp_root();
    $abs = static function (string $path) use ($root): string {
        if ($path === '') {
            return $path;
        }
        return str_starts_with($path, '/') ? $path : $root . '/' . $path;
    };

    // The HTML page cache is off in dev unless CACHE_TTL is set explicitly, so a
    // template edit shows up on the next reload.
    $env_name  = $get('APP_ENV', 'dev');
    $ttl_set   = $get('CACHE_TTL');
    $cache_ttl = $ttl_set !== '' ? (int) $ttl_set : ($env_name === 'dev' ? 0 : 3600);

    $config = [
        'root'        => $root,
        'site_url'    => rtrim($get('SITE_URL', 'http://localhost:8080'), '/'),
        'env'         => $env_name,
        'db_path'     => $abs($get('DB_PATH', 'data/site.sqlite')),
        'cache_dir'   => $abs($get('CACHE_DIR', 'cache')),
        'cache_ttl'   => $cache_ttl,
        'content_dir' => $root . '/content',
        'media_dir'   => $root . '/public/media',
        'lead_email'  => $get('LEAD_EMAIL_TO', 'hello@thingstodoinparaguay.com'),
        'smtp'        => [
            'host' => $get('SMTP_HOST'),
            'port' => (int) $get('SMTP_PORT', '587'),
            'user' => $get('SMTP_USER'),
            'pass' => $get('SMTP_PASS'),
        ],
        'vendercrm'   => [
            'endpoint' => $get('VENDERCRM_ENDPOINT'),
            'api_key'  => $get('VENDERCRM_API_KEY'),
        ],
        'mailchimp'   => [
            'api_key' => $get('MAILCHIMP_API_KEY'),
            'list_id' => $get('MAILCHIMP_LIST_ID'),
        ],
        'ga4_id'      => $get('GA4_ID'),
        'whatsapp'    => $get('WHATSAPP_NUMBER', '595995628862'),
        // Brand constants (plan §1.10). Overridable per-install via the settings table.
        'site_name'   => 'Things to do in Paraguay',
        'tagline'     => 'Tours, day trips and relocation help in Paraguay',
        'address'     => 'Edificio Skytower, Asunción, Paraguay',
        'phone'       => '+595 995 628 862',
        'email'       => 'hello@thingstodoinparaguay.com',
        'locale'      => 'en',
    ];

    return $config;
}
