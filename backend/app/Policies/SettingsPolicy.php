<?php

namespace App\Policies;

use App\Enums\AccountType;
use App\Enums\TenantRole;
use App\Models\OrganizerUser;
use App\Models\User;

class SettingsPolicy
{
    /**
     * Platform administrator bypass.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole(AccountType::SYSTEM_ADMIN)) {
            return true;
        }
        return null;
    }

    /**
     * Check if user active role can access the given setting key.
     */
    public function accessSetting(User $user, string $settingKey): bool
    {
        return $user->getActiveRole()->canAccessSetting($settingKey);
    }

    /**
     * Platform configuration is strictly exclusive to System Administrators.
     */
    public function updatePlatformConfiguration(User $user): bool
    {
        return $user->hasRole(AccountType::SYSTEM_ADMIN);
    }

    /**
     * Event Host settings can be updated only by event owners for their own organizer.
     */
    public function updateHostSettings(User $user, string $organizerId): bool
    {
        if ($user->hasRole(AccountType::SYSTEM_ADMIN)) {
            return true;
        }

        if (!$user->hasRole(AccountType::EVENT_HOST)) {
            return false;
        }

        return OrganizerUser::where('organizer_id', $organizerId)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->where('role', TenantRole::EVENT_OWNER->value)
            ->exists();
    }

    /**
     * Granting or altering administrator permissions is strictly restricted to System Administrators.
     * Administrators must not be allowed to grant themselves additional permissions.
     */
    public function grantAdminPermissions(User $user, User $target): bool
    {
        // Administrators cannot grant permissions to anyone, especially themselves
        if (!$user->hasRole(AccountType::SYSTEM_ADMIN)) {
            return false;
        }

        return true;
    }

    /**
     * Attendee display currency preference update.
     */
    public function updateDisplayCurrency(User $user): bool
    {
        return $user->isActive();
    }
}
