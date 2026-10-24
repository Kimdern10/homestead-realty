<?php

namespace App\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Turns an uploaded photo into two compressed WebP files on the public disk:
 * a large copy for the gallery and a small copy for cards and thumbnails.
 * Uses PHP's built-in GD extension so it runs on shared hosting.
 */
class ImageOptimizer
{
    public const int LARGE_WIDTH = 1600;

    public const int SMALL_WIDTH = 720;

    public const int PORTRAIT_WIDTH = 600;

    public const int QUALITY = 78;

    /**
     * Store the photo as WebP and return the path and size of the large copy.
     *
     * The small copy is saved next to it with a "-sm" suffix.
     *
     * @return array{path: string, width: int, height: int}
     */
    public function store(UploadedFile|string $file, string $directory = 'listings'): array
    {
        $source = $this->load($file);

        $path = trim($directory, '/').'/'.Str::lower((string) Str::ulid()).'.webp';

        [$large, $width, $height] = $this->encode($source, self::LARGE_WIDTH);

        Storage::disk('public')->put($path, $large);
        Storage::disk('public')->put(Str::replaceLast('.webp', '-sm.webp', $path), $this->encode($source, self::SMALL_WIDTH)[0]);

        return ['path' => $path, 'width' => $width, 'height' => $height];
    }

    /**
     * Store a single square-ish portrait (agent photo) and return its path.
     */
    public function storePortrait(UploadedFile|string $file, string $directory = 'agents'): string
    {
        $path = trim($directory, '/').'/'.Str::lower((string) Str::ulid()).'.webp';

        Storage::disk('public')->put($path, $this->encode($this->load($file), self::PORTRAIT_WIDTH)[0]);

        return $path;
    }

    /**
     * Read the upload (or a file path) into a GD image, turning phone photos the right way up.
     */
    private function load(UploadedFile|string $file): GdImage
    {
        $realPath = $file instanceof UploadedFile ? (string) $file->getRealPath() : $file;
        $contents = (string) @file_get_contents($realPath);
        $image = $contents !== '' ? @imagecreatefromstring($contents) : false;

        if (! $image instanceof GdImage) {
            throw new RuntimeException('The file could not be read as an image.');
        }

        $mime = $file instanceof UploadedFile ? $file->getMimeType() : (mime_content_type($realPath) ?: null);

        if (function_exists('exif_read_data') && in_array($mime, ['image/jpeg', 'image/jpg'], true)) {
            $exif = @exif_read_data($realPath);
            $orientation = is_array($exif) ? ($exif['Orientation'] ?? 1) : 1;

            $rotated = match ($orientation) {
                3 => imagerotate($image, 180, 0),
                6 => imagerotate($image, -90, 0),
                8 => imagerotate($image, 90, 0),
                default => $image,
            };

            if ($rotated instanceof GdImage) {
                $image = $rotated;
            }
        }

        return $image;
    }

    /**
     * Scale the image down to the given width (never up) and encode it as WebP.
     *
     * @param  positive-int  $maxWidth
     * @return array{0: string, 1: int, 2: int}
     */
    private function encode(GdImage $source, int $maxWidth): array
    {
        $width = imagesx($source);
        $height = imagesy($source);

        $image = $source;

        if ($width > $maxWidth) {
            $height = max(1, (int) round($height * $maxWidth / $width));
            $width = $maxWidth;
            $image = imagecreatetruecolor($width, $height);
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
        } elseif (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        imagesavealpha($image, true);

        ob_start();
        imagewebp($image, null, self::QUALITY);

        return [(string) ob_get_clean(), $width, $height];
    }
}
