<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrganizerUser;
use App\Models\User;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\DemoOrdersSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'title' => 'Kingston Harbour Sunday Fish Fry (Extended)',
            'pickup_instructions' => 'Station B, next to the pier steps.',
            'packaging_fee_jmd' => 150,
            'advance_order_deadline' => '2026-11-21 18:00',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Kingston Harbour Sunday Fish Fry (Extended)')
            ->assertJsonPath('data.packagingFeeJMD', 150)
            ->assertJsonPath('data.advanceOrderDeadline', 'Saturday, Nov 21, 2026 at 6:00 PM');

        // Stored in UTC: 18:00 Jamaica time is 23:00 UTC
        $this->assertSame('2026-11-21 23:00:00', Event::withoutGlobalScopes()->find(self::KINGSTON)->advance_order_deadline->utc()->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'event_updated', 'target_id' => self::KINGSTON]);
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
        Event::withoutGlobalScopes()->whereKey(self::KINGSTON)->update(['status' => 'draft']);

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

        $payload = [
            'title' => 'Port Royal Sunset Seafood & Reggae Night',
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
            ->assertJsonPath('data.category', 'Seafood & Fish Fry');

        $createdId = $response->json('data.id');
        $this->assertNotEmpty($createdId);

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
