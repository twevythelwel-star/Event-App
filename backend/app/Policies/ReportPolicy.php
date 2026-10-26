<?php

namespace App\Policies;

use App\Enums\TenantRole;
use App\Models\OrganizerUser;
use App\Models\User;
use App\Services\TenantContext;

class ReportPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->is_platform_admin ?? false) {
            return true;
        }
        return null;
    }

    /**
     * View executive financial reconciliation reports, gross revenue, and collection rates.
     * Boundary: Strictly restricted to EVENT_OWNER.
     * Cross-organizer access is completely prohibited.
     */
    public function viewFinancialReports(User $user, ?string $organizerId = null): bool
    {
        $tenantId = $organizerId ?? TenantContext::id();
        if (!$tenantId) return false;

        $role = $this->getUserRoleOnTenant($user, $tenantId);
        return $role === TenantRole::EVENT_OWNER;
    }

    /**
     * View kitchen portion prep reports and food handover progress.
     * Accessible by EVENT_OWNER, PAYMENT_VERIFIER, and FOOD_COLLECTOR for operational preparation.
     */
    public function viewKitchenReports(User $user, ?string $organizerId = null): bool
    {
        $tenantId = $organizerId ?? TenantContext::id();
        if (!$tenantId) return false;

        $role = $this->getUserRoleOnTenant($user, $tenantId);
        return in_array($role, [
            TenantRole::EVENT_OWNER,
            TenantRole::PAYMENT_VERIFIER,
            TenantRole::FOOD_COLLECTOR,
        ]);
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
