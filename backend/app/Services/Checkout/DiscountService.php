<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Models\DiscountCode;
use App\Models\DiscountRedemption;
use App\Models\Event;
use App\Models\Order;
use App\Services\OrderPlacementException;
use App\Support\Money;

/**
 * Promotional codes. A code is checked when the buyer enters it (to show the saving) and again inside the order's
 * transaction with the code row locked, so a code limited to 100 uses is never used 101 times. The saving becomes a
 * discount line on the order, applied before fees and taxes like the host's other discounts.
 */
final class DiscountService
{
    public static function normalize(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', trim($code)) ?? '');
    }

    /**
     * The code if it can be used for this cart, else an exception saying why.
     *
     * @param list<array{kind: string, id?: string, quantity: int, line_total_minor: int}> $lines quote/order lines
     */
    public static function find(Event $event, string $code, array $lines, ?int $userId, bool $lock = false): DiscountCode
    {
        $code = self::normalize($code);
        $query = DiscountCode::where('event_id', $event->id)->where('code', $code);
        $discount = ($lock ? $query->lockForUpdate() : $query)->first();
        if (!$discount || !$discount->is_active) {
            throw new OrderPlacementException("The code {$code} is not valid for this event.");
        }
        if ($discount->starts_at && $discount->starts_at->isFuture()) {
            throw new OrderPlacementException("The code {$code} can be used from " . $discount->starts_at->copy()->setTimezone($event->timezone ?: config('app.timezone'))->format('M j, g:i A') . '.');
        }
        if ($discount->ends_at && $discount->ends_at->isPast()) {
            throw new OrderPlacementException("The code {$code} has expired.");
        }
        if ($discount->max_uses !== null && $discount->liveUses() >= $discount->max_uses) {
            throw new OrderPlacementException("The code {$code} has been fully used.");
        }
        if ($discount->max_uses_per_customer !== null && $userId !== null && $discount->liveUses($userId) >= $discount->max_uses_per_customer) {
            throw new OrderPlacementException("You have already used the code {$code}.");
        }
        $tickets = array_sum(array_map(fn ($l) => $l['kind'] === 'ticket' ? (int) $l['quantity'] : 0, $lines));
        if ($discount->min_tickets && $tickets < $discount->min_tickets) {
            throw new OrderPlacementException("The code {$code} needs at least {$discount->min_tickets} tickets in the order.");
        }
        if (self::basis($discount, $lines) <= 0) {
            throw new OrderPlacementException("The code {$code} does not apply to anything in your order.");
        }

        return $discount;
    }

    /** What the code takes off this cart, never more than what it applies to. */
    public static function amount(DiscountCode $discount, array $lines): int
    {
        $basis = self::basis($discount, $lines);
        $amount = $discount->kind === 'percent' ? (int) round($basis * min(100, $discount->amount) / 100) : (int) $discount->amount;

        return max(0, min($basis, $amount));
    }

    /**
     * The discount as a charge line for ChargeCalculator (applied with the other discounts, before fees and taxes).
     *
     * @return array{kind: string, label: string, charge_rule_id: null, method: string, rate_bp: ?int, basis_minor: int, amount_minor: int, currency: string, applies_to: string}
     */
    public static function chargeLine(DiscountCode $discount, array $lines, string $currency): array
    {
        return [
            'kind' => 'discount', 'label' => "Code {$discount->code}", 'charge_rule_id' => null,
            'method' => $discount->kind === 'percent' ? 'percent' : 'fixed_per_order',
            'rate_bp' => $discount->kind === 'percent' ? $discount->amount * 100 : null,
            'basis_minor' => self::basis($discount, $lines), 'amount_minor' => self::amount($discount, $lines),
            'currency' => $currency, 'applies_to' => $discount->applies_to,
        ];
    }

    public static function redeem(DiscountCode $discount, Order $order, int $amountMinor): void
    {
        DiscountRedemption::create(['discount_code_id' => $discount->id, 'order_id' => $order->id, 'user_id' => $order->user_id, 'amount_minor' => $amountMinor]);
    }

    private static function basis(DiscountCode $discount, array $lines): int
    {
        $tiers = array_map('strval', (array) ($discount->ticket_tier_ids ?? []));
        $sum = 0;
        foreach ($lines as $line) {
            $kind = $line['kind'] ?? '';
            $applies = match ($discount->applies_to) {
                'food' => $kind === 'food',
                'all' => true,
                default => $kind === 'ticket' && ($tiers === [] || in_array((string) ($line['tier_id'] ?? $line['id'] ?? ''), $tiers, true)),
            };
            if ($applies) {
                $sum += (int) $line['line_total_minor'];
            }
        }

        return $sum;
    }

    /** @return array{code: string, saving: string} for showing an applied code */
    public static function describe(DiscountCode $discount, array $lines, string $currency): array
    {
        return ['code' => $discount->code, 'saving' => Money::formatMinor(self::amount($discount, $lines), $currency)];
    }
}
