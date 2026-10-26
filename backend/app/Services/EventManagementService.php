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
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
            ->with(['menuItems' => fn ($q) => $q->withoutGlobalScopes()->whereNull('deleted_at')->where('is_active', true)->orderBy('sort_order')])
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
     * @param array<string, mixed> $data Validated event fields (snake_case)
     */
    public function updateEvent(User $user, string $eventId, array $data): Event
    {
        $event = $this->findEvent($user, $eventId);

        if (array_key_exists('advance_order_deadline', $data)) {
            // Deadlines are entered in the event's own timezone
            $data['advance_order_deadline'] = Carbon::parse($data['advance_order_deadline'], $event->timezone ?: 'America/Jamaica')->utc();
        }
        if (($data['is_ordering_open'] ?? false) === true && $event->status !== 'published') {
            throw new \InvalidArgumentException('Publish the event before opening it for orders.');
        }

        $event->fill($data)->save();
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
            if ($event->menuItems->isEmpty()) {
                throw new \InvalidArgumentException('Cannot publish: add at least one menu item first.');
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

    private function findMenuItem(Event $event, string $itemId): MenuItem
    {
        $item = MenuItem::withoutGlobalScopes()->where('event_id', $event->id)->whereKey($itemId)->first();
        if (!$item) {
            throw (new ModelNotFoundException('Menu item not found.'))->setModel(MenuItem::class);
        }

        return $item;
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
