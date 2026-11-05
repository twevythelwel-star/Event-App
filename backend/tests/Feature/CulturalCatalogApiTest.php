<?php

namespace Tests\Feature;

use App\Models\Dish;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\CulturalCatalogSeeder;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CulturalCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = 'evt-kingston-harbour-fry';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CulturalCatalogSeeder::class, FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
    }

    private function host(): User
    {
        return User::where('email', 'host@islandevents.example')->firstOrFail();
    }

    public function test_catalog_exposes_regions_countries_cuisines_and_country_specific_dishes(): void
    {
        $catalog = $this->getJson('/api/v1/cultural-catalog')->assertOk();

        $catalog->assertJsonFragment(['id' => 'caribbean', 'name' => 'Caribbean']);
        $catalog->assertJsonFragment(['id' => 'greater-antilles', 'name' => 'Greater Antilles']);
        $catalog->assertJsonFragment(['id' => 'JM', 'name' => 'Jamaica']);
        $catalog->assertJsonFragment(['name' => 'Jamaican Cuisine']);
        $catalog->assertJsonFragment(['name' => 'Ackee and Saltfish']);
        $catalog->assertJsonFragment(['name' => 'Haitian Cuisine']);
        $catalog->assertJsonFragment(['name' => 'Soup Joumou']);
        $catalog->assertJsonFragment(['id' => 'asian', 'name' => 'Asian']);
        $catalog->assertJsonFragment(['id' => 'central-asia', 'name' => 'Central Asia']);
        $catalog->assertJsonFragment(['name' => 'Uzbek Cuisine']);
        $catalog->assertJsonFragment(['name' => 'Plov']);
        $catalog->assertJsonFragment(['name' => 'Dosa', 'dietaryType' => 'vegetarian']);
    }

    public function test_host_saves_multiple_cultural_identities_countries_catalog_dishes_and_custom_dishes(): void
    {
        $jerkChicken = Dish::where('name', 'Jerk Chicken')->firstOrFail();
        $plov = Dish::where('name', 'Plov')->firstOrFail();

        $this->actingAs($this->host())->patchJson('/api/v1/host/events/' . self::EVENT, [
            'cultural_category_ids' => ['caribbean', 'asian'],
            'country_ids' => ['JM', 'UZ'],
            'dish_ids' => [$jerkChicken->id, $plov->id],
            'custom_dishes' => [[
                'name' => 'Festival Dumpling',
                'country_id' => 'JM',
                'alternative_spellings' => ['festival dumplin'],
                'is_fusion' => true,
                'dietary_type' => 'vegan',
            ]],
        ])
            ->assertOk()
            ->assertJsonFragment(['culturalCategoryIds' => ['asian', 'caribbean']])
            ->assertJsonFragment(['countryIds' => ['JM', 'UZ']])
            ->assertJsonFragment(['name' => 'Jerk Chicken'])
            ->assertJsonFragment(['name' => 'Plov'])
            ->assertJsonPath('data.customDishes.0.name', 'Festival Dumpling')
            ->assertJsonPath('data.customDishes.0.dietaryType', 'vegan')
            ->assertJsonPath('data.customDishes.0.isFusion', true);

        $this->assertDatabaseHas('event_cultural_categories', [
            'event_id' => self::EVENT,
            'cultural_category_id' => 'caribbean',
        ]);
        $this->assertDatabaseHas('event_countries', ['event_id' => self::EVENT, 'country_id' => 'JM']);
        $this->assertDatabaseHas('event_countries', ['event_id' => self::EVENT, 'country_id' => 'UZ']);
    }

    public function test_country_and_dish_selections_must_match_selected_cultures_and_countries(): void
    {
        $jerkChicken = Dish::where('name', 'Jerk Chicken')->firstOrFail();

        $this->actingAs($this->host())->patchJson('/api/v1/host/events/' . self::EVENT, [
            'cultural_category_ids' => ['european'],
            'country_ids' => ['JM'],
            'dish_ids' => [$jerkChicken->id],
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('event_countries', ['event_id' => self::EVENT, 'country_id' => 'JM']);
        $this->assertSame([], Event::withoutGlobalScopes()->findOrFail(self::EVENT)->culturalCategories()->pluck('id')->all());
    }

    public function test_virtual_events_can_save_without_a_physical_address_and_featured_is_privileged(): void
    {
        $this->actingAs($this->host())->patchJson('/api/v1/host/events/' . self::EVENT, [
            'event_format' => 'virtual',
            'virtual_url' => 'https://events.example.test/room',
            'virtual_access_instructions' => 'A joining link will be emailed to registered attendees.',
        ])
            ->assertOk()
            ->assertJsonPath('data.eventFormat', 'virtual')
            ->assertJsonPath('data.virtualUrl', 'https://events.example.test/room')
            ->assertJsonPath('data.venueName', 'Online event');

        $this->actingAs($this->host())->patchJson('/api/v1/host/events/' . self::EVENT, [
            'is_featured' => true,
        ])->assertForbidden();
    }

    public function test_only_system_administrators_can_change_the_catalog(): void
    {
        $this->actingAs($this->host())->postJson('/api/v1/admin/cultural-catalog/categories', [
            'name' => 'New Identity',
        ])->assertForbidden();
    }
}
