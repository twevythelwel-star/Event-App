<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Models\Event;

/**
 * Reads the host-configured ticket types stored in events.ticket_tiers.
 * The JSON was written by more than one client over time, so a few key spellings are accepted.
 * Used by OrderPlacementService (authoritative) and OrderQuoteService (display).
 */
final class TicketTierCatalog
{
    /** The host's price for this tier in the given currency, or null when none is set. */
    public static function priceFor(array $tier, string $currency): ?float
    {
        $price = $currency === 'JMD'
            ? ($tier['price_jmd'] ?? $tier['priceJMD'] ?? null)
            : ($tier['price_usd'] ?? $tier['priceUSD'] ?? null);

        return is_numeric($price) && (float) $price >= 0 ? (float) $price : null;
    }

    /**
     * The host's price for this tier in exact minor units, or null when none is set. A tier may carry
     * price_minor (with its currency) directly; otherwise the decimal price for the currency is parsed as text.
     */
    public static function priceMinorFor(array $tier, string $currency): ?int
    {
        if (isset($tier['price_minor']) && is_numeric($tier['price_minor'])
            && strtoupper((string) ($tier['currency'] ?? $currency)) === strtoupper($currency)) {
            return max(0, (int) $tier['price_minor']);
        }
        $price = $currency === 'JMD'
            ? ($tier['price_jmd'] ?? $tier['priceJMD'] ?? null)
            : ($tier['price_usd'] ?? $tier['priceUSD'] ?? null);
        if (!is_numeric($price) || (float) $price < 0) {
            return null;
        }

        return \App\Support\Money::parse(is_string($price) ? $price : (string) $price, $currency)->minor();
    }

    /**
     * What this tier costs right now: its early-bird price while the event's early-bird period runs (when the host
     * set one, in this currency), otherwise the regular price. Null when the tier has no price in this currency.
     */
    public static function currentPriceMinor(array $tier, string $currency, ?Event $event, ?int $now = null): ?int
    {
        $regular = self::priceMinorFor($tier, $currency);
        if ($regular === null || !self::isEarlyBird($event, $now)) {
            return $regular;
        }
        $early = strtoupper($currency) === 'USD' ? ($tier['earlyBirdPriceUSD'] ?? null) : null;
        if (!is_numeric($early) || (float) $early < 0) {
            return $regular;
        }

        return min($regular, \App\Support\Money::parse((string) $early, $currency)->minor());
    }

    /** Whether the event's early-bird period is running inside its configured start/end window. */
    public static function isEarlyBird(?Event $event, ?int $now = null): bool
    {
        if (!$event?->early_bird_ends_at) {
            return false;
        }
        $now ??= time();
        $startsAt = $event->early_bird_starts_at ?? $event->ticket_sales_start_at;

        return $now < $event->early_bird_ends_at->getTimestamp()
            && (!$startsAt || $now >= $startsAt->getTimestamp());
    }

    /**
     * The tier with the event's own ticket sales period applied: sales open no earlier than the event's start of
     * sales and close no later than its end of sales, whatever the tier says.
     */
    public static function withEventSaleWindow(array $tier, ?Event $event): array
    {
        if (!$event) {
            return $tier;
        }
        $start = $tier['saleStartIso'] ?? $tier['sale_start_iso'] ?? null;
        $end = $tier['saleEndIso'] ?? $tier['sale_end_iso'] ?? null;
        if ($event->ticket_sales_start_at && (empty($start) || strtotime((string) $start) < $event->ticket_sales_start_at->getTimestamp())) {
            $tier['saleStartIso'] = $event->ticket_sales_start_at->toIso8601String();
            unset($tier['sale_start_iso']);
        }
        if ($event->ticket_sales_end_at && (empty($end) || strtotime((string) $end) > $event->ticket_sales_end_at->getTimestamp())) {
            $tier['saleEndIso'] = $event->ticket_sales_end_at->toIso8601String();
            unset($tier['sale_end_iso']);
        }

        return $tier;
    }

    /** The JSON key holding remaining stock, or null when the tier has no stock limit. */
    public static function availabilityKey(array $tier): ?string
    {
        // The host editor (EventManagementService) writes availableStock / available_stock.
        foreach (['availableStock', 'available_stock', 'quantity_available', 'quantityAvailable'] as $key) {
            if (array_key_exists($key, $tier) && $tier[$key] !== null) {
                return $key;
            }
        }

        return null;
    }

    public static function available(array $tier): ?int
    {
        $key = self::availabilityKey($tier);

        return $key === null ? null : max(0, (int) $tier[$key]);
    }

    /**
     * Where the tier is in its sale window: "not_started", "ended", or "open" (also when no window is set).
     * Sale times are ISO 8601 with an offset, so they compare correctly whatever the server's time zone.
     */
    public static function saleWindow(array $tier, ?int $now = null): string
    {
        $now ??= time();
        $start = $tier['saleStartIso'] ?? $tier['sale_start_iso'] ?? null;
        $end = $tier['saleEndIso'] ?? $tier['sale_end_iso'] ?? null;
        if (!empty($start) && ($ts = strtotime((string) $start)) !== false && $now < $ts) {
            return 'not_started';
        }
        if (!empty($end) && ($ts = strtotime((string) $end)) !== false && $now >= $ts) {
            return 'ended';
        }

        return 'open';
    }

