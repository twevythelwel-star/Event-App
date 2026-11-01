<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\TenantRole;
use App\Exceptions\SecurityException;
use App\Models\Event;
use App\Models\MenuItem;
use App\Models\OrganizerUser;
use App\Models\SecurityAuditLog;
use App\Models\User;
use App\Services\LocationAddressNormalizer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Event Host editing of their own events and menus.
 *
 * Only organizer members whose role can manage event configuration (event owners, platform admins) and
 * System Administrators may edit; payment verifiers, collectors and assigned event staff may not.
 * Every lookup is scoped to the user's organizers, so another organizer's event behaves as not found.
 */
class EventManagementService
{
    public const STATUSES = ['draft', 'published', 'unpublished', 'cancelled'];

    public function __construct(
        protected MediaStorageService $media
    ) {
    }

    /**
     * Organizer ids whose events this user may edit, or null for every organizer (System Administrators).
     */
    public function manageableOrganizerIds(User $user): ?Collection
    {
        if ($user->hasRole(AccountType::SYSTEM_ADMIN)) {
            return null;
        }

        return OrganizerUser::where('user_id', $user->id)
            ->where('is_active', true)
            ->get()
            ->filter(function (OrganizerUser $membership) {
                $role = $membership->role instanceof TenantRole ? $membership->role : TenantRole::tryFrom((string) $membership->role);

                return $role?->canManageEventConfiguration() ?? false;
            })
            ->pluck('organizer_id')
            ->values();
    }

