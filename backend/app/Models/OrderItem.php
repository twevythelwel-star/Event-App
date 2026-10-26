<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'order_id',
        'menu_item_id',
        'name',
        'category',
        'pricing_type',
        'base_price',
        'selected_options_summary',
        'options_total_extra',
        'custom_donation_amount',
        'final_item_price',
        'currency',
        'quantity',
        'collected_quantity',
        'special_instructions',
        'handover_status',
        'handed_over_at',
        'handed_over_by',
        'station_handed_over',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'options_total_extra' => 'decimal:2',
        'custom_donation_amount' => 'decimal:2',
        'final_item_price' => 'decimal:2',
        'quantity' => 'integer',
        'collected_quantity' => 'integer',
        'selected_options_summary' => 'array',
        'handed_over_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    /**
     * Get remaining uncollected portions.
     */
    public function getRemainingQuantityAttribute(): int
    {
        return max(0, $this->quantity - ($this->collected_quantity ?? 0));
    }
}
