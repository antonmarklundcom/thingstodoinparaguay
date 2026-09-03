<?php
declare(strict_types=1);

namespace Ttp;

/**
 * docs/url-map.csv — the URL contract (plan §1.4). Single source of truth for
 * the redirects table (bin/seed.php) and for bin/verify.php.
 */
final class UrlMap
{
    /** @return array<int,array{old_path:string,type:string,action:string,target:string,new_type:string,title:string,notes:string}> */
    public static function rows(?string $file = null): array
    {
        static $cache = [];
        $file ??= ttp_root() . '/docs/url-map.csv';
        if (isset($cache[$file])) {
            return $cache[$file];
        }

        $fh = fopen($file, 'rb');
        if ($fh === false) {
            throw new \RuntimeException("cannot read {$file}");
        }
        $header = fgetcsv($fh, 0, ',', '"', '');
        $rows = [];
        while (($r = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            if ($r === [null] || $r === []) {
                continue;
            }
            $r = array_pad($r, 7, '');
            $rows[] = [
                'old_path' => trim((string) $r[0]),
                'type'     => trim((string) $r[1]),
                'action'   => trim((string) $r[2]),
                'target'   => trim((string) $r[3]),
                'new_type' => trim((string) $r[4]),
                'title'    => trim((string) $r[5]),
                'notes'    => trim((string) $r[6]),
            ];
        }
        fclose($fh);
        unset($header);

        return $cache[$file] = $rows;
    }
}
