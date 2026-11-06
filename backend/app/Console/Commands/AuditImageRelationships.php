<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventMedia;
use App\Models\MenuItem;
use App\Services\MediaStorageService;
use App\Support\ImageSubjects;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Checks every image reference in the database against what it is attached to and reports what guests actually see:
 * the host's upload, a matching sample (labelled illustrative), or the labelled fallback because the image does not
 * show that dish or event, is not catalogued, is hidden or is missing. Read-only: nothing is changed.
 */
class AuditImageRelationships extends Command
{
    protected $signature = 'images:audit {--only-problems : List only references guests do not see as stored}';

    protected $description = 'Report how each stored image reference relates to the dish or event it is attached to';

    public function handle(): int
    {
        $disk = Storage::disk(MediaStorageService::diskName());
        $rows = [];
        $add = function (string $kind, string $subject, string $image, string $verdict, bool $problem) use (&$rows) {
            if ($problem || !$this->option('only-problems')) {
                $rows[] = [$kind, mb_strimwidth($subject, 0, 44, '…'), mb_strimwidth($image, 0, 48, '…'), $verdict];
            }
        };

        foreach (Event::allTenants()->whereNotNull('flyer_path')->get() as $event) {
            $path = (string) $event->flyer_path;
            match (true) {
                MediaStorageService::isStoredPath($path) && !$disk->exists($path) => $add('Flyer', $event->title, $path, 'Upload missing: generated artwork shows', true),
                MediaStorageService::isStoredPath($path) && $event->flyerIsHidden() => $add('Flyer', $event->title, $path, 'Hidden by an administrator', true),
                MediaStorageService::isStoredPath($path) => $add('Flyer', $event->title, $path, "Host's upload shown" . ($event->flyer_variants ? '' : ' (no variants yet)'), false),
                ImageSubjects::isBundled($path) => $add('Flyer', $event->title, $path, 'Stock photo blocked: generated artwork shows', true),
                default => $add('Flyer', $event->title, $path, "Host's linked artwork shown", false),
            };
        }

        foreach (EventMedia::all() as $media) {
            $exists = $disk->exists((string) $media->original_path);
            $add('Gallery', (string) $media->alt_text, (string) $media->original_path,
                !$exists ? 'Upload missing' : ($media->isHidden() ? 'Hidden by an administrator' : "Host's upload shown"), !$exists || $media->isHidden());
        }

        foreach (MenuItem::withoutGlobalScopes()->whereNull('deleted_at')->where(fn ($q) => $q->whereNotNull('image_url')->orWhereNotNull('image_path'))->get() as $item) {
            $url = (string) ($item->image_path ?: $item->image_url);
            if (MediaStorageService::isStoredPath($item->image_path)) {
                $missing = !$disk->exists((string) $item->image_path);
                $add('Menu', $item->name, (string) $item->image_path,
                    $missing ? 'Upload missing: fallback shows' : ($item->imageIsHidden() ? 'Hidden by an administrator' : "Host's upload shown"), $missing || $item->imageIsHidden());
            } elseif (ImageSubjects::isBundled($url)) {
                $sample = ImageSubjects::sample($url);
                match (true) {
                    $sample === null => $add('Menu', $item->name, $url, 'Not catalogued: fallback shows', true),
                    !is_file(public_path($sample['file'])) => $add('Menu', $item->name, $url, 'File missing: fallback shows', true),
                    ImageSubjects::sampleFitsMenuItem($url, $item) => $add('Menu', $item->name, $url, 'Matching sample shown (illustrative)', false),
                    default => $add('Menu', $item->name, $url, "Shows {$sample['dish']}, not this dish: fallback shows", true),
                };
            } else {
                $add('Menu', $item->name, $url, "Host's linked photo shown", false);
            }
        }

        $this->table(['Kind', 'Attached to', 'Image', 'What guests see'], $rows);
        $this->info(count($rows) . ' reference(s) listed.');

        return self::SUCCESS;
    }
}
