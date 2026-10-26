<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketDispute extends Model
{
    use HasFactory, HasUuids;

    // Kept in step with the SPA's DisputeType / DisputeStatus
    public const TYPES = ['ticket_refund', 'lost_qr_code', 'payment_mismatch', 'customer_complaint'];

    public const OPEN_STATUSES = ['open', 'under_review'];

    protected $fillable = [
        'dispute_number',
        'organizer_id',
        'event_id',
        'order_id',
        'user_id',
        'dispute_type',
        'status',
        'amount',
        'currency',
        'reason',
        'assigned_admin_id',
        'assigned_admin_name',
        'resolution_notes',
        'resolved_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'resolved_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class, 'organizer_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }
}
