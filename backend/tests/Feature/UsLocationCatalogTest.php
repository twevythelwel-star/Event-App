<?php

namespace Tests\Feature;

use App\Models\TargetMarket;
use App\Services\LocationAddressNormalizer;
use Database\Seeders\FishFryEventsSeeder;
use Database\Seeders\UsLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UsLocationCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UsLocationSeeder::class);
    }

    public function test_location_catalog_includes_us_jurisdictions_and_twenty_recommended_markets(): void
    {
        $response = $this->getJson('/api/v1/locations')->assertOk();

        $this->assertSame(56, count($response->json('states')));
        $this->assertSame(20, count($response->json('markets')));
        $response->assertJsonPath('countryCode', 'US')
            ->assertJsonFragment(['city' => 'Lauderhill', 'stateCode' => 'FL'])
            ->assertJsonFragment(['city' => 'Baltimore', 'stateCode' => 'MD']);
    }

    public function test_reseeding_preserves_administrator_market_edits(): void
    {
        $market = TargetMarket::where('city', 'Lauderhill')->where('state_code', 'FL')->firstOrFail();
        $market->update(['city' => 'Lauderhill Heights', 'discovery_priority' => 7]);

        $this->seed(UsLocationSeeder::class);

        $this->assertSame(1, TargetMarket::where('city', 'Lauderhill Heights')->count());
        $this->assertSame(7, $market->fresh()->discovery_priority);
        $this->assertSame(20, TargetMarket::count());
    }

    public function test_public_event_catalog_filters_by_state_city_and_zip(): void
    {
        $this->seed(FishFryEventsSeeder::class);

        $response = $this->getJson('/api/v1/events?state=GA&city=Atlanta&zip=30303')->assertOk();
        $events = $response->json('data');

        $this->assertNotEmpty($events);
        foreach ($events as $event) {
            $this->assertSame('GA', $event['stateCode']);
            $this->assertSame('Atlanta', $event['city']);
            $this->assertSame('30303', $event['zipCode']);
        }
    }

    public function test_address_normalization_matches_market_and_uses_its_timezone(): void
    {
        $normalized = app(LocationAddressNormalizer::class)->normalize([
            'street_address' => '  100  Main Street ',
            'venue_building_name' => ' Community Hall ',
            'city' => 'lauderhill',
            'state_code' => 'fl',
            'zip_code' => '33311',
        ]);

        $this->assertSame('Lauderhill', $normalized['city']);
        $this->assertSame('FL', $normalized['state_code']);
        $this->assertSame('US', $normalized['country_code']);
        $this->assertSame('America/New_York', $normalized['timezone']);
        $this->assertNotEmpty($normalized['target_market_id']);
        $this->assertSame('100 Main Street, Community Hall, Lauderhill, FL, 33311, United States', $normalized['venue_address']);
    }

    public function test_address_normalization_rejects_unpaired_coordinates_and_non_us_timezones(): void
    {
        $address = [
            'street_address' => '100 Main Street',
            'city' => 'Lauderhill',
            'state_code' => 'FL',
            'zip_code' => '33311',
        ];

        try {
            app(LocationAddressNormalizer::class)->normalize($address + ['latitude' => 26.14]);
            $this->fail('An unpaired coordinate should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('latitude', $exception->errors());
        }

        try {
            app(LocationAddressNormalizer::class)->normalize($address + ['timezone' => 'Europe/London']);
            $this->fail('A non-U.S. event time zone should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('timezone', $exception->errors());
        }
    }
}
