<?php
declare(strict_types=1);

namespace Ttp;

use RuntimeException;
use Ttp\Repo\MediaRepo;

/**
 * The image pipeline behind the admin's media library (plan §5.2).
 *
 * An upload is accepted only after the *file* says it is an image — the browser's
 * declared type and the original filename are never trusted, and the stored
 * extension is derived from the detected mime. Each accepted image is re-encoded
 * with GD at 400/800/1600 px wide (never upscaled) in WebP and in its original
 * format, under public/media/YYYY/MM/. Re-encoding also strips whatever metadata
 * the source carried.
 */
final class Uploader
{
    /** Widths generated for every upload, largest last. */
    public const WIDTHS = [400, 800, 1600];

    /** Detected mime => the extension we store it under. */
    public const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    public const MAX_BYTES = 12582912;   // 12 MB
    public const MAX_PIXELS = 50000000;  // 50 MP — a decompression-bomb guard

    private const JPEG_QUALITY = 82;
    private const WEBP_QUALITY = 80;

    /**
     * Store one image.
     *
     * @param string $sourcePath a file already on disk (the PHP upload temp file)
     * @param string $clientName the name the browser sent — used only for the slug
     * @return array<string,mixed> the media row that was created
     * @throws RuntimeException with a message meant for the person uploading
     */
    public static function store(
        string $sourcePath,
        string $clientName,
        string $alt,
        ?string $mediaDir = null,
        ?string $webBase = null
    ): array {
        if (!is_file($sourcePath)) {
            throw new RuntimeException('The upload did not arrive. Try again.');
        }

        $size = (int) filesize($sourcePath);
        if ($size <= 0) {
            throw new RuntimeException('That file is empty.');
        }
        if ($size > self::MAX_BYTES) {
            throw new RuntimeException(
                'That file is ' . self::human($size) . '. The limit is ' . self::human(self::MAX_BYTES) . '.'
            );
        }

        $mime = self::detectMime($sourcePath);
        if (!isset(self::ALLOWED[$mime])) {
            throw new RuntimeException('Only JPEG, PNG, WebP and GIF images can be uploaded.');
        }

        $info = @getimagesize($sourcePath);
        if ($info === false || (int) $info[0] < 1 || (int) $info[1] < 1) {
            throw new RuntimeException('That file is not a readable image.');
        }
        [$sourceWidth, $sourceHeight] = [(int) $info[0], (int) $info[1]];
        if ($sourceWidth * $sourceHeight > self::MAX_PIXELS) {
            throw new RuntimeException('That image has too many pixels to process.');
        }

        $image = self::load($sourcePath, $mime);
        $image = self::applyExifRotation($image, $sourcePath, $mime);
        $sourceWidth  = imagesx($image);
        $sourceHeight = imagesy($image);

        $extension = self::ALLOWED[$mime];
        $mediaDir  = rtrim($mediaDir ?? (string) ttp_config()['media_dir'], '/');
        $webBase   = rtrim($webBase ?? '/media', '/');
        $folder    = gmdate('Y/m');
        $targetDir = $mediaDir . '/' . $folder;
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            imagedestroy($image);
            throw new RuntimeException('Cannot write to ' . $targetDir . ' — check the folder permissions.');
        }
        self::protectDirectory($mediaDir);

        $base = self::baseName($clientName, $targetDir);

        // Never upscale: a 500 px source gets one 500 px variant, not three blurry ones.
        $widths = array_values(array_filter(self::WIDTHS, static fn (int $w): bool => $w <= $sourceWidth));
        if ($widths === []) {
            $widths = [$sourceWidth];
        }

        $sizes    = [];
        $largest  = null;
        try {
            foreach ($widths as $width) {
                $height = max(1, (int) round($sourceHeight * ($width / $sourceWidth)));
                $canvas = self::resize($image, $width, $height, $mime);

                $webpName = $base . '-' . $width . '.webp';
                $origName = $base . '-' . $width . '.' . $extension;
                self::writeWebp($canvas, $targetDir . '/' . $webpName);
                self::writeOriginal($canvas, $targetDir . '/' . $origName, $mime);
                imagedestroy($canvas);

                $sizes[] = [
                    'width'    => $width,
                    'height'   => $height,
                    'webp'     => $webBase . '/' . $folder . '/' . $webpName,
                    'original' => $webBase . '/' . $folder . '/' . $origName,
                ];
                $largest = end($sizes);
            }
        } finally {
            imagedestroy($image);
        }

        if ($largest === null) {
            throw new RuntimeException('The image could not be resized.');
        }

