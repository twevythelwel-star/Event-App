<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An auditable money correction on an order: one row per refund (or other adjustment), never edited.
 * amount_minor is the positive amount returned to the customer.
 */
class OrderAdjustment extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Financial adjustment history is append-only.'));
        static::deleting(fn () => throw new \LogicException('Financial adjustment history cannot be deleted.'));
    }

    public const TYPE_REFUND = 'refund';
    public const TYPE_CORRECTION = 'correction';

    protected $fillable = [
        'order_id',
        'payment_id',
        'organizer_id',
        'type',
        'amount_minor',
        'currency',
        'reason',
        'actor_user_id',
        'actor_name',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withoutGlobalScopes();
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function amount(): Money
    {
        return Money::ofMinor((int) $this->amount_minor, $this->currency);
    }
}
