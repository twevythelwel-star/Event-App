<?php

namespace App\Policies;

use App\Enums\TenantRole;
use App\Models\Order;
use App\Models\OrganizerUser;
use App\Models\User;
use App\Services\TenantContext;

class OrderPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->is_platform_admin ?? false) {
            return true;
        }
        return null;
    }

    /**
     * Determine whether the user can view the order.
     * Prevents cross-organizer IDOR breaches.
     */
    public function view(User $user, Order $order): bool
    {
        // 1. Attendee viewing own order
        if ($user->hasRole(\App\Enums\AccountType::ATTENDEE)) {
            if (!empty($user->email) && strtolower($order->email ?? '') === strtolower($user->email)) {
                return true;
            }
            if (!empty($user->phone) && !empty($order->customer_phone) && preg_replace('/\D/', '', $order->customer_phone) === preg_replace('/\D/', '', $user->phone)) {
                return true;
            }
        }

        // 2. Strict tenant isolation: order must belong to active tenant
        if ($order->organizer_id !== TenantContext::id()) {
            return false;
        }

        $role = $this->getUserRoleOnTenant($user, $order->organizer_id);
        return $role !== null;
    }

    /**
     * View sensitive customer details (unmasked phone number, email, billing address).
     * Food collectors only receive masked info to maintain customer privacy.
     */
    public function viewCustomerDetails(User $user, Order $order): bool
    {
        if ($order->organizer_id !== TenantContext::id()) {
            return false;
        }

        $role = $this->getUserRoleOnTenant($user, $order->organizer_id);
        return in_array($role, [TenantRole::EVENT_OWNER, TenantRole::PAYMENT_VERIFIER]);
    }

    /**
     * Determine whether the user can verify or reject the payment for this order.
     */
    public function verifyPayment(User $user, Order $order): bool
    {
        if ($order->organizer_id !== TenantContext::id()) {
            return false;
        }

        $role = $this->getUserRoleOnTenant($user, $order->organizer_id);
        return in_array($role, [TenantRole::EVENT_OWNER, TenantRole::PAYMENT_VERIFIER]);
    }

    /**
     * Determine whether the user can record portion collection handover on this order.
     */
    public function collectItems(User $user, Order $order): bool
    {
        if ($order->organizer_id !== TenantContext::id()) {
            return false;
        }

        $role = $this->getUserRoleOnTenant($user, $order->organizer_id);
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
