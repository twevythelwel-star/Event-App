<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Validates, sanitizes and stores uploaded event flyers and menu photos on the private disk, per organizer.
 *
 * Every image is decoded and re-encoded with GD, which drops EXIF/GPS metadata and anything appended to
 * the file, so only real pixels are ever stored or served. Files are never reachable by a public URL;
 * MediaController decides who may read them.
 */
class MediaStorageService
{
    /** The default disk; config/media.php (MEDIA_DISK) can point uploads at an S3-compatible private disk instead. */
    public const DISK = 'local';

    public static function diskName(): string
    {
        return (string) config('media.disk', self::DISK);
    }

    public const MAX_BYTES = 5 * 1024 * 1024;

    /** Allowed formats and their file extensions (SVG and GIF are deliberately excluded). */
    private const FORMATS = [
        IMAGETYPE_JPEG => ['mime' => 'image/jpeg', 'ext' => 'jpg'],
        IMAGETYPE_PNG => ['mime' => 'image/png', 'ext' => 'png'],
        IMAGETYPE_WEBP => ['mime' => 'image/webp', 'ext' => 'webp'],
    ];

    /** Minimum and maximum pixel sizes, matching the host dashboard's flyer checks. */
    private const LIMITS = [
        'flyers' => ['min' => [300, 200], 'max' => [4500, 4500]],
        'menu' => ['min' => [200, 200], 'max' => [4500, 4500]],
        'event-media' => ['min' => [200, 200], 'max' => [4500, 4500]],
        'performers' => ['min' => [120, 120], 'max' => [4500, 4500]],
        'sponsors' => ['min' => [80, 40], 'max' => [4500, 4500]],
        // The event photography library (PhotoLibrary also requires its own, larger minimum).
        'library' => ['min' => [1200, 800], 'max' => [6000, 6000]],
    ];

    /**
    * @param 'flyers'|'menu'|'event-media' $kind
     * @return array{path: string, mime: string, size: int, width: int, height: int}
     */
    public function storeImage(UploadedFile $file, string $organizerId, string $kind): array
    {
        if (!$file->isValid()) {
            // Typically PHP's upload_max_filesize / post_max_size rejecting the file before Laravel sees it
            throw new \InvalidArgumentException('The upload failed: ' . $file->getErrorMessage());
        }
        if ($file->getSize() > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Images must be 5 MB or smaller.');
        }

        // Trust the file's actual contents, not its name or the browser-reported type
        $info = @getimagesize($file->getRealPath());
        if ($info === false || !isset(self::FORMATS[$info[2]])) {
            throw new \InvalidArgumentException('Upload a JPEG, PNG or WebP image.');
        }

        [$width, $height] = $info;
        [$minW, $minH] = self::LIMITS[$kind]['min'];
        [$maxW, $maxH] = self::LIMITS[$kind]['max'];
        if ($width < $minW || $height < $minH) {
            throw new \InvalidArgumentException("The image is {$width}x{$height}px; the minimum is {$minW}x{$minH}px.");
        }
        if ($width > $maxW || $height > $maxH) {
            throw new \InvalidArgumentException("The image is {$width}x{$height}px; the maximum is {$maxW}x{$maxH}px.");
        }

        $format = self::FORMATS[$info[2]];
        $bytes = $this->reencode($file->getRealPath(), $info[2]);

        $path = "tenants/{$organizerId}/{$kind}/" . Str::uuid() . '.' . $format['ext'];
        Storage::disk(self::diskName())->put($path, $bytes);

        return ['path' => $path, 'mime' => $format['mime'], 'size' => strlen($bytes), 'width' => $width, 'height' => $height];
    }

    public function delete(?string $path): void
    {
        if ($path && self::isStoredPath($path)) {
            Storage::disk(self::diskName())->delete($path);
        }
    }

    /** Responsive sizes: the longest edge of each variant, in pixels. */
    public const SIZES = ['small' => 400, 'medium' => 800, 'large' => 1600];

    /**
     * The formats variants are written in: WebP always, plus AVIF where this server's GD can encode it.
     *
     * @return list<'webp'|'avif'>
     */
    public static function variantFormats(): array
    {
        return function_exists('imageavif') && (gd_info()['AVIF Support'] ?? false) ? ['webp', 'avif'] : ['webp'];
    }

