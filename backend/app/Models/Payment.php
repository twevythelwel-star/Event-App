<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One payment attempt with a processor. The status here is the processor's, as last confirmed server-side;
 * the order's payment status is derived from it. No card data is ever stored.
 */
class Payment extends Model
{
    use HasUuids;

    public const PENDING = 'pending';
    public const PROCESSING = 'processing';
    public const SUCCEEDED = 'succeeded';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';

    /** Attempts that may still take the customer's money: a new attempt must not be opened beside one. */
    public const OPEN = [self::PENDING, self::PROCESSING];

    protected $fillable = [
        'order_id',
        'organizer_id',
        'provider',
        'method',
        'status',
        'amount_minor',
        'currency',
        'amount_received_minor',
        'provider_reference',
        'provider_capture_id',
        'idempotency_key',
        'failure_code',
        'failure_message',
        'metadata',
        'succeeded_at',
        'reconciliation_status',
        'last_reconciled_at',
        'reconciliation_note',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'amount_received_minor' => 'integer',
        'metadata' => 'array',
        'succeeded_at' => 'datetime',
        'last_reconciled_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withoutGlobalScopes();
    }

    public function amount(): Money
    {
        return Money::ofMinor((int) $this->amount_minor, (string) $this->currency);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(OrderAdjustment::class);
    }
}
