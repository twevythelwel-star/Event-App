<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Models\Event;
use App\Services\MediaStorageService;
use Illuminate\Support\Facades\Storage;

/**
 * The images in an event-ticket pass, at Apple's sizes (1x, 2x, 3x), as PNG:
 *   icon  29 x 29     the EventBite mark on the brand navy (notifications, lock screen)
 *   logo  160 x 50    the official EventBite logo, scaled to fit, never redrawn
 *   strip 375 x 98    the host's own uploaded flyer only, cropped around its focal point
 * Library or generated artwork is never used on a pass: there is no room on a Wallet pass to label an illustrative
 * photo, and it must not look like the real event. Rendered images are cached by source.
 */
final class AppleWalletImages
{
    private const NAVY = [7, 17, 31];

    public const SIZES = [
        'icon' => [29, 29],
        'logo' => [160, 50],
        'strip' => [375, 98],
    ];

    /** @return array<string, string> file name in the pass => PNG bytes */
    public function forEvent(?Event $event): array
    {
        $files = [];
        foreach ([1, 2, 3] as $scale) {
            $suffix = $scale === 1 ? '' : "@{$scale}x";
            $files["icon{$suffix}.png"] = $this->cached("icon-{$scale}", fn () => $this->icon($scale));
            $files["logo{$suffix}.png"] = $this->cached("logo-{$scale}", fn () => $this->logo($scale));
        }
        $flyer = $this->hostFlyer($event);
        if ($flyer !== null) {
            foreach ([1, 2, 3] as $scale) {
                $suffix = $scale === 1 ? '' : "@{$scale}x";
                $key = 'strip-' . sha1($flyer['path'] . '|' . $flyer['focus']) . "-{$scale}";
                $strip = $this->cached($key, fn () => $this->strip($flyer['bytes'], $flyer['fx'], $flyer['fy'], $scale));
                if ($strip !== null) {
                    $files["strip{$suffix}.png"] = $strip;
                }
            }
        }

        return array_filter($files, fn ($bytes) => is_string($bytes) && $bytes !== '');
    }

    private function icon(int $scale): ?string
    {
        $size = 29 * $scale;
        $mark = @imagecreatefrompng(public_path('images/brand/eventbite-mark-512.png'));
        if (!$mark) {
            return null;
        }
        $canvas = $this->canvas($size, $size, true);
        $this->fitInto($canvas, $mark, (int) round($size * 0.12), (int) round($size * 0.12), (int) round($size * 0.76), (int) round($size * 0.76));

        return $this->png($canvas);
    }

    private function logo(int $scale): ?string
    {
        $logo = @imagecreatefrompng(public_path('images/brand/eventbite-logo-600.png'));
        if (!$logo) {
            return null;
        }
        [$maxW, $maxH] = [160 * $scale, 50 * $scale];
        $ratio = min($maxW / imagesx($logo), $maxH / imagesy($logo));
        $w = (int) floor(imagesx($logo) * $ratio);
        $h = (int) floor(imagesy($logo) * $ratio);
        $canvas = $this->canvas($w, $h, false);
        imagecopyresampled($canvas, $logo, 0, 0, 0, 0, $w, $h, imagesx($logo), imagesy($logo));

        return $this->png($canvas);
    }

    private function strip(string $bytes, int $fx, int $fy, int $scale): ?string
    {
        $src = @imagecreatefromstring($bytes);
        if (!$src) {
            return null;
        }
        [$w, $h] = [375 * $scale, 98 * $scale];
        $sw = imagesx($src);
        $sh = imagesy($src);
        $ratio = max($w / $sw, $h / $sh);
        $cropW = (int) round($w / $ratio);
        $cropH = (int) round($h / $ratio);
        $x = (int) max(0, min($sw - $cropW, round($sw * $fx / 100 - $cropW / 2)));
        $y = (int) max(0, min($sh - $cropH, round($sh * $fy / 100 - $cropH / 2)));
        $canvas = $this->canvas($w, $h, true);
        imagecopyresampled($canvas, $src, 0, 0, $x, $y, $w, $h, $cropW, $cropH);
        // A gentle darkening so Wallet's white primary text stays readable over any artwork.
        imagefilledrectangle($canvas, 0, 0, $w, $h, imagecolorallocatealpha($canvas, 7, 17, 31, 80));

        return $this->png($canvas);
    }

    /** @return array{path: string, bytes: string, fx: int, fy: int, focus: string}|null */
    private function hostFlyer(?Event $event): ?array
    {
        $path = (string) ($event?->flyer_path ?? '');
        if ($event === null || !MediaStorageService::isStoredPath($path) || $event->flyerIsHidden()) {
            return null;
        }
        try {
            $bytes = Storage::disk(MediaStorageService::diskName())->get($path);
        } catch (\Throwable) {
            return null;
        }
        if (!is_string($bytes) || $bytes === '') {
            return null;
        }
        $fx = (int) ($event->flyer_position_x ?? 50);
        $fy = (int) ($event->flyer_position_y ?? 35);

        return ['path' => $path, 'bytes' => $bytes, 'fx' => $fx, 'fy' => $fy, 'focus' => "{$fx},{$fy}"];
    }

    private function canvas(int $w, int $h, bool $navy): \GdImage
    {
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $fill = $navy ? imagecolorallocate($img, ...self::NAVY) : imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefilledrectangle($img, 0, 0, $w, $h, $fill);
        imagealphablending($img, true);

        return $img;
    }

    private function fitInto(\GdImage $canvas, \GdImage $src, int $x, int $y, int $boxW, int $boxH): void
    {
        $ratio = min($boxW / imagesx($src), $boxH / imagesy($src));
        $w = (int) round(imagesx($src) * $ratio);
        $h = (int) round(imagesy($src) * $ratio);
        imagecopyresampled($canvas, $src, $x + intdiv($boxW - $w, 2), $y + intdiv($boxH - $h, 2), 0, 0, $w, $h, imagesx($src), imagesy($src));
    }

    private function png(\GdImage $img): string
    {
        ob_start();
        imagepng($img, null, 9);

        return (string) ob_get_clean();
    }

    private function cached(string $key, callable $render): ?string
    {
        $dir = (string) config('wallet.apple.image_cache_path');
        $file = $dir . DIRECTORY_SEPARATOR . $key . '.png';
        if ($dir !== '' && is_file($file)) {
            return (string) file_get_contents($file);
        }
        $bytes = $render();
        if (is_string($bytes) && $bytes !== '' && $dir !== '') {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            @file_put_contents($file, $bytes);
        }

        return $bytes;
    }
}
