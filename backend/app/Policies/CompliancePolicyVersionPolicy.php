<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Compliance\CompliancePolicyVersion;
use App\Models\User;
use App\Support\AdminAuthorization as Admin;

/** Policy versions: compliance managers draft them; only a system administrator publishes one. Published text is fixed. */
class CompliancePolicyVersionPolicy
{
    public function viewAny(User $user): bool
    {
        return Admin::isComplianceManager($user);
    }

    public function create(User $user): bool
    {
        return Admin::isComplianceManager($user);
    }

    public function update(User $user, CompliancePolicyVersion $version): bool
    {
        return Admin::isComplianceManager($user) && $version->status === 'draft';
    }

    public function publish(User $user, CompliancePolicyVersion $version): bool
    {
        return Admin::isSystemAdmin($user) && $version->status === 'draft';
    }

    public function delete(User $user, CompliancePolicyVersion $version): bool
    {
        return $this->update($user, $version);
    }
}
