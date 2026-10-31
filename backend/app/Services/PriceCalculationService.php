<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Support\Money;

/**
 * Item prices and order financials in exact integer minor units (see App\Support\Money).
 * Amounts in major units are accepted and returned only at the edges, for older callers.
 */
class PriceCalculationService
{
    /**
     * Major units (e.g. 18.50) to integer minor units (1850), parsed exactly.
     */
    public static function toMinorUnits(float|int|string $amount, string $currency = 'USD'): int
    {
        return Money::parse($amount, $currency)->minor();
    }

    /**
     * Minor units back to major units, for older callers that expect a number.
     */
    public static function fromMinorUnits(int $minorUnits, string $currency = 'USD'): float
    {
        return Money::ofMinor($minorUnits, $currency)->toMajorNumber();
    }

    /**
     * Price of one unit of a dish in the order currency: base price, chosen option extras, or the
     * customer's own amount for "pay what you like" items, validated against the host's minimum.
     *
     * @param array $selectedChoiceIndices [groupId => choiceIndex]
     * @return array{base_price: float, options_total_extra: float, final_item_price: float, base_price_minor: int, options_extra_minor: int, final_price_minor: int, selected_options_summary: list<string>}
     */
    public static function calculateItemPrice(
        MenuItem $item,
        string $currency,
        array $selectedChoiceIndices = [],
        float|int|string|null $customDonationAmount = null
    ): array {
        if ($item->pricing_type === 'custom_amount') {
            $config = is_array($item->custom_amount_config) ? $item->custom_amount_config : [];
            $minimumRaw = $currency === 'JMD'
                ? ($config['minJMD'] ?? $config['min_jmd'] ?? 0)
                : ($config['minUSD'] ?? $config['min_usd'] ?? 0);
            $minimum = Money::parse($minimumRaw ?? 0, $currency);

            try {
                $amount = Money::parse($customDonationAmount ?? 0, $currency);
            } catch (\InvalidArgumentException) {
                throw new OrderPlacementException("Enter a valid amount for '{$item->name}'.");
            }
            if (!$amount->isPositive() || $amount->compareTo($minimum) < 0) {
                throw new OrderPlacementException(
                    "Enter an amount for '{$item->name}'" . ($minimum->isPositive() ? ' of at least ' . $minimum->display() : '') . '.'
                );
            }

            return [
                'base_price' => $amount->toMajorNumber(),
                'options_total_extra' => 0.0,
                'final_item_price' => $amount->toMajorNumber(),
                'base_price_minor' => $amount->minor(),
                'options_extra_minor' => 0,
                'final_price_minor' => $amount->minor(),
                'selected_options_summary' => ['Custom Contribution: ' . $amount->display()],
            ];
        }

        $base = self::basePrice($item, $currency);
        $options = Money::zero($currency);
        $summaryList = [];

        if ($item->pricing_type === 'customizable' && !empty($item->custom_option_groups)) {
            foreach ($item->custom_option_groups as $group) {
                $groupId = $group['id'] ?? '';
                $selectedIndex = $selectedChoiceIndices[$groupId] ?? 0;
                $choice = $group['choices'][$selectedIndex] ?? null;
                if (!$choice && array_key_exists($groupId, $selectedChoiceIndices)) {
                    // A choice that doesn't exist must not silently drop its price.
                    throw new OrderPlacementException("That option for '{$item->name}' ({$group['title']}) isn't available.");
                }

                if ($choice) {
                    $extra = $currency === 'JMD' ? ($choice['extraPriceJMD'] ?? 0) : ($choice['extraPriceUSD'] ?? 0);
                    $options = $options->plus(Money::parse($extra ?? 0, $currency));
                    $summaryList[] = "{$group['title']}: {$choice['label']}";
                }
            }
        }

        $final = $base->plus($options);

        return [
            'base_price' => $base->toMajorNumber(),
            'options_total_extra' => $options->toMajorNumber(),
            'final_item_price' => $final->toMajorNumber(),
            'base_price_minor' => $base->minor(),
            'options_extra_minor' => $options->minor(),
            'final_price_minor' => $final->minor(),
            'selected_options_summary' => $summaryList,
        ];
    }

