<?php

namespace App\Policies;

use App\Enums\AccountType;
use App\Models\User;

class UserPolicy
{
    /**
     * Platform administrators have root oversight.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole(AccountType::SYSTEM_ADMIN)) {
            return true;
        }
        return null;
    }

    /**
     * Determine whether the user can view the list of platform users.
     */
    public function viewAny(User $user): bool
    {
        // System Admin or Delegated Admin with user support permissions
        if ($user->hasRole(AccountType::ADMINISTRATOR)) {
            return $user->adminPermissions?->can_support_orders ?? false;
        }

        return false;
    }

    /**
     * Determine whether the user can view a specific user profile.
     */
    public function view(User $user, User $target): bool
    {
        // Own profile is always viewable
        if ($user->id === $target->id) {
            return true;
        }

        // Delegated Admin can view non-system-admin users for support
        if ($user->hasRole(AccountType::ADMINISTRATOR)) {
            if ($target->hasRole(AccountType::SYSTEM_ADMIN)) {
                return false; // Delegated admins cannot inspect root system admins
            }
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can update personal profile.
     */
    public function update(User $user, User $target): bool
    {
        // Users can edit their own profile
        return $user->id === $target->id;
    }

    /**
     * Determine whether user can activate, suspend, or recover an account.
     */
    public function manageStatus(User $user, User $target): bool
    {
        // Invariant: Nobody can suspend themselves
        if ($user->id === $target->id) {
            return false;
        }

        // Delegated Admin CANNOT suspend or modify System Administrators
        if ($target->hasRole(AccountType::SYSTEM_ADMIN)) {
            return false;
        }

        return $user->hasRole(AccountType::SYSTEM_ADMIN);
    }

    /**
     * Determine whether user can assign roles or elevate privileges.
     * Invariant: Never allow a user to elevate their own privileges.
     * Ordinary Administrators must not be able to create or promote System Administrators.
     */
    public function assignRole(User $user, User $target, AccountType|string $role): bool
    {
        return $user->canAssignRole($target, $role);
    }
}
