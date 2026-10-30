<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventWorkerAssignment;
use App\Models\Order;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cash recorded by a Payment Clerk at the event terminal goes through the cash ledger:
 * the order balance is updated, overpayment and closed orders are refused, and only
 * a clerk assigned to that event can record it.
 */
class WorkerCashReceiptTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private User $clerk;

    protected function setUp(): void
    {
        parent::setUp();

        $organizer = Organizer::create(['name' => 'Cash Org', 'slug' => 'cash-org']);
        $this->event = $this->makeEvent($organizer, 'cash-night');

        $this->clerk = User::create(['name' => 'Cash Clerk', 'email' => 'clerk@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        EventWorkerAssignment::create([
            'organizer_id' => $organizer->id,
            'event_id' => $this->event->id,
            'user_id' => $this->clerk->id,
            'worker_role' => 'payment_clerk',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function makeEvent(Organizer $organizer, string $slug): Event
    {
        return Event::allTenants()->create([
            'organizer_id' => $organizer->id,
            'slug' => $slug,
            'title' => ucwords(str_replace('-', ' ', $slug)),
            'date_str' => 'Saturday, November 28, 2026',
            'time_str' => '6:00 PM - 11:00 PM',
            'venue_name' => 'Pier',
            'venue_address' => 'Kingston',
            'organizer_name' => $organizer->name,
            'organizer_phone' => '+18765550000',
            'advance_order_deadline' => now()->addWeek(),
            'status' => 'published',
        ]);
    }

    private function order(Event $event, string $number, string $status = 'awaiting_payment'): Order
    {
        return Order::allTenants()->create([
            'organizer_id' => $event->organizer_id,
            'event_id' => $event->id,
            'ticket_code' => "SFF-{$number}",
            'order_number' => "ORD-2026-{$number}",
            'customer_name' => 'Cash Guest',
            'customer_phone' => '+1 876 555 0101',
            'method' => 'cash',
            'subtotal' => 3000,
            'total_amount' => 3000,
            'amount_paid' => 0,
            'currency' => 'JMD',
            'status' => $status,
            'secure_ticket_token' => "tok_cash_{$number}",
        ]);
    }

    private function pay(Order $order, $amount, ?User $as = null, ?Event $event = null)
    {
        return $this->actingAs($as ?? $this->clerk)->postJson('/worker/events/' . ($event ?? $this->event)->id . '/cash', [
            'order_number' => $order->order_number,
            'amount' => $amount,
        ]);
    }

    public function test_partial_then_full_cash_updates_the_order_and_ledger(): void
    {
        $order = $this->order($this->event, '100001');

        $this->pay($order, 1000)->assertOk()->assertJson(['success' => true, 'status' => 'partially_paid', 'balance' => 2000]);
        $this->assertSame('partially_paid', $order->fresh()->status);

        $this->pay($order, 2000)->assertOk()->assertJson(['success' => true, 'status' => 'paid', 'balance' => 0]);
        $fresh = $order->fresh();
        $this->assertSame('paid', $fresh->status);
        $this->assertEquals(3000, (float) $fresh->amount_paid);

        $this->assertDatabaseCount('cash_payments', 2);
        $this->assertDatabaseHas('cash_payments', ['order_id' => $order->id, 'organizer_id' => $this->event->organizer_id, 'amount' => 1000]);
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'cash_receipt_recorded', 'user_id' => $this->clerk->id]);
    }

    public function test_overpayment_and_closed_orders_are_refused(): void
    {
        $order = $this->order($this->event, '100002');
        $this->pay($order, 3000.01)->assertStatus(422);
        $this->assertSame('awaiting_payment', $order->fresh()->status);

        $refunded = $this->order($this->event, '100003', 'refunded');
        $this->pay($refunded, 100)->assertStatus(422);

        $this->assertDatabaseCount('cash_payments', 0);
    }

    public function test_cash_cannot_be_recorded_without_an_assignment_or_for_another_event(): void
    {
        $order = $this->order($this->event, '100004');
        $stranger = User::create(['name' => 'Stranger', 'email' => 'stranger@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        $this->pay($order, 500, $stranger)->assertForbidden();

        // An order from another organizer's event cannot be paid through this event's terminal.
        $otherOrganizer = Organizer::create(['name' => 'Other Org', 'slug' => 'other-org']);
        $otherOrder = $this->order($this->makeEvent($otherOrganizer, 'other-night'), '100005');
        $this->pay($otherOrder, 500)->assertNotFound();

        $this->assertDatabaseCount('cash_payments', 0);
        $this->assertSame('awaiting_payment', $otherOrder->fresh()->status);
    }

    public function test_a_ticket_checker_cannot_record_cash(): void
    {
        $checker = User::create(['name' => 'Checker', 'email' => 'checker@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        EventWorkerAssignment::create([
            'organizer_id' => $this->event->organizer_id,
            'event_id' => $this->event->id,
            'user_id' => $checker->id,
            'worker_role' => 'ticket_checker',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->pay($this->order($this->event, '100006'), 500, $checker)->assertForbidden();
        $this->assertDatabaseCount('cash_payments', 0);
    }
}
