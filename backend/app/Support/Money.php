<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;

/**
 * An exact amount of money: an integer number of minor units (cents) in one currency.
 *
 * All financial arithmetic goes through this class; there is no floating-point maths on money anywhere.
 * Decimal input from forms and JSON is parsed as text, so "19.99" is exactly 1999 cents. Percentages are
 * expressed in basis points (1% = 100 bp) and rounded half-up to the nearest minor unit. Amounts in
 * different currencies can never be added or compared.
 *
 * Formatting (display(), or the static format()/formatMinor() helpers for views) uses the currency's
 * configured symbol and separators (config/money.php).
 */
final class Money implements JsonSerializable
{
    private function __construct(
        private readonly int $minor,
        private readonly Currency $currency,
    ) {
    }

    public static function ofMinor(int $minor, ?string $currency = null): self
    {
        return new self($minor, Currency::of($currency));
    }

    public static function zero(?string $currency = null): self
    {
        return new self(0, Currency::of($currency));
    }

    /**
     * Parse a decimal amount ("12.5", "1,234.56", 12, "-3.00") exactly into minor units.
     * More decimal places than the currency allows are rounded half-up (e.g. 0.125 USD → 13 cents).
     */
    public static function parse(string|int|float|null $amount, ?string $currency = null): self
    {
        $currencyObj = Currency::of($currency);

        return new self(self::parseToMinor($amount, $currencyObj), $currencyObj);
    }

    public function minor(): int
    {
        return $this->minor;
    }

    public function currency(): string
    {
        return $this->currency->code;
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    public function times(int $quantity): self
    {
        return new self($this->minor * $quantity, $this->currency);
    }

    /** The given share in basis points (825 = 8.25%), rounded half-up to the nearest minor unit. */
    public function percentage(int $basisPoints): self
    {
        $product = $this->minor * $basisPoints;
        $sign = $product < 0 ? -1 : 1;
        $abs = abs($product);
        $result = intdiv($abs, 10000) + (($abs % 10000) * 2 >= 10000 ? 1 : 0);

        return new self($sign * $result, $this->currency);
    }

    public function negate(): self
    {
        return new self(-$this->minor, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    public function compareTo(self $other): int
    {
        $this->assertSameCurrency($other);

        return $this->minor <=> $other->minor;
    }

    public function equals(self $other): bool
    {
        return $this->currency->code === $other->currency->code && $this->minor === $other->minor;
    }

    public function min(self $other): self
    {
        return $this->compareTo($other) <= 0 ? $this : $other;
    }

    public function max(self $other): self
    {
        return $this->compareTo($other) >= 0 ? $this : $other;
    }

    /** "1234.50" for USD: exact, no symbol, for storage in legacy decimal columns and APIs. */
    public function toDecimalString(): string
    {
        $digits = $this->currency->minorUnit;
        $abs = abs($this->minor);
        $sign = $this->minor < 0 ? '-' : '';
        if ($digits === 0) {
            return $sign . $abs;
        }
        $factor = $this->currency->factor();

        return $sign . intdiv($abs, $factor) . '.' . str_pad((string) ($abs % $factor), $digits, '0', STR_PAD_LEFT);
    }

    /** For JSON clients that expect a number in major units. Exact for any amount below 2^53 minor units. */
    public function toMajorNumber(): float
    {
        return (float) $this->toDecimalString();
    }

    /** "$1,234.50", "-$3.00", "J$2,800.00" using the currency's configured display rules. */
    public function display(): string
    {
        $c = $this->currency;
        $abs = abs($this->minor);
        $factor = $c->factor();
        $whole = number_format(intdiv($abs, $factor), 0, '', $c->thousands);
        $number = $c->minorUnit > 0
            ? $whole . $c->decimal . str_pad((string) ($abs % $factor), $c->minorUnit, '0', STR_PAD_LEFT)
            : $whole;
        $text = $c->symbolPosition === 'after' ? $number . ' ' . $c->symbol : $c->symbol . $number;

        return ($this->minor < 0 ? '-' : '') . $text;
    }

    /** @return array{amount_minor: int, currency: string, amount: string, formatted: string} */
    public function jsonSerialize(): array
    {
        return [
            'amount_minor' => $this->minor,
            'currency' => $this->currency->code,
            'amount' => $this->toDecimalString(),
            'formatted' => $this->display(),
        ];
    }

    /**
     * Format an amount given in major units (as stored in the legacy decimal columns).
     * Kept for existing views; new code should hold Money objects and call format().
     */
    public static function format(float|int|string|null $amount, ?string $currency): string
    {
        return self::parse($amount ?? 0, $currency)->display();
    }

    /** Format an integer amount of minor units. */
    public static function formatMinor(?int $minor, ?string $currency): string
    {
        return self::ofMinor((int) $minor, $currency)->display();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency->code !== $other->currency->code) {
            throw new InvalidArgumentException("Cannot combine {$this->currency->code} and {$other->currency->code} amounts.");
        }
    }

    private static function parseToMinor(string|int|float|null $amount, Currency $currency): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }
        if (is_int($amount)) {
            return $amount * $currency->factor();
        }
        // Floats arrive only at input boundaries (JSON numbers); render them with enough digits to be exact
        // to the currency's precision plus one rounding digit, then parse as text.
        $text = is_float($amount)
            ? sprintf('%.' . ($currency->minorUnit + 1) . 'F', $amount)
            : str_replace([',', ' ', $currency->symbol], '', trim($amount));

        if (!preg_match('/^([+-])?(\d*)(?:\.(\d*))?$/', $text, $m) || ($m[2] === '' && ($m[3] ?? '') === '')) {
            throw new InvalidArgumentException("'{$amount}' is not a valid amount.");
        }
        $negative = ($m[1] ?? '') === '-';
        $whole = $m[2] === '' ? '0' : ltrim($m[2], '0');
        $fraction = $m[3] ?? '';
        $digits = $currency->minorUnit;

        $kept = str_pad(substr($fraction, 0, $digits), $digits, '0');
        $roundUp = isset($fraction[$digits]) && (int) $fraction[$digits] >= 5;

        if (strlen($whole) > 15) {
            throw new InvalidArgumentException("'{$amount}' is too large.");
        }
        $minor = (int) (($whole === '' ? '0' : $whole) . $kept) + ($roundUp ? 1 : 0);

        return $negative ? -$minor : $minor;
    }
}
