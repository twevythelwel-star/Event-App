<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A compliance administrator's suspension of one host's publishing privileges. While it is active the host's events
 * cannot be published (EventPublicationGates) and no administrator override applies. It ends only when lifted.
 */
class HostPublishingSuspension extends Model
{
    protected $fillable = ['user_id', 'reason', 'suspended_by', 'suspended_at', 'review_by', 'lifted_at', 'lifted_by', 'lift_reason'];

    protected $casts = ['suspended_at' => 'datetime', 'review_by' => 'date', 'lifted_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function suspender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

    public function lifter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lifted_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('lifted_at');
    }

    public static function activeFor(int $userId): ?self
    {
        return self::where('user_id', $userId)->active()->latest('suspended_at')->first();
    }
}