    public function eventsQuery(User $user): Builder
    {
        $query = Event::withoutGlobalScopes()
            ->with(['menuItems' => fn($q) => $q->withoutGlobalScopes()->whereNull('deleted_at')->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('advance_order_deadline');

        $organizers = $this->manageableOrganizerIds($user);

        return $organizers === null ? $query : $query->whereIn('organizer_id', $organizers);
    }

    public function findEvent(User $user, string $eventId): Event
    {
        $event = $this->eventsQuery($user)->whereKey($eventId)->first();
        if (!$event) {
            throw (new ModelNotFoundException('Event not found.'))->setModel(Event::class);
        }

        return $event;
    }

    /**
     * Create a new event (defaults to draft status unless explicitly published after passing readiness checks).
     *
     * @param array<string, mixed> $data Validated event fields (snake_case)
     */
    public function createEvent(User $user, array $data): Event
    {
        $organizers = $this->manageableOrganizerIds($user);
        if ($organizers !== null && $organizers->isEmpty()) {
            throw new SecurityException('You are not authorized to create events.');
        }

        $requestedOrgId = $data['organizer_id'] ?? null;
        if ($organizers !== null) {
            if ($requestedOrgId !== null && !$organizers->contains($requestedOrgId)) {
                throw new SecurityException('You may only create events for organizers you manage.');
            }
            $organizerId = $requestedOrgId ?: (string) $organizers->first();
        } else {
            $organizerId = $requestedOrgId ?: 'org-kingston-youth';
        }

        $data = app(LocationAddressNormalizer::class)->normalize($data);
        $data['organizer_id'] = $organizerId;

        $timezone = $data['timezone'] ?? config('events.market.default_timezone', 'America/New_York');
        $this->assertValidTimezone($timezone);

        if (isset($data['ticket_tiers']) && is_array($data['ticket_tiers'])) {
            $this->assertTicketTiersValid($data['ticket_tiers']);
        }

        $deadlineRaw = $data['advance_order_deadline'] ?? now($timezone)->addDays(14)->toIso8601String();
        $deadlineUtc = Carbon::parse($deadlineRaw, $timezone)->utc();

        $title = trim((string) ($data['title'] ?? 'Untitled Community Event'));
        $slug = !empty($data['slug'])
            ? $data['slug']
            : Str::slug($title) . '-' . strtolower(Str::random(6));

        $event = Event::withoutGlobalScopes()->create([
            'id' => 'evt-' . strtolower(Str::random(10)),
            'organizer_id' => $organizerId,
            'slug' => $slug,
            'title' => $title,
            'tagline' => $data['tagline'] ?? '',
            'category' => $data['category'] ?? 'Community',
            'date_str' => $data['date_str'] ?? '',
            'event_date_iso' => $data['event_date_iso'] ?? null,
            'time_str' => $data['time_str'] ?? '',
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'timezone' => $timezone,
            'venue_name' => $data['venue_name'] ?? '',
            'venue_building_name' => $data['venue_building_name'] ?? null,
            'street_address' => $data['street_address'] ?? null,
            'city' => $data['city'] ?? null,
            'state_code' => $data['state_code'] ?? null,
            'target_market_id' => $data['target_market_id'] ?? null,
            'zip_code' => $data['zip_code'] ?? null,
            'country_code' => $data['country_code'] ?? 'US',
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'venue_address' => $data['venue_address'] ?? '',
            'coordinates' => $data['coordinates'] ?? '',
            'description' => $data['description'] ?? '',
            'currency' => $data['currency'] ?? config('events.market.default_currency', 'USD'),
            'organizer_name' => $data['organizer_name'] ?? $user->name,
            'organizer_phone' => $data['organizer_phone'] ?? ($user->phone ?? '+1 (305) 555-0100'),
            'organizer_whatsapp' => $data['organizer_whatsapp'] ?? '',
            'organizer_email' => $data['organizer_email'] ?? $user->email,
            'advance_order_deadline' => $deadlineUtc,
            'is_ordering_open' => false,
            'status' => 'draft',
            'pickup_instructions' => $data['pickup_instructions'] ?? '',
            'packaging_fee_usd' => $data['packaging_fee_usd'] ?? 0,
            'packaging_fee_jmd' => $data['packaging_fee_jmd'] ?? 0,
            'supports_food_ordering' => $data['supports_food_ordering'] ?? true,
            'ticket_tiers' => $data['ticket_tiers'] ?? [],
            'is_flyer_public' => false,
        ]);

        $this->audit($user, 'event_created', 'events', $event->id, "Draft event '{$event->title}' created via Event Wizard.");

        // The event's promotional QR identity and artwork; artwork failure never fails event creation.
        app(\App\Services\EventQr\EventQrService::class)->ensureFor($event);

        return $this->findEvent($user, $event->id);
    }

    /**
     * @param array<string, mixed> $data Validated event fields (snake_case)
     */
    public function updateEvent(User $user, string $eventId, array $data): Event
    {
        $event = $this->findEvent($user, $eventId);
        $data = app(LocationAddressNormalizer::class)->normalize(array_merge([
            'street_address' => $event->street_address,
            'venue_building_name' => $event->venue_building_name,
            'city' => $event->city,
            'state_code' => $event->state_code,
            'zip_code' => $event->zip_code,
            'country_code' => $event->country_code ?? 'US',
            'latitude' => $event->latitude,
            'longitude' => $event->longitude,
            'venue_address' => $event->venue_address,
            'timezone' => $event->timezone,
        ], $data));

        if (array_key_exists('timezone', $data) && $data['timezone'] !== null) {
            $this->assertValidTimezone((string) $data['timezone']);
        }

        if (array_key_exists('ticket_tiers', $data) && is_array($data['ticket_tiers'])) {
            $this->assertTicketTiersValid($data['ticket_tiers']);
        }

        $effectiveTimezone = $data['timezone'] ?? $event->timezone ?: config('events.market.default_timezone', 'America/New_York');

        if (array_key_exists('advance_order_deadline', $data)) {
            // Deadlines are entered in the event's own timezone
            $data['advance_order_deadline'] = Carbon::parse($data['advance_order_deadline'], $effectiveTimezone)->utc();
        }
        if (($data['is_ordering_open'] ?? false) === true && $event->status !== 'published') {
            throw new \InvalidArgumentException('Publish the event before opening it for orders.');
        }

        $before = $event->only(\App\Services\Notifications\NotificationService::EVENT_NOTICE_FIELDS);
        $event->fill($data)->save();
        \App\Services\Notifications\NotificationService::afterEventSaved($event, $before);
        $this->audit($user, 'event_updated', 'events', $event->id, "Event '{$event->title}' updated: " . implode(', ', array_keys($data)) . '.');

        return $this->findEvent($user, $eventId);
    }

    public function setStatus(User $user, string $eventId, string $status): Event
    {
        $event = $this->findEvent($user, $eventId);

        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException("Unsupported event status '{$status}'.");
        }
        if ($event->status === 'cancelled' && $status !== 'cancelled') {
            throw new \InvalidArgumentException('A cancelled event cannot be reopened; create a new event instead.');
        }

        if ($status === 'published') {
            if (trim((string) $event->title) === '' || trim((string) $event->date_str) === '' || trim((string) $event->venue_name) === '') {
                throw new \InvalidArgumentException('Cannot publish: the event needs a title, date and venue.');
            }
            if ($event->menuItems->isEmpty() && empty($event->ticket_tiers)) {
                throw new \InvalidArgumentException('Cannot publish: add at least one menu item first.');
            }
            if (is_array($event->ticket_tiers) && !empty($event->ticket_tiers)) {
                $this->assertTicketTiersValid($event->ticket_tiers);
            }
        }

        $previous = $event->status;
        $event->status = $status;
        $event->is_flyer_public = $status === 'published';
        $event->is_ordering_open = $status === 'published';
        $event->save();

        $this->audit($user, 'event_status_changed', 'events', $event->id, "Event '{$event->title}' status {$previous} -> {$status}.");

        return $this->findEvent($user, $eventId);
    }

    /**
     * @param array<string, mixed> $data Validated menu item fields (snake_case)
     */
    public function createMenuItem(User $user, string $eventId, array $data): Event
    {
        $event = $this->findEvent($user, $eventId);
        $this->assertMenuItemConsistent($data);

        MenuItem::withoutGlobalScopes()->create(array_merge($data, [
            'organizer_id' => $event->organizer_id,
            'event_id' => $event->id,
            'is_active' => true,
            'sort_order' => $data['sort_order'] ?? ((int) $event->menuItems->max('sort_order') + 1),
        ]));

        $this->audit($user, 'menu_item_created', 'menu_items', $event->id, "Menu item '{$data['name']}' added to '{$event->title}'.");

        return $this->findEvent($user, $eventId);
    }

    /**
     * @param array<string, mixed> $data Validated menu item fields (snake_case)
     */
    public function updateMenuItem(User $user, string $eventId, string $itemId, array $data): Event
    {
        $event = $this->findEvent($user, $eventId);
        $item = $this->findMenuItem($event, $itemId);
        $this->assertMenuItemConsistent(array_merge($item->only(['pricing_type', 'custom_option_groups', 'custom_amount_config']), $data));

        // Replacing an uploaded photo with a link (or nothing) removes the stored file
        if (array_key_exists('image_url', $data) && $data['image_url'] !== $item->image_url && $item->image_path) {
            $this->media->delete($item->image_path);
            $data['image_path'] = null;
        }

        $item->fill($data)->save();
        $this->audit($user, 'menu_item_updated', 'menu_items', $item->id, "Menu item '{$item->name}' updated on '{$event->title}'.");

        return $this->findEvent($user, $eventId);
    }

    /**
     * Soft delete: past orders keep their frozen item snapshots.
     */
    public function deleteMenuItem(User $user, string $eventId, string $itemId): Event
    {
        $event = $this->findEvent($user, $eventId);
        $item = $this->findMenuItem($event, $itemId);

        if ($event->status === 'published' && $event->menuItems->count() <= 1) {
            throw new \InvalidArgumentException('A published event needs at least one menu item. Unpublish it first.');
        }

        $item->delete();
        $this->audit($user, 'menu_item_deleted', 'menu_items', $item->id, "Menu item '{$item->name}' removed from '{$event->title}'.");

        return $this->findEvent($user, $eventId);
    }

    public function uploadFlyer(User $user, string $eventId, UploadedFile $file): Event
    {
        $event = $this->findEvent($user, $eventId);
        $stored = $this->media->storeImage($file, $event->organizer_id, 'flyers');
        $previous = $event->flyer_path;

        $event->fill([
            'flyer_path' => $stored['path'],
            'flyer_mime_type' => $stored['mime'],
            'flyer_size_bytes' => $stored['size'],
            'flyer_width' => $stored['width'],
            'flyer_height' => $stored['height'],
            'is_flyer_public' => $event->status === 'published',
        ])->save();
        $this->media->delete($previous);

        $this->audit($user, 'event_flyer_uploaded', 'events', $event->id, "Flyer uploaded for '{$event->title}' ({$stored['width']}x{$stored['height']}, {$stored['size']} bytes).");

        return $this->findEvent($user, $eventId);
    }

    public function removeFlyer(User $user, string $eventId): Event
    {
        $event = $this->findEvent($user, $eventId);
        $previous = $event->flyer_path;

        $event->fill([
            'flyer_path' => null,
            'flyer_thumbnail_path' => null,
            'flyer_mime_type' => null,
            'flyer_size_bytes' => null,
            'flyer_width' => null,
            'flyer_height' => null,
            'is_flyer_public' => false,
        ])->save();
        $this->media->delete($previous);

        $this->audit($user, 'event_flyer_removed', 'events', $event->id, "Flyer removed from '{$event->title}'.");

        return $this->findEvent($user, $eventId);
    }

    public function uploadMenuPhoto(User $user, string $eventId, string $itemId, UploadedFile $file): Event
    {
        $event = $this->findEvent($user, $eventId);
        $item = $this->findMenuItem($event, $itemId);
        $stored = $this->media->storeImage($file, $event->organizer_id, 'menu');
        $previous = $item->image_path;

        $item->image_path = $stored['path'];
        // Version the URL so browsers fetch the new photo instead of a cached one
        $item->image_url = "/api/v1/media/menu/{$item->id}/photo?v=" . substr(sha1($stored['path']), 0, 10);
        $item->save();
        $this->media->delete($previous);

        $this->audit($user, 'menu_photo_uploaded', 'menu_items', $item->id, "Photo uploaded for '{$item->name}' on '{$event->title}'.");

        return $this->findEvent($user, $eventId);
    }

    private function findMenuItem(Event $event, string $itemId): MenuItem
    {
        $item = MenuItem::withoutGlobalScopes()->where('event_id', $event->id)->whereKey($itemId)->first();
        if (!$item) {
            throw (new ModelNotFoundException('Menu item not found.'))->setModel(MenuItem::class);
        }

        return $item;
    }

    private function assertValidTimezone(string $timezone): void
    {
        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            throw new \InvalidArgumentException("Invalid IANA timezone '{$timezone}'.");
        }
    }

