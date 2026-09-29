<?php
declare(strict_types=1);

namespace Ttp;

/**
 * IndexNow: tells Bing, Yandex, Seznam and Naver that URLs changed, so they crawl
 * within minutes instead of days. Off unless INDEXNOW_KEY is set and the site is not dev.
 * The key is public by design: it is served at /<key>.txt so the engines can verify it.
 */
final class IndexNow
{
    private const ENDPOINT = 'https://api.indexnow.org/indexnow';

    public static function key(): string
    {
        $key = (string) ttp_config()['indexnow_key'];
        return preg_match('/^[a-z0-9-]{8,128}$/', $key) === 1 ? $key : '';
    }

    public static function enabled(): bool
    {
        return self::key() !== '' && ttp_config()['env'] !== 'dev';
    }

    /** @param array<int,string> $paths site paths such as /caacupe/ @return int URLs submitted */
    public static function submitPaths(array $paths): int
    {
        if (!self::enabled() || !function_exists('curl_init')) {
            return 0;
        }
        $urls = [];
        foreach (array_unique($paths) as $path) {
            if (is_string($path) && $path !== '' && !str_starts_with($path, '/admin')) {
                $urls[] = Seo::url($path);
            }
        }
        return self::post($urls);
    }

    /** @param array<int,string> $urls absolute URLs */
    public static function post(array $urls): int
    {
        $key = self::key();
        if ($key === '' || $urls === []) {
            return 0;
        }
        $host = (string) parse_url(Seo::siteUrl(), PHP_URL_HOST);
        $sent = 0;
        foreach (array_chunk($urls, 9000) as $chunk) {
            $body = json_encode([
                'host'        => $host,
                'key'         => $key,
                'keyLocation' => Seo::url('/' . $key . '.txt'),
                'urlList'     => array_values($chunk),
            ], JSON_UNESCAPED_SLASHES);
            $ch = curl_init(self::ENDPOINT);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=utf-8'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 3,
                CURLOPT_CONNECTTIMEOUT => 2,
            ]);
            curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if ($code === 200 || $code === 202) {
                $sent += count($chunk);
            }
        }
        return $sent;
    }
}
