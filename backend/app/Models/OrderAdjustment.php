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

    public const TYPE_REFUND = 'refund';
    public const TYPE_CORRECTION = 'correction';

    protected $fillable = [
        'order_id',
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

    public function amount(): Money
    {
        return Money::ofMinor((int) $this->amount_minor, $this->currency);
    }
}
