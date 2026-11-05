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
        'vendor_assignment',
        'handover_status',
        'handed_over_at',
        'handed_over_by',
        'station_handed_over',
        'line_type',
        'package_line_id',
        'preparation_status',
    ];

    /**
     * Line types: a single food item, a ticket, a meal package (it carries the price) and the package's
     * components (no price of their own; each is prepared and handed over like a single item).
     */
    public const ITEM = 'item';
    public const TICKET = 'ticket';
    public const PACKAGE = 'package';
    public const COMPONENT = 'component';

    public const PREPARATION_STATUSES = ['pending' => 'Not started', 'preparing' => 'Preparing', 'ready' => 'Ready for pickup'];

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

    public function packageLine(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'package_line_id');
    }

    /**
     * Food that is physically prepared and handed over: single items and package components. A package line only
     * groups its components and carries the price, so counting it as well would count the food twice.
     */
    public function isHandoverItem(): bool
    {
        return $this->menu_item_id !== null && $this->line_type !== self::PACKAGE;
    }

    /** Paid when its order is paid; a refunded or cancelled order's items are not payable any more. */
    public function getPaymentStatusAttribute(): string
    {
        $order = $this->order;
        if (!$order) {
            return 'unknown';
        }
        if (in_array($order->status, ['refunded', 'cancelled'], true)) {
            return $order->status;
        }

        return $order->isEligibleForHandover() ? 'paid' : 'unpaid';
    }

    /** Not collected, partly collected, or collected. */
    public function getPickupStatusAttribute(): string
    {
        $collected = (int) ($this->collected_quantity ?? 0);

        return $collected <= 0 ? 'not_collected' : ($collected >= (int) $this->quantity ? 'collected' : 'partly_collected');
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
