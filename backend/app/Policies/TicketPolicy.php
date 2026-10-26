<?php

namespace App\Policies;

use App\Enums\TenantRole;
use App\Models\OrganizerUser;
use App\Models\User;
use App\Services\TenantContext;

class TicketPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->is_platform_admin ?? false) {
            return true;
        }
        return null;
    }

    /**
     * Scan and verify ticket QR codes at the collection tent.
     * Allowed for food collectors, payment verifiers, and event owners on this organizer.
     */
    public function scanTicket(User $user, string $ticketOrganizerId): bool
    {
        // Enforce tenant boundary
        if ($ticketOrganizerId !== TenantContext::id()) {
            return false;
        }

        $role = $this->getUserRoleOnTenant($user, $ticketOrganizerId);
        return in_array($role, [
            TenantRole::EVENT_OWNER,
            TenantRole::PAYMENT_VERIFIER,
            TenantRole::FOOD_COLLECTOR,
        ]);
    }

    /**
     * Authorize supervisor override for uncollected or duplicate ticket flags.
     * Boundary: restricted to EVENT_OWNER.
     */
    public function supervisorOverride(User $user, string $ticketOrganizerId): bool
    {
        if ($ticketOrganizerId !== TenantContext::id()) {
            return false;
        }

        $role = $this->getUserRoleOnTenant($user, $ticketOrganizerId);
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
