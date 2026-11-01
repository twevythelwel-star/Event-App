<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
    }

    private function attendee(): User
    {
        return User::where('email', 'attendee@islandevents.example')->firstOrFail();
    }

    private function orderPayload(array $items, array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Demo Attendee',
            'customer_phone' => '+1 305 555 0199',
            'method' => 'bank',
            'currency' => 'USD',
            'payment_reference' => 'ACH-REF-001',
            'items' => $items,
        ], $overrides);
    }

    public function test_session_login_and_logout_round_trip(): void
    {
        $this->getJson('/api/v1/session')->assertOk()->assertJson(['user' => null]);

        $this->postJson('/api/v1/session', ['email' => 'host@islandevents.example', 'password' => 'wrong-password'])
            ->assertStatus(422);

        $this->postJson('/api/v1/session', ['email' => 'host@islandevents.example', 'password' => DemoAccountsSeeder::DEMO_PASSWORD])
            ->assertOk()
            ->assertJsonPath('user.email', 'host@islandevents.example')
            ->assertJsonPath('user.roles', ['attendee', 'event_host']);

        $this->getJson('/api/v1/session')->assertJsonPath('user.email', 'host@islandevents.example');

        $this->deleteJson('/api/v1/session')->assertOk();
        $this->getJson('/api/v1/session')->assertJson(['user' => null]);
    }

    public function test_login_limit_is_per_account_so_a_shared_venue_ip_is_not_locked_out(): void
    {
        // Eight different people signing in from the same venue Wi-Fi within a minute
        for ($i = 1; $i <= 8; $i++) {
            \App\Models\User::create([
                'name' => "Gate Staff {$i}",
                'email' => "gate{$i}@example.test",
                'password' => password_hash('correct-horse', PASSWORD_DEFAULT),
                'status' => 'active',
            ]);
            $this->postJson('/api/v1/session', ['email' => "gate{$i}@example.test", 'password' => 'correct-horse'])->assertOk();
            $this->deleteJson('/api/v1/session');
        }

        // Guessing one account's password is still cut off after 5 attempts
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/v1/session', ['email' => 'host@islandevents.example', 'password' => "guess-{$i}"])->assertStatus(422);
        }
        $this->postJson('/api/v1/session', ['email' => 'host@islandevents.example', 'password' => 'guess-6'])->assertStatus(429);
    }

    public function test_events_endpoint_returns_published_events_with_menus(): void
    {
        $response = $this->getJson('/api/v1/events')->assertOk();

        $events = collect($response->json('data'));
        $this->assertCount(20, $events); // one sample event per target market
        $this->assertTrue($events->every(
            fn (array $event) => $event['currency'] === 'USD'
                && str_ends_with($event['venueAddress'], 'United States')
                && str_starts_with($event['timezone'], 'America/')
        ));

        $miami = $events->firstWhere('id', 'evt-kingston-harbour-fry');
        $this->assertSame('south-florida-jamaican-fish-fry', $miami['slug']);
        $this->assertSame('USD', $miami['currency']);
        $this->assertSame('America/New_York', $miami['timezone']);
        $this->assertStringContainsString('United States', $miami['venueAddress']);
        $this->assertNotEmpty($miami['menu']);
        $this->assertSame('kgn-escovitch', $miami['menu'][0]['id']);
        $this->assertSame('customizable', $miami['menu'][0]['pricingType']);
    }

    public function test_guest_cannot_place_an_order(): void
    {
        $this->postJson('/api/v1/events/south-florida-jamaican-fish-fry/orders', $this->orderPayload([
            ['menu_item_id' => 'kgn-fried-chicken', 'quantity' => 1],
        ]))->assertUnauthorized();
    }

    public function test_attendee_order_is_priced_by_the_server(): void
    {
        $response = $this->actingAs($this->attendee())->postJson('/api/v1/events/south-florida-jamaican-fish-fry/orders', $this->orderPayload([
            ['menu_item_id' => 'kgn-escovitch', 'quantity' => 2, 'selected_choices' => ['fish-size' => 1, 'sides' => 0]],
            ['menu_item_id' => 'kgn-fried-chicken', 'quantity' => 1],
        ], ['packaging_fee' => 0]));

        $response->assertCreated()
            ->assertJsonPath('data.status', 'payment_submitted')
            ->assertJsonPath('data.amountPaid', 0);

        // ($18 + $2 large portion) x 2 + $15 chicken, plus the $1.50 event packaging fee.
        $this->assertEquals(55, $response->json('data.subtotal'));
        $this->assertEquals(1.5, $response->json('data.packagingFee'));
        $this->assertEquals(56.5, $response->json('data.totalAmount'));
        $this->assertSame('USD', $response->json('data.currency'));
        $this->assertStringStartsWith('tok_', $response->json('data.secureTicketToken'));

        $this->actingAs($this->attendee())->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.orderNumber', $response->json('data.orderNumber'));
    }

    public function test_failed_order_rolls_back_stock_already_reserved(): void
    {
        $chicken = MenuItem::withoutGlobalScopes()->findOrFail('kgn-fried-chicken');
        $stockBefore = $chicken->available_stock;

        $escovitch = MenuItem::withoutGlobalScopes()->findOrFail('kgn-escovitch');
        $escovitch->update(['available_stock' => 1]);

        $this->actingAs($this->attendee())->postJson('/api/v1/events/south-florida-jamaican-fish-fry/orders', $this->orderPayload([
            ['menu_item_id' => 'kgn-fried-chicken', 'quantity' => 3],
            ['menu_item_id' => 'kgn-escovitch', 'quantity' => 5],
        ]))->assertStatus(422);

        $this->assertSame($stockBefore, $chicken->fresh()->available_stock);
        $this->assertSame(0, Order::withoutGlobalScopes()->count());
    }

    public function test_menu_items_from_another_event_are_rejected(): void
    {
        $otherEventItem = MenuItem::withoutGlobalScopes()->where('event_id', '!=', 'evt-kingston-harbour-fry')->firstOrFail();

        $this->actingAs($this->attendee())->postJson('/api/v1/events/south-florida-jamaican-fish-fry/orders', $this->orderPayload([
            ['menu_item_id' => $otherEventItem->id, 'quantity' => 1],
        ]))->assertStatus(422);
    }
}
