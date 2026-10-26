<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\HandoverAuditLog;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderPlacementService;
use App\Services\TenantContext;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\DemoOrdersSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderOperationsApiTest extends TestCase
{
    use RefreshDatabase;

    private Order $otherOrganizerOrder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class, DemoOrdersSeeder::class]);

        // An order at an event the demo host does not own
        $hellshire = TenantContext::withoutTenantScope(fn () => Event::where('organizer_id', 'org-hellshire-fishers')->with('menuItems')->firstOrFail());
        $result = TenantContext::withoutTenantScope(fn () => app(OrderPlacementService::class)->placeOrder($hellshire, [
            'customer_name' => 'Other Guest',
            'customer_phone' => '+1 876 555 0300',
            'method' => 'bank',
            'currency' => 'JMD',
            'payment_reference' => 'JN-0001',
            'items' => [['menu_item_id' => $hellshire->menuItems->firstWhere('pricing_type', 'fixed')->id, 'quantity' => 1]],
        ], $this->user('attendee')));
        $this->otherOrganizerOrder = $result['order'];
    }

    private function user(string $who): User
    {
        return User::where('email', "{$who}@islandevents.example")->firstOrFail();
    }

    private function kingstonOrder(string $status): Order
    {
        return Order::withoutGlobalScopes()->where('event_id', 'evt-kingston-harbour-fry')->where('status', $status)->with('items')->firstOrFail();
    }

    public function test_host_lists_only_their_own_organizers_orders(): void
    {
        $orders = collect($this->actingAs($this->user('host'))->getJson('/api/v1/operations/orders')->assertOk()->json('data'));

        $this->assertCount(4, $orders);
        $this->assertTrue($orders->every(fn ($o) => $o['organizerId'] === 'org-kingston-youth'));
        $this->assertSame('Kingston Harbour Waterfront Sunday Fish Fry', $orders->first()['eventTitle']);
        $this->assertTrue($orders->first()['capabilities']['verify']);
    }

    public function test_attendee_has_no_operable_orders(): void
    {
        $this->actingAs($this->user('attendee'))->getJson('/api/v1/operations/orders')->assertOk()->assertJsonCount(0, 'data');

        $order = $this->kingstonOrder('payment_submitted');
        $this->actingAs($this->user('attendee'))
            ->postJson("/api/v1/operations/orders/{$order->id}/payment", ['status' => 'paid'])
            ->assertNotFound();
    }

    public function test_host_cannot_touch_another_organizers_order(): void
    {
        $this->actingAs($this->user('host'))
            ->postJson("/api/v1/operations/orders/{$this->otherOrganizerOrder->id}/payment", ['status' => 'paid'])
            ->assertNotFound();

        $this->actingAs($this->user('host'))
            ->postJson('/api/v1/operations/lookup', ['code' => $this->otherOrganizerOrder->ticket_code])
            ->assertNotFound();

        $this->assertSame('payment_submitted', $this->otherOrganizerOrder->fresh()->status);
    }

    public function test_host_verifies_payment_and_it_is_audited(): void
    {
        $order = $this->kingstonOrder('payment_submitted');

        $response = $this->actingAs($this->user('host'))
            ->postJson("/api/v1/operations/orders/{$order->id}/payment", ['status' => 'paid', 'notes' => 'Matched NCB statement'])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.verifiedBy', 'Demo Event Host');
        $this->assertEquals((float) $order->total_amount, $response->json('data.amountPaid'));

        $this->assertDatabaseHas('security_audit_logs', ['action' => 'order_payment_status_changed', 'target_id' => $order->id]);
    }

    public function test_payment_rules_are_enforced(): void
    {
        $order = $this->kingstonOrder('payment_submitted');
        $host = $this->user('host');

        $this->actingAs($host)->postJson("/api/v1/operations/orders/{$order->id}/payment", ['status' => 'partially_paid', 'amount_paid' => $order->total_amount])
            ->assertStatus(422);
        $this->actingAs($host)->postJson("/api/v1/operations/orders/{$order->id}/payment", ['status' => 'rejected'])
            ->assertStatus(422);
        $this->actingAs($host)->postJson("/api/v1/operations/orders/{$order->id}/payment", ['status' => 'refunded'])
            ->assertStatus(422);

        $this->actingAs($host)->postJson("/api/v1/operations/orders/{$order->id}/payment", ['status' => 'partially_paid', 'amount_paid' => 500])
            ->assertOk()
            ->assertJsonPath('data.status', 'partially_paid')
            ->assertJsonPath('data.amountPaid', 500);
    }

    public function test_staff_member_can_scan_and_hand_over_but_not_verify_or_see_payment_details(): void
    {
        $staff = $this->user('staff');
        $paid = $this->kingstonOrder('paid');

        $lookup = $this->actingAs($staff)->postJson('/api/v1/operations/lookup', ['code' => strtolower($paid->ticket_code)])
            ->assertOk()
            ->assertJsonPath('data.id', $paid->id)
            ->assertJsonPath('data.capabilities.verify', false)
            ->assertJsonPath('data.capabilities.collect', true);
        $this->assertArrayNotHasKey('paymentReference', $lookup->json('data'));

        $pending = $this->kingstonOrder('payment_submitted');
        $this->actingAs($staff)->postJson("/api/v1/operations/orders/{$pending->id}/payment", ['status' => 'paid'])
            ->assertForbidden();
    }

    public function test_handover_tracks_portions_audits_and_closes_the_order(): void
    {
        $staff = $this->user('staff');
        $order = $this->kingstonOrder('paid');
        $chicken = $order->items->firstWhere('name', 'Kingston Seasoned Fried Chicken');
        $escovitch = $order->items->firstWhere('menu_item_id', 'kgn-escovitch');

        $this->actingAs($staff)->postJson("/api/v1/operations/orders/{$order->id}/items/{$chicken->id}/handover", ['quantity_delta' => 1, 'station' => 'mains', 'staff_name' => 'Someone Else'])
            ->assertOk()
            ->assertJsonPath('data.allHandedOver', false)
            ->assertJsonPath('audit.quantityCollected', 1)
            ->assertJsonPath('audit.remainingQuantity', 1)
            ->assertJsonPath('audit.staffName', 'Demo Food Station Staff');

        // Over-collecting and unexplained reversals are refused
        $this->actingAs($staff)->postJson("/api/v1/operations/orders/{$order->id}/items/{$chicken->id}/handover", ['quantity_delta' => 5])
            ->assertStatus(422);
        $this->actingAs($staff)->postJson("/api/v1/operations/orders/{$order->id}/items/{$chicken->id}/handover", ['quantity_delta' => -1, 'action_type' => 'reversal'])
            ->assertStatus(422);

        $this->actingAs($staff)->postJson("/api/v1/operations/orders/{$order->id}/items/{$chicken->id}/handover", ['quantity_delta' => 1]);
        $this->actingAs($staff)->postJson("/api/v1/operations/orders/{$order->id}/items/{$escovitch->id}/handover", ['quantity_delta' => 1])
            ->assertOk()
            ->assertJsonPath('data.allHandedOver', true)
            ->assertJsonPath('data.fulfillmentStatus', 'collected');

        $this->assertSame(3, HandoverAuditLog::withoutGlobalScopes()->where('order_id', $order->id)->count());
        $this->assertNotNull($order->fresh()->closed_at);
    }

    public function test_unpaid_orders_need_a_reasoned_supervisor_override(): void
    {
        $host = $this->user('host');
        $order = $this->kingstonOrder('payment_submitted');
        $item = $order->items->first();

        $this->actingAs($host)->postJson("/api/v1/operations/orders/{$order->id}/items/{$item->id}/handover", ['quantity_delta' => 1])
            ->assertStatus(422);
        $this->actingAs($host)->postJson("/api/v1/operations/orders/{$order->id}/close")
            ->assertStatus(422);

        $this->actingAs($host)->postJson("/api/v1/operations/orders/{$order->id}/items/{$item->id}/handover", [
            'quantity_delta' => 1,
            'action_type' => 'supervisor_override',
            'override_reason' => 'Bank app outage; transfer screenshot shown',
            'supervisor_name' => 'Demo Event Host',
        ])->assertOk()->assertJsonPath('audit.actionType', 'supervisor_override');
    }

    public function test_cash_order_authorized_unpaid_can_be_closed(): void
    {
        $order = Order::withoutGlobalScopes()->where('event_id', 'evt-kingston-harbour-fry')->where('method', 'cash')->firstOrFail();

        $this->actingAs($this->user('host'))->postJson("/api/v1/operations/orders/{$order->id}/close", ['station' => 'all'])
            ->assertOk()
            ->assertJsonPath('data.allHandedOver', true);
    }

    public function test_fulfillment_status_updates_and_cannot_skip_handover(): void
    {
        $order = $this->kingstonOrder('payment_submitted');
        $host = $this->user('host');

        $this->actingAs($host)->postJson("/api/v1/operations/orders/{$order->id}/fulfillment", ['fulfillment_status' => 'preparing'])
            ->assertOk()
            ->assertJsonPath('data.fulfillmentStatus', 'preparing');
        $this->actingAs($host)->postJson("/api/v1/operations/orders/{$order->id}/fulfillment", ['fulfillment_status' => 'collected'])
            ->assertStatus(422);
    }
}
