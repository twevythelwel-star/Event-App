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

    public static function maxPerOrder(array $tier): int
    {
        return max(0, (int) ($tier['maxPerCustomer'] ?? $tier['max_per_customer'] ?? $tier['max_per_order'] ?? $tier['maxPerOrder'] ?? 10));
    }

    /**
     * Tiers a customer can be offered: configured with an id and priced in the event's currency.
     *
     * @return list<array{id: string, name: string, description: ?string, price: float, available: ?int, max: int, sold_out: bool}>
     */
    public static function sellable(Event $event): array
    {
        $currency = (string) ($event->currency ?: \App\Support\Currency::defaultCode());
        $tiers = [];
        foreach (is_array($event->ticket_tiers) ? $event->ticket_tiers : [] as $tier) {
            if (!is_array($tier) || !isset($tier['id'])) {
                continue;
            }
            $priceMinor = self::priceMinorFor($tier, $currency);
            if ($priceMinor === null) {
                continue;
            }
            $price = \App\Support\Money::ofMinor($priceMinor, $currency)->toMajorNumber();
            $available = self::available($tier);
            $tiers[] = [
                'id' => (string) $tier['id'],
                'name' => (string) ($tier['name'] ?? 'Admission ticket'),
                'description' => isset($tier['description']) ? (string) $tier['description'] : null,
                'price' => $price,
                'price_minor' => $priceMinor,
                'currency' => $currency,
                'available' => $available,
                'max' => $available === null ? self::maxPerOrder($tier) : min(self::maxPerOrder($tier), $available),
                'sold_out' => $available !== null && $available <= 0,
            ];
        }

        return $tiers;
    }
}
