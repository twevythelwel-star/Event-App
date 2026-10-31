<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * A currency the application is configured for (config/money.php). The single source of a currency's
 * minor-unit precision, symbol and display separators.
 */
final class Currency
{
    private function __construct(
        public readonly string $code,
        public readonly int $minorUnit,
        public readonly string $symbol,
        public readonly string $symbolPosition,
        public readonly string $decimal,
        public readonly string $thousands,
        public readonly string $name,
    ) {
    }

    public static function of(?string $code): self
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return self::default();
        }
        $definition = self::definitions()[$code] ?? null;
        if (!is_array($definition)) {
            throw new InvalidArgumentException("Unsupported currency '{$code}'. Add it to config/money.php.");
        }

        return new self(
            $code,
            (int) ($definition['minor_unit'] ?? 2),
            (string) ($definition['symbol'] ?? $code . ' '),
            (string) ($definition['symbol_position'] ?? 'before'),
            (string) ($definition['decimal'] ?? '.'),
            (string) ($definition['thousands'] ?? ','),
            (string) ($definition['name'] ?? $code),
        );
    }

    public static function default(): self
    {
        return self::of(self::defaultCode());
    }

    public static function defaultCode(): string
    {
        $code = self::config('money.default_currency') ?? 'USD';

        return strtoupper((string) ($code ?: 'USD'));
    }

    public static function isSupported(?string $code): bool
    {
        return isset(self::definitions()[strtoupper(trim((string) $code))]);
    }

    /** @return list<string> */
    public static function supportedCodes(): array
    {
        return array_keys(self::definitions());
    }

    /** 10^minor_unit: how many minor units make one major unit. */
    public function factor(): int
    {
        return 10 ** $this->minorUnit;
    }

    /** Reads Laravel config when an application is booted; null in standalone scripts. */
    private static function config(string $key): mixed
    {
        try {
            return function_exists('app') && app()->bound('config') ? app('config')->get($key) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string, array<string, mixed>> */
    private static function definitions(): array
    {
        $configured = self::config('money.currencies');
        if (is_array($configured) && $configured !== []) {
            return array_change_key_case($configured, CASE_UPPER);
        }

        // Outside a booted application (standalone scripts), fall back to the shipped definitions.
        return [
            'USD' => ['name' => 'US Dollar', 'minor_unit' => 2, 'symbol' => '$', 'symbol_position' => 'before', 'decimal' => '.', 'thousands' => ','],
            'JMD' => ['name' => 'Jamaican Dollar', 'minor_unit' => 2, 'symbol' => 'J$', 'symbol_position' => 'before', 'decimal' => '.', 'thousands' => ','],
        ];
    }
}
