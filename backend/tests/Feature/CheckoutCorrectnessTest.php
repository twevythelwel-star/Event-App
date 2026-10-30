<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Organizer;
use App\Models\User;
use App\Services\OrderPlacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Money and stock rules at checkout: host-configured ticket types only, at the host's price,
 * no overselling when two checkouts hold the same event, and one order per submission.
 */
class CheckoutCorrectnessTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private MenuItem $dish;
    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $organizer = Organizer::create(['name' => 'Checkout Org', 'slug' => 'checkout-org']);
        $this->event = Event::allTenants()->create([
            'organizer_id' => $organizer->id,
            'slug' => 'checkout-night',
            'title' => 'Checkout Night',
            'date_str' => 'Saturday, November 28, 2026',
            'time_str' => '6:00 PM - 11:00 PM',
            'currency' => 'JMD',
            'venue_name' => 'Pier',
            'venue_address' => 'Kingston',
            'organizer_name' => 'Checkout Org',
            'organizer_phone' => '+18765550000',
            'advance_order_deadline' => now()->addWeek(),
            'status' => 'published',
            'is_ordering_open' => true,
            'ticket_tiers' => [
                ['id' => 'tier-ga', 'name' => 'General Admission', 'price_jmd' => 2000, 'price_usd' => 13, 'quantity_available' => 1, 'max_per_order' => 4],
                ['id' => 'tier-free-text', 'name' => 'Unpriced Pass', 'quantity_available' => 10],
            ],
        ]);
        $this->dish = MenuItem::allTenants()->create([
            'organizer_id' => $organizer->id,
            'event_id' => $this->event->id,
            'name' => 'Escovitch Fish',
            'category' => 'Mains',
            'pricing_type' => 'fixed',
            'price_usd' => 10,
            'price_jmd' => 1500,
            'available_stock' => 5,
            'is_active' => true,
        ]);
        $this->buyer = User::create(['name' => 'Buyer One', 'email' => 'buyer@example.test', 'phone' => '+18765550123', 'password' => bcrypt('password'), 'status' => 'active']);
    }

    private function place(array $items, array $extra = [], ?Event $event = null): array
    {
        return app(OrderPlacementService::class)->placeOrder($event ?? Event::allTenants()->findOrFail($this->event->id), array_merge([
            'customer_name' => 'Buyer One',
            'customer_phone' => '+1 876 555 0123',
            'method' => 'bank',
            'currency' => 'JMD',
            'items' => $items,
        ], $extra), $this->buyer);
    }

    public function test_configured_ticket_is_sold_at_the_hosts_price(): void
    {
        $result = $this->place([['menu_item_id' => 'tier-ga', 'quantity' => 1]]);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $line = $result['order']->items->first();
        $this->assertNull($line->menu_item_id);
        $this->assertSame('tier-ga', $line->ticket_tier_id);
        $this->assertSame('Tickets', $line->category);
        $this->assertEquals(2000, (float) $line->final_item_price);
        $this->assertEquals(2000, (float) $result['order']->total_amount);
        $this->assertSame('awaiting_payment', $result['order']->status);
    }

    public function test_ticket_types_the_host_never_created_are_rejected(): void
    {
        foreach (['tier-vip', 'family-bundle', 'general-admission'] as $invented) {
            $result = $this->place([['menu_item_id' => $invented, 'quantity' => 1]]);
            $this->assertFalse($result['success'], "{$invented} should not be sellable");
        }
        $this->assertSame(0, Order::allTenants()->count());
    }

    public function test_ticket_type_without_a_price_is_not_sold(): void
    {
        $result = $this->place([['menu_item_id' => 'tier-free-text', 'quantity' => 1]]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('price', strtolower($result['error']));
    }

    public function test_two_checkouts_holding_the_same_event_cannot_oversell(): void
    {
        // Both requests loaded the event while one ticket was left, as concurrent requests would.
        $first = Event::allTenants()->findOrFail($this->event->id);
        $second = Event::allTenants()->findOrFail($this->event->id);

        $this->assertTrue($this->place([['menu_item_id' => 'tier-ga', 'quantity' => 1]], [], $first)['success']);
        $late = $this->place([['menu_item_id' => 'tier-ga', 'quantity' => 1]], [], $second);

        $this->assertFalse($late['success']);
        $this->assertStringContainsString('Insufficient ticket inventory', $late['error']);
        $this->assertSame(0, (int) Event::allTenants()->find($this->event->id)->ticket_tiers[0]['quantity_available']);
        $this->assertSame(1, Order::allTenants()->count());
    }

    public function test_food_stock_cannot_go_below_zero(): void
    {
        $this->assertFalse($this->place([['menu_item_id' => $this->dish->id, 'quantity' => 6]])['success']);
        $this->assertTrue($this->place([['menu_item_id' => $this->dish->id, 'quantity' => 5]])['success']);
        $this->assertFalse($this->place([['menu_item_id' => $this->dish->id, 'quantity' => 1]])['success']);
        $this->assertSame(0, (int) $this->dish->fresh()->available_stock);
    }

    public function test_failed_line_rolls_back_stock_already_reserved(): void
    {
        $result = $this->place([
            ['menu_item_id' => $this->dish->id, 'quantity' => 2],
            ['menu_item_id' => 'tier-vip', 'quantity' => 1],
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame(5, (int) $this->dish->fresh()->available_stock);
    }

    public function test_client_prices_are_ignored(): void
    {
        $result = $this->place([['menu_item_id' => $this->dish->id, 'quantity' => 1, 'price' => 1, 'final_item_price' => 1]], ['packaging_fee' => 0, 'total_amount' => 1]);

        $this->assertTrue($result['success']);
        $this->assertEquals(1500, (float) $result['order']->items->first()->final_item_price);
    }

    public function test_zero_quantity_lines_are_ignored_but_an_empty_order_is_rejected(): void
    {
        $result = $this->place([
            ['menu_item_id' => $this->dish->id, 'quantity' => 1],
            ['menu_item_id' => 'tier-ga', 'quantity' => 0],
        ]);
        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertCount(1, $result['order']->items);

        $empty = $this->place([['menu_item_id' => $this->dish->id, 'quantity' => 0]]);
        $this->assertFalse($empty['success']);
    }

    public function test_the_same_submission_creates_one_order(): void
    {
        $first = $this->place([['menu_item_id' => $this->dish->id, 'quantity' => 1]], ['idempotency_key' => 'form-abc-123']);
        $again = $this->place([['menu_item_id' => $this->dish->id, 'quantity' => 1]], ['idempotency_key' => 'form-abc-123']);

        $this->assertTrue($again['success']);
        $this->assertSame($first['order']->id, $again['order']->id);
        $this->assertSame(1, Order::allTenants()->count());
        $this->assertSame(4, (int) $this->dish->fresh()->available_stock);
        $this->assertSame('form-abc-123', $first['order']->fresh()->idempotency_key);
    }

    public function test_event_page_shows_real_tickets_in_the_events_currency(): void
    {
        // Ordering itself moved to the checkout flow (CustomerCheckoutFlowTest); the event page
        // lists the host's priced ticket types and links into that flow.
        $this->buyer->forceFill(['phone' => null])->save();

        $this->actingAs($this->buyer)->get('/events/' . $this->event->id)->assertOk()
            ->assertSee('General Admission')
            ->assertSee('J$2,000.00')
            ->assertDontSee('Unpriced Pass')
            ->assertSee(route('checkout.tickets', $this->event), false)
            ->assertDontSee('555-0199');
    }

    public function test_browser_order_redirects_to_tickets_with_confirmation(): void
    {
        $response = $this->actingAs($this->buyer)->post('/events/' . $this->event->slug . '/order', [
            'customer_name' => 'Buyer One',
            'customer_phone' => '+1 876 555 0123',
            'method' => 'bank',
            'currency' => 'JMD',
            'idempotency_key' => 'browser-form-1',
            'items' => [
                ['menu_item_id' => 'tier-ga', 'quantity' => 1],
                ['menu_item_id' => $this->dish->id, 'quantity' => 0],
            ],
        ]);

        $order = Order::allTenants()->firstOrFail();
        $response->assertRedirect(route('attendee.dashboard'))->assertSessionHas('success');
        $this->assertStringContainsString($order->ticket_code, session('success'));
        $this->assertSame('awaiting_payment', $order->status);
    }

    public function test_browser_order_failure_returns_to_the_form_with_the_reason(): void
    {
        $response = $this->actingAs($this->buyer)->from('/events/' . $this->event->slug)->post('/events/' . $this->event->slug . '/order', [
            'customer_name' => 'Buyer One',
            'customer_phone' => '+1 876 555 0123',
            'method' => 'bank',
            'currency' => 'JMD',
            'items' => [['menu_item_id' => $this->dish->id, 'quantity' => 9]],
        ]);

        $response->assertRedirect('/events/' . $this->event->slug)->assertSessionHas('error');
        $this->assertStringContainsString('Insufficient stock', session('error'));
        $this->assertSame(0, Order::allTenants()->count());
    }

    public function test_api_order_still_returns_json(): void
    {
        $this->actingAs($this->buyer)->postJson('/events/' . $this->event->slug . '/order', [
            'customer_name' => 'Buyer One',
            'customer_phone' => '+1 876 555 0123',
            'method' => 'cash',
            'currency' => 'JMD',
            'items' => [['menu_item_id' => $this->dish->id, 'quantity' => 1]],
        ])->assertCreated()->assertJsonPath('status', 'awaiting_payment');
    }

    public function test_currency_is_always_the_events(): void
    {
        $result = $this->place([['menu_item_id' => $this->dish->id, 'quantity' => 1]], ['currency' => 'USD']);

        $this->assertTrue($result['success']);
        $this->assertSame('JMD', $result['order']->currency);
        $this->assertEquals(1500, (float) $result['order']->total_amount);
    }

    public function test_option_choices_must_exist(): void
    {
        $plate = MenuItem::allTenants()->create([
            'organizer_id' => $this->event->organizer_id, 'event_id' => $this->event->id, 'name' => 'Jerk Plate',
            'category' => 'Mains', 'pricing_type' => 'customizable', 'price_usd' => 8, 'price_jmd' => 1200, 'is_active' => true,
            'custom_option_groups' => [['id' => 'size', 'title' => 'Size', 'choices' => [['label' => 'Regular', 'extraPriceJMD' => 0], ['label' => 'Large', 'extraPriceJMD' => 300]]]],
        ]);

        $bad = $this->place([['menu_item_id' => $plate->id, 'quantity' => 1, 'selected_choices' => ['size' => 9]]]);
        $this->assertFalse($bad['success']);

        $good = $this->place([['menu_item_id' => $plate->id, 'quantity' => 1, 'selected_choices' => ['size' => 1]]]);
        $this->assertEquals(1500, (float) $good['order']->items->first()->final_item_price);
    }

    public function test_donation_amount_respects_the_hosts_minimum(): void
    {
        $donation = MenuItem::allTenants()->create([
            'organizer_id' => $this->event->organizer_id, 'event_id' => $this->event->id, 'name' => 'Youth Fund',
            'category' => 'Donations', 'pricing_type' => 'custom_amount', 'price_usd' => 0, 'price_jmd' => 0, 'is_active' => true,
            'custom_amount_config' => ['minJMD' => 500, 'minUSD' => 5],
        ]);

        $this->assertFalse($this->place([['menu_item_id' => $donation->id, 'quantity' => 1, 'custom_donation_amount' => 100]])['success']);
        $this->assertFalse($this->place([['menu_item_id' => $donation->id, 'quantity' => 1]])['success']);
        $ok = $this->place([['menu_item_id' => $donation->id, 'quantity' => 1, 'custom_donation_amount' => 750]]);
        $this->assertTrue($ok['success']);
        $this->assertEquals(750, (float) $ok['order']->total_amount);
    }

    public function test_ordering_closed_after_page_load_is_respected(): void
    {
        $stale = Event::allTenants()->findOrFail($this->event->id);
        Event::allTenants()->whereKey($this->event->id)->update(['is_ordering_open' => false]);

        $result = $this->place([['menu_item_id' => $this->dish->id, 'quantity' => 1]], [], $stale);

        $this->assertFalse($result['success']);
        $this->assertSame(0, Order::allTenants()->count());
    }
}
