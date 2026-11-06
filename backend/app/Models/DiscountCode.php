<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A promotional code the host created, with its limits and dates. Codes are stored upper-case. */
class DiscountCode extends Model
{
    use HasUuids;

    protected $fillable = ['event_id', 'code', 'description', 'kind', 'amount', 'applies_to', 'ticket_tier_ids', 'min_tickets', 'max_uses',
        'max_uses_per_customer', 'starts_at', 'ends_at', 'affiliate_id', 'is_active'];

    protected $casts = ['amount' => 'integer', 'ticket_tier_ids' => 'array', 'min_tickets' => 'integer', 'max_uses' => 'integer',
        'max_uses_per_customer' => 'integer', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean'];

    public function redemptions(): HasMany
    {
        return $this->hasMany(DiscountRedemption::class);
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /** Uses by orders that are still live (a cancelled or expired order gives its use back). */
    public function liveUses(?int $userId = null): int
    {
        return DiscountRedemption::query()->join('orders', 'orders.id', '=', 'discount_redemptions.order_id')
            ->where('discount_redemptions.discount_code_id', $this->id)
            ->whereNotIn('orders.status', \App\Services\Checkout\TicketPricing::RELEASED_STATUSES)->whereNull('orders.inventory_released_at')
            ->when($userId !== null, fn ($q) => $q->where('discount_redemptions.user_id', $userId))
            ->count();
    }

    public function summary(string $currency): string
    {
        $off = $this->kind === 'percent' ? "{$this->amount}% off" : \App\Support\Money::formatMinor($this->amount, $currency) . ' off';
        $scope = ['tickets' => 'tickets', 'food' => 'food', 'all' => 'the order'][$this->applies_to] ?? 'tickets';

        return "{$off} {$scope}";
    }
}
