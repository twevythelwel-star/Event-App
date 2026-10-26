<?php

namespace App\Policies;

use App\Enums\TenantRole;
use App\Models\OrganizerUser;
use App\Models\User;
use App\Services\TenantContext;

class StaffPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->is_platform_admin ?? false) {
            return true;
        }
        return null;
    }

    /**
     * View staff roster for the organizer.
     */
    public function viewRoster(User $user, ?string $organizerId = null): bool
    {
        $tenantId = $organizerId ?? TenantContext::id();
        if (!$tenantId) return false;

        $role = $this->getUserRoleOnTenant($user, $tenantId);
        // Event owners and payment verifiers can view the team roster
        return in_array($role, [TenantRole::EVENT_OWNER, TenantRole::PAYMENT_VERIFIER]);
    }

    /**
     * Invite, assign roles, or remove staff members.
     * Boundary: Strictly restricted to EVENT_OWNER.
     */
    public function manageStaff(User $user, ?string $organizerId = null): bool
    {
        $tenantId = $organizerId ?? TenantContext::id();
        if (!$tenantId) return false;

        $role = $this->getUserRoleOnTenant($user, $tenantId);
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
