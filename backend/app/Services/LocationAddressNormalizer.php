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
            \DateTimeZone::listIdentifiers(),
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
        $data['country_code'] = strtoupper(trim((string) ($data['country_code'] ?? config('events.market.default_country', 'US'))));
        // The market's countries come from configuration. U.S. regions use the curated catalog; other enabled
        // countries can use free-form region and postal values without constraining future location catalogs.
        if (!in_array($data['country_code'], (array) config('events.market.allowed_countries', ['US']), true)) {
            throw ValidationException::withMessages(['country_code' => 'Choose a country enabled for event locations.']);
        }
        $usesStateCatalog = $data['country_code'] === 'US';
        if (!empty($data['timezone']) && !in_array($data['timezone'], self::supportedTimezones(), true)) {
            throw ValidationException::withMessages(['timezone' => 'Choose a valid event time zone.']);
        }

        if (($data['event_format'] ?? 'in_person') === 'virtual') {
            $data['venue_name'] = filled($data['virtual_url'] ?? null) ? 'Online event' : ($data['venue_name'] ?? 'Online event');
            $data['venue_address'] = '';
            $data['street_address'] = null;
            $data['venue_building_name'] = null;
            $data['city'] = null;
            $data['state_code'] = null;
            $data['zip_code'] = null;
            $data['target_market_id'] = null;
            return $data;
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
        if ($usesStateCatalog && $stateCode !== '') {
            $state = UsState::where('code', $stateCode)->where('is_enabled', true)->first();
            if (!$state) {
                throw ValidationException::withMessages(['state_code' => 'Choose an enabled U.S. state or territory.']);
            }
            $data['state_code'] = $stateCode;
        } else {
            $state = null;
            if ($stateCode !== '') {
                $data['state_code'] = trim((string) $data['state_code']);
            }
            if ($usesStateCatalog && !empty($data['zip_code']) && !preg_match('/^\d{5}(?:-\d{4})?$/', (string) $data['zip_code'])) {
                throw ValidationException::withMessages(['zip_code' => 'Enter a valid U.S. ZIP code.']);
            }
        }

        if (empty($data['street_address']) || empty($data['city']) || ($usesStateCatalog && (!$state || empty($data['zip_code'])))) {
            if (($data['event_status'] ?? null) === 'draft') {
                return $data;
            }
            $message = $usesStateCatalog
                ? 'A complete U.S. street address, city, state, and ZIP code are required.'
                : 'A street address and city are required for an international venue.';
            throw ValidationException::withMessages(['street_address' => $message]);
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

        if (!empty($data['street_address']) && !empty($data['city']) && ($state || !$usesStateCatalog)) {
            $parts = array_filter([
                $data['street_address'],
                $data['venue_building_name'] ?? null,
                $data['city'],
                $state?->code ?? ($data['state_code'] ?? null),
                $data['zip_code'] ?? null,
                $usesStateCatalog ? 'United States' : $data['country_code'],
            ]);
            $data['venue_address'] = implode(', ', $parts);
        }

        return $data;
    }
}
