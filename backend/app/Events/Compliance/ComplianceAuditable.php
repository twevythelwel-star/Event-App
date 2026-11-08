<?php

declare(strict_types=1);

namespace App\Events\Compliance;

/**
 * A compliance event that must be written to the audit log (WriteComplianceAuditLog). Events are dispatched after
 * the surrounding database transaction commits, so a rolled-back action leaves no audit entry or notification.
 */
interface ComplianceAuditable
{
    /** @return array{action: string, target_entity: string, target_id: ?string, details: string, actor: ?\App\Models\User, organizer_id: ?string, event_id: ?string, is_pii_access: bool} */
    public function auditEntry(): array;
}