    /**
     * Writes the responsive variants of a stored original: every size in every supported format, optionally cropped
     * first. The sanitized original is never modified. Variants are stored next to each other under
     * "{kind}-derivatives/", and the returned map is what the database keeps (storage references only).
     *
     * @param array{x: int, y: int, width: int, height: int}|null $crop the area to keep, in percent of the image
     * @return array<string, array{width: int, height: int, webp: string, avif?: string}>
     */
    public function createVariants(string $originalPath, string $organizerId, string $kind, ?array $crop = null): array
    {
        $disk = Storage::disk(self::diskName());
        if (!$disk->exists($originalPath)) {
            throw new \InvalidArgumentException('The original image is no longer available.');
        }
        $previousLimit = ini_get('memory_limit');
        ini_set('memory_limit', '256M');
        $written = [];

        try {
            $source = @imagecreatefromstring($disk->get($originalPath));
            if ($source === false) {
                throw new \InvalidArgumentException('The image could not be decoded to make its smaller sizes.');
            }
            $source = $this->cropByPercent($source, $crop);
            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);
            $base = "tenants/{$organizerId}/{$kind}-derivatives/" . Str::uuid();

            $variants = [];
            foreach (self::SIZES as $size => $maxEdge) {
                $scale = min(1, $maxEdge / max($sourceWidth, $sourceHeight));
                $width = max(1, (int) round($sourceWidth * $scale));
                $height = max(1, (int) round($sourceHeight * $scale));
                $resized = imagecreatetruecolor($width, $height);
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                imagecopyresampled($resized, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

                $variants[$size] = ['width' => $width, 'height' => $height];
                foreach (self::variantFormats() as $format) {
                    ob_start();
                    // AVIF at a quick encoder speed: these are re-made on every upload, not once at build time.
                    $format === 'avif' ? imageavif($resized, null, 55, 8) : imagewebp($resized, null, 82);
                    $bytes = (string) ob_get_clean();
                    $path = "{$base}-{$size}.{$format}";
                    $disk->put($path, $bytes);
                    $written[] = $path;
                    $variants[$size][$format] = $path;
                }
                imagedestroy($resized);
            }
            imagedestroy($source);

            return $variants;
        } catch (\Throwable $e) {
            // A half-made set is worse than none: remove whatever was written before failing.
            foreach ($written as $path) {
                $disk->delete($path);
            }
            throw $e;
        } finally {
            ini_set('memory_limit', $previousLimit);
        }
    }

    /** Deletes every file in a variant map made by createVariants(). */
    public function deleteVariants(?array $variants): void
    {
        foreach ((array) $variants as $variant) {
            foreach (['webp', 'avif'] as $format) {
                $this->delete($variant[$format] ?? null);
            }
        }
    }

    /**
     * The three WebP variants in the shape older callers expect ({small, medium, large} → {path, width, height}).
     *
     * @return array<string, array{path: string, width: int, height: int}>
     */
    public static function webpPaths(array $variants): array
    {
        return array_map(fn (array $v) => ['path' => $v['webp'], 'width' => $v['width'], 'height' => $v['height']], $variants);
    }

    /** Keeps the given area of the image; the crop is in percent of the width and height, clamped to the image. */
    private function cropByPercent(\GdImage $source, ?array $crop): \GdImage
    {
        if (!$crop || !isset($crop['x'], $crop['y'], $crop['width'], $crop['height'])) {
            return $source;
        }
        $w = imagesx($source);
        $h = imagesy($source);
        $x = (int) round($w * max(0, min(99, (int) $crop['x'])) / 100);
        $y = (int) round($h * max(0, min(99, (int) $crop['y'])) / 100);
        $cropW = max(1, min($w - $x, (int) round($w * max(1, min(100, (int) $crop['width'])) / 100)));
        $cropH = max(1, min($h - $y, (int) round($h * max(1, min(100, (int) $crop['height'])) / 100)));
        if ($x === 0 && $y === 0 && $cropW === $w && $cropH === $h) {
            return $source;
        }

        $cropped = imagecreatetruecolor($cropW, $cropH);
        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);
        imagecopy($cropped, $source, 0, 0, $x, $y, $cropW, $cropH);
        imagedestroy($source);

        return $cropped;
    }

    /**
     * Whether a flyer/photo path refers to an upload on the private disk (rather than a bundled /images asset).
     */
    public static function isStoredPath(?string $path): bool
    {
        return is_string($path) && str_starts_with($path, 'tenants/');
    }

    private function reencode(string $sourcePath, int $type): string
    {
        // A 4500x4500 image needs ~80 MB once decoded
        $previousLimit = ini_get('memory_limit');
        ini_set('memory_limit', '256M');

        try {
            $image = match ($type) {
                IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
                IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
                IMAGETYPE_WEBP => @imagecreatefromwebp($sourcePath),
            };
            if ($image === false) {
                throw new \InvalidArgumentException('The image could not be read; it may be corrupt.');
            }

            ob_start();
            match ($type) {
                IMAGETYPE_JPEG => imagejpeg($image, null, 85),
                IMAGETYPE_PNG => (function () use ($image) {
                    imagesavealpha($image, true);
                    imagepng($image, null, 6);
                })(),
                IMAGETYPE_WEBP => imagewebp($image, null, 85),
            };
            $bytes = (string) ob_get_clean();
            imagedestroy($image);

            return $bytes;
        } finally {
            ini_set('memory_limit', $previousLimit);
        }
    }
}
