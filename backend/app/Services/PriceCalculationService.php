<?php

namespace App\Services;

use App\Models\MenuItem;

class PriceCalculationService
{
    /**
     * Convert major currency units (e.g. $18.50 or J$2800) into integer minor units (cents).
     */
    public static function toMinorUnits(float $amount, string $currency = 'USD'): int
    {
        return (int) round($amount * 100);
    }

    /**
     * Convert integer minor units back to major units without floating point distortion.
     */
    public static function fromMinorUnits(int $minorUnits, string $currency = 'USD'): float
    {
        return $minorUnits / 100.0;
    }

    /**
     * Calculate item price in exact integer minor units.
     *
     * @param MenuItem $item
     * @param string $currency 'USD' or 'JMD'
     * @param array $selectedChoiceIndices [groupId => choiceIndex]
     * @param float|null $customDonationAmount
     * @return array
     */
    public static function calculateItemPrice(
        MenuItem $item,
        string $currency,
        array $selectedChoiceIndices = [],
        ?float $customDonationAmount = null
    ): array {
        if ($item->pricing_type === 'custom_amount') {
            $amt = $customDonationAmount ?? 0.0;
            $config = is_array($item->custom_amount_config) ? $item->custom_amount_config : [];
            $minimum = (float) ($currency === 'JMD' ? ($config['minJMD'] ?? $config['min_jmd'] ?? 0) : ($config['minUSD'] ?? $config['min_usd'] ?? 0));
            if ($amt <= 0 || $amt < $minimum) {
                throw new OrderPlacementException(
                    "Enter an amount for '{$item->name}'" . ($minimum > 0 ? ' of at least ' . $currency . ' ' . number_format($minimum, 2) : '') . '.'
                );
            }
            $amtMinor = self::toMinorUnits($amt, $currency);
            return [
                'base_price' => $amt,
                'options_total_extra' => 0.0,
                'final_item_price' => $amt,
                'base_price_minor' => $amtMinor,
                'options_extra_minor' => 0,
                'final_price_minor' => $amtMinor,
                'selected_options_summary' => ["Custom Contribution: {$currency} " . number_format($amt, 2)],
            ];
        }

        $basePrice = $currency === 'JMD' ? (float) $item->price_jmd : (float) $item->price_usd;
        $basePriceMinor = self::toMinorUnits($basePrice, $currency);
        $optionsExtraMinor = 0;
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
                    $extra = $currency === 'JMD'
                        ? (float) ($choice['extraPriceJMD'] ?? 0)
                        : (float) ($choice['extraPriceUSD'] ?? 0);
                    $extraMinor = self::toMinorUnits($extra, $currency);
                    $optionsExtraMinor += $extraMinor;
                    $summaryList[] = "{$group['title']}: {$choice['label']}";
                }
            }
        }

        $finalPriceMinor = $basePriceMinor + $optionsExtraMinor;

        return [
            'base_price' => $basePrice,
            'options_total_extra' => self::fromMinorUnits($optionsExtraMinor, $currency),
            'final_item_price' => self::fromMinorUnits($finalPriceMinor, $currency),
            'base_price_minor' => $basePriceMinor,
            'options_extra_minor' => $optionsExtraMinor,
            'final_price_minor' => $finalPriceMinor,
            'selected_options_summary' => $summaryList,
        ];
    }

    /**
     * Compute entire order totals in integer minor units.
     *
     * @param array $items Array of items with 'final_item_price' and 'quantity'
     * @param float $packagingFee
     * @param string $currency
     * @return array
     */
    public static function calculateOrderTotals(array $items, float $packagingFee, string $currency = 'USD'): array
    {
        $subtotalMinor = 0;

        foreach ($items as $item) {
            $priceMinor = self::toMinorUnits((float) $item['final_item_price'], $currency);
            $qty = (int) $item['quantity'];
            $subtotalMinor += ($priceMinor * $qty);
        }

        $packagingFeeMinor = self::toMinorUnits($packagingFee, $currency);
        $totalAmountMinor = $subtotalMinor + $packagingFeeMinor;

        return [
            'subtotal' => self::fromMinorUnits($subtotalMinor, $currency),
            'packaging_fee' => self::fromMinorUnits($packagingFeeMinor, $currency),
            'total_amount' => self::fromMinorUnits($totalAmountMinor, $currency),
            'subtotal_minor' => $subtotalMinor,
            'packaging_fee_minor' => $packagingFeeMinor,
            'total_amount_minor' => $totalAmountMinor,
        ];
    }

    /**
     * Accurately calculate order financials using exact integer minor units (cents).
     * Clearly distinguishes order totals, amounts received, outstanding balances,
     * refunds, and net receipts with zero floating-point error.
     *
     * @param int $subtotalMinor Subtotal in integer minor units (cents)
     * @param int $packagingFeeMinor Packaging fee in integer minor units (cents)
     * @param int $amountReceivedMinor Amount verified/received in minor units
     * @param int $refundedMinor Amount refunded in minor units
     * @param string $currency Authoritative currency (default: 'USD')
     * @return array
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
     * Prevents attendees or browser requests from changing the authoritative currency.
     *
     * @param string $eventCurrency The event's official currency (e.g. 'USD')
     * @param string|null $requestedCurrency The currency sent in browser request/profile
     * @return string Locked authoritative currency
     */
    public static function lockAuthoritativeCurrency(string $eventCurrency, ?string $requestedCurrency = null): string
    {
        $authoritative = strtoupper(trim($eventCurrency ?: 'USD'));

        // If client requested a different currency, log or ignore, but strictly return authoritative
        return $authoritative;
    }

    /**
     * Format minor units into display string with appropriate currency symbol.
     */
    public static function formatMinorUnits(int $minorUnits, string $currency = 'USD'): string
    {
        $major = $minorUnits / 100.0;
        if (strtoupper($currency) === 'JMD') {
            return 'J$' . number_format(round($major));
        }
        return '$' . number_format($major, 2);
    }
}
