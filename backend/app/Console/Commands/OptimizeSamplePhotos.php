<?php

namespace App\Console\Commands;

use App\Services\MediaStorageService;
use App\Support\ImageSubjects;
use Illuminate\Console\Command;

/**
 * Makes small, medium and large WebP (and AVIF, where GD supports it) copies of every catalogued sample photo, so
 * pages send a few dozen kilobytes instead of the ~1 MB originals. Originals are never changed. Re-running is safe:
 * existing copies are kept unless --force is given.
 */
class OptimizeSamplePhotos extends Command
{
    protected $signature = 'images:optimize-samples {--force : Remake copies that already exist}';

    protected $description = 'Make responsive WebP/AVIF copies of the catalogued sample photos in public/images/optimized';

    public function handle(): int
    {
        $formats = MediaStorageService::variantFormats();
        $made = 0;
        foreach ((array) config('visual_assets.sample_photos', []) as $photos) {
            foreach ((array) $photos as $photo) {
                $source = public_path($photo['file']);
                if (!is_file($source)) {
                    $this->warn("Missing: {$photo['file']}");
                    continue;
                }
                $image = @imagecreatefromstring((string) file_get_contents($source));
                if ($image === false) {
                    $this->warn("Unreadable: {$photo['file']}");
                    continue;
                }
                foreach (ImageSubjects::SAMPLE_SIZES as $size => $edge) {
                    foreach ($formats as $format) {
                        $target = public_path(ImageSubjects::optimizedPath($photo['file'], $size, $format));
                        if (is_file($target) && !$this->option('force')) {
                            continue;
                        }
                        @mkdir(dirname($target), 0775, true);
                        $scale = min(1, $edge / max(imagesx($image), imagesy($image)));
                        $w = max(1, (int) round(imagesx($image) * $scale));
                        $h = max(1, (int) round(imagesy($image) * $scale));
                        $resized = imagecreatetruecolor($w, $h);
                        imagecopyresampled($resized, $image, 0, 0, 0, 0, $w, $h, imagesx($image), imagesy($image));
                        $format === 'avif' ? imageavif($resized, $target, 55, 6) : imagewebp($resized, $target, 80);
                        imagedestroy($resized);
                        $made++;
                    }
                }
                imagedestroy($image);
                $this->line("Optimized {$photo['file']}");
            }
        }
        $this->info("{$made} file(s) written.");

        return self::SUCCESS;
    }
}
