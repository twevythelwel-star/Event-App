<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CancellationRequest extends Model
{
    use HasUuids;

    protected $fillable = ['order_id', 'organizer_id', 'requested_by', 'reviewed_by', 'status', 'customer_reason', 'review_reason', 'requested_at', 'reviewed_at'];

    protected $casts = ['requested_at' => 'datetime', 'reviewed_at' => 'datetime'];

    public function order(): BelongsTo { return $this->belongsTo(Order::class)->withoutGlobalScopes(); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
