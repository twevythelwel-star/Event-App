<?php

namespace App\Policies;

use App\Enums\TenantRole;
use App\Models\Event;
use App\Models\OrganizerUser;
use App\Models\User;
use App\Services\TenantContext;

class EventPolicy
{
    /**
     * Intercept all checks: platform admins have global bypass.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->is_platform_admin ?? false) {
            return true;
        }
        return null;
    }

    /**
     * Determine whether the user can view the event.
     */
    public function view(User $user, Event $event): bool
    {
        // Publicly viewable if published, or if user is a member of the event's organizer
        if ($event->is_ordering_open) {
            return true;
        }

        return $this->getUserRoleOnTenant($user, $event->organizer_id) !== null;
    }

    /**
     * Determine whether the user can update the event, flyers, or menu pricing.
     * Boundary: Strictly restricted to EVENT_OWNER.
     */
    public function update(User $user, Event $event): bool
    {
        // Cross-organizer check
        if ($event->organizer_id !== TenantContext::id()) {
            return false;
        }

        $role = $this->getUserRoleOnTenant($user, $event->organizer_id);
        return $role === TenantRole::EVENT_OWNER;
    }

    /**
     * Determine whether the user can create an event for this organizer.
     */
    public function create(User $user): bool
    {
        $tenantId = TenantContext::id();
        if (!$tenantId) return false;

        $role = $this->getUserRoleOnTenant($user, $tenantId);
        return $role === TenantRole::EVENT_OWNER;
    }

    /**
     * Determine whether the user can delete the event.
     */
    public function delete(User $user, Event $event): bool
    {
        if ($event->organizer_id !== TenantContext::id()) {
            return false;
        }

        $role = $this->getUserRoleOnTenant($user, $event->organizer_id);
        return $role === TenantRole::EVENT_OWNER;
    }

    private function getUserRoleOnTenant(User $user, string $organizerId): ?TenantRole
    {
        $membership = OrganizerUser::where('organizer_id', $organizerId)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        return $membership?->role;
    }
}
