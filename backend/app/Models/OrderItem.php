<?php

namespace App\Models;

use App\Models\Concerns\SyncsMinorUnits;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory, HasUuids, SyncsMinorUnits;

    protected $fillable = [
        'order_id',
        'menu_item_id',
        'ticket_tier_id',
        'name',
        'category',
        'pricing_type',
        'base_price',
        'selected_options_summary',
        'options_total_extra',
        'custom_donation_amount',
        'final_item_price',
        'unit_price_minor',
        'options_minor',
        'line_total_minor',
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
        'unit_price_minor' => 'integer',
        'options_minor' => 'integer',
        'line_total_minor' => 'integer',
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


    protected static function booted(): void
    {
        // Runs after the minor-unit sync, so the line total always follows the frozen unit price and quantity.
        static::saving(function (OrderItem $item) {
            $item->line_total_minor = (int) $item->unit_price_minor * (int) $item->quantity;
        });
    }

    /** @return array<string, string> */
    protected function minorUnitPairs(): array
    {
        return ['final_item_price' => 'unit_price_minor', 'options_total_extra' => 'options_minor'];
    }

    protected function minorUnitCurrency(): string
    {
        return (string) ($this->currency ?: \App\Support\Currency::defaultCode());
    }
}
