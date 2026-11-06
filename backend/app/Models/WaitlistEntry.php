<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A buyer waiting for a sold-out ticket type; offered tickets in the order they joined. */
class WaitlistEntry extends Model
{
    use HasUuids;

    protected $fillable = ['event_id', 'ticket_tier_id', 'user_id', 'quantity', 'status', 'hold_id', 'offered_at', 'offer_expires_at'];

    protected $casts = ['quantity' => 'integer', 'offered_at' => 'datetime', 'offer_expires_at' => 'datetime'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withoutGlobalScopes();
    }
}
