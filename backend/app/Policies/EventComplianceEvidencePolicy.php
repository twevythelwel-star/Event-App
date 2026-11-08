<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EventComplianceEvidence;
use App\Models\User;
use App\Support\AdminAuthorization as Admin;

/** Food permits, alcohol licences and other evidence hosts submit: reviewed by compliance managers in scope. */
class EventComplianceEvidencePolicy
{
    public function viewAny(User $user): bool
    {
        return Admin::isComplianceManager($user);
    }

    public function review(User $user, EventComplianceEvidence $evidence): bool
    {
        return Admin::isComplianceManager($user)
            && $evidence->submitted_by !== $user->id
            && Admin::organizerInScope($user, $evidence->event?->organizer_id);
    }
}
