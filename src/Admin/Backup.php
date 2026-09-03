<?php
declare(strict_types=1);

namespace Ttp\Admin;

use RuntimeException;
use Ttp\Db;
use Ttp\Exporter;

/**
 * The admin's "Download backup" (plan §5.2): the current database exported to
 * content/ Markdown, plus a manifest of every media file, zipped in memory.
 *
 * It is the same Exporter bin/export.php uses, so a backup taken here can be
 * unzipped over content/ and replayed with bin/seed.php.
 */
final class Backup
{
    /** @return array{0:string,1:string} filename, zip bytes */
    public static function build(): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new RuntimeException(
                'This server has no ZIP support. Run `php bin/export.php` over SSH instead — '
                . 'it writes the same files into content/.'
            );
        }

        $stamp = gmdate('Y-m-d-His');
        $work  = sys_get_temp_dir() . '/ttp-backup-' . bin2hex(random_bytes(4));
        $zipAt = $work . '/backup.zip';
        if (!@mkdir($work . '/content', 0775, true)) {
            throw new RuntimeException('Could not create a temporary folder for the backup.');
        }

        try {
            Exporter::run($work . '/content');

            $zip = new \ZipArchive();
            if ($zip->open($zipAt, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Could not create the zip file.');
            }

            $base = $work . '/content';
            foreach (self::files($base) as $file) {
                $zip->addFile($file, 'content/' . ltrim(substr($file, strlen($base)), '/'));
            }
            $zip->addFromString('media.csv', self::mediaCsv());
            $zip->addFromString('README.txt', self::readme($stamp));
            $zip->close();

            $bytes = (string) file_get_contents($zipAt);
        } finally {
            self::removeTree($work);
        }

        return ['thingstodoinparaguay-backup-' . $stamp . '.zip', $bytes];
    }

    /** @return array<int,string> */
    private static function files(string $dir): array
    {
        $out = [];
        $it  = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $out[] = $file->getPathname();
            }
        }
        sort($out);
        return $out;
    }

    /**
     * The image files themselves are not zipped — a media library runs to
     * hundreds of megabytes and PHP would run out of memory. The manifest lists
     * every path so `public/media/` can be checked against it after a restore.
     */
    private static function mediaCsv(): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }
        fputcsv($handle, ['id', 'path', 'width', 'height', 'mime', 'alt', 'created_at', 'variants']);
        foreach (Db::all('SELECT * FROM media ORDER BY id') as $row) {
            $variants = json_decode((string) $row['sizes_json'], true);
            $paths    = [];
            foreach (is_array($variants) ? $variants : [] as $variant) {
                $paths[] = (string) ($variant['webp'] ?? '');
                $paths[] = (string) ($variant['original'] ?? '');
            }
            fputcsv($handle, array_map([App::class, 'csvSafe'], [
                (string) $row['id'], (string) $row['path'], (string) $row['width'], (string) $row['height'],
                (string) $row['mime'], (string) $row['alt'], (string) $row['created_at'],
                implode(' ', array_filter($paths)),
            ]));
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);
        return $csv;
    }

    private static function readme(string $stamp): string
    {
        return <<<TEXT
        Things to do in Paraguay — content backup, {$stamp} UTC

        content/   every post, page, tour, service and category as Markdown with front
                   matter. This is exactly what bin/seed.php reads.
        media.csv  a list of the uploaded images and every size generated for them. The
                   image files themselves are NOT in this zip — copy public/media/ over
                   FTP or SSH if you want those too.

        To restore on a fresh install:
          1. unzip this over the project so content/ is replaced
          2. php bin/migrate.php
          3. php bin/seed.php --force
          4. copy public/media/ back
          5. php bin/cache-clear.php

        Note: bin/seed.php never overwrites an item edited in the admin, so on an
        install that already has content, restore into a fresh database.

        TEXT;
    }

    private static function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            if ($file instanceof \SplFileInfo) {
                $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
            }
        }
        @rmdir($dir);
    }
}
