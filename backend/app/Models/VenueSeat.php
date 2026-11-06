<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One seat at a table or in a row. It belongs to an order while that order is live; once the order expires, is
 * cancelled or refunded, the seat is free again (SeatingService::isTaken), so no release step can be missed.
 */
class VenueSeat extends Model
{
    use HasUuids;

    /** Orders in these states no longer hold their seats. */
    public const RELEASED_ORDER_STATUSES = ['expired', 'cancelled', 'refunded'];

    protected $fillable = ['event_id', 'area_id', 'label', 'row_label', 'number', 'x', 'y', 'is_blocked', 'is_accessible', 'order_id', 'claimed_at'];

    protected $casts = ['number' => 'integer', 'x' => 'integer', 'y' => 'integer', 'is_blocked' => 'boolean', 'is_accessible' => 'boolean', 'claimed_at' => 'datetime'];

    public function area(): BelongsTo
    {
        return $this->belongsTo(VenueArea::class, 'area_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withoutGlobalScopes();
    }

    /** A spoken name for the seat: "Table 3, seat 4" or "Section A, row B, seat 12". */
    public function spokenName(): string
    {
        $area = $this->relationLoaded('area') ? $this->area : $this->area()->first();

        return trim(($area?->label ? $area->label . ', ' : '') . ($this->row_label ? 'row ' . $this->row_label . ', ' : '') . 'seat ' . $this->number);
    }
}
