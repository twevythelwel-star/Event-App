<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One admitted person on an order, with their own admission credential (see AttendeeTicketService).
 *
 * The raw credential is never stored: only its SHA-256 hash (token_hash) and the nonce it is derived from.
 *
 * @property string $id
 * @property string $order_id
 * @property string $event_id
 * @property int $sequence
 * @property string $ticket_number
 * @property int $token_version
 * @property string $qr_status
 * @property array<string, mixed>|null $qr_design
 */
class AttendeeTicket extends Model
{
    use HasUuids;

    public const QR_PENDING = 'pending';
    public const QR_READY = 'ready';
    public const QR_FAILED = 'failed';

    protected $fillable = [
        'order_id',
        'event_id',
        'organizer_id',
        'order_item_id',
        'ticket_tier_id',
        'sequence',
        'ticket_number',
        'credential_nonce',
        'token_hash',
        'token_version',
        'token_rotated_at',
        'checked_in_at',
        'checked_in_by',
        'check_in_request_id',
        'qr_status',
        'qr_design',
        'qr_design_hash',
        'qr_attempts',
        'qr_failure_reason',
        'qr_generated_at',
    ];

    protected $hidden = ['credential_nonce', 'token_hash'];

    protected $casts = [
        'sequence' => 'integer',
        'token_version' => 'integer',
        'token_rotated_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'qr_design' => 'array',
        'qr_attempts' => 'integer',
        'qr_generated_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withoutGlobalScopes();
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function isAdmitted(): bool
    {
        return $this->checked_in_at !== null;
    }
}
