<?php

declare(strict_types=1);

namespace App\Services\Events;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Address lookup and map previews through a configured mapping service (Mapbox at present).
 *
 * Coordinates are never typed in or guessed: a pin can only come from a lookup result, which the server signs
 * (an encrypted token naming the coordinates, the provider and the address it matched). Saving an event accepts a
 * pin only with a valid token, so every pin shown to guests is one the mapping service returned and the host
 * confirmed. With no service configured, lookups and previews are simply unavailable and no pin is shown.
 */
final class MapLocationService
{
    private const TOKEN_TTL_SECONDS = 86400;

    public function enabled(): bool
    {
        return $this->provider() !== null;
    }

    public function provider(): ?string
    {
        $provider = strtolower((string) config('services.maps.provider'));

        return $provider === 'mapbox' && filled(config('services.maps.mapbox_token')) ? 'mapbox' : null;
    }

    /**
     * Candidate places for an address, best first. Each carries a signed token the event form sends back.
     *
     * @return list<array{label: string, latitude: float, longitude: float, token: string}>
     */
    public function lookup(string $address, string $countryCode = 'US'): array
    {
        if ($this->provider() !== 'mapbox' || mb_strlen(trim($address)) < 5) {
            return [];
        }
        try {
            $response = Http::timeout(6)->acceptJson()->get('https://api.mapbox.com/geocoding/v5/mapbox.places/' . rawurlencode(trim($address)) . '.json', [
                'access_token' => config('services.maps.mapbox_token'),
                'country' => strtolower($countryCode),
                'types' => 'address,poi',
                'limit' => 5,
                'autocomplete' => 'false',
            ]);
        } catch (\Throwable $e) {
            Log::warning('maps.lookup_failed', ['error' => $e->getMessage()]);

            return [];
        }
        if (!$response->successful()) {
            Log::warning('maps.lookup_failed', ['status' => $response->status()]);

            return [];
        }

        $results = [];
        foreach ((array) $response->json('features', []) as $feature) {
            $center = $feature['center'] ?? null;
            if (!is_array($center) || count($center) !== 2 || !is_numeric($center[0]) || !is_numeric($center[1])) {
                continue;
            }
            [$lng, $lat] = [(float) $center[0], (float) $center[1]];
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                continue;
            }
            $label = (string) ($feature['place_name'] ?? '');
            $placeId = (string) ($feature['id'] ?? '');
            $results[] = [
                'label' => $label,
                'latitude' => round($lat, 7),
                'longitude' => round($lng, 7),
                'token' => $this->sign(['lat' => round($lat, 7), 'lng' => round($lng, 7), 'place_id' => $placeId, 'label' => $label, 'source' => 'mapbox']),
            ];
        }

        return $results;
    }

    /**
     * The verified pin a token stands for, or null when the token is missing, altered or too old.
     *
     * @return array{latitude: float, longitude: float, location_source: string, location_place_id: string, label: string}|null
     */
    public function verify(?string $token): ?array
    {
        if (!filled($token)) {
            return null;
        }
        try {
            $data = json_decode(Crypt::decryptString((string) $token), true, 4, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }
        if (!is_array($data) || !isset($data['lat'], $data['lng'], $data['issued'], $data['source'])
            || time() - (int) $data['issued'] > self::TOKEN_TTL_SECONDS) {
            return null;
        }

        return [
            'latitude' => (float) $data['lat'],
            'longitude' => (float) $data['lng'],
            'location_source' => (string) $data['source'],
            'location_place_id' => (string) ($data['place_id'] ?? ''),
            'label' => (string) ($data['label'] ?? ''),
        ];
    }

    /** A static map image of a verified pin, or null when no service is configured. */
    public function previewUrl(float $latitude, float $longitude, int $width = 640, int $height = 320): ?string
    {
        $publicToken = (string) config('services.maps.mapbox_public_token');
        if ($this->provider() !== 'mapbox' || !filled($publicToken)) {
            return null;
        }
        $pin = sprintf('pin-l+c7ff45(%F,%F)', $longitude, $latitude);

        return sprintf('https://api.mapbox.com/styles/v1/mapbox/streets-v12/static/%s/%F,%F,15/%dx%d@2x?access_token=%s',
            $pin, $longitude, $latitude, $width, $height, rawurlencode($publicToken));
    }

    /** A link that opens the pin in the guest's own maps app. */
    public static function directionsUrl(float $latitude, float $longitude): string
    {
        return sprintf('https://www.google.com/maps/dir/?api=1&destination=%F,%F', $latitude, $longitude);
    }

    private function sign(array $data): string
    {
        return Crypt::encryptString(json_encode($data + ['issued' => time()], JSON_THROW_ON_ERROR));
    }
}
