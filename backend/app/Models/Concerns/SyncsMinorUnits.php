<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\Currency;
use App\Support\Money;

/**
 * Keeps each legacy decimal money column and its integer *_minor column in step while both exist
 * (expand/contract migration). Whichever side was changed is the source: setting `total_minor` rewrites
 * `total_amount` exactly, and code that still sets `total_amount` gets `total_minor` filled in. All
 * calculations use the minor-unit columns; the decimals exist only for older readers.
 *
 * A model declares the pairs in minorUnitPairs() (decimal column => minor column) and its currency in
 * minorUnitCurrency().
 */
trait SyncsMinorUnits
{
    public static function bootSyncsMinorUnits(): void
    {
        static::saving(function ($model) {
            $model->syncMinorUnitColumns();
        });
    }

    /** @return array<string, string> decimal column => minor column */
    abstract protected function minorUnitPairs(): array;

    abstract protected function minorUnitCurrency(): string;

    protected function syncMinorUnitColumns(): void
    {
        $currency = Currency::isSupported($this->minorUnitCurrency()) ? $this->minorUnitCurrency() : Currency::defaultCode();

        foreach ($this->minorUnitPairs() as $decimal => $minor) {
            $decimalChanged = $this->isDirty($decimal) || (!$this->exists && $this->getAttribute($decimal) !== null);
            $minorChanged = $this->isDirty($minor) && $this->getAttribute($minor) !== null;

            if ($minorChanged && !$this->isDirty($decimal)) {
                $this->setAttribute($decimal, Money::ofMinor((int) $this->getAttribute($minor), $currency)->toDecimalString());
            } elseif ($decimalChanged && !$minorChanged) {
                $this->setAttribute($minor, Money::parse((string) ($this->getAttribute($decimal) ?? '0'), $currency)->minor());
            } elseif ($minorChanged && $this->isDirty($decimal)) {
                // Both were set: the minor-unit value is authoritative.
                $this->setAttribute($decimal, Money::ofMinor((int) $this->getAttribute($minor), $currency)->toDecimalString());
            }
        }
    }

    /** The amount in a minor-unit column as Money, in this model's currency. */
    public function money(string $minorColumn): Money
    {
        return Money::ofMinor((int) ($this->getAttribute($minorColumn) ?? 0), $this->minorUnitCurrency());
    }
}
