<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventMedia;
use App\Models\MenuItem;
use App\Models\User;

/**
 * Administrators hide (or restore) an image a host uploaded: an event's flyer, a gallery photo or a menu photo.
 * Hiding never deletes the file, so the decision can be reversed; a hidden image is no longer served publicly
 * (MediaController), the generated cover or a plain placeholder shows in its place, and the host still sees it,
 * marked, on their own pages. Every decision is audited with its reason.
 */
class ImageModerationService
{
    public const KINDS = ['flyer', 'gallery', 'menu'];

    /** @return string a short description of what changed, for the confirmation message */
    public function decide(User $admin, Event $event, string $kind, ?string $targetId, string $decision, string $reason): string
    {
        $status = $decision === 'hide' ? 'hidden' : 'approved';
        $reason = trim($reason);

        [$label, $targetEntity, $id] = match ($kind) {
            'flyer' => $this->flyer($event, $status),
            'gallery' => $this->gallery($admin, $event, (string) $targetId, $status, $reason),
            'menu' => $this->menu($event, (string) $targetId, $status),
            default => throw new \InvalidArgumentException('Unknown image type.'),
        };

        AuditLogService::log(
            action: $status === 'hidden' ? 'image_hidden' : 'image_restored',
            targetEntity: $targetEntity,
            targetId: $id,
            details: "{$event->title}: {$label} " . ($status === 'hidden' ? 'hidden' : 'restored') . ". Reason: {$reason}",
            actor: $admin,
            organizerId: (string) $event->organizer_id,
            eventId: (string) $event->id,
        );

        return ucfirst($label) . ($status === 'hidden' ? ' hidden' : ' restored');
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function flyer(Event $event, string $status): array
    {
        if (!MediaStorageService::isStoredPath($event->flyer_path)) {
            throw new \InvalidArgumentException('This event has no uploaded flyer.');
        }
        $event->forceFill(['flyer_moderation_status' => $status])->save();

        return ['the flyer', 'events', (string) $event->id];
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function gallery(User $admin, Event $event, string $mediaId, string $status, string $reason): array
    {
        $media = EventMedia::where('event_id', $event->id)->whereKey($mediaId)->firstOrFail();
        $media->forceFill([
            'moderation_status' => $status,
            'moderated_by' => $admin->id,
            'moderated_at' => now(),
            'moderation_note' => $status === 'hidden' ? mb_substr($reason, 0, 250) : null,
        ])->save();

        return ['a gallery photo', 'event_media', (string) $media->id];
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function menu(Event $event, string $itemId, string $status): array
    {
        $item = MenuItem::withoutGlobalScopes()->where('event_id', $event->id)->whereNull('deleted_at')->whereKey($itemId)->firstOrFail();
        if (!MediaStorageService::isStoredPath($item->image_path)) {
            throw new \InvalidArgumentException('That dish has no uploaded photo.');
        }
        $item->forceFill(['image_moderation_status' => $status])->save();

        return ["the photo of {$item->name}", 'menu_items', (string) $item->id];
    }
}
