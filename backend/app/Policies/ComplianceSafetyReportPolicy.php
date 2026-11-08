<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Compliance\ComplianceSafetyReport;
use App\Models\User;
use App\Support\AdminAuthorization as Admin;

/** Safety reports: any active signed-in user may file one; compliance managers in scope handle them. */
class ComplianceSafetyReportPolicy
{
    public function create(User $user): bool
    {
        return $user->isActive();
    }

    public function viewAny(User $user): bool
    {
        return Admin::isComplianceManager($user);
    }

    public function update(User $user, ComplianceSafetyReport $report): bool
    {
        return Admin::isComplianceManager($user)
            && ($report->event_id === null ? Admin::organizerScope($user) === null : Admin::organizerInScope($user, $report->event?->organizer_id));
    }
}
