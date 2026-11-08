<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HostIdentityVerification;
use App\Models\User;
use App\Support\AdminAuthorization as Admin;

/**
 * Host government ID verifications. Every administrator action needs an explicit grant of identity document access
 * (Admin::canAccessIdentityDocuments); an administrator role alone is never enough. Nobody reviews their own ID.
 */
class HostIdentityVerificationPolicy
{
    public function viewAny(User $user): bool
    {
        return Admin::canAccessIdentityDocuments($user);
    }

    public function view(User $user, HostIdentityVerification $verification): bool
    {
        return $user->id === $verification->user_id || Admin::canAccessIdentityDocuments($user);
    }

    public function review(User $user, HostIdentityVerification $verification): bool
    {
        return Admin::canAccessIdentityDocuments($user) && $user->id !== $verification->user_id;
    }

    public function viewDocument(User $user, HostIdentityVerification $verification): bool
    {
        return Admin::canAccessIdentityDocuments($user) && $user->id !== $verification->user_id;
    }

    /** Emergency purge of stored documents: a system administrator who also holds identity document access. */
    public function lockdown(User $user): bool
    {
        return Admin::isSystemAdmin($user) && Admin::canAccessIdentityDocuments($user);
    }
}
