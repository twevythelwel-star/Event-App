<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\HostPaymentPolicy;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrganizerUser;
use App\Models\User;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\DemoOrdersSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HostEventsApiTest extends TestCase
{
    use RefreshDatabase;

    private const KINGSTON = 'evt-kingston-harbour-fry';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class, DemoOrdersSeeder::class]);
    }

    private function user(string $who): User
    {
        return User::where('email', "{$who}@islandevents.example")->firstOrFail();
    }

    private function newItem(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Bammy & Festival Combo',
            'category' => 'Sides',
            'pricing_type' => 'fixed',
            'price_usd' => 4.5,
            'price_jmd' => 700,
            'available_stock' => 80,
        ], $overrides);
    }

    public function test_host_lists_only_their_own_organizers_events_with_menus(): void
    {
        $events = $this->actingAs($this->user('host'))->getJson('/api/v1/host/events')->assertOk()->json('data');

        $this->assertCount(1, $events);
        $this->assertSame(self::KINGSTON, $events[0]['id']);
        $this->assertNotEmpty($events[0]['menu']);

        $this->actingAs($this->user('attendee'))->getJson('/api/v1/host/events')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($this->user('staff'))->getJson('/api/v1/host/events')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_host_edits_event_details(): void
    {
        $this->actingAs($this->user('host'))->patchJson('/api/v1/host/events/' . self::KINGSTON, [
            'title' => 'South Florida Jamaican Fish Fry (Extended)',
            'pickup_instructions' => 'Station B, next to the pier steps.',
            'packaging_fee_jmd' => 150,
            'advance_order_deadline' => '2026-11-21 18:00',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'South Florida Jamaican Fish Fry (Extended)')
            ->assertJsonPath('data.packagingFeeJMD', 150)
            ->assertJsonPath('data.advanceOrderDeadline', 'Saturday, Nov 21, 2026 at 6:00 PM');

        // Stored in UTC: 18:00 Jamaica time is 23:00 UTC
        $this->assertSame('2026-11-21 23:00:00', Event::withoutGlobalScopes()->find(self::KINGSTON)->advance_order_deadline->utc()->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'event_updated', 'target_id' => self::KINGSTON]);
    }

    public function test_host_ticket_configuration_is_persisted_with_the_event(): void
    {
        $event = Event::withoutGlobalScopes()->findOrFail(self::KINGSTON);
        $tier = $event->ticket_tiers[0];
        $tier = array_merge($tier, [
            'category' => 'Premium VIP',
            'minPerOrder' => 2,
            'admissionCount' => 2,
            'ageRestriction' => '21+',
            'admissionPrivileges' => 'Priority entrance',
            'includedFoodAndDrinks' => 'One welcome drink',
            'benefits' => ['Priority entrance', 'Reserved seating'],
            'refundEligible' => false,
            'visibility' => 'public',
            'designStyle' => 'elegant_gold',
            'accentColor' => 'gold',
            'backgroundStyle' => 'gradient',
            'isActive' => true,
        ]);

        $this->actingAs($this->user('host'))
            ->patchJson('/api/v1/host/events/' . self::KINGSTON, ['ticket_tiers' => [$tier]])
            ->assertOk()
            ->assertJsonPath('data.ticketTiers.0.category', 'Premium VIP')
            ->assertJsonPath('data.ticketTiers.0.minPerOrder', 2)
            ->assertJsonPath('data.ticketTiers.0.admissionCount', 2)
            ->assertJsonPath('data.ticketTiers.0.ageRestriction', '21+')
            ->assertJsonPath('data.ticketTiers.0.refundEligible', false)
            ->assertJsonPath('data.ticketTiers.0.designStyle', 'elegant_gold')
            ->assertJsonPath('data.ticketTiers.0.accentColor', 'gold')
            ->assertJsonPath('data.ticketTiers.0.backgroundStyle', 'gradient');
    }

    public function test_public_catalog_excludes_hidden_and_inactive_ticket_tiers_but_host_data_keeps_them(): void
    {
        $event = Event::withoutGlobalScopes()->findOrFail(self::KINGSTON);
        $tiers = $event->ticket_tiers;
        $this->assertGreaterThanOrEqual(3, count($tiers));
        $tiers[0]['visibility'] = 'hidden';
        $tiers[1]['isActive'] = false;
        $tiers[2]['minPerOrder'] = 2;
        $event->ticket_tiers = $tiers;
        $event->save();

        $catalog = $this->getJson('/api/v1/events?search=South%20Florida%20Jamaican%20Fish%20Fry')->assertOk();
        $publicEvent = collect($catalog->json('data'))->firstWhere('id', self::KINGSTON);
        $this->assertNotNull($publicEvent);
        $this->assertCount(count($tiers) - 2, $publicEvent['ticketTiers']);
        $this->assertSame(2, $publicEvent['ticketTiers'][0]['min']);
        $this->assertArrayNotHasKey('visibility', $publicEvent['ticketTiers'][0]);

        $hostEvent = $this->actingAs($this->user('host'))
            ->getJson('/api/v1/host/events')
            ->assertOk()
            ->json('data.0');
        $this->assertCount(count($tiers), $hostEvent['ticketTiers']);
    }

    public function test_host_schedule_round_trips_overnight_and_multi_day_times_in_event_timezone(): void
    {
        $host = $this->user('host');
        $url = '/api/v1/host/events/' . self::KINGSTON;
        $schedule = [
            'event_date_iso' => '2027-01-15',
            'start_time' => '22:30',
            'end_time' => '02:00',
            'doors_open_time' => '21:00',
            'setup_time' => '19:00',
            'timezone' => 'America/New_York',
            'ticket_sales_start_at' => '2027-01-10T10:00',
            'ticket_sales_end_at' => '2027-01-14T18:00',
            'early_bird_starts_at' => '2027-01-11T09:00',
            'early_bird_ends_at' => '2027-01-12T18:00',
        ];

        $this->actingAs($host)->patchJson($url, $schedule)
            ->assertOk()
            ->assertJsonPath('data.eventDateIso', '2027-01-15')
            ->assertJsonPath('data.endDateIso', null)
            ->assertJsonPath('data.startTime', '22:30')
            ->assertJsonPath('data.endTime', '02:00')
            ->assertJsonPath('data.ticketSalesStartIso', '2027-01-10T10:00')
            ->assertJsonPath('data.earlyBirdSaleStartIso', '2027-01-11T09:00')
            ->assertJsonPath('data.earlyBirdSaleEndIso', '2027-01-12T18:00');

        $event = Event::withoutGlobalScopes()->findOrFail(self::KINGSTON);
        $this->assertSame('2027-01-16 03:30:00', $event->starts_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2027-01-16 07:00:00', $event->ends_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2027-01-11 14:00:00', $event->early_bird_starts_at->utc()->format('Y-m-d H:i:s'));
        $this->assertFalse(\App\Services\Checkout\TicketTierCatalog::isEarlyBird(
            $event,
            \Illuminate\Support\Carbon::parse('2027-01-11 13:59:00', 'UTC')->timestamp
        ));
        $this->assertTrue(\App\Services\Checkout\TicketTierCatalog::isEarlyBird(
            $event,
            \Illuminate\Support\Carbon::parse('2027-01-11 14:00:00', 'UTC')->timestamp
        ));
        $this->assertFalse(\App\Services\Checkout\TicketTierCatalog::isEarlyBird(
            $event,
            \Illuminate\Support\Carbon::parse('2027-01-12 23:00:00', 'UTC')->timestamp
        ));

        $this->actingAs($host)->patchJson($url, ['end_date_iso' => '2027-01-18'])
            ->assertOk()
            ->assertJsonPath('data.endDateIso', '2027-01-18')
            ->assertJsonPath('data.endTime', '02:00');
        $this->assertSame(
            '2027-01-18 07:00:00',
            Event::withoutGlobalScopes()->findOrFail(self::KINGSTON)->ends_at->utc()->format('Y-m-d H:i:s')
        );
    }

    public function test_host_schedule_rejects_an_invalid_ticket_and_early_bird_window(): void
    {
        $this->actingAs($this->user('host'))
            ->patchJson('/api/v1/host/events/' . self::KINGSTON, [
                'event_date_iso' => '2027-01-15',
                'start_time' => '18:00',
                'end_time' => '22:00',
                'timezone' => 'America/New_York',
                'ticket_sales_start_at' => '2027-01-10T10:00',
                'ticket_sales_end_at' => '2027-01-09T10:00',
                'early_bird_starts_at' => '2027-01-12T10:00',
                'early_bird_ends_at' => '2027-01-11T10:00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ticket_sales_end_at', 'early_bird_ends_at']);
    }

    public function test_host_confirms_and_removes_only_a_mapping_service_location_pin(): void
    {
        config([
            'services.maps.provider' => 'mapbox',
            'services.maps.mapbox_token' => 'private-test-token',
        ]);
        Http::fake([
            'api.mapbox.com/*' => Http::response(['features' => [[
                'center' => [-80.123456, 25.987654],
                'place_name' => '100 Park Place, Miami, Florida, United States',
                'id' => 'address.test-location',
            ]]], 200),
        ]);

        $host = $this->user('host');
        $lookup = $this->actingAs($host)->postJson('/api/v1/host/location-lookup', [
            'address' => '100 Park Place, Miami, FL',
            'country_code' => 'US',
        ])->assertOk()->assertJsonPath('enabled', true);
        $token = $lookup->json('results.0.token');
        $this->assertNotEmpty($token);

        $url = '/api/v1/host/events/' . self::KINGSTON;
        $this->actingAs($host)->patchJson($url, ['location_token' => $token])
            ->assertOk()
            ->assertJsonPath('data.locationVerified', true)
            ->assertJsonPath('data.latitude', 25.987654)
            ->assertJsonPath('data.longitude', -80.123456);

        $this->actingAs($host)->patchJson($url, ['location_token' => $token . 'tampered'])->assertStatus(422);
        $this->actingAs($host)->patchJson($url, ['clear_location_pin' => true])
            ->assertOk()
            ->assertJsonPath('data.locationVerified', false)
            ->assertJsonPath('data.latitude', null)
            ->assertJsonPath('data.longitude', null);
    }

    public function test_enabled_non_us_country_accepts_free_form_region_and_postal_code(): void
    {
        config(['events.market.allowed_countries' => ['US', 'CA']]);

        $this->actingAs($this->user('host'))->patchJson('/api/v1/host/events/' . self::KINGSTON, [
            'country_code' => 'CA',
            'street_address' => '10 King Street',
            'city' => 'Toronto',
            'state_code' => 'Ontario',
            'zip_code' => 'M5H 1A1',
        ])->assertOk()->assertJsonPath('data.countryCode', 'CA')->assertJsonPath('data.stateCode', 'Ontario');
    }

    public function test_only_owners_of_that_organizer_can_edit(): void
    {
        $verifier = User::create(['name' => 'Treasurer', 'email' => 'treasurer@example.test', 'password' => bcrypt('x'), 'status' => 'active']);
        OrganizerUser::create(['organizer_id' => 'org-kingston-youth', 'user_id' => $verifier->id, 'role' => 'payment_verifier']);

        $this->actingAs($verifier)->patchJson('/api/v1/host/events/' . self::KINGSTON, ['title' => 'Hijacked'])->assertNotFound();
        $this->actingAs($this->user('staff'))->postJson('/api/v1/host/events/' . self::KINGSTON . '/menu', $this->newItem())->assertNotFound();

        $otherEvent = Event::withoutGlobalScopes()->where('organizer_id', 'org-hellshire-fishers')->firstOrFail();
        $this->actingAs($this->user('host'))->patchJson("/api/v1/host/events/{$otherEvent->id}", ['title' => 'Hijacked'])->assertNotFound();
        $this->actingAs($this->user('host'))->postJson("/api/v1/host/events/{$otherEvent->id}/status", ['status' => 'unpublished'])->assertNotFound();

        $this->assertNotSame('Hijacked', $otherEvent->fresh()->title);
    }

    public function test_publish_lifecycle_and_rules(): void
    {
        $host = $this->user('host');
        $url = '/api/v1/host/events/' . self::KINGSTON . '/status';

        $this->actingAs($host)->postJson($url, ['status' => 'unpublished'])
            ->assertOk()
            ->assertJsonPath('data.status', 'unpublished')
            ->assertJsonPath('data.isOrderingOpen', false);

        // Unpublished events leave the public catalog but stay in the host's list
        $this->assertFalse(collect($this->getJson('/api/v1/events')->json('data'))->contains('id', self::KINGSTON));
        $this->actingAs($host)->getJson('/api/v1/host/events')->assertJsonPath('data.0.status', 'unpublished');

        $this->actingAs($host)->patchJson('/api/v1/host/events/' . self::KINGSTON, ['is_ordering_open' => true])->assertStatus(422);

        // Publishing again needs this event's own Event Compliance acknowledgement (the seeded event has none).
        $this->actingAs($host)->postJson($url, ['status' => 'published'])->assertStatus(422);
        $this->actingAs($host)->patchJson('/api/v1/host/events/' . self::KINGSTON, $this->eventComplianceFields() + $this->eventArrangementFields(true))->assertOk();

        $this->actingAs($host)->postJson($url, ['status' => 'published'])
            ->assertOk()
            ->assertJsonPath('data.isOrderingOpen', true);

        $this->actingAs($host)->postJson($url, ['status' => 'cancelled'])->assertOk();
        $this->actingAs($host)->postJson($url, ['status' => 'published'])->assertStatus(422);
    }

    public function test_publishing_requires_a_menu(): void
    {
        $host = $this->user('host');
        MenuItem::withoutGlobalScopes()->where('event_id', self::KINGSTON)->delete();
        // Nothing to sell at all: no menu and no ticket tiers.
        Event::withoutGlobalScopes()->whereKey(self::KINGSTON)->update(['status' => 'draft', 'ticket_tiers' => null]);

        $this->actingAs($host)->postJson('/api/v1/host/events/' . self::KINGSTON . '/status', ['status' => 'published'])
            ->assertStatus(422);
    }

    public function test_host_manages_menu_items_and_prices(): void
    {
        $host = $this->user('host');
        $base = '/api/v1/host/events/' . self::KINGSTON . '/menu';
        $before = MenuItem::withoutGlobalScopes()->where('event_id', self::KINGSTON)->count();

        $menu = $this->actingAs($host)->postJson($base, $this->newItem())->assertCreated()->json('data.menu');
        $this->assertCount($before + 1, $menu);
        $created = collect($menu)->firstWhere('name', 'Bammy & Festival Combo');
        $this->assertSame(700, $created['priceJMD']);

        $this->actingAs($host)->putJson("{$base}/{$created['id']}", ['price_jmd' => 800, 'available_stock' => 0])
            ->assertOk();
        $item = MenuItem::withoutGlobalScopes()->find($created['id']);
        $this->assertSame(800, $item->price_jmd);
        $this->assertSame(0, $item->available_stock);

        $this->actingAs($host)->deleteJson("{$base}/{$created['id']}")->assertOk()->assertJsonCount($before, 'data.menu');
        $this->assertSoftDeleted('menu_items', ['id' => $created['id']]);
    }

    public function test_menu_validation_rules(): void
    {
        $host = $this->user('host');
        $base = '/api/v1/host/events/' . self::KINGSTON . '/menu';

        $this->actingAs($host)->postJson($base, $this->newItem(['price_jmd' => -5]))->assertStatus(422);
        $this->actingAs($host)->postJson($base, $this->newItem(['pricing_type' => 'customizable']))->assertStatus(422);
        $this->actingAs($host)->postJson($base, $this->newItem([
            'pricing_type' => 'custom_amount',
            'custom_amount_config' => ['minUSD' => 10, 'maxUSD' => 5, 'minJMD' => 1000, 'maxJMD' => 5000],
        ]))->assertStatus(422);
        $this->actingAs($host)->postJson($base, $this->newItem([
            'pricing_type' => 'customizable',
            'custom_option_groups' => [['id' => 'size', 'title' => 'Size', 'required' => true, 'choices' => [['label' => 'Big', 'extraPriceUSD' => -1, 'extraPriceJMD' => 0]]]],
        ]))->assertStatus(422);

        // Items from another event cannot be edited through this event
        $foreign = MenuItem::withoutGlobalScopes()->where('event_id', '!=', self::KINGSTON)->firstOrFail();
        $this->actingAs($host)->putJson("{$base}/{$foreign->id}", ['price_jmd' => 1])->assertNotFound();
        $this->assertNotSame(1, $foreign->fresh()->price_jmd);
    }

    public function test_food_service_configuration_is_saved_and_returned_for_attendees(): void
    {
        $response = $this->actingAs($this->user('host'))->patchJson('/api/v1/host/events/' . self::KINGSTON, [
            'food_ordering_mode' => 'event_day',
            'food_fulfillment' => 'pickup_and_delivery',
            'food_delivery_instructions' => 'Delivery to tables in the main pavilion.',
            'food_service_start_time' => '12:00',
            'food_service_end_time' => '18:00',
            'food_service_fee' => 2.25,
            'food_max_orders' => 75,
            'food_pickup_windows' => [
                ['start' => '13:00', 'end' => '14:00', 'max_orders' => 15],
            ],
        ])->assertOk()
            ->assertJsonPath('data.foodServiceConfig.serviceMode', 'eventDay')
            ->assertJsonPath('data.foodServiceConfig.fulfillmentMode', 'pickup_and_delivery')
            ->assertJsonPath('data.foodServiceConfig.serviceFeeUSD', 2.25)
            ->assertJsonPath('data.foodServiceConfig.maxOrdersPerEvent', 75)
            ->assertJsonPath('data.foodServiceConfig.pickupWindows.0.maxOrders', 15);

        $this->assertDatabaseHas('events', [
            'id' => self::KINGSTON,
            'food_ordering_mode' => 'event_day',
            'food_fulfillment' => 'pickup_and_delivery',
            'food_service_fee_minor' => 225,
            'food_max_orders' => 75,
        ]);
    }

    public function test_host_can_create_a_package_from_existing_items_and_menu_metadata_persists(): void
    {
        $host = $this->user('host');
        $base = '/api/v1/host/events/' . self::KINGSTON . '/menu';
        $included = MenuItem::withoutGlobalScopes()
            ->where('event_id', self::KINGSTON)
            ->where('item_type', 'item')
            ->firstOrFail();

        $packageResponse = $this->actingAs($host)->postJson($base, $this->newItem([
            'name' => 'Jamaican Sunday Dinner',
            'category' => 'Meal Packages',
            'item_type' => 'package',
            'price_usd' => 22,
            'price_jmd' => 0,
            'package_config' => [
                'components' => [[
                    'menu_item_id' => $included->id,
                    'quantity' => 2,
                    'substitutes' => [],
                ]],
                'choice_groups' => [],
                'add_ons' => [],
            ],
        ]))->assertCreated();

        $packageResponse->assertJsonPath('data.packages.0.name', 'Jamaican Sunday Dinner')
            ->assertJsonPath('data.packages.0.components.0.menuItemId', $included->id)
            ->assertJsonPath('data.packages.0.components.0.quantity', 2);
        $this->assertDatabaseHas('menu_items', [
            'event_id' => self::KINGSTON,
            'name' => 'Jamaican Sunday Dinner',
            'item_type' => 'package',
        ]);

        $itemResponse = $this->actingAs($host)->postJson($base, $this->newItem([
            'name' => 'Coconut Rice',
            'country_of_origin' => 'Jamaica',
            'ingredients' => 'Rice, coconut milk, thyme.',
            'dietary_tags' => ['vegan'],
            'allergens' => ['sesame'],
            'prep_minutes' => 20,
            'max_per_order' => 4,
            'is_featured' => true,
        ]))->assertCreated();

        $created = collect($itemResponse->json('data.menu'))->firstWhere('name', 'Coconut Rice');
        $this->assertSame('Jamaica', $created['countryOfOrigin']);
        $this->assertSame('Rice, coconut milk, thyme.', $created['ingredients']);
        $this->assertSame(['vegan'], $created['dietaryTags']);
        $this->assertSame(['sesame'], $created['allergens']);
        $this->assertSame(20, $created['prepTimeMinutes']);
        $this->assertSame(4, $created['maxQtyPerOrder']);
        $this->assertTrue($created['isFeatured']);
    }

    public function test_menu_availability_datetimes_are_stored_in_utc_from_the_event_timezone(): void
    {
        $this->actingAs($this->user('host'))
            ->postJson('/api/v1/host/events/' . self::KINGSTON . '/menu', $this->newItem([
                'available_from' => '2026-11-20T12:30',
                'available_until' => '2026-11-20T14:00',
            ]))
            ->assertCreated();

        $item = MenuItem::withoutGlobalScopes()
            ->where('event_id', self::KINGSTON)
            ->where('name', 'Bammy & Festival Combo')
            ->firstOrFail();

        $this->assertSame('2026-11-20 17:30:00', $item->available_from->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-20 19:00:00', $item->available_until->utc()->format('Y-m-d H:i:s'));
    }

    public function test_deleting_a_menu_item_keeps_past_orders_intact(): void
    {
        $host = $this->user('host');
        $order = Order::withoutGlobalScopes()->where('event_id', self::KINGSTON)->with('items')->firstOrFail();
        $item = $order->items->first();

        $this->actingAs($host)->deleteJson('/api/v1/host/events/' . self::KINGSTON . "/menu/{$item->menu_item_id}")->assertOk();

        $this->assertSame($item->name, $order->fresh('items')->items->first()->name);
    }

    public function test_host_creates_draft_event_with_timezone_and_ticket_tiers_via_wizard(): void
    {
        $host = $this->user('host');
        HostPaymentPolicy::updateOrCreate(['organizer_id' => 'org-kingston-youth'], [
            'refund_policy_deadline_hours' => 36,
            'refund_policy_notes' => 'Organizer default policy note.',
            'refund_policy_mode' => 'manual_full_only',
            'host_cancellation_policy' => 'contact_organizer',
            'postponement_policy' => 'tickets_valid_for_new_date',
            'weather_cancellation_policy' => 'reschedule_tickets_valid',
            'food_order_cancellation_deadline_hours' => 30,
            'food_order_modification_deadline_hours' => 12,
        ]);

        $payload = [
            'title' => 'Lauderhill Sunset Seafood & Reggae Night',
            'tagline' => 'Fresh Lobster, Snapper & Acoustic Roots',
            'category' => 'Seafood & Fish Fry',
            'date_str' => 'Saturday, December 19, 2026',
            'event_date_iso' => '2026-12-19',
            'time_str' => '4:00 PM – 11:00 PM',
            'start_time' => '16:00',
            'end_time' => '23:00',
            'timezone' => 'America/New_York',
            'venue_name' => 'Atlanta Community Park',
            'street_address' => '100 Park Place',
            'city' => 'Atlanta',
            'state_code' => 'GA',
            'zip_code' => '30303',
            'country_code' => 'US',
            'venue_address' => '100 Park Place, Atlanta, GA, 30303, United States',
            'organizer_name' => 'Atlanta Community Foundation',
            'organizer_phone' => '+1 (404) 555-0188',
            'advance_order_deadline' => '2026-12-18 18:00',
            'packaging_fee_usd' => 1.25,
            'packaging_fee_jmd' => 200,
            'supports_food_ordering' => true,
            'ticket_tiers' => [
                [
                    'id' => 'tier-ga',
                    'name' => 'General Admission',
                    'category' => 'General',
                    'priceUSD' => 15,
                    'priceJMD' => 2300,
                    'availableStock' => 150,
                    'maxPerCustomer' => 8,
                    'saleStartIso' => '2026-11-01T00:00',
                    'saleEndIso' => '2026-12-19T16:00',
                    'isActive' => true,
                ],
            ],
        ];

        $payload['title'] = 'Atlanta Community Food & Music Night';
        $response = $this->actingAs($host)->postJson('/api/v1/host/events', $payload)
            ->assertCreated()
            ->assertJsonPath('data.title', 'Atlanta Community Food & Music Night')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.timezone', 'America/New_York')
            ->assertJsonPath('data.category', 'Seafood & Fish Fry')
            ->assertJsonPath('data.refundPolicyDeadlineHours', 36)
            ->assertJsonPath('data.refundPolicyNotes', 'Organizer default policy note.')
            ->assertJsonPath('data.refundPolicyMode', 'manual_full_only')
            ->assertJsonPath('data.foodOrderCancellationDeadlineHours', 30);

        $createdId = $response->json('data.id');
        $this->assertNotEmpty($createdId);

        $this->actingAs($host)->patchJson("/api/v1/host/events/{$createdId}", [
            'refund_policy_mode' => 'non_refundable',
            'non_refundable_ticket_categories' => ['General'],
            'food_order_modification_deadline_hours' => 6,
        ])->assertOk()
            ->assertJsonPath('data.refundPolicyMode', 'non_refundable')
            ->assertJsonPath('data.nonRefundableTicketCategories.0', 'General')
            ->assertJsonPath('data.foodOrderModificationDeadlineHours', 6);

        // Reject invalid ticket tier pricing (negative price) on server
        $invalidTierPayload = $payload;
        $invalidTierPayload['ticket_tiers'][0]['priceUSD'] = -5;
        $this->actingAs($host)->patchJson("/api/v1/host/events/{$createdId}", [
            'ticket_tiers' => $invalidTierPayload['ticket_tiers'],
        ])->assertStatus(422);

        // Reject invalid IANA timezone on server
        $this->actingAs($host)->patchJson("/api/v1/host/events/{$createdId}", [
            'timezone' => 'Invalid/Timezone',
        ])->assertStatus(422);

        // Unauthorized staff cannot create events
        $this->actingAs($this->user('staff'))->postJson('/api/v1/host/events', $payload)->assertStatus(403);
    }
}
