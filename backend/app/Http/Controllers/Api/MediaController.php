<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
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
        if (!$event || !MediaStorageService::isStoredPath($event->flyer_path) || !$this->canView($request, $event)) {
            abort(404);
        }

        return $this->serve($event->flyer_path, $event->flyer_mime_type, $event->status === 'published');
    }

    public function menuPhoto(Request $request, string $itemId): Response
    {
        $item = MenuItem::withoutGlobalScopes()->whereNull('deleted_at')->find($itemId);
        $event = $item?->event_id ? Event::withoutGlobalScopes()->find($item->event_id) : null;
        if (!$item || !$event || !MediaStorageService::isStoredPath($item->image_path) || !$this->canView($request, $event)) {
            abort(404);
        }

        return $this->serve($item->image_path, null, $event->status === 'published');
    }

    private function canView(Request $request, Event $event): bool
    {
        if ($event->status === 'published') {
            return true;
        }

        $user = $request->user();

        return $user !== null && $this->events->eventsQuery($user)->whereKey($event->id)->exists();
    }

    private function serve(string $path, ?string $mime, bool $isPublic): Response
    {
        $disk = Storage::disk(MediaStorageService::DISK);
        if (!$disk->exists($path)) {
            abort(404);
        }

        return $disk->response($path, null, [
            'Content-Type' => $mime ?: $disk->mimeType($path),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
            // Unpublished images must not linger in shared caches
            'Cache-Control' => $isPublic ? 'public, max-age=300' : 'private, no-store',
        ]);
    }
}
