<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Tickets set aside for one buyer for a short time: while they check out, or as a waitlist offer. */
class TicketHold extends Model
{
    use HasUuids;

    protected $fillable = ['event_id', 'ticket_tier_id', 'quantity', 'user_id', 'source', 'expires_at'];

    protected $casts = ['quantity' => 'integer', 'expires_at' => 'datetime'];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }
}
