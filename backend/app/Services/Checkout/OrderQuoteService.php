<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Models\Event;
use App\Models\MenuItem;
use App\Services\OrderPlacementException;
use App\Services\PriceCalculationService;

/**
 * Read-only price preview for a cart, so the customer sees what they will pay and what is no longer
 * available before submitting. It reserves nothing: OrderPlacementService re-prices and re-checks
 * stock inside a locked transaction when the order is actually placed.
 */
final class OrderQuoteService
{
    /**
     * @param array{tiers?: array<string, int>, items?: array<string, array{quantity: int, choices?: array, amount?: float|null}>} $cart
     * @return array{currency: string, lines: list<array>, subtotal: float, fee: float, total: float, problems: list<string>, has_food: bool, empty: bool}
     */
    public function quote(Event $event, array $cart): array
    {
        $currency = PriceCalculationService::lockAuthoritativeCurrency((string) ($event->currency ?: 'JMD'));
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
            }
            elseif ($quantity > $tier['max']) {
                $problems[] = "{$tier['name']}: at most {$tier['max']} per order.";
            }
            $lines[] = [
                'kind' => 'ticket',
                'id' => $tier['id'],
                'label' => $tier['name'],
                'detail' => null,
                'quantity' => $quantity,
                'unit' => $tier['price'],
                'amount' => round($tier['price'] * $quantity, 2),
                'category' => 'Tickets',
                'final_item_price' => $tier['price'],
            ];
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
                $price = PriceCalculationService::calculateItemPrice(
                    $item,
                    $currency,
                    (array) ($entry['choices'] ?? []),
                    isset($entry['amount']) ? (float) $entry['amount'] : null,
                );
            } catch (OrderPlacementException $e) {
                $problems[] = $e->getMessage();
                continue;
            }
            $lines[] = [
                'kind' => 'food',
                'id' => $item->id,
                'label' => $item->name,
                'detail' => $price['selected_options_summary'] ? implode(' · ', $price['selected_options_summary']) : null,
                'quantity' => $quantity,
                'unit' => $price['final_item_price'],
                'amount' => round($price['final_item_price'] * $quantity, 2),
                'category' => $item->category,
                'final_item_price' => $price['final_item_price'],
            ];
        }

        $hasFood = collect($lines)->contains(fn ($line) => $line['kind'] === 'food');
        $fee = $hasFood ? (float) ($currency === 'JMD' ? ($event->packaging_fee_jmd ?? 0) : ($event->packaging_fee_usd ?? 0)) : 0.0;
        $totals = PriceCalculationService::calculateOrderTotals($lines, $fee, $currency);

        return [
            'currency' => $currency,
            'lines' => $lines,
            'subtotal' => (float) $totals['subtotal'],
            'fee' => (float) $totals['packaging_fee'],
            'total' => (float) $totals['total_amount'],
            'problems' => array_values(array_unique($problems)),
            'has_food' => $hasFood,
            'empty' => $lines === [],
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
