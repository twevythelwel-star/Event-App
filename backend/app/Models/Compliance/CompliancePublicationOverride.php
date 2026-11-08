<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A compliance administrator's decision that one requirement item may not block an event's publication, with a
 * documented reason, an expiry (review) date and an audit trail. Only requirement items can be overridden
 * (EventPublicationGates); it lapses on its review date or when revoked.
 */
class CompliancePublicationOverride extends Model
{
    protected $fillable = ['event_id', 'gate_code', 'gate_message', 'reason', 'granted_by', 'granted_at', 'review_by', 'revoked_by', 'revoked_at', 'revoke_reason'];

    protected $casts = ['granted_at' => 'datetime', 'review_by' => 'date', 'revoked_at' => 'datetime'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id')->withoutGlobalScopes();
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->whereDate('review_by', '>=', now()->toDateString());
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->review_by !== null && !$this->review_by->isBefore(now()->startOfDay());
    }
}