    /**
     * Order totals from priced lines (each with final_price_minor or final_item_price, and quantity) plus a
     * flat fee. Kept for older callers; checkout uses App\Services\Pricing\OrderTotals with configured charges.
     */
    public static function calculateOrderTotals(array $items, float|int|string $packagingFee, string $currency = 'USD'): array
    {
        $subtotal = Money::zero($currency);
        foreach ($items as $item) {
            $unit = isset($item['final_price_minor'])
                ? Money::ofMinor((int) $item['final_price_minor'], $currency)
                : Money::parse($item['final_item_price'] ?? 0, $currency);
            $subtotal = $subtotal->plus($unit->times((int) ($item['quantity'] ?? 0)));
        }
        $fee = Money::parse($packagingFee, $currency);
        $total = $subtotal->plus($fee);

        return [
            'subtotal' => $subtotal->toMajorNumber(),
            'packaging_fee' => $fee->toMajorNumber(),
            'total_amount' => $total->toMajorNumber(),
            'subtotal_minor' => $subtotal->minor(),
            'packaging_fee_minor' => $fee->minor(),
            'total_amount_minor' => $total->minor(),
        ];
    }

    /**
     * Order financials in exact minor units: totals, amount received, balance, refunds and net receipts.
     */
    public static function calculateOrderFinancials(
        int $subtotalMinor,
        int $packagingFeeMinor,
        int $amountReceivedMinor,
        int $refundedMinor = 0,
        string $currency = 'USD'
    ): array {
        $totalAmountMinor = $subtotalMinor + $packagingFeeMinor;
        $outstandingBalanceMinor = max(0, $totalAmountMinor - $amountReceivedMinor);
        $netReceiptsMinor = $amountReceivedMinor - $refundedMinor;

        $isFullyPaid = $amountReceivedMinor >= $totalAmountMinor && $totalAmountMinor > 0;
        $isPartiallyPaid = $amountReceivedMinor > 0 && $amountReceivedMinor < $totalAmountMinor;

        return [
            'currency' => $currency,
            'subtotal_minor' => $subtotalMinor,
            'packaging_fee_minor' => $packagingFeeMinor,
            'total_amount_minor' => $totalAmountMinor,
            'amount_received_minor' => $amountReceivedMinor,
            'outstanding_balance_minor' => $outstandingBalanceMinor,
            'refunded_amount_minor' => $refundedMinor,
            'net_receipts_minor' => $netReceiptsMinor,
            'is_fully_paid' => $isFullyPaid,
            'is_partially_paid' => $isPartiallyPaid,
            'formatted_subtotal' => self::formatMinorUnits($subtotalMinor, $currency),
            'formatted_packaging_fee' => self::formatMinorUnits($packagingFeeMinor, $currency),
            'formatted_total' => self::formatMinorUnits($totalAmountMinor, $currency),
            'formatted_amount_received' => self::formatMinorUnits($amountReceivedMinor, $currency),
            'formatted_outstanding_balance' => self::formatMinorUnits($outstandingBalanceMinor, $currency),
            'formatted_refunded' => self::formatMinorUnits($refundedMinor, $currency),
            'formatted_net_receipts' => self::formatMinorUnits($netReceiptsMinor, $currency),
        ];
    }

    /**
     * Lock the authoritative currency of an order to the event's configured currency.
     * A currency sent by the browser is ignored.
     */
    public static function lockAuthoritativeCurrency(string $eventCurrency, ?string $requestedCurrency = null): string
    {
        return strtoupper(trim($eventCurrency ?: \App\Support\Currency::defaultCode()));
    }

    /**
     * Minor units as display text, using the currency's configured symbol and separators.
     */
    public static function formatMinorUnits(int $minorUnits, string $currency = 'USD'): string
    {
        return Money::formatMinor($minorUnits, $currency);
    }

    /** A dish's base price in the order currency: its stored minor-unit price, or the legacy column. */
    private static function basePrice(MenuItem $item, string $currency): Money
    {
        if ($item->price_minor !== null && strtoupper((string) ($item->currency ?: $currency)) === $currency) {
            return Money::ofMinor((int) $item->price_minor, $currency);
        }

        return Money::parse(($currency === 'JMD' ? $item->price_jmd : $item->price_usd) ?? 0, $currency);
    }
}
