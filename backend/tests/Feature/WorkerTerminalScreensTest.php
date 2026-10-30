<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventWorkerAssignment;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The staff terminal screens use the server: food stations look up real orders and hand over through the
 * locked handover service, the supervisor desk shows live counts, and no screen fakes a result.
 */
class WorkerTerminalScreensTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private MenuItem $fish;

    protected function setUp(): void
    {
        parent::setUp();

        $organizer = Organizer::create(['name' => 'Fry Org', 'slug' => 'fry-org']);
        $this->event = Event::allTenants()->create([
            'organizer_id' => $organizer->id,
            'slug' => 'fry-night',
            'title' => 'Fry Night',
            'date_str' => 'Saturday, November 28, 2026',
            'time_str' => '6:00 PM - 11:00 PM',
            'venue_name' => 'Pier',
            'venue_address' => 'Kingston',
            'organizer_name' => 'Fry Org',
            'organizer_phone' => '+18765550000',
            'advance_order_deadline' => now()->addWeek(),
            'status' => 'published',
        ]);
        $this->fish = MenuItem::withoutGlobalScopes()->create([
            'organizer_id' => $organizer->id,
            'event_id' => $this->event->id,
            'name' => 'Escovitch Fish',
            'category' => 'mains',
            'pricing_type' => 'fixed',
            'price_jmd' => 1500,
            'price_usd' => 10,
            'is_active' => true,
        ]);
    }

    private function worker(string $role, string $station = 'all'): User
    {
        $user = User::create(['name' => ucfirst($role), 'email' => uniqid($role) . '@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        EventWorkerAssignment::create([
            'organizer_id' => $this->event->organizer_id,
            'event_id' => $this->event->id,
            'user_id' => $user->id,
            'worker_role' => $role,
            'station_assignment' => $station,
            'status' => 'active',
            'is_active' => true,
        ]);

        return $user;
    }

    private function foodOrder(string $code, string $status, int $quantity = 3, string $category = 'mains'): Order
    {
        $order = Order::allTenants()->create([
            'organizer_id' => $this->event->organizer_id,
            'event_id' => $this->event->id,
            'ticket_code' => "SFF-{$code}",
            'order_number' => "ORD-2026-{$code}",
            'customer_name' => 'Dana Guest',
            'customer_phone' => '+1 876 555 0101',
            'method' => 'cash',
            'subtotal' => 4500,
            'total_amount' => 4500,
            'amount_paid' => $status === 'paid' ? 4500 : 0,
            'currency' => 'JMD',
            'status' => $status,
            'secure_ticket_token' => "tok_food_{$code}",
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'menu_item_id' => $this->fish->id,
            'name' => 'Escovitch Fish',
            'category' => $category,
            'pricing_type' => 'fixed',
            'base_price' => 1500,
            'final_item_price' => 1500,
            'currency' => 'JMD',
            'quantity' => $quantity,
            'collected_quantity' => 0,
        ]);

        return $order->fresh('items');
    }

    private function url(string $action): string
    {
        return "/worker/events/{$this->event->id}/{$action}";
    }

    public function test_food_station_looks_up_by_qr_token_and_hands_over_up_to_the_ordered_quantity(): void
    {
        $station = $this->worker('food_station_worker');
        $order = $this->foodOrder('200001', 'paid');
        $item = $order->items->first();

        $this->actingAs($station)->postJson($this->url('lookup'), ['code' => $order->secure_ticket_token])
            ->assertOk()
            ->assertJsonPath('order.order_number', $order->order_number)
            ->assertJsonPath('order.customer_first_name', 'Dana')
            ->assertJsonPath('order.payment_verified', true)
            ->assertJsonPath('order.items.0.remaining', 3)
            ->assertJsonMissing(['total_amount' => 4500])
            ->assertJsonMissingPath('order.customer_phone');

        $this->actingAs($station)->postJson($this->url('collect'), ['order_id' => $order->id, 'order_item_id' => $item->id, 'quantity' => 2])
            ->assertOk()->assertJsonPath('order.items.0.remaining', 1);

        // Asking for more than is left is refused and changes nothing.
        $this->actingAs($station)->postJson($this->url('collect'), ['order_id' => $order->id, 'order_item_id' => $item->id, 'quantity' => 2])
            ->assertStatus(422);
        $this->assertSame(2, (int) $item->fresh()->collected_quantity);

        $this->assertDatabaseHas('handover_audit_logs', ['order_id' => $order->id, 'quantity_collected' => 2, 'action_type' => 'collection']);
    }

    public function test_unpaid_orders_and_other_stations_items_are_not_handed_over(): void
    {
        $order = $this->foodOrder('200002', 'awaiting_payment');
        $item = $order->items->first();

        $this->actingAs($this->worker('food_station_worker'))->postJson($this->url('collect'), ['order_id' => $order->id, 'order_item_id' => $item->id, 'quantity' => 1])
            ->assertStatus(422);

        $paid = $this->foodOrder('200003', 'paid', 2, 'mains');
        $this->actingAs($this->worker('food_station_worker', 'drinks'))
            ->postJson($this->url('collect'), ['order_id' => $paid->id, 'order_item_id' => $paid->items->first()->id, 'quantity' => 1])
            ->assertForbidden();

        $this->assertSame(0, (int) $item->fresh()->collected_quantity);
        $this->assertSame(0, (int) $paid->items->first()->fresh()->collected_quantity);
    }

    public function test_food_lookup_needs_a_food_assignment_and_stays_inside_the_event(): void
    {
        $order = $this->foodOrder('200004', 'paid');

        $this->actingAs($this->worker('payment_clerk'))->postJson($this->url('lookup'), ['code' => $order->ticket_code])->assertForbidden();
        $this->actingAs($this->worker('food_station_worker'))->postJson($this->url('lookup'), ['code' => 'ORD-2026-999999'])->assertNotFound();
    }

    public function test_terminal_pages_call_the_server_and_show_no_simulated_results(): void
    {
        $checker = $this->actingAs($this->worker('ticket_checker'))->get($this->url('terminal'))->assertOk();
        $checker->assertSee(route('worker.scan', $this->event->id), false);

        $food = $this->actingAs($this->worker('food_station_worker'))->get($this->url('terminal'))->assertOk();
        $food->assertSee(route('worker.lookup', $this->event->id), false);
        $food->assertSee(route('worker.collect', $this->event->id), false);

        $clerk = $this->actingAs($this->worker('payment_clerk'))->get($this->url('terminal'))->assertOk();
        $clerk->assertSee(route('worker.cash', $this->event->id), false);

        $stranger = User::create(['name' => 'Stranger', 'email' => 'stranger@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        $this->actingAs($stranger)->get($this->url('terminal'))->assertForbidden();
        $this->actingAs($this->worker('custom_restricted_worker'))->get($this->url('terminal'))->assertForbidden();

        foreach ([$checker, $food, $clerk] as $page) {
            $page->assertDontSee('alert(', false);
            $page->assertDontSee('ORD-2026-8812');
            $page->assertDontSee('87% Admitted');
        }
    }

    public function test_supervisor_desk_shows_live_counts(): void
    {
        $this->foodOrder('200005', 'paid', 3);
        $handedOver = $this->foodOrder('200006', 'paid', 2);
        $handedOver->items->first()->update(['collected_quantity' => 2]);
        $this->foodOrder('200007', 'awaiting_payment', 5); // unpaid food is not counted as owed

        $this->actingAs($this->worker('event_supervisor'))->get($this->url('terminal'))
            ->assertOk()
            ->assertSee('2 / 5')                              // portions handed over / paid portions
            ->assertSee('3 paid portions still to hand over')
            ->assertDontSee('142 / 165');
    }
}
