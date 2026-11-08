<?php

declare(strict_types=1);

namespace App\Events\Compliance;

use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A sensitive compliance action: written to the audit log by WriteComplianceAuditLog. More specific events extend
 * this one (and some also notify people, through SendComplianceNotifications).
 */
class ComplianceActionRecorded implements ComplianceAuditable, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $action,
        public readonly string $targetEntity,
        public readonly ?string $targetId,
        public readonly string $details,
        public readonly ?User $actor = null,
        public readonly ?string $organizerId = null,
        public readonly ?string $eventId = null,
        public readonly bool $isPiiAccess = false,
    ) {
    }

    public function auditEntry(): array
    {
        return [
            'action' => $this->action,
            'target_entity' => $this->targetEntity,
            'target_id' => $this->targetId,
            'details' => $this->details,
            'actor' => $this->actor,
            'organizer_id' => $this->organizerId,
            'event_id' => $this->eventId,
            'is_pii_access' => $this->isPiiAccess,
        ];
    }
}
