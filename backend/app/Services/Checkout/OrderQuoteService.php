<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
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
            if ($tier['sale_window'] !== 'open') {
                $problems[] = $tier['sale_window'] === 'ended' ? "Sales for {$tier['name']} have ended." : "{$tier['name']} is not on sale yet.";
            } elseif ($tier['available'] !== null && $quantity > $tier['available']) {
                $problems[] = $tier['available'] === 0
                    ? "{$tier['name']} is sold out."
                    : "Only {$tier['available']} {$tier['name']} left. Lower the quantity to continue.";
            } elseif ($quantity > $tier['max']) {
                $problems[] = "{$tier['name']}: at most {$tier['max']} per order.";
            } elseif ($quantity < $tier['min']) {
                $problems[] = "{$tier['name']}: at least {$tier['min']} per order.";
            }
            $lines[] = $this->line('ticket', $tier['id'], $tier['name'], null, $quantity, (int) $tier['price_minor'], 'Tickets', $currency);
        }

        $hasFoodInCart = ($cart['items'] ?? []) !== [] || ($cart['packages'] ?? []) !== [];
        $menu = !$hasFoodInCart
            ? collect()
            : MenuItem::withoutGlobalScopes()->where('event_id', $event->id)->whereNull('deleted_at')->get()->keyBy('id');
        if ($hasFoodInCart) {
            $state = $event->supports_food_ordering === false
                ? ['open' => false, 'reason' => 'This event is not taking food orders.']
                : $event->foodOrderingState();
            if (!$state['open']) {
                $problems[] = $state['reason'];
            }
        }
        // Units of each item this cart needs, single items and package parts together, checked against stock.
        $needs = [];

        foreach ($cart['items'] ?? [] as $itemId => $entry) {
            $quantity = (int) ($entry['quantity'] ?? 0);
            if ($quantity <= 0) {
                continue;
            }
            $item = $menu->get((string) $itemId);
            if (!$item || !$item->isAvailableAt() || $item->isPackage()) {
                $problems[] = 'A dish in your order is no longer available and was left out.';
                continue;
            }
            if ($item->max_per_order && $quantity > $item->max_per_order) {
                $problems[] = "{$item->name}: at most {$item->max_per_order} per customer.";
            }
            $needs[$item->id] = ($needs[$item->id] ?? 0) + $quantity;
            try {
                $price = PriceCalculationService::calculateItemPrice($item, $currency, (array) ($entry['choices'] ?? []), $entry['amount'] ?? null);
            } catch (OrderPlacementException $e) {
                $problems[] = $e->getMessage();
                continue;
            }
            $detail = $price['selected_options_summary'] ? implode(' · ', $price['selected_options_summary']) : null;
            $lines[] = $this->line('food', $item->id, $item->name, $detail, $quantity, (int) $price['final_price_minor'], $item->category, $currency);
        }

        foreach ($cart['packages'] ?? [] as $entry) {
            $quantity = (int) ($entry['quantity'] ?? 0);
            $package = $menu->get((string) ($entry['menu_item_id'] ?? ''));
            if ($quantity <= 0) {
                continue;
            }
            if (!$package || !$package->isPackage() || !$package->isAvailableAt()) {
                $problems[] = 'A meal package in your order is no longer available and was left out.';
                continue;
            }
            if ($package->max_per_order && $quantity > $package->max_per_order) {
                $problems[] = "{$package->name}: at most {$package->max_per_order} per customer.";
            }
            try {
                $priced = \App\Services\Menu\MealPackage::price($package, (array) ($entry['selection'] ?? []), $currency, $menu);
            } catch (OrderPlacementException $e) {
                $problems[] = $e->getMessage();
                continue;
            }
            $needs[$package->id] = ($needs[$package->id] ?? 0) + $quantity;
            foreach ($priced['parts'] as $part) {
                $needs[$part['item']->id] = ($needs[$part['item']->id] ?? 0) + $part['quantity'] * $quantity;
            }
            $lines[] = $this->line('food', $package->id, $package->name, implode(' · ', $priced['summary']), $quantity, $priced['unit_minor'], $package->category, $currency)
                + ['package' => true];
        }

        if ($hasFoodInCart) {
            $food = (array) ($cart['food'] ?? []);
            if ($event->food_max_orders) {
                $foodOrders = Order::withoutGlobalScopes()
                    ->where('event_id', $event->id)
                    ->whereNotIn('status', ['cancelled', 'refunded'])
                    ->whereHas('items', fn ($query) => $query->whereNotNull('menu_item_id'))
                    ->count();
                if ($foodOrders >= $event->food_max_orders) {
                    $problems[] = 'Food orders for this event are full.';
                }
            }

            $windows = collect($event->pickupWindows())->keyBy(fn ($window) => \App\Services\Events\EventSchedule::windowKey($window));
            if (($food['method'] ?? 'pickup') === 'delivery') {
                if ($event->food_fulfillment !== 'pickup_and_delivery') {
                    $problems[] = 'This event offers food pickup only.';
                } elseif (mb_strlen(trim((string) ($food['delivery_location'] ?? ''))) < 2) {
                    $problems[] = 'Tell us your table number or area for food delivery.';
                }
            } elseif ($windows->isNotEmpty()) {
                $key = (string) ($food['pickup_window'] ?? '');
                $window = $windows->get($key);
                if (!$window) {
                    $problems[] = 'Choose a pickup time for your food.';
                } elseif (!empty($window['max_orders'])) {
                    $taken = Order::withoutGlobalScopes()
                        ->where('event_id', $event->id)
                        ->where('pickup_window', $key)
                        ->whereNotIn('status', ['cancelled', 'refunded'])
                        ->count();
                    if ($taken >= (int) $window['max_orders']) {
                        $problems[] = "The {$window['start']}–{$window['end']} pickup time is full. Choose another.";
                    }
                }
            }
        }

        foreach ($needs as $itemId => $units) {
            $item = $menu->get($itemId);
            if ($item && $item->available_stock !== null && $units > (int) $item->available_stock) {
                $problems[] = (int) $item->available_stock === 0
                    ? "{$item->name} is sold out."
                    : "Only {$item->available_stock} {$item->name} left. Lower the quantity to continue.";
            }
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
                    'special_instructions' => $entry['instructions'] ?? null,
                ], fn ($v) => $v !== null && $v !== []);
            }
        }
        foreach ($cart['packages'] ?? [] as $entry) {
            if ((int) ($entry['quantity'] ?? 0) > 0) {
                $items[] = array_filter([
                    'menu_item_id' => (string) $entry['menu_item_id'],
                    'quantity' => (int) $entry['quantity'],
                    'package' => (array) ($entry['selection'] ?? []),
                    'special_instructions' => $entry['instructions'] ?? null,
                ], fn ($v) => $v !== null);
            }
        }

        return $items;
    }
}