        $path = (string) $largest['original'];
        Db::run(
            'INSERT INTO media (filename, path, width, height, alt, mime, sizes_json, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                basename($path),
                $path,
                (int) $largest['width'],
                (int) $largest['height'],
                trim($alt),
                $mime,
                (string) json_encode($sizes, JSON_UNESCAPED_SLASHES),
                gmdate('c'),
            ]
        );

        $media = MediaRepo::find(Db::lastId());
        if ($media === null) {
            throw new RuntimeException('The image was written but could not be recorded.');
        }
        return $media;
    }

    /** Deletes an image and every variant it generated. */
    public static function delete(array $media, ?string $mediaDir = null, ?string $webBase = null): int
    {
        $mediaDir = rtrim($mediaDir ?? (string) ttp_config()['media_dir'], '/');
        $webBase  = rtrim($webBase ?? '/media', '/');
        $removed  = 0;

        $paths = [(string) $media['path']];
        foreach (self::sizes($media) as $size) {
            $paths[] = (string) ($size['webp'] ?? '');
            $paths[] = (string) ($size['original'] ?? '');
        }

        foreach (array_unique(array_filter($paths)) as $webPath) {
            if (!str_starts_with($webPath, $webBase . '/')) {
                continue;
            }
            $file = $mediaDir . '/' . ltrim(substr($webPath, strlen($webBase)), '/');
            // Refuse anything that escapes the media directory.
            $real = realpath($file);
            if ($real === false || !str_starts_with($real, (string) realpath($mediaDir))) {
                continue;
            }
            if (@unlink($real)) {
                $removed++;
            }
        }

        Db::run('UPDATE content_items SET cover_media_id = NULL WHERE cover_media_id = ?', [(int) $media['id']]);
        Db::run('UPDATE content_items SET og_image_media_id = NULL WHERE og_image_media_id = ?', [(int) $media['id']]);
        Db::run('DELETE FROM media WHERE id = ?', [(int) $media['id']]);

        return $removed;
    }

    /** @return array<int,array{width:int,height:int,webp:string,original:string}> */
    public static function sizes(array $media): array
    {
        $decoded = json_decode((string) ($media['sizes_json'] ?? '[]'), true);
        return is_array($decoded) ? $decoded : [];
    }

    /** The PHP upload error codes, as a sentence the person uploading can act on. */
    public static function uploadErrorMessage(int $code): ?string
    {
        return match ($code) {
            UPLOAD_ERR_OK         => null,
            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE  => 'That file is larger than the server accepts (' . ini_get('upload_max_filesize') . ').',
            UPLOAD_ERR_PARTIAL    => 'The upload was interrupted. Try again.',
            UPLOAD_ERR_NO_FILE    => 'Choose a file first.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server has no temporary folder for uploads.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the upload to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
            default               => 'The upload failed (code ' . $code . ').',
        };
    }

    // -----------------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------------

    public static function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = (string) finfo_file($finfo, $path);
                finfo_close($finfo);
                if ($mime !== '') {
                    return strtolower($mime);
                }
            }
        }
        $info = @getimagesize($path);
        return is_array($info) && isset($info['mime']) ? strtolower((string) $info['mime']) : '';
    }

    /** @return \GdImage */
    private static function load(string $path, string $mime): object
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/gif'  => @imagecreatefromgif($path),
            default      => false,
        };
        if ($image === false) {
            throw new RuntimeException('That image could not be opened — it may be corrupt.');
        }
        return $image;
    }

    /** @return \GdImage */
    private static function applyExifRotation(object $image, string $path, string $mime): object
    {
        if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 0) : 0;
        $angle = match ($orientation) {
            3       => 180,
            6       => -90,
            8       => 90,
            default => 0,
        };
        if ($angle === 0) {
            return $image;
        }
        $rotated = @imagerotate($image, $angle, 0);
        if ($rotated === false) {
            return $image;
        }
        imagedestroy($image);
        return $rotated;
    }

    /** @return \GdImage */
    private static function resize(object $image, int $width, int $height, string $mime): object
    {
        $canvas = imagecreatetruecolor($width, $height);
        if ($mime === 'image/png' || $mime === 'image/webp' || $mime === 'image/gif') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);
        }
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
        return $canvas;
    }

    private static function writeWebp(object $canvas, string $file): void
    {
        if (!function_exists('imagewebp') || !imagewebp($canvas, $file, self::WEBP_QUALITY)) {
            throw new RuntimeException('This server cannot write WebP images (GD is missing WebP support).');
        }
        @chmod($file, 0644);
    }

    private static function writeOriginal(object $canvas, string $file, string $mime): void
    {
        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($canvas, $file, self::JPEG_QUALITY),
            'image/png'  => imagepng($canvas, $file, 6),
            'image/webp' => imagewebp($canvas, $file, self::WEBP_QUALITY),
            'image/gif'  => imagegif($canvas, $file),
            default      => false,
        };
        if ($ok !== true) {
            throw new RuntimeException('The resized image could not be written to disk.');
        }
        @chmod($file, 0644);
    }

    /** A slug of the client filename, made unique inside the target folder. */
    private static function baseName(string $clientName, string $targetDir): string
    {
        $stem = pathinfo($clientName, PATHINFO_FILENAME);
        $slug = Str::slug($stem);
        if ($slug === '' || strlen($slug) < 3) {
            $slug = 'image';
        }
        $slug = substr($slug, 0, 60);

        $base = $slug;
        $n    = 2;
        while (glob($targetDir . '/' . $base . '-*') !== []) {
            $base = $slug . '-' . $n++;
            if ($n > 500) {
                $base = $slug . '-' . bin2hex(random_bytes(4));
                break;
            }
        }
        return $base;
    }

    /**
     * public/media/ holds only uploads, so make sure the web server will never
     * execute anything it finds there even if something else lands in the folder.
     */
    private static function protectDirectory(string $mediaDir): void
    {
        $file = $mediaDir . '/.htaccess';
        if (is_file($file)) {
            return;
        }
        @file_put_contents($file, <<<'HTACCESS'
# Uploads only. Nothing in this tree is ever executed.
<IfModule mod_php.c>
    php_flag engine off
</IfModule>
<IfModule mod_php7.c>
    php_flag engine off
</IfModule>
<IfModule mod_php8.c>
    php_flag engine off
</IfModule>
<FilesMatch "\.(php|phtml|phar|php[0-9]|cgi|pl|py|sh)$">
    Require all denied
</FilesMatch>

HTACCESS);
    }

    private static function human(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        return max(1, (int) round($bytes / 1024)) . ' KB';
    }
}
