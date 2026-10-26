<?php

namespace App\Services;

use App\Models\SecurityAuditLog;
use App\Models\User;
use Illuminate\Support\Str;

class AuditLogService
{
    /**
     * Records an immutable security audit event.
     */
    public static function log(
        string $action,
        string $targetEntity,
        ?string $targetId,
        string $details,
        ?User $actor = null,
        bool $isPiiAccess = false,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): SecurityAuditLog {
        $actorRole = $actor ? $actor->getActiveRole()->value : 'system';

        return SecurityAuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $actor?->id,
            'actor_role' => $actorRole,
            'action' => $action,
            'target_entity' => $targetEntity,
            'target_id' => $targetId,
            'ip_address' => $ipAddress ?? '127.0.0.1',
            'user_agent' => $userAgent ?? 'App/Backend',
            'details' => $details,
            'is_pii_access' => $isPiiAccess,
            'created_at' => now(),
        ]);
    }

    /**
     * Records privileged PII inspection (customer phone, email, payment dispute).
     */
    public static function logPiiAccess(
        User $actor,
        string $targetEntity,
        string $targetId,
        string $reason,
        ?string $ipAddress = null
    ): SecurityAuditLog {
        return self::log(
            action: 'pii_record_viewed',
            targetEntity: $targetEntity,
            targetId: $targetId,
            details: "Privileged PII access by {$actor->name} ({$actor->email}): {$reason}",
            actor: $actor,
            isPiiAccess: true,
            ipAddress: $ipAddress
        );
    }
}
