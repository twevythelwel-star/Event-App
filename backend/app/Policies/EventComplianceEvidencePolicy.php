<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EventComplianceEvidence;
use App\Models\OrganizerUser;
use App\Models\User;
use App\Support\AdminAuthorization as Admin;

/**
 * Food permits, alcohol licences and other evidence hosts submit for their events' requirements checklists.
 * Compliance managers in scope review them; the event's own hosts and those managers may download the documents;
 * a host may withdraw a submission nobody has started reviewing.
 */
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

    /** The private document: the event's hosts, and compliance managers whose scope covers the event. */
    public function viewDocument(User $user, EventComplianceEvidence $evidence): bool
    {
        $organizerId = $evidence->event?->organizer_id;
        if ($organizerId === null) {
            return false;
        }

        return self::managesEvent($user, $organizerId)
            || (Admin::isComplianceManager($user) && Admin::organizerInScope($user, $organizerId));
    }

    /** A host withdraws their event's submission before review starts (kept, soft deleted, for the audit trail). */
    public function withdraw(User $user, EventComplianceEvidence $evidence): bool
    {
        return $evidence->status === 'submitted' && $evidence->event !== null && self::managesEvent($user, $evidence->event->organizer_id);
    }

    private static function managesEvent(User $user, ?string $organizerId): bool
    {
        return $organizerId !== null && OrganizerUser::where('user_id', $user->id)->where('organizer_id', $organizerId)->where('is_active', true)->exists();
    }
}
