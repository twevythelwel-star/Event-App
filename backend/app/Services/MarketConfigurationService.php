<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/**
 * MarketConfigurationService
 *
 * Enforces United States-only launch configuration for EventBite.
 * Manages default country, currency, address validation, timezones, and
 * provides a modular registry for future international expansion.
 */
class MarketConfigurationService
{
    public const DEFAULT_COUNTRY = 'US';
    public const DEFAULT_CURRENCY = 'USD';
    public const DEFAULT_TIMEZONE = 'America/New_York';

    public const US_STATES = [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
        'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
        'DC' => 'District of Columbia', 'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii',
        'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
        'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine',
        'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota',
        'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska',
        'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico',
        'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
        'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island',
        'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas',
        'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington',
        'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
    ];

    public const US_TIMEZONES = [
        'America/New_York' => 'Eastern Time (ET)',
        'America/Chicago' => 'Central Time (CT)',
        'America/Denver' => 'Mountain Time (MT)',
        'America/Los_Angeles' => 'Pacific Time (PT)',
        'America/Phoenix' => 'Arizona (MST - No DST)',
        'America/Anchorage' => 'Alaska Time (AKT)',
        'Pacific/Honolulu' => 'Hawaii Time (HST - No DST)',
    ];

    /**
     * Active country registry. Configured for US only on initial launch.
     */
    private static array $allowedCountries = ['US'];

    /**
     * Active currency registry. Configured for USD only on initial launch.
     */
    private static array $allowedCurrencies = ['USD'];

    /**
     * Modular registry for future international expansion without breaking core code.
     */
    private static array $marketRegistry = [
        'US' => [
            'country_name' => 'United States',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'phone_prefix' => '+1',
            'default_timezone' => 'America/New_York',
            'timezones' => [
                'America/New_York', 'America/Chicago', 'America/Denver',
                'America/Los_Angeles', 'America/Phoenix', 'America/Anchorage', 'Pacific/Honolulu'
            ],
            'postal_regex' => '/^\d{5}(-\d{4})?$/',
            'is_active' => true,
        ],
    ];

    /**
     * Check if a country code is supported in the current operating market.
     */
    public static function isCountryAllowed(string $countryCode): bool
    {
        return in_array(strtoupper(trim($countryCode)), self::$allowedCountries, true);
    }

    /**
     * Check if a currency is supported in the current operating market.
     */
    public static function isCurrencyAllowed(string $currency): bool
    {
        return in_array(strtoupper(trim($currency)), self::$allowedCurrencies, true);
    }

    /**
     * Validate and normalize a US address.
     *
     * @param array $address ['street' => ..., 'city' => ..., 'state' => ..., 'zip' => ..., 'country' => ...]
     * @return array Normalized valid address
     * @throws InvalidArgumentException if invalid
     */
    public static function validateUSAddress(array $address): array
    {
        $country = strtoupper(trim((string) ($address['country'] ?? 'US')));
        if ($country !== 'US') {
            throw new InvalidArgumentException("Operating market restricted to United States (US). Provided: {$country}");
        }

        $street = trim((string) ($address['street'] ?? ''));
        if (strlen($street) < 3) {
            throw new InvalidArgumentException("US Street Address is required and must be at least 3 characters.");
        }

        $city = trim((string) ($address['city'] ?? ''));
        if (strlen($city) < 2) {
            throw new InvalidArgumentException("US City is required and must be at least 2 characters.");
        }

        $state = strtoupper(trim((string) ($address['state'] ?? '')));
        if (!array_key_exists($state, self::US_STATES)) {
            throw new InvalidArgumentException("Invalid US state code: {$state}. Must be a valid 2-letter US state or territory code.");
        }

        $zip = trim((string) ($address['zip'] ?? ''));
        if (!preg_match('/^\d{5}(-\d{4})?$/', $zip)) {
            throw new InvalidArgumentException("Invalid US ZIP code format: {$zip}. Must be 5 digits (e.g. 10001) or ZIP+4 (e.g. 10001-1234).");
        }

        return [
            'street' => $street,
            'city' => $city,
            'state' => $state,
            'state_name' => self::US_STATES[$state],
            'zip' => $zip,
            'country' => 'US',
            'country_name' => 'United States',
            'formatted' => "{$street}, {$city}, {$state} {$zip}, United States",
        ];
    }

    /**
     * Validate that an IANA timezone identifier is a supported US timezone.
     */
    public static function isSupportedUSTimezone(string $timezone): bool
    {
        return array_key_exists($timezone, self::US_TIMEZONES);
    }

    /**
     * Format a 10-digit US telephone number into standard E.164 (+1XXXXXXXXXX) and readable formats.
     */
    public static function formatUSPhoneNumber(string $rawPhone): array
    {
        $digits = preg_replace('/\D/', '', $rawPhone);
        if (str_starts_with($digits, '1') && strlen($digits) === 11) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) !== 10) {
            throw new InvalidArgumentException("Invalid US phone number: {$rawPhone}. Must be 10 digits.");
        }

        $area = substr($digits, 0, 3);
        $prefix = substr($digits, 3, 3);
        $line = substr($digits, 6, 4);

        return [
            'e164' => "+1{$digits}",
            'national' => "({$area}) {$prefix}-{$line}",
            'digits' => $digits,
            'country' => 'US',
        ];
    }

    /**
     * Register market extension for future international expansion.
     * Note: Does not activate international operations on initial launch unless explicitly permitted.
     */
    public static function registerMarketExtension(string $countryCode, array $config, bool $activateNow = false): void
    {
        $code = strtoupper(trim($countryCode));
        self::$marketRegistry[$code] = $config;

        if ($activateNow) {
            if (!in_array($code, self::$allowedCountries, true)) {
                self::$allowedCountries[] = $code;
            }
            $currency = strtoupper($config['currency'] ?? '');
            if ($currency && !in_array($currency, self::$allowedCurrencies, true)) {
                self::$allowedCurrencies[] = $currency;
            }
        }
    }

    /**
     * Reset market registry back to initial US launch configuration.
     */
    public static function resetToInitialUSLaunch(): void
    {
        self::$allowedCountries = ['US'];
        self::$allowedCurrencies = ['USD'];
    }
}
