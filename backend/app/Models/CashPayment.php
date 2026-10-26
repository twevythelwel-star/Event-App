<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashPayment extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $fillable = [
        'organizer_id',
        'event_id',
        'order_id',
        'transaction_type',
        'receipt_number',
        'customer_name',
        'customer_phone',
        'event_name',
        'order_number',
        'ticket_code',
        'amount',
        'currency',
        'received_at',
        'received_by',
        'notes',
        'original_record_id',
        'reversal_of_id',
        'reversal_reason',
        'reversal_authorized_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'received_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function originalRecord(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_record_id');
    }

    /**
     * Check if this cash record is a reversal.
     */
    public function isReversal(): bool
    {
        return $this->transaction_type === 'reversal';
    }
}