    /**
     * Validates ticket tiers pricing, stock availability, and sale windows on the server.
     *
     * @param array<int, array<string, mixed>> $tiers
     */
    private function assertTicketTiersValid(array $tiers): void
    {
        foreach ($tiers as $idx => $tier) {
            if (!is_array($tier)) {
                throw new \InvalidArgumentException("Ticket tier #{$idx} must be an object.");
            }
            $name = trim((string) ($tier['name'] ?? ''));
            if (strlen($name) < 2) {
                throw new \InvalidArgumentException('Each ticket tier requires a name of at least 2 characters.');
            }
            $priceUsd = (float) ($tier['priceUSD'] ?? $tier['price_usd'] ?? -1);
            $priceJmd = (float) ($tier['priceJMD'] ?? $tier['price_jmd'] ?? -1);
            if ($priceUsd < 0 || $priceJmd < 0) {
                throw new \InvalidArgumentException("Ticket tier '{$name}' prices cannot be negative.");
            }
            $stock = (int) ($tier['availableStock'] ?? $tier['available_stock'] ?? 0);
            if ($stock <= 0) {
                throw new \InvalidArgumentException("Ticket tier '{$name}' must have a positive quantity limit.");
            }
            $maxPerCustomer = (int) ($tier['maxPerCustomer'] ?? $tier['max_per_customer'] ?? 1);
            if ($maxPerCustomer < 1 || $maxPerCustomer > $stock) {
                throw new \InvalidArgumentException("Ticket tier '{$name}' maxPerCustomer must be between 1 and availableStock ({$stock}).");
            }
            $saleStart = $tier['saleStartIso'] ?? $tier['sale_start_iso'] ?? null;
            $saleEnd = $tier['saleEndIso'] ?? $tier['sale_end_iso'] ?? null;
            if (!empty($saleStart) && !empty($saleEnd)) {
                $startTs = strtotime((string) $saleStart);
                $endTs = strtotime((string) $saleEnd);
                if ($startTs === false || $endTs === false || $startTs > $endTs) {
                    throw new \InvalidArgumentException("Ticket tier '{$name}' sale start date must be on or before sale end date.");
                }
            }
        }
    }

