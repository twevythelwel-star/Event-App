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
 * Gate check-in through the ticket checker terminal: a paid ticket is admitted once,
 * the time and checker are stored on the order, and every later scan is refused.
 */
class TicketCheckInTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private User $checker;

    protected function setUp(): void
    {
        parent::setUp();

        // Events created through the app get UUID keys, which the worker routes bind by.
        $organizer = Organizer::create(['name' => 'Gate Org', 'slug' => 'gate-org']);
        $this->event = Event::allTenants()->create([
            'organizer_id' => $organizer->id,
            'slug' => 'gate-night',
            'title' => 'Gate Night',
            'date_str' => 'Saturday, November 28, 2026',
            'time_str' => '6:00 PM - 11:00 PM',
            'venue_name' => 'Pier',
            'venue_address' => 'Kingston',
            'organizer_name' => 'Gate Org',
            'organizer_phone' => '+18765550000',
            'advance_order_deadline' => now()->addWeek(),
            'status' => 'published',
        ]);

        $this->checker = User::create(['name' => 'Gate Checker', 'email' => 'gate@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        EventWorkerAssignment::create([
            'organizer_id' => $organizer->id,
            'event_id' => $this->event->id,
            'user_id' => $this->checker->id,
            'worker_role' => 'ticket_checker',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function order(string $status, string $code): Order
    {
        return Order::allTenants()->create([
            'organizer_id' => $this->event->organizer_id,
            'event_id' => $this->event->id,
            'ticket_code' => $code,
            'order_number' => "ORD-{$code}",
            'customer_name' => 'Gate Guest',
            'customer_phone' => '+1 876 555 0101',
            'method' => 'bank',
            'subtotal' => 2000,
            'total_amount' => 2000,
            'amount_paid' => $status === 'paid' ? 2000 : 0,
            'currency' => 'JMD',
            'status' => $status,
            'secure_ticket_token' => "tok_{$code}",
        ]);
    }

    private function scan(string $code, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->checker)->postJson("/worker/events/{$this->event->id}/scan", ['ticket_code' => $code]);
    }

    public function test_paid_ticket_is_admitted_once_and_check_in_is_stored(): void
    {
        $order = $this->order('paid', 'GATE-PAID');

        $this->scan($order->ticket_code)->assertOk()->assertJson(['success' => true, 'already_admitted' => false]);

        $order->refresh();
        $this->assertInstanceOf(\DateTimeInterface::class, $order->checked_in_at);
        $this->assertSame($this->checker->id, (int) $order->checked_in_by);
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'ticket_redemption_verified', 'target_id' => $order->id]);

        $firstCheckIn = $order->checked_in_at->toDateTimeString();

        // The second scan reads the stored check-in back from the database and refuses entry.
        $this->scan($order->ticket_code)->assertOk()->assertJson(['success' => false, 'already_admitted' => true]);
        $this->assertSame($firstCheckIn, $order->fresh()->checked_in_at->toDateTimeString());
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'ticket_redemption_rejected', 'target_id' => $order->id]);
    }

    public function test_unpaid_ticket_is_refused_and_not_checked_in(): void
    {
        $order = $this->order('payment_submitted', 'GATE-UNPAID');

        $this->scan($order->ticket_code)->assertOk()->assertJson(['success' => false, 'already_admitted' => false]);

        $this->assertNull($order->fresh()->checked_in_at);
        $this->assertNull($order->fresh()->checked_in_by);
    }

    public function test_person_without_an_assignment_cannot_check_anyone_in(): void
    {
        $order = $this->order('paid', 'GATE-STRANGER');
        $stranger = User::create(['name' => 'Not Staff', 'email' => 'stranger@example.test', 'password' => bcrypt('password'), 'status' => 'active']);

        $this->scan($order->ticket_code, $stranger)->assertForbidden();
        $this->assertNull($order->fresh()->checked_in_at);
    }

    public function test_deleting_the_checker_keeps_the_check_in_time(): void
    {
        $order = $this->order('paid', 'GATE-DELETE');
        $this->scan($order->ticket_code)->assertOk()->assertJson(['success' => true]);

        $this->checker->forceDelete();

        $this->assertNotNull($order->fresh()->checked_in_at);
        $this->assertNull($order->fresh()->checked_in_by);
    }
}
