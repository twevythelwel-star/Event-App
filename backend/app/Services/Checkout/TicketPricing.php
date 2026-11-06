<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Models\Event;
use App\Models\OrderItem;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Smarter ticket prices and releases, read from the host's ticket type (events.ticket_tiers). All optional:
 *
 * - price_steps: [{up_to, price_minor}] — dynamic pricing by tickets sold, e.g. the first 50 at $15, up to 150 at
 *   $20, up to 200 at $25. A purchase that crosses a step is priced ticket by ticket, so nobody pays the next price
 *   for a ticket that was still at the lower one, and the price advances by itself as tickets sell.
 * - early_bird_price_minor + early_bird_ends_iso: a lower price until a date (any currency). The event-wide
 *   early-bird period (TicketTierCatalog::isEarlyBird with earlyBirdPriceUSD) keeps working as before.
 * - releases: [{quantity, opens_iso}] — limited releases: only the tickets released so far can be sold, e.g. 20 VIP
 *   tickets now and 20 more next Friday.
 * - group_discounts: [{min_quantity, percent}] — a lower price per ticket when buying several of this type.
 *
 * Tickets "sold" are those on live orders (not expired, cancelled or refunded). Callers that sell hold the event row
 * lock, so the count and the stock cannot change underneath them.
 */
final class TicketPricing
{
    /** Orders in these states no longer count as sold. */
    public const RELEASED_STATUSES = ['expired', 'cancelled', 'refunded'];

