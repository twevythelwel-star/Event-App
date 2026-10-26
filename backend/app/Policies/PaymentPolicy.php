<?php

namespace App\Policies;

use App\Enums\TenantRole;
use App\Models\CashPayment;
use App\Models\OrganizerUser;
use App\Models\User;
use App\Services\TenantContext;

class PaymentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->is_platform_admin ?? false) {
            return true;
        }
        return null;
    }

    /**
     * View cash ledger records or audit details.
     */
    public function viewLedger(User $user, ?string $organizerId = null): bool
    {
        $tenantId = $organizerId ?? TenantContext::id();
        if (!$tenantId) return false;

        $role = $this->getUserRoleOnTenant($user, $tenantId);
        // Food collectors cannot view cash ledgers or revenue totals
        return in_array($role, [TenantRole::EVENT_OWNER, TenantRole::PAYMENT_VERIFIER]);
    }

    /**
     * Record a new cash payment receipt into the ledger.
     */
    public function recordCashReceipt(User $user, ?string $organizerId = null): bool
    {
        $tenantId = $organizerId ?? TenantContext::id();
        if (!$tenantId) return false;

        $role = $this->getUserRoleOnTenant($user, $tenantId);
        return in_array($role, [TenantRole::EVENT_OWNER, TenantRole::PAYMENT_VERIFIER]);
    }

    /**
     * Authorize a cash reversal or adjustment.
     * Boundary: strictly restricted to EVENT_OWNER (cashiers cannot reverse their own entries unilaterally).
     */
    public function authorizeReversal(User $user, ?string $organizerId = null): bool
    {
        $tenantId = $organizerId ?? TenantContext::id();
        if (!$tenantId) return false;

        $role = $this->getUserRoleOnTenant($user, $tenantId);
        return $role === TenantRole::EVENT_OWNER;
    }

    /**
     * Manage bank transfer account numbers, Zelle emails, and Cash App tags.
     * Boundary: strictly restricted to EVENT_OWNER.
     */
    public function managePaymentSettings(User $user, ?string $organizerId = null): bool
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
