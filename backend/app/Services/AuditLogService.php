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
        ?string $userAgent = null,
        string $outcome = 'success',
        ?string $organizerId = null,
        ?string $eventId = null
    ): SecurityAuditLog {
        $actorRole = $actor ? $actor->getActiveRole()->value : 'system';
        // The standalone test runner (tests/run_backend_tests.php) has no application container.
        // A bound request exists for real HTTP traffic and HTTP feature tests; in pure console it is
        // an empty request whose headers simply yield no scanner session.
        $request = function_exists('app') && app()->bound('request') ? request() : null;

        // Scanner terminals send a per-device session UUID (X-EventBite-Scanner-Session); correlate it
        // into the record so repeated suspicious scans from one device are visible to investigators.
        $scannerSession = $request?->header('X-EventBite-Scanner-Session');
        if (is_string($scannerSession) && preg_match('/^[a-f0-9-]{36}$/i', $scannerSession)) {
            $details .= ' [scanner_session:' . strtolower($scannerSession) . ']';
        }

        return SecurityAuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $actor?->id,
            'actor_role' => $actorRole,
            'action' => $action,
            'target_entity' => $targetEntity,
            'target_id' => $targetId,
            'ip_address' => $ipAddress ?? $request?->ip() ?? '127.0.0.1',
            'user_agent' => $userAgent ?? substr((string) ($request?->userAgent() ?? 'App/Backend'), 0, 255),
            'details' => $details,
            'is_pii_access' => $isPiiAccess,
            'outcome' => $outcome,
            'organizer_id' => $organizerId,
            'event_id' => $eventId,
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