    /** @return array<string, int> tickets sold per ticket type on live orders */
    public static function soldByTier(Event $event): array
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.event_id', $event->id)
            ->whereNull('orders.deleted_at')
            ->whereNotIn('orders.status', self::RELEASED_STATUSES)
            ->whereNull('orders.inventory_released_at')
            ->whereNotNull('order_items.ticket_tier_id')
            ->groupBy('order_items.ticket_tier_id')
            ->select('order_items.ticket_tier_id', DB::raw('SUM(order_items.quantity) as sold'))
            ->pluck('sold', 'order_items.ticket_tier_id')
            ->map(fn ($n) => (int) $n)->all();
    }

    /**
     * How many of this ticket type may still be sold under its release schedule (null: no schedule).
     */
    public static function releasedRemaining(array $tier, int $sold, ?int $now = null): ?int
    {
        $releases = self::releases($tier);
        if ($releases === []) {
            return null;
        }
        $now ??= now()->getTimestamp();
        $released = 0;
        foreach ($releases as $release) {
            if ($release['opens'] === null || $release['opens'] <= $now) {
                $released += $release['quantity'];
            }
        }

        return max(0, $released - $sold);
    }

    /** The next release that has not opened yet, for "20 more on Friday at 10 AM". */
    public static function nextRelease(array $tier, ?int $now = null): ?array
    {
        $now ??= now()->getTimestamp();
        foreach (self::releases($tier) as $release) {
            if ($release['opens'] !== null && $release['opens'] > $now) {
                return $release;
            }
        }

        return null;
    }

    /**
     * Prices `quantity` tickets of this type, splitting across price steps when the purchase crosses one.
     *
     * @return list<array{quantity: int, unit_price_minor: int, label: ?string}>
     */
    public static function lines(array $tier, string $currency, ?Event $event, int $quantity, int $sold, ?int $now = null): array
    {
        if ($quantity <= 0) {
            return [];
        }
        $now ??= now()->getTimestamp();
        $base = TicketTierCatalog::currentPriceMinor($tier, $currency, $event, $now);
        if ($base === null) {
            return [];
        }

        // A dated early-bird price on the ticket type itself wins while it runs.
        $early = self::tierEarlyBird($tier, $currency, $now);
        $lines = [];
        if ($early !== null && $early < $base) {
            $lines[] = ['quantity' => $quantity, 'unit_price_minor' => $early, 'label' => 'Early-bird price'];
        } elseif (($steps = self::steps($tier, $currency)) !== [] && $base === TicketTierCatalog::priceMinorFor($tier, $currency)) {
            // Price steps by tickets sold (only when no event-wide early-bird price applies).
            $position = $sold;
            $left = $quantity;
            foreach ($steps as $i => $step) {
                if ($left === 0) {
                    break;
                }
                $isLast = $i === array_key_last($steps);
                if (!$isLast && $position >= $step['up_to']) {
                    continue;
                }
                $take = $isLast ? $left : min($left, $step['up_to'] - $position);
                if ($take > 0) {
                    $lines[] = ['quantity' => $take, 'unit_price_minor' => $step['price_minor'], 'label' => count($steps) > 1 ? 'Price step ' . ($i + 1) : null];
                    $left -= $take;
                    $position += $take;
                }
            }
        } else {
            $lines[] = ['quantity' => $quantity, 'unit_price_minor' => $base, 'label' => null];
        }

        // Group price: a percentage off every ticket of this type when buying enough of them.
        $group = self::groupPercent($tier, $quantity);
        if ($group > 0) {
            foreach ($lines as &$line) {
                $line['unit_price_minor'] = (int) round($line['unit_price_minor'] * (100 - $group) / 100);
                $line['label'] = trim(($line['label'] ? $line['label'] . ', ' : '') . "group price ({$group}% off)");
            }
            unset($line);
        }

        return self::merge($lines);
    }

    /** The price the next ticket would sell at (for display). */
    public static function nextPriceMinor(array $tier, string $currency, ?Event $event, int $sold, ?int $now = null): ?int
    {
        return self::lines($tier, $currency, $event, 1, $sold, $now)[0]['unit_price_minor'] ?? null;
    }

    /** @return list<array{up_to: int, price_minor: int}> valid steps in order */
    public static function steps(array $tier, string $currency): array
    {
        $steps = [];
        foreach ((array) ($tier['price_steps'] ?? []) as $step) {
            $upTo = (int) ($step['up_to'] ?? 0);
            $price = self::minor($step, $currency);
            if ($upTo > 0 && $price !== null) {
                $steps[] = ['up_to' => $upTo, 'price_minor' => $price];
            }
        }
        usort($steps, fn ($a, $b) => $a['up_to'] <=> $b['up_to']);

        return $steps;
    }

    /** @return list<array{min_quantity: int, percent: int}> */
    public static function groupDiscounts(array $tier): array
    {
        $rules = [];
        foreach ((array) ($tier['group_discounts'] ?? []) as $rule) {
            $min = (int) ($rule['min_quantity'] ?? 0);
            $percent = (int) ($rule['percent'] ?? 0);
            if ($min >= 2 && $percent > 0 && $percent < 100) {
                $rules[] = ['min_quantity' => $min, 'percent' => $percent];
            }
        }
        usort($rules, fn ($a, $b) => $a['min_quantity'] <=> $b['min_quantity']);

        return $rules;
    }

    public static function groupPercent(array $tier, int $quantity): int
    {
        $percent = 0;
        foreach (self::groupDiscounts($tier) as $rule) {
            if ($quantity >= $rule['min_quantity']) {
                $percent = max($percent, $rule['percent']);
            }
        }

        return $percent;
    }

    /** @return list<array{quantity: int, opens: ?int, opens_iso: ?string}> */
    public static function releases(array $tier): array
    {
        $out = [];
        foreach ((array) ($tier['releases'] ?? []) as $release) {
            $quantity = (int) ($release['quantity'] ?? 0);
            $iso = $release['opens_iso'] ?? null;
            $ts = $iso ? strtotime((string) $iso) : null;
            if ($quantity > 0) {
                $out[] = ['quantity' => $quantity, 'opens' => $ts ?: null, 'opens_iso' => $iso ?: null];
            }
        }
        usort($out, fn ($a, $b) => ($a['opens'] ?? 0) <=> ($b['opens'] ?? 0));

        return $out;
    }

    /**
     * Food and drink included with each ticket of this type (a bundle): menu item ids and how many per ticket.
     *
     * @return list<array{menu_item_id: string, quantity: int}>
     */
    public static function bundleItems(array $tier): array
    {
        $items = [];
        foreach ((array) ($tier['bundle_items'] ?? []) as $item) {
            $id = (string) ($item['menu_item_id'] ?? '');
            $qty = max(1, min(10, (int) ($item['quantity'] ?? 1)));
            if ($id !== '') {
                $items[] = ['menu_item_id' => $id, 'quantity' => $qty];
            }
        }

        return $items;
    }

    private static function tierEarlyBird(array $tier, string $currency, int $now): ?int
    {
        $ends = $tier['early_bird_ends_iso'] ?? null;
        if (!$ends || ($ts = strtotime((string) $ends)) === false || $now >= $ts) {
            return null;
        }

        return isset($tier['early_bird_price_minor']) && is_numeric($tier['early_bird_price_minor'])
            ? max(0, (int) $tier['early_bird_price_minor']) : null;
    }

    private static function minor(array $row, string $currency): ?int
    {
        if (isset($row['price_minor']) && is_numeric($row['price_minor'])) {
            return max(0, (int) $row['price_minor']);
        }
        if (isset($row['price']) && is_numeric($row['price'])) {
            return Money::parse((string) $row['price'], $currency)->minor();
        }

        return null;
    }

    /** @param list<array{quantity: int, unit_price_minor: int, label: ?string}> $lines */
    private static function merge(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            $last = array_key_last($out);
            if ($last !== null && $out[$last]['unit_price_minor'] === $line['unit_price_minor'] && $out[$last]['label'] === $line['label']) {
                $out[$last]['quantity'] += $line['quantity'];
            } else {
                $out[] = $line;
            }
        }

        return $out;
    }
}
