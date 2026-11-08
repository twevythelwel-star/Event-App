<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * The compliance slice of the append-only security audit log: identity reviews and document access, permit and
 * licence reviews, overrides, suspensions, safety reports, policy changes, access grants and exports. Written by
 * the WriteComplianceAuditLog listener for every ComplianceAuditable event.
 */
class ComplianceAuditLog extends SecurityAuditLog
{
    public const ACTIONS = [
        'compliance_override_granted', 'compliance_override_revoked',
        'compliance_evidence_start', 'compliance_evidence_verify', 'compliance_evidence_reject',
        'compliance_evidence_submitted', 'compliance_evidence_withdrawn', 'compliance_document_downloaded',
        'compliance_event_suspended', 'compliance_event_reinstated', 'compliance_host_suspended', 'compliance_host_reinstated',
        'compliance_safety_report_updated', 'compliance_policy_drafted', 'compliance_policy_published', 'compliance_report_exported',
        'compliance_identity_access_changed', 'host_id_verification_status_changed', 'host_id_temporary_link_issued',
        'host_id_document_viewed', 'host_id_document_purged_by_retention', 'host_id_integrity_mismatch', 'host_id_malware_blocked',
        'security_breach_id_lockdown_executed', 'host_compliance_policies_accepted',
    ];

    protected $table = 'security_audit_logs';

    protected static function booted(): void
    {
        parent::booted(); // keeps the audit log's append-only guards
        static::addGlobalScope('compliance', fn (Builder $query) => $query->whereIn('action', self::ACTIONS));
    }
}