    public static function maxPerOrder(array $tier): int
    {
        return max(0, (int) ($tier['maxPerCustomer'] ?? $tier['max_per_customer'] ?? $tier['max_per_order'] ?? $tier['maxPerOrder'] ?? 10));
    }

    public static function minPerOrder(array $tier): int
    {
        return max(1, (int) ($tier['minPerOrder'] ?? $tier['min_per_order'] ?? 1));
    }

    /**
     * Tiers a customer can be offered: configured with an id and priced in the event's currency.
     *
     * A tier outside its sale window is still listed (so the page can say why) but has max 0 and on_sale false.
     *
     * @return list<array{id: string, name: string, description: ?string, price: float, available: ?int, max: int, sold_out: bool, sale_window: string, on_sale: bool}>
     */
    public static function sellable(Event $event, ?int $userId = null): array
    {
        $currency = (string) ($event->currency ?: \App\Support\Currency::defaultCode());
        $tiers = [];
        // Smarter pricing and inventory (TicketPricing, TicketInventory) need what is sold; read it once.
        $sold = $event->exists ? TicketPricing::soldByTier($event) : [];
        foreach (is_array($event->ticket_tiers) ? $event->ticket_tiers : [] as $tier) {
            if (!is_array($tier) || !isset($tier['id']) || ($tier['isActive'] ?? true) === false
                || ($tier['visibility'] ?? 'public') !== 'public') {
                continue;
            }
            $tierSold = (int) ($sold[(string) $tier['id']] ?? 0);
            // The price of the next ticket: early-bird, the current price step, or the regular price.
            $priceMinor = $event->exists ? TicketPricing::nextPriceMinor($tier, $currency, $event, $tierSold) : self::currentPriceMinor($tier, $currency, $event);
            if ($priceMinor === null) {
                continue;
            }
            $regularMinor = self::priceMinorFor($tier, $currency);
            $price = \App\Support\Money::ofMinor($priceMinor, $currency)->toMajorNumber();
            // Stock, limited by the release schedule, less what other buyers hold right now.
            $available = $event->exists ? TicketInventory::availableFor($event, $tier, $userId, $sold) : self::available($tier);
            $steps = TicketPricing::steps($tier, $currency);
            $nextStep = collect($steps)->first(fn ($s) => $tierSold < $s['up_to']);
            $window = self::saleWindow(self::withEventSaleWindow($tier, $event));
            $soldOut = $available !== null && $available <= 0;
            $max = $available === null ? self::maxPerOrder($tier) : min(self::maxPerOrder($tier), $available);
            $min = self::minPerOrder($tier);
            $tiers[] = [
                'id' => (string) $tier['id'],
                'name' => (string) ($tier['name'] ?? 'Admission ticket'),
                'description' => isset($tier['description']) ? (string) $tier['description'] : null,
                'category' => (string) ($tier['category'] ?? 'General Admission'),
                'benefits' => is_array($tier['benefits'] ?? null) ? array_values($tier['benefits']) : [],
                'admission_privileges' => isset($tier['admissionPrivileges']) ? (string) $tier['admissionPrivileges'] : null,
                'included_food_and_drinks' => isset($tier['includedFoodAndDrinks']) ? (string) $tier['includedFoodAndDrinks'] : null,
                'age_restriction' => isset($tier['ageRestriction']) ? (string) $tier['ageRestriction'] : null,
                'admission_count' => max(1, (int) ($tier['admissionCount'] ?? 1)),
                'min' => $min,
                'max_per_order' => self::maxPerOrder($tier),
                'price' => $price,
                'price_minor' => $priceMinor,
                'regular_price_minor' => $regularMinor,
                // Only a real early-bird price is called one; a lower first price step is not.
                'early_bird' => $priceMinor < $regularMinor && (self::isEarlyBird($event)
                    || (($tier['early_bird_ends_iso'] ?? null) && strtotime((string) $tier['early_bird_ends_iso']) > now()->getTimestamp())),
                'currency' => $currency,
                'available' => $available,
                'max' => $window === 'open' ? $max : 0,
                'sold_out' => $soldOut,
                'sale_window' => $window,
                'on_sale' => $window === 'open' && !$soldOut && $max > 0,
                // Smarter ticketing, for display: the price step now selling and how many remain at it, the next
                // limited release, group prices and what a bundle includes.
                'price_step' => $nextStep && count($steps) > 1 ? ['left_at_price' => max(0, $nextStep['up_to'] - $tierSold), 'is_last' => $nextStep === end($steps)] : null,
                'next_release' => TicketPricing::nextRelease($tier),
                'group_discounts' => TicketPricing::groupDiscounts($tier),
                'bundle_items' => TicketPricing::bundleItems($tier),
                'sold' => $tierSold,
            ];
        }

        return $tiers;
    }
}
