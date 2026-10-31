<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Support\Currency;
use App\Support\Money;

/**
 * Money totals for dashboards and reports, one set per currency. Amounts in different currencies are never
 * added together; each figure is a sum of integer minor units from the orders' own *_minor columns.
 *
 *   gross      total of all orders
 *   received   money actually received (amount_paid_minor), including part payments
 *   pending    still owed on orders that are not closed (awaiting, submitted, under verification, part paid)
 *   refunded   money returned to customers (refunded_minor, from the adjustments ledger)
 *   net        received − refunded
 *   cash       cash recorded at the event (receipts less reversals)
 */
final class RevenueSummary
{
    private const OPEN_STATUSES = ['awaiting_payment', 'payment_submitted', 'under_verification', 'partially_paid'];

    /**
     * Accepts any iterable of orders and cash rows (Eloquent collections, arrays), using plain loops only.
     *
     * @param iterable<int, \App\Models\Order> $orders
     * @param iterable<int, \App\Models\CashPayment>|null $cash
     * @return array<string, array<string, array{amount_minor: int, currency: string, amount: string, formatted: string}>> keyed by currency
     */
    public static function byCurrency(iterable $orders, ?iterable $cash = null): array
    {
        $default = Currency::defaultCode();
        $code = fn ($row) => strtoupper((string) ($row->currency ?? ''));
        $blank = ['gross' => 0, 'received' => 0, 'pending' => 0, 'refunded' => 0, 'cash' => 0];
        $totals = [];

        foreach ($orders as $order) {
            if (($currency = $code($order)) === '') {
                continue;
            }
            $t = $totals[$currency] ?? $blank;
            $t['gross'] += (int) $order->total_minor;
            $t['received'] += (int) $order->amount_paid_minor;
            $t['refunded'] += (int) $order->refunded_minor;
            if (in_array($order->status, self::OPEN_STATUSES, true)) {
                $t['pending'] += max(0, (int) $order->total_minor - (int) $order->amount_paid_minor);
            }
            $totals[$currency] = $t;
        }
        foreach ($cash ?? [] as $row) {
            if (($currency = $code($row)) === '') {
                continue;
            }
            $t = $totals[$currency] ?? $blank;
            $t['cash'] += (int) $row->amount_minor;
            $totals[$currency] = $t;
        }
        if ($totals === []) {
            $totals[$default] = $blank;
        }

        // The default currency first, then the rest in the order they were first seen.
        uksort($totals, fn ($a, $b) => ($a === $default ? 0 : 1) <=> ($b === $default ? 0 : 1));

        $summary = [];
        foreach ($totals as $currency => $t) {
            $part = fn (int $minor) => Money::ofMinor($minor, $currency)->jsonSerialize();
            $summary[$currency] = [
                'gross' => $part($t['gross']),
                'received' => $part($t['received']),
                'pending' => $part($t['pending']),
                'refunded' => $part($t['refunded']),
                'net' => $part($t['received'] - $t['refunded']),
                'cash' => $part($t['cash']),
            ];
        }

        return $summary;
    }
}
