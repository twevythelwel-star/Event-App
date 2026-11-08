<?php

declare(strict_types=1);

namespace App\Events\Compliance;

use App\Models\EventComplianceEvidence;
use App\Models\User;

/** An administrator started, verified or rejected a permit or licence submission. Audited; the submitter is told. */
final class ComplianceEvidenceReviewed extends ComplianceActionRecorded
{
    public function __construct(public readonly EventComplianceEvidence $evidence, User $reviewer, string $step, ?string $comment = null)
    {
        parent::__construct(
            action: 'compliance_evidence_' . $step,
            targetEntity: 'event_compliance_evidence',
            targetId: (string) $evidence->id,
            details: "{$evidence->item_title} for event {$evidence->event_id}: {$evidence->status}" . ($comment ? ". Comment: {$comment}" : ''),
            actor: $reviewer,
            organizerId: $evidence->event?->organizer_id,
            eventId: (string) $evidence->event_id,
        );
    }
}
