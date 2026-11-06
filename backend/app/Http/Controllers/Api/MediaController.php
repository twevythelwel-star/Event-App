<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventMedia;
use App\Models\MenuItem;
use App\Services\EventManagementService;
use App\Services\MediaStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves uploaded flyers and menu photos from private storage.
 *
 * Anyone may see images of published events; images of draft, unpublished or cancelled events are only
 * served to accounts that can manage that event. Everything else is a plain 404 so unpublished events
 * cannot be discovered by probing ids.
 */
class MediaController extends Controller
{
    public function __construct(
        protected EventManagementService $events
    ) {}

    public function flyer(Request $request, string $eventId): Response
    {
        $event = Event::withoutGlobalScopes()->find($eventId);
        if (!$event || !MediaStorageService::isStoredPath($event->flyer_path) || !$this->canView($request, $event, $event->flyerIsHidden())) {
            abort(404);
        }

        [$path, $mime] = $this->pick($request, $event->flyer_variants, $event->flyer_path, $event->flyer_mime_type, $event->flyer_thumbnail_path);

        return $this->serve($path, $mime, $this->isPublic($event, $event->flyerIsHidden()));
    }

    public function menuPhoto(Request $request, string $itemId): Response
    {
        $item = MenuItem::withoutGlobalScopes()->whereNull('deleted_at')->find($itemId);
        $event = $item?->event_id ? Event::withoutGlobalScopes()->find($item->event_id) : null;
        if (!$item || !$event || !MediaStorageService::isStoredPath($item->image_path) || !$this->canView($request, $event, $item->imageIsHidden())) {
            abort(404);
        }

        [$path, $mime] = $this->pick($request, $item->image_variants, $item->image_path, null, null);

        return $this->serve($path, $mime, $this->isPublic($event, $item->imageIsHidden()));
    }

    public function eventMedia(Request $request, string $mediaId): Response
    {
        $media = EventMedia::find($mediaId);
        $event = $media?->event_id ? Event::withoutGlobalScopes()->find($media->event_id) : null;
        if (!$media || !$event || !$this->canView($request, $event, $media->isHidden())) {
            abort(404);
        }

        // Uploaded before formats were recorded: the single-path WebP columns stand in for the variant map.
        $variants = $media->variants ?: array_filter([
            'small' => $media->small_thumbnail_path ? ['webp' => $media->small_thumbnail_path] : null,
            'medium' => ($media->medium_thumbnail_path ?? $media->thumbnail_path) ? ['webp' => $media->medium_thumbnail_path ?? $media->thumbnail_path] : null,
            'large' => ($media->large_thumbnail_path ?? $media->thumbnail_path) ? ['webp' => $media->large_thumbnail_path ?? $media->thumbnail_path] : null,
        ]);
        [$path, $mime] = $this->pick($request, $variants, $media->original_path, $media->mime_type, $media->thumbnail_path);

        return $this->serve($path, $mime, $this->isPublic($event, $media->isHidden()));
    }

    public function sponsorLogo(Request $request, string $sponsorId): Response
    {
        $sponsor = \App\Models\EventSponsor::find($sponsorId);
        $event = $sponsor ? Event::withoutGlobalScopes()->find($sponsor->event_id) : null;
        if (!$sponsor || !$event || !MediaStorageService::isStoredPath($sponsor->logo_path) || !$this->canView($request, $event)) {
            abort(404);
        }
        [$path, $mime] = $this->pick($request, $sponsor->logo_variants, $sponsor->logo_path, null, null);

        return $this->serve($path, $mime, $this->isPublic($event, false));
    }

    public function performerPhoto(Request $request, string $performerId): Response
    {
        $performer = \App\Models\EventPerformer::find($performerId);
        $event = $performer ? Event::withoutGlobalScopes()->find($performer->event_id) : null;
        if (!$performer || !$event || !MediaStorageService::isStoredPath($performer->photo_path) || !$this->canView($request, $event)) {
            abort(404);
        }
        [$path, $mime] = $this->pick($request, $performer->photo_variants, $performer->photo_path, null, null);

        return $this->serve($path, $mime, $this->isPublic($event, false));
    }

    /**
     * The file for ?variant=small|medium|large|thumbnail and ?format=webp|avif; anything else is the original.
     * A missing format falls back to WebP, and a missing size to the original, so a link never breaks.
     *
     * @return array{0: string, 1: ?string} path and MIME type
     */
    private function pick(Request $request, ?array $variants, string $original, ?string $originalMime, ?string $thumbnail): array
    {
        $variant = (string) $request->query('variant', 'original');
        if ($variant === 'thumbnail') {
            $variant = 'medium';
        }
        if (!array_key_exists($variant, MediaStorageService::SIZES)) {
            return [$original, $originalMime];
        }
        $format = $request->query('format') === 'avif' ? 'avif' : 'webp';
        $path = $variants[$variant][$format] ?? $variants[$variant]['webp'] ?? ($variant === 'medium' ? $thumbnail : null);
        if ($path === null) {
            return [$original, $originalMime];
        }

        return [$path, str_ends_with($path, '.avif') ? 'image/avif' : 'image/webp'];
    }

    /**
     * Published events' images are public. Unpublished events' images, and images an administrator hid, are only
     * served to accounts that manage the event (and administrators), so they can still see what was taken down.
     */
    private function canView(Request $request, Event $event, bool $hidden = false): bool
    {
        if ($event->status === 'published' && !$hidden) {
            return true;
        }

        $user = $request->user();
        if ($user === null) {
            return false;
        }

        // The event's managers, and administrators allowed to oversee it (so they can review what they moderate).
        return $this->events->eventsQuery($user)->whereKey($event->id)->exists()
            || \Illuminate\Support\Facades\Gate::forUser($user)->allows(\App\Support\AdminAuthorization::EVENT_VIEW, $event);
    }

    private function isPublic(Event $event, bool $hidden): bool
    {
        return $event->status === 'published' && !$hidden;
    }

    private function serve(string $path, ?string $mime, bool $isPublic): Response
    {
        $disk = Storage::disk(MediaStorageService::diskName());
        if (!$disk->exists($path)) {
            abort(404);
        }

        // Unpublished or hidden images must never be stored by a browser or CDN. A public image whose link carries the
        // version of its stored file (?v=, as every page link does) never changes at that URL, so a CDN in front of
        // the application may keep it for a year; an unversioned link is cached briefly (config/media.php).
        $versioned = request()->filled('v');
        $cache = !$isPublic ? 'private, no-store'
            : ($versioned ? 'public, max-age=' . (int) config('media.public_max_age', 31536000) . ', immutable'
                : 'public, max-age=' . (int) config('media.unversioned_max_age', 300));

        return $disk->response($path, null, [
            'Content-Type' => $mime ?: $disk->mimeType($path),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
            'Cache-Control' => $cache,
        ]);
    }
}
