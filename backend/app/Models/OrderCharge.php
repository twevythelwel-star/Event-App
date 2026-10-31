<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One fee, tax or discount line on an order, frozen at checkout so later rule changes never alter it.
 * amount_minor is always positive; discounts are subtracted when the total is built.
 */
class OrderCharge extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id',
        'kind',
        'label',
        'charge_rule_id',
        'method',
        'rate_bp',
        'basis_minor',
        'amount_minor',
        'currency',
    ];

    protected $casts = [
        'rate_bp' => 'integer',
        'basis_minor' => 'integer',
        'amount_minor' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withoutGlobalScopes();
    }

    public function amount(): Money
    {
        return Money::ofMinor((int) $this->amount_minor, $this->currency);
    }
}
