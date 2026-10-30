<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Display formatting for amounts. Formatting only: totals are always calculated by the
 * pricing services, never in views.
 */
final class Money
{
    public static function format(float|int|string|null $amount, ?string $currency): string
    {
        $symbol = match (strtoupper((string) $currency)) {
            'JMD' => 'J$',
            'USD' => 'US$',
            default => strtoupper((string) $currency) . ' ',
        };

        return $symbol . number_format((float) $amount, 2);
    }
}
