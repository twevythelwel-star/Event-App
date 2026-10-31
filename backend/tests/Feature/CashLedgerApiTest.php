<?php

namespace Tests\Feature;

use App\Models\CashPayment;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrganizerUser;
use App\Models\User;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\DemoOrdersSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashLedgerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class, DemoOrdersSeeder::class]);
    }

    private function user(string $who): User
    {
        return User::where('email', "{$who}@islandevents.example")->firstOrFail();
    }

    private function cashOrder(): Order
    {
        return Order::withoutGlobalScopes()->where('event_id', 'evt-kingston-harbour-fry')->where('method', 'cash')->firstOrFail();
    }

    private function paidOrder(): Order
    {
        return Order::withoutGlobalScopes()->where('event_id', 'evt-kingston-harbour-fry')->where('status', 'paid')->firstOrFail();
    }

    /** A Kingston organizer member who may take payments but is not the owner. */
    private function verifier(): User
    {
        $user = User::create([
            'name' => 'Treasurer Davis',
            'email' => 'treasurer@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        OrganizerUser::create(['organizer_id' => 'org-kingston-youth', 'user_id' => $user->id, 'role' => 'payment_verifier']);

        return $user;
    }

    private function recordCash(User $user, array $payload)
    {
        return $this->actingAs($user)->postJson('/api/v1/operations/cash', $payload);
    }

    public function test_host_records_cash_against_an_order(): void
    {
        $order = $this->cashOrder();
        $half = round((float) $order->total_amount / 2, 2);

        $response = $this->recordCash($this->user('host'), [
            'order_id' => $order->id,
            'amount' => $half,
            'received_from' => 'Pastor Brown',
            'notes' => 'Paid at the gate',
        ])->assertCreated();

        $response->assertJsonPath('receipt.transactionType', 'receipt')
            ->assertJsonPath('receipt.receivedBy', 'Demo Event Host')
            ->assertJsonPath('receipt.orderId', $order->id)
            ->assertJsonPath('receipt.eventId', 'evt-kingston-harbour-fry')
            ->assertJsonPath('order.status', 'partially_paid');
        $this->assertStringStartsWith('CSH-', $response->json('receipt.receiptNumber'));
        $this->assertStringContainsString('Handed to: Pastor Brown', $response->json('receipt.notes'));

        // Paying the rest settles the order
        $this->recordCash($this->user('host'), ['order_id' => $order->id, 'amount' => (float) $order->total_amount - $half])
            ->assertCreated()
            ->assertJsonPath('order.status', 'paid');

        $this->assertSame(2, CashPayment::withoutGlobalScopes()->where('order_id', $order->id)->count());
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'cash_receipt_recorded']);
    }

    public function test_cash_amount_rules(): void
    {
        $order = $this->cashOrder();
        $host = $this->user('host');

        $this->recordCash($host, ['order_id' => $order->id, 'amount' => (float) $order->total_amount + 1])->assertStatus(422);
        $this->recordCash($host, ['order_id' => $order->id, 'amount' => 100, 'currency' => 'USD'])->assertStatus(422);
        $this->recordCash($host, ['order_id' => $order->id, 'amount' => 0])->assertStatus(422);
        $this->recordCash($host, ['amount' => 500, 'customer_name' => 'Walk Up', 'customer_phone' => '8765550000'])->assertStatus(422);
    }

    public function test_walk_up_cash_is_scoped_to_the_hosts_events(): void
    {
        $host = $this->user('host');

        $this->recordCash($host, [
            'event_id' => 'evt-kingston-harbour-fry',
            'amount' => 10,
            'customer_name' => 'Walk Up Guest',
            'customer_phone' => '+1 305 555 0140',
        ])->assertCreated()->assertJsonPath('order', null);

        $otherEvent = Event::withoutGlobalScopes()->where('organizer_id', 'org-hellshire-fishers')->firstOrFail();
        $this->recordCash($host, [
            'event_id' => $otherEvent->id,
            'amount' => 10,
            'customer_name' => 'Walk Up Guest',
            'customer_phone' => '+1 305 555 0140',
        ])->assertForbidden();
    }

    public function test_food_station_staff_and_attendees_cannot_handle_cash(): void
    {
        $order = $this->cashOrder();

        $this->recordCash($this->user('staff'), ['order_id' => $order->id, 'amount' => 100])->assertForbidden();
        $this->actingAs($this->user('staff'))->getJson('/api/v1/operations/cash')->assertOk()->assertJsonCount(0, 'data');

        $this->recordCash($this->user('attendee'), ['order_id' => $order->id, 'amount' => 100])->assertNotFound();
        $this->actingAs($this->user('attendee'))->getJson('/api/v1/operations/cash')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_only_the_owner_can_reverse_and_reversals_are_bounded(): void
    {
        $order = $this->cashOrder();
        $verifier = $this->verifier();

        // A payment verifier may take cash ...
        $receiptId = $this->recordCash($verifier, ['order_id' => $order->id, 'amount' => 5])
            ->assertCreated()
            ->json('receipt.id');

        // ... but cannot unwind it
        $this->actingAs($verifier)->postJson("/api/v1/operations/cash/{$receiptId}/reverse", ['amount' => 1000, 'reason' => 'Mistake'])
            ->assertForbidden();

        $host = $this->user('host');
        $this->actingAs($host)->postJson("/api/v1/operations/cash/{$receiptId}/reverse", ['amount' => 6, 'reason' => 'Too much'])
            ->assertStatus(422);

        $reversal = $this->actingAs($host)->postJson("/api/v1/operations/cash/{$receiptId}/reverse", ['amount' => 4, 'reason' => 'Counted wrong'])
            ->assertCreated()
            ->assertJsonPath('reversal.transactionType', 'reversal')
            ->assertJsonPath('reversal.amount', -4)
            ->assertJsonPath('reversal.originalRecordId', $receiptId)
            ->assertJsonPath('reversal.reversalAuthorizedBy', 'Demo Event Host')
            ->assertJsonPath('order.amountPaid', 1);

        // Only the remaining 600 can still be reversed, and a reversal cannot be reversed
        $this->actingAs($host)->postJson("/api/v1/operations/cash/{$receiptId}/reverse", ['amount' => 2, 'reason' => 'Again'])->assertStatus(422);
        $this->actingAs($host)->postJson('/api/v1/operations/cash/' . $reversal->json('reversal.id') . '/reverse', ['amount' => 100, 'reason' => 'Undo'])
            ->assertStatus(422);

        $this->actingAs($host)->postJson("/api/v1/operations/cash/{$receiptId}/reverse", ['amount' => 1, 'reason' => 'Customer took cash back'])
            ->assertCreated()
            ->assertJsonPath('order.amountPaid', 0)
            ->assertJsonPath('order.status', 'awaiting_payment');
    }

    public function test_host_sees_own_ledger_only(): void
    {
        $this->recordCash($this->user('host'), ['order_id' => $this->cashOrder()->id, 'amount' => 10])->assertCreated();

        $this->actingAs($this->user('host'))->getJson('/api/v1/operations/cash')->assertOk()->assertJsonCount(1, 'data');

        $outsider = User::create(['name' => 'Other Host', 'email' => 'other@example.test', 'password' => bcrypt('x'), 'status' => 'active']);
        OrganizerUser::create(['organizer_id' => 'org-hellshire-fishers', 'user_id' => $outsider->id, 'role' => 'event_owner']);
        $this->actingAs($outsider)->getJson('/api/v1/operations/cash')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_refunds_are_owner_only_bounded_and_mark_full_refunds(): void
    {
        $order = $this->paidOrder();
        $total = (float) $order->total_amount;
        $host = $this->user('host');

        $this->actingAs($this->verifier())->postJson("/api/v1/operations/orders/{$order->id}/refund", ['amount' => 5, 'reason' => 'x'])
            ->assertForbidden();
        $this->actingAs($host)->postJson("/api/v1/operations/orders/{$order->id}/refund", ['amount' => $total + 1, 'reason' => 'Too much'])
            ->assertStatus(422);

        $this->actingAs($host)->postJson("/api/v1/operations/orders/{$order->id}/refund", ['amount' => 5, 'reason' => 'One item unavailable'])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.refundDetails.amount', 5)
            ->assertJsonPath('data.refundDetails.refundedBy', 'Demo Event Host');

        $this->actingAs($host)->postJson("/api/v1/operations/orders/{$order->id}/refund", ['amount' => $total - 5, 'reason' => 'Event cancelled'])
            ->assertOk()
            ->assertJsonPath('data.status', 'refunded')
            ->assertJsonCount(2, 'data.refunds');

        // Refunded orders release no food
        $item = $order->items()->first();
        $this->actingAs($host)->postJson("/api/v1/operations/orders/{$order->id}/items/{$item->id}/handover", ['quantity_delta' => 1])
            ->assertStatus(422);
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'order_refunded', 'target_entity' => 'orders']);
    }

    public function test_unpaid_orders_cannot_be_refunded_and_other_organizers_orders_are_hidden(): void
    {
        $host = $this->user('host');
        $unpaid = Order::withoutGlobalScopes()->where('event_id', 'evt-kingston-harbour-fry')->where('status', 'payment_submitted')->firstOrFail();

        $this->actingAs($host)->postJson("/api/v1/operations/orders/{$unpaid->id}/refund", ['amount' => 100, 'reason' => 'x'])
            ->assertStatus(422);
        $this->actingAs($host)->postJson('/api/v1/operations/orders/not-a-real-order/refund', ['amount' => 100, 'reason' => 'x'])
            ->assertNotFound();
    }
}
