<?php
declare(strict_types=1);

namespace Ttp;

use Ttp\Repo\MediaRepo;

/**
 * Registers image files that already sit under public/media/ (committed to git,
 * not uploaded through the admin) as a media row.
 *
 * A cover is named by a size-less base path such as "/media/generated/foo"; the
 * files are "<base>-<width>.avif|webp|jpg|jpeg|png", the naming both webimg and
 * src/Uploader.php produce. The row's sizes_json carries every width found, in
 * the shape View::image() reads.
 */
final class Media
{
    private const EXTENSIONS = ['avif', 'webp', 'jpg', 'jpeg', 'png'];

    /**
     * @return int|null the media id, or null when no file matches the base path
     */
    public static function registerFromFiles(string $base, string $alt = '', ?string $publicDir = null): ?int
    {
        $base = '/' . trim($base, '/');
        if (!str_starts_with($base, '/media/') || str_contains($base, '..')) {
            return null;
        }
        $publicDir = rtrim($publicDir ?? (ttp_root() . '/public'), '/');

        /** @var array<int,array<string,string>> $byWidth */
        $byWidth = [];
        foreach (self::EXTENSIONS as $ext) {
            foreach (glob($publicDir . $base . '-*.' . $ext) ?: [] as $file) {
                if (preg_match('/-(\d+)\.' . $ext . '$/', $file, $m) === 1) {
                    $byWidth[(int) $m[1]][$ext] = $file;
                }
            }
        }
        if ($byWidth === []) {
            return null;
        }
        ksort($byWidth);

        $sizes = [];
        foreach ($byWidth as $width => $files) {
            $pick   = $files['webp'] ?? $files['jpg'] ?? $files['jpeg'] ?? $files['png'] ?? $files['avif'];
            $info   = @getimagesize($pick);
            $height = $info !== false ? (int) $info[1] : 0;
            $web    = static fn (string $file): string => $base . '-' . $width . '.' . pathinfo($file, PATHINFO_EXTENSION);

            $size = [
                'width'    => $width,
                'height'   => $height,
                'original' => $web($files['jpg'] ?? $files['jpeg'] ?? $files['png'] ?? $files['webp'] ?? $files['avif']),
            ];
            if (isset($files['webp'])) {
                $size['webp'] = $web($files['webp']);
            }
            if (isset($files['avif'])) {
                $size['avif'] = $web($files['avif']);
            }
            $sizes[] = $size;
        }

        $largest = end($sizes);
        $path    = (string) $largest['original'];
        $mime    = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'avif'        => 'image/avif',
            default       => 'image/webp',
        };
        $json = (string) json_encode($sizes, JSON_UNESCAPED_SLASHES);

        $existing = MediaRepo::findByPath($path);
        if ($existing !== null) {
            Db::run(
                'UPDATE media SET alt = ?, width = ?, height = ?, mime = ?, sizes_json = ? WHERE id = ?',
                [$alt, (int) $largest['width'], (int) $largest['height'], $mime, $json, (int) $existing['id']]
            );
            return (int) $existing['id'];
        }

        Db::run(
            'INSERT INTO media (filename, path, width, height, alt, mime, sizes_json, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [basename($path), $path, (int) $largest['width'], (int) $largest['height'], $alt, $mime, $json, gmdate('c')]
        );
        return Db::lastId();
    }
}
