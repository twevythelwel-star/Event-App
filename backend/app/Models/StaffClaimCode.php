<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffClaimCode extends Model
{
    use HasFactory;

    protected $table = 'staff_claim_codes';

    protected $fillable = [
        'user_id',
        'code_hash',
        'status',
        'expires_at',
        'redeemed_at',
        'redeemed_by_host_id',
        'assigned_event_id',
        'revoked_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'redeemed_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function redeemedByHost(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redeemed_by_host_id');
    }

    public function assignedEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'assigned_event_id');
    }

    public function isExpired(): bool
    {
        if ($this->status === 'expired') {
            return true;
        }

        if (empty($this->expires_at)) {
            return false;
        }

        $dt = $this->expires_at instanceof \DateTimeInterface
            ? $this->expires_at
            : new \DateTimeImmutable((string) $this->expires_at, new \DateTimeZone('UTC'));

        return $dt < now();
    }

    public function isRedeemed(): bool
    {
        return $this->status === 'redeemed' || !empty($this->redeemed_at);
    }

    public function isRevoked(): bool
    {
        return $this->status === 'revoked' || !empty($this->revoked_at);
    }

    public function isValid(): bool
    {
        return $this->status === 'active'
            && !$this->isExpired()
            && !$this->isRevoked()
            && !$this->isRedeemed();
    }

    public function revoke(): bool
    {
        $this->status = 'revoked';
        $this->revoked_at = now();
        return $this->save();
    }

    public function redeem(User $host, Event $event): bool
    {
        $this->status = 'redeemed';
        $this->redeemed_at = now();
        $this->redeemed_by_host_id = $host->id;
        $this->assigned_event_id = $event->id;
        return $this->save();
    }

    /**
     * Return minimal masked identification to prevent assigning the wrong person
     * without disclosing full name, email, phone, or other profile PII.
     * e.g. "Usain Bolt" => "U*** B."
     */
    public function getMaskedOwnerName(): string
    {
        $user = $this->user;
        if (!$user) {
            return 'Registered User';
        }

        $first = trim((string) ($user->first_name ?? ''));
        $last = trim((string) ($user->last_name ?? ''));

        if (!empty($first) && !empty($last)) {
            $maskedFirst = mb_substr($first, 0, 1) . str_repeat('*', max(1, min(4, mb_strlen($first) - 1)));
            $maskedLast = mb_substr($last, 0, 1) . '.';
            return "{$maskedFirst} {$maskedLast}";
        }

        if (!empty($first)) {
            return mb_substr($first, 0, 1) . '***';
        }

        if (!empty($user->name)) {
            $parts = explode(' ', trim((string) $user->name));
            if (count($parts) > 1) {
                return mb_substr($parts[0], 0, 1) . '*** ' . mb_substr($parts[1], 0, 1) . '.';
            }
            return mb_substr($parts[0], 0, 1) . '***';
        }

        return 'Account Holder (Verified)';
    }
}
