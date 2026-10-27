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
    public const DISK = 'local';

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
    ];

    /**
     * @param 'flyers'|'menu' $kind
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
        Storage::disk(self::DISK)->put($path, $bytes);

        return ['path' => $path, 'mime' => $format['mime'], 'size' => strlen($bytes), 'width' => $width, 'height' => $height];
    }

    public function delete(?string $path): void
    {
        if ($path && self::isStoredPath($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
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
