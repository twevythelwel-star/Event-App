<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Support\Money;

/**
 * The money summary of an order, kept as separate parts:
 *   item subtotal − discounts + platform fees + host fees + taxes = total.
 * Every figure is an integer number of minor units in one currency.
 */
final class OrderTotals
{
    /**
     * @param list<array{line_total_minor: int}> $lines
     * @param list<array{kind: string, amount_minor: int}> $charges
     */
    public function __construct(
        public readonly string $currency,
        public readonly int $subtotalMinor,
        public readonly int $discountMinor,
        public readonly int $platformFeeMinor,
        public readonly int $hostFeeMinor,
        public readonly int $taxMinor,
        public readonly array $charges,
    ) {
    }

    /**
     * @param list<array{line_total_minor: int}> $lines
     * @param list<array{kind: string, amount_minor: int}> $charges
     */
    public static function from(string $currency, array $lines, array $charges): self
    {
        $sumOf = fn (string $kind) => array_sum(array_map(fn ($c) => $c['kind'] === $kind ? (int) $c['amount_minor'] : 0, $charges));

        return new self(
            $currency,
            array_sum(array_map(fn ($l) => (int) $l['line_total_minor'], $lines)),
            $sumOf('discount'),
            $sumOf('platform_fee'),
            $sumOf('host_fee'),
            $sumOf('tax'),
            $charges,
        );
    }

    public function feesMinor(): int
    {
        return $this->platformFeeMinor + $this->hostFeeMinor;
    }

    public function totalMinor(): int
    {
        return $this->subtotalMinor - $this->discountMinor + $this->feesMinor() + $this->taxMinor;
    }

    public function money(int $minor): Money
    {
        return Money::ofMinor($minor, $this->currency);
    }

    /** Order columns this summary fills (the decimal columns follow via the model's minor-unit sync). */
    public function orderAttributes(): array
    {
        return [
            'currency' => $this->currency,
            'subtotal_minor' => $this->subtotalMinor,
            'discount_minor' => $this->discountMinor,
            'fees_minor' => $this->feesMinor(),
            'tax_minor' => $this->taxMinor,
            'total_minor' => $this->totalMinor(),
        ];
    }

    /** For views and JSON: each part as minor units, decimal text and formatted text. */
    public function toArray(): array
    {
        $part = fn (int $minor) => Money::ofMinor($minor, $this->currency)->jsonSerialize();

        return [
            'currency' => $this->currency,
            'subtotal' => $part($this->subtotalMinor),
            'discount' => $part($this->discountMinor),
            'platform_fees' => $part($this->platformFeeMinor),
            'host_fees' => $part($this->hostFeeMinor),
            'tax' => $part($this->taxMinor),
            'total' => $part($this->totalMinor()),
            'charges' => array_map(fn ($c) => $c + ['formatted' => Money::formatMinor((int) $c['amount_minor'], $this->currency)], $this->charges),
        ];
    }
}
