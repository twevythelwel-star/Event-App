<?php

namespace App\Services;

use App\Models\TargetMarket;
use App\Models\UsState;
use Illuminate\Validation\ValidationException;

class LocationAddressNormalizer
{
    /** @return array<int, string> */
    public static function supportedTimezones(): array
    {
        return array_values(array_unique(array_merge(
            \DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, 'US'),
            ['America/Puerto_Rico', 'America/St_Thomas', 'Pacific/Guam', 'Pacific/Saipan', 'Pacific/Pago_Pago'],
        )));
    }

    /**
     * Normalize submitted postal fields and compose the display address from validated components.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function normalize(array $data): array
    {
        $data['country_code'] = strtoupper(trim((string) ($data['country_code'] ?? 'US')));
        if ($data['country_code'] !== 'US') {
            throw ValidationException::withMessages(['country_code' => 'Events are currently available only in the United States and U.S. territories.']);
        }
        if (!empty($data['timezone']) && !in_array($data['timezone'], self::supportedTimezones(), true)) {
            throw ValidationException::withMessages(['timezone' => 'Choose an event time zone used in the United States or its territories.']);
        }

        foreach (['street_address', 'venue_building_name', 'city', 'zip_code'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = trim(preg_replace('/\s+/', ' ', (string) $data[$field]));
                if ($data[$field] === '') {
                    $data[$field] = null;
                }
            }
        }

        $stateCode = strtoupper(trim((string) ($data['state_code'] ?? '')));
        if ($stateCode !== '') {
            $state = UsState::where('code', $stateCode)->where('is_enabled', true)->first();
            if (!$state) {
                throw ValidationException::withMessages(['state_code' => 'Choose an enabled U.S. state or territory.']);
            }
            $data['state_code'] = $stateCode;
        } else {
            $state = null;
        }

        if (empty($data['street_address']) || empty($data['city']) || !$state || empty($data['zip_code'])) {
            throw ValidationException::withMessages([
                'street_address' => 'A complete U.S. street address, city, state, and ZIP code are required.',
            ]);
        }

        if ((isset($data['latitude']) && $data['latitude'] !== null) !== (isset($data['longitude']) && $data['longitude'] !== null)) {
            throw ValidationException::withMessages(['latitude' => 'Provide both coordinates or leave both blank.']);
        }

        if (!empty($data['city']) && $state) {
            $market = TargetMarket::where('state_code', $stateCode)
                ->whereRaw('LOWER(city) = ?', [mb_strtolower($data['city'])])
                ->where('is_enabled', true)
                ->first();
            $data['target_market_id'] = $market?->id;
            $data['city'] = mb_convert_case($data['city'], MB_CASE_TITLE, 'UTF-8');
            if (empty($data['timezone'])) {
                $data['timezone'] = $market?->default_timezone ?? $state->default_timezone;
            }
        } elseif (array_key_exists('city', $data) || array_key_exists('state_code', $data)) {
            $data['target_market_id'] = null;
        }

        if (!empty($data['street_address']) && !empty($data['city']) && $state) {
            $parts = array_filter([
                $data['street_address'],
                $data['venue_building_name'] ?? null,
                $data['city'],
                $state->code,
                $data['zip_code'] ?? null,
                'United States',
            ]);
            $data['venue_address'] = implode(', ', $parts);
        }

        return $data;
    }
}