    /**
     * Customizable items need at least one option group; custom-amount items need a sane min/max range.
     *
     * @param array<string, mixed> $data
     */
    private function assertMenuItemConsistent(array $data): void
    {
        $type = $data['pricing_type'] ?? 'fixed';

        if ($type === 'customizable' && empty($data['custom_option_groups'])) {
            throw new \InvalidArgumentException('Customizable items need at least one option group.');
        }

        if ($type === 'custom_amount') {
            $config = $data['custom_amount_config'] ?? null;
            if (!is_array($config)) {
                throw new \InvalidArgumentException('Custom-amount items need minimum and maximum amounts.');
            }
            foreach (['USD', 'JMD'] as $currency) {
                $min = (float) ($config["min{$currency}"] ?? 0);
                $max = (float) ($config["max{$currency}"] ?? 0);
                if ($min <= 0 || $max < $min) {
                    throw new \InvalidArgumentException("Custom-amount range in {$currency} must have a positive minimum no larger than the maximum.");
                }
            }
        }
    }

    private function audit(User $user, string $action, string $entity, string $targetId, string $details): void
    {
        SecurityAuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'actor_role' => $user->getActiveRole()->value,
            'action' => $action,
            'target_entity' => $entity,
            'target_id' => $targetId,
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'api',
            'details' => $details,
            'is_pii_access' => false,
            'created_at' => now(),
        ]);
    }
}
