<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventWorkerRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EventWorkerAssignment extends Model
{
    use HasFactory;

    protected $table = 'event_worker_assignments';

    protected $fillable = [
        'organizer_id',
        'event_id',
        'user_id',
        'worker_role',
        'station_assignment',
        'custom_permissions',
        'status',
        'starts_at',
        'expires_at',
        'timezone',
        'accepted_at',
        'declined_at',
        'decline_reason',
        'invite_email',
        'invitation_token_hash',
        'invitation_sent_at',
        'invitation_expires_at',
        'invitation_accepted_at',
        'revoked_at',
        'revoked_by',
        'revocation_reason',
        'assigned_by',
        'is_active',
        'last_active_at',
    ];

    protected $casts = [
        'worker_role' => EventWorkerRole::class,
        'custom_permissions' => 'array',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
        'invitation_sent_at' => 'datetime',
        'invitation_expires_at' => 'datetime',
        'invitation_accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_active_at' => 'datetime',
    ];

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class, 'organizer_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    private function toDateTime(mixed $val): ?\DateTimeInterface
    {
        if (empty($val)) {
            return null;
        }

        if ($val instanceof \DateTimeInterface) {
            return $val;
        }

        try {
            return new \DateTimeImmutable((string) $val, new \DateTimeZone('UTC'));
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check whether this assignment has reached its scheduled start time.
     */
    public function isStarted(): bool
    {
        $dt = $this->toDateTime($this->starts_at);
        if ($dt === null) {
            return true;
        }

        return $dt <= now();
    }

    /**
     * Check whether this assignment is scheduled for future access.
     */
    public function isScheduled(): bool
    {
        $dt = $this->toDateTime($this->starts_at);
        if ($dt === null) {
            return false;
        }

        return $dt > now();
    }

    /**
     * Check whether this assignment has expired.
     */
    public function isExpired(): bool
    {
        $now = now();
        $expireDt = $this->toDateTime($this->expires_at);

        if ($expireDt !== null && $expireDt < $now) {
            return true;
        }

        if ($this->status === 'expired') {
            return true;
        }

        $inviteDt = $this->toDateTime($this->invitation_expires_at);
        if ($this->status === 'pending_invite' && $inviteDt !== null && $inviteDt < $now) {
            return true;
        }

        return false;
    }

    /**
     * Check if this assignment has been revoked.
     */
    public function isRevoked(): bool
    {
        return $this->status === 'revoked' || !empty($this->revoked_at);
    }

    /**
     * Check if this assignment is currently active and within valid time boundaries.
     */
    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if (in_array($this->status, ['pending_acceptance', 'pending_invite', 'declined', 'revoked', 'expired', 'suspended'], true)) {
            return false;
        }

        if ($this->isRevoked() || $this->isExpired() || !$this->isStarted()) {
            return false;
        }

        return true;
    }


    /**
     * Check whether this worker assignment can perform an action.
     * Enforces active state, time interval, and role-specific permissions.
     */
    public function canPerform(string $action): bool
    {
        if (!$this->isCurrentlyActive()) {
            return false;
        }

        $role = $this->worker_role instanceof EventWorkerRole
            ? $this->worker_role
            : EventWorkerRole::tryFrom((string) $this->worker_role);

        if (!$role) {
            return false;
        }

        return $role->canPerform($action, $this->custom_permissions ?? []);
    }

    /**
     * Check whether an action is prohibited for this worker assignment.
     */
    public function isProhibited(string $action): bool
    {
        $role = $this->worker_role instanceof EventWorkerRole
            ? $this->worker_role
            : EventWorkerRole::tryFrom((string) $this->worker_role);

        if (!$role) {
            return true;
        }

        return $role->isProhibited($action);
    }

    /**
     * Sanitize order data according to this worker's role and station assignment.
     *
     * @param array<string, mixed> $orderData
     * @return array<string, mixed>
     */
    public function sanitizeOrder(array $orderData): array
    {
        $role = $this->worker_role instanceof EventWorkerRole
            ? $this->worker_role
            : EventWorkerRole::tryFrom((string) $this->worker_role);

        if (!$role) {
            return [];
        }

        return $role->sanitizeOrderForWorker($orderData, $this->station_assignment ?? 'all');
    }

    /**
     * Accept a pending assignment.
     */
    public function accept(User $worker): bool
    {
        $now = now();
        $this->user_id = $worker->id;
        $this->accepted_at = $now;
        $this->invitation_accepted_at = $now;

        // Transition to scheduled if start time is in the future, otherwise active
        if ($this->isScheduled()) {
            $this->status = 'scheduled';
            $this->is_active = true;
        } else {
            $this->status = 'active';
            $this->is_active = true;
        }

        $this->last_active_at = $now;
        return $this->save();
    }

    /**
     * Decline a pending assignment.
     */
    public function decline(User $worker, string $reason = ''): bool
    {
        $this->user_id = $worker->id;
        $this->status = 'declined';
        $this->is_active = false;
        $this->declined_at = now();
        $this->decline_reason = $reason;
        return $this->save();
    }

    /**
     * Revoke worker assignment immediately.
     */
    public function revoke(User $revokedBy, string $reason = ''): bool
    {
        $this->is_active = false;
        $this->status = 'revoked';
        $this->revoked_at = now();
        $this->revoked_by = $revokedBy->id;
        $this->revocation_reason = $reason;
        return $this->save();
    }
}
