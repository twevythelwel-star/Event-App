<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Compliance\ComplianceAuditable;
use App\Services\AuditLogService;

/**
 * Writes every ComplianceAuditable event to the append-only audit log (read back through ComplianceAuditLog).
 * Runs synchronously so the audit entry exists as soon as the action has committed.
 */
class WriteComplianceAuditLog
{
    public function handle(ComplianceAuditable $event): void
    {
        $entry = $event->auditEntry();
        AuditLogService::log(
            action: $entry['action'],
            targetEntity: $entry['target_entity'],
            targetId: $entry['target_id'],
            details: $entry['details'],
            actor: $entry['actor'],
            isPiiAccess: $entry['is_pii_access'],
            organizerId: $entry['organizer_id'],
            eventId: $entry['event_id'],
        );
    }
}
