<?php

namespace App\Models;

use App\Models\Concerns\SyncsMinorUnits;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant, SyncsMinorUnits;

    protected $fillable = [
        'organizer_id',
        'event_id',
        'user_id',
        'ticket_code',
        'order_number',
        'customer_name',
        'customer_phone',
        'email',
        'method',
        'subtotal',
        'packaging_fee',
        'total_amount',
        'amount_paid',
        'subtotal_minor',
        'discount_minor',
        'fees_minor',
        'tax_minor',
        'total_minor',
        'amount_paid_minor',
        'refunded_minor',
        'currency',
        'status',
        'fulfillment_status',
        'payment_reference',
        'sender_name',
        'verified_at',
        'verified_by',
        'verification_notes',
        'rejection_reason',
        'refund_details',
        'authorized_unpaid',
        'secure_ticket_token',
        'idempotency_key',
        'all_handed_over',
        'closed_at',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'packaging_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'subtotal_minor' => 'integer',
        'discount_minor' => 'integer',
        'fees_minor' => 'integer',
        'tax_minor' => 'integer',
        'total_minor' => 'integer',
        'amount_paid_minor' => 'integer',
        'refunded_minor' => 'integer',
        'refund_details' => 'array',
        'authorized_unpaid' => 'boolean',
        'all_handed_over' => 'boolean',
        'verified_at' => 'datetime',
        'closed_at' => 'datetime',
        'checked_in_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function cashPayments(): HasMany
    {
        return $this->hasMany(CashPayment::class, 'order_id');
    }

    public function handoverLogs(): HasMany
    {
        return $this->hasMany(HandoverAuditLog::class, 'order_id');
    }

    /**
     * Check if this order is verified and ready for food handover.
     */
    public function isEligibleForHandover(): bool
    {
        if ($this->status === 'refunded' || $this->status === 'rejected') {
            return false;
        }

        return $this->status === 'paid' || $this->authorized_unpaid === true;
    }


    /** @return array<string, string> */
    protected function minorUnitPairs(): array
    {
        // packaging_fee is the legacy name for all fees on the order (host and platform), kept for older readers.
        return ['subtotal' => 'subtotal_minor', 'packaging_fee' => 'fees_minor', 'total_amount' => 'total_minor', 'amount_paid' => 'amount_paid_minor'];
    }

    protected function minorUnitCurrency(): string
    {
        return (string) ($this->currency ?: \App\Support\Currency::defaultCode());
    }

    public function charges(): HasMany
    {
        return $this->hasMany(OrderCharge::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(OrderAdjustment::class);
    }

    /** What is still owed: total less what has been received. Never negative. */
    public function balanceMinor(): int
    {
        return max(0, (int) $this->total_minor - (int) $this->amount_paid_minor);
    }
}
