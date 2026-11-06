<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventMedia;
use App\Models\MenuItem;
use App\Services\MediaStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Adds responsive variants to images uploaded before variants existed (flyers, gallery photos, menu photos). The
 * uploaded originals are kept exactly as they are; only new files are written and recorded. Missing originals are
 * reported and skipped, never deleted from their records.
 */
class MakeImageVariants extends Command
{
    protected $signature = 'images:make-variants {--dry-run : Only report what would be done}';

    protected $description = 'Write responsive WebP/AVIF variants for uploaded images that have none, keeping the originals';

    public function handle(MediaStorageService $media): int
    {
        $disk = Storage::disk(MediaStorageService::diskName());
        $dry = (bool) $this->option('dry-run');
        $done = 0;
        $missing = 0;

        $make = function (string $label, string $path, string $organizerId, string $kind, callable $save) use ($disk, $dry, $media, &$done, &$missing) {
            if (!$disk->exists($path)) {
                $this->warn("{$label}: original {$path} is missing; left as it is.");
                $missing++;

                return;
            }
            if ($dry) {
                $this->line("{$label}: would make variants.");
                $done++;

                return;
            }
            try {
                $save($media->createVariants($path, $organizerId, $kind));
                $this->line("{$label}: variants made.");
                $done++;
            } catch (\Throwable $e) {
                $this->warn("{$label}: {$e->getMessage()}");
            }
        };

        Event::allTenants()->whereNull('flyer_variants')->where('flyer_path', 'like', 'tenants/%')->each(function (Event $event) use ($make) {
            $make("Flyer of {$event->id}", $event->flyer_path, (string) $event->organizer_id, 'flyers', function (array $variants) use ($event) {
                $event->forceFill(['flyer_variants' => $variants, 'flyer_thumbnail_path' => $event->flyer_thumbnail_path ?: $variants['medium']['webp']])->save();
            });
        });

        EventMedia::whereNull('variants')->each(function (EventMedia $item) use ($make) {
            $event = Event::allTenants()->find($item->event_id);
            if (!$event || !MediaStorageService::isStoredPath($item->original_path)) {
                return;
            }
            $make("Gallery photo {$item->id}", $item->original_path, (string) $event->organizer_id, 'event-media', function (array $variants) use ($item) {
                $item->forceFill(['variants' => $variants])->save();
            });
        });

        MenuItem::withoutGlobalScopes()->whereNull('image_variants')->where('image_path', 'like', 'tenants/%')->each(function (MenuItem $item) use ($make) {
            $event = Event::allTenants()->find($item->event_id);
            if (!$event) {
                return;
            }
            $make("Menu photo of {$item->name}", $item->image_path, (string) $event->organizer_id, 'menu', function (array $variants) use ($item) {
                $item->forceFill(['image_variants' => $variants])->save();
            });
        });

        $this->info(($dry ? 'Would process ' : 'Processed ') . "{$done} image(s); {$missing} original(s) missing.");

        return self::SUCCESS;
    }
}
