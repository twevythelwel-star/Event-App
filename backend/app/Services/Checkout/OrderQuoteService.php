<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Models\Event;
use App\Models\MenuItem;
use App\Services\OrderPlacementException;
use App\Services\PriceCalculationService;
use App\Services\Pricing\ChargeCalculator;
use App\Services\Pricing\OrderTotals;
use App\Support\Money;

/**
 * Read-only price preview for a cart, so the customer sees the complete price (items, discounts, fees,
 * taxes and total) and anything no longer available before confirming. It reserves nothing:
 * OrderPlacementService re-prices, re-applies the same charges and re-checks stock inside a locked
 * transaction when the order is placed. All amounts are integer minor units.
 */
final class OrderQuoteService
{
    public function __construct(
        private readonly ChargeCalculator $charges = new ChargeCalculator(),
    ) {
    }

    /**
     * @param array{tiers?: array<string, int>, items?: array<string, array{quantity: int, choices?: array, amount?: string|float|null}>} $cart
     * @return array{currency: string, lines: list<array>, totals: OrderTotals, subtotal: float, fee: float, tax: float, discount: float, total: float, total_minor: int, problems: list<string>, has_food: bool, empty: bool}
     */
    public function quote(Event $event, array $cart): array
    {
        $currency = PriceCalculationService::lockAuthoritativeCurrency((string) $event->currency);
        $lines = [];
        $problems = [];

        $tiers = collect(TicketTierCatalog::sellable($event))->keyBy('id');
        foreach ($cart['tiers'] ?? [] as $tierId => $quantity) {
            $quantity = (int) $quantity;
            if ($quantity <= 0) {
                continue;
            }
            $tier = $tiers->get((string) $tierId);
            if (!$tier) {
                $problems[] = 'A ticket type in your order is no longer on sale and was left out.';
                continue;
            }
            if ($tier['available'] !== null && $quantity > $tier['available']) {
                $problems[] = $tier['available'] === 0
                    ? "{$tier['name']} is sold out."
                    : "Only {$tier['available']} {$tier['name']} left. Lower the quantity to continue.";
            } elseif ($quantity > $tier['max']) {
                $problems[] = "{$tier['name']}: at most {$tier['max']} per order.";
            }
            $lines[] = $this->line('ticket', $tier['id'], $tier['name'], null, $quantity, (int) $tier['price_minor'], 'Tickets', $currency);
        }

        $itemIds = array_keys($cart['items'] ?? []);
        $menu = $itemIds === []
            ? collect()
            : MenuItem::withoutGlobalScopes()->whereIn('id', $itemIds)->where('event_id', $event->id)->get()->keyBy('id');

        foreach ($cart['items'] ?? [] as $itemId => $entry) {
            $quantity = (int) ($entry['quantity'] ?? 0);
            if ($quantity <= 0) {
                continue;
            }
            $item = $menu->get((string) $itemId);
            if (!$item || !$item->is_active) {
                $problems[] = 'A dish in your order is no longer available and was left out.';
                continue;
            }
            if ($item->available_stock !== null && $quantity > (int) $item->available_stock) {
                $problems[] = (int) $item->available_stock === 0
                    ? "{$item->name} is sold out."
                    : "Only {$item->available_stock} {$item->name} left. Lower the quantity to continue.";
            }
            try {
                $price = PriceCalculationService::calculateItemPrice($item, $currency, (array) ($entry['choices'] ?? []), $entry['amount'] ?? null);
            } catch (OrderPlacementException $e) {
                $problems[] = $e->getMessage();
                continue;
            }
            $detail = $price['selected_options_summary'] ? implode(' · ', $price['selected_options_summary']) : null;
            $lines[] = $this->line('food', $item->id, $item->name, $detail, $quantity, (int) $price['final_price_minor'], $item->category, $currency);
        }

        $totals = OrderTotals::from($currency, $lines, $this->charges->charges($event, $lines, $currency));
        $major = fn (int $minor) => Money::ofMinor($minor, $currency)->toMajorNumber();

        return [
            'currency' => $currency,
            'lines' => $lines,
            'totals' => $totals,
            // Major-unit figures for views and JSON clients that expect numbers; the minor values are authoritative.
            'subtotal' => $major($totals->subtotalMinor),
            'discount' => $major($totals->discountMinor),
            'fee' => $major($totals->feesMinor()),
            'tax' => $major($totals->taxMinor),
            'total' => $major($totals->totalMinor()),
            'total_minor' => $totals->totalMinor(),
            'problems' => array_values(array_unique($problems)),
            'has_food' => collect($lines)->contains(fn ($line) => $line['kind'] === 'food'),
            'empty' => $lines === [],
        ];
    }

    private function line(string $kind, string $id, string $label, ?string $detail, int $quantity, int $unitMinor, ?string $category, string $currency): array
    {
        $lineTotal = $unitMinor * $quantity;

        return [
            'kind' => $kind,
            'id' => $id,
            'label' => $label,
            'detail' => $detail,
            'quantity' => $quantity,
            'unit_minor' => $unitMinor,
            'line_total_minor' => $lineTotal,
            'unit' => Money::ofMinor($unitMinor, $currency)->toMajorNumber(),
            'amount' => Money::ofMinor($lineTotal, $currency)->toMajorNumber(),
            'category' => $category,
            'final_item_price' => Money::ofMinor($unitMinor, $currency)->toMajorNumber(),
            'final_price_minor' => $unitMinor,
        ];
    }

    /**
     * The cart as an OrderPlacementService payload `items` list.
     *
     * @return list<array>
     */
    public function toOrderItems(array $cart): array
    {
        $items = [];
        foreach ($cart['tiers'] ?? [] as $tierId => $quantity) {
            if ((int) $quantity > 0) {
                $items[] = ['menu_item_id' => (string) $tierId, 'quantity' => (int) $quantity];
            }
        }
        foreach ($cart['items'] ?? [] as $itemId => $entry) {
            if ((int) ($entry['quantity'] ?? 0) > 0) {
                $items[] = array_filter([
                    'menu_item_id' => (string) $itemId,
                    'quantity' => (int) $entry['quantity'],
                    'selected_choices' => $entry['choices'] ?? null,
                    'custom_donation_amount' => $entry['amount'] ?? null,
                ], fn ($v) => $v !== null && $v !== []);
            }
        }

        return $items;
    }
}
