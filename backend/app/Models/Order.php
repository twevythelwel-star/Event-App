<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant;

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
        'all_handed_over',
        'closed_at',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'packaging_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
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
}
