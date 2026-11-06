<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A ticket holder passing their order's tickets to someone else. Only a hash of the acceptance token is stored. */
class TicketTransfer extends Model
{
    use HasUuids;

    protected $fillable = ['order_id', 'from_user_id', 'to_email', 'to_user_id', 'token_hash', 'status', 'expires_at', 'accepted_at'];

    protected $casts = ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withoutGlobalScopes();
    }

    public function isOpen(): bool
    {
        return $this->status === 'pending' && $this->expires_at->isFuture();
    }
}
