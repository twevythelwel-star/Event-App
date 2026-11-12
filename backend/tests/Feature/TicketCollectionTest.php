<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventWorkerAssignment;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Organizer;
use App\Models\TicketTransfer;
use App\Models\User;
use App\Services\OrderPlacementService;
use App\Support\TicketLifecycle;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** "My Tickets": the collection by lifecycle, past tickets as keepsakes that never admit anyone, and the share image. */
class TicketCollectionTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = 'evt-kingston-harbour-fry';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function attendee(): User
    {
        return User::where('email', 'attendee@islandevents.example')->firstOrFail();
    }

    private function event(): Event
    {
        return Event::allTenants()->findOrFail(self::EVENT);
    }

    private function order(string $status = 'paid'): Order
    {
        $item = MenuItem::withoutGlobalScopes()->where('event_id', self::EVENT)->where('is_active', true)->firstOrFail();
        $result = app(OrderPlacementService::class)->placeOrder($this->event(), [
            'customer_name' => 'Keepsake Holder', 'customer_phone' => '+1 305 555 0199', 'method' => 'cash',
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ], $this->attendee());
        $this->assertTrue($result['success'], $result['error'] ?? 'order placed');
        $order = $result['order']->fresh();
        if ($status !== 'pending') {
            $order->forceFill(['status' => $status, 'amount_paid_minor' => $status === 'paid' ? $order->total_minor : 0])->save();
        }

        return $order->fresh();
    }

    private function endTheEvent(): void
    {
        $this->event()->forceFill([
            'starts_at' => now()->subDays(3)->setTime(17, 0), 'ends_at' => now()->subDays(3)->setTime(23, 0),
            'event_date_iso' => now()->subDays(3)->toDateString(),
        ])->save();
    }

    public function test_an_event_is_over_only_after_it_ends(): void
    {
        $event = new Event(['timezone' => 'America/New_York']);
        $this->assertFalse(TicketLifecycle::eventIsOver($event), 'no date: never over');
        $this->assertFalse(TicketLifecycle::eventIsOver(null));

        $event->ends_at = now()->subHours(2);
        $this->assertFalse(TicketLifecycle::eventIsOver($event), 'a short grace after the end');
        $event->ends_at = now()->subHours(4);
        $this->assertTrue(TicketLifecycle::eventIsOver($event));

        // Date only: a late-night event still scans after midnight; it is over from 6 AM the next day.
        $event = new Event(['timezone' => 'America/New_York', 'event_date_iso' => '2026-11-21']);
        Carbon::setTestNow(Carbon::parse('2026-11-22 01:30', 'America/New_York'));
        $this->assertFalse(TicketLifecycle::eventIsOver($event));
        Carbon::setTestNow(Carbon::parse('2026-11-22 06:00', 'America/New_York'));
        $this->assertTrue(TicketLifecycle::eventIsOver($event));
    }

    public function test_the_collection_groups_tickets_by_where_they_are_in_their_life(): void
    {
        $paid = $this->order('paid');
        $pending = $this->order('pending');
        $refunded = $this->order('refunded');
        $cancelled = $this->order('cancelled');

        $page = $this->actingAs($this->attendee())->get(route('tickets.collection'))->assertOk();
        $page->assertSee('My Tickets')->assertSee('role="tablist"', false);
        foreach (['upcoming', 'active', 'past', 'cancelled', 'refunded', 'transferred'] as $tab) {
            $page->assertSee('id="panel-' . $tab . '"', false);
        }
        $html = $page->getContent();
        $this->assertSame(2, $this->countIn($html, 'panel-upcoming', 'data-state='), 'paid and awaiting payment are both upcoming');
        $this->assertSame(1, $this->countIn($html, 'panel-active', 'data-state="active"'));
        $this->assertSame(1, $this->countIn($html, 'panel-refunded', 'data-state="refunded"'));
        $this->assertSame(1, $this->countIn($html, 'panel-cancelled', 'data-state="cancelled"'));
        $page->assertSee(route('tickets.show', $paid), false)->assertSee('Awaiting payment');

        // The collection never shows an admission QR code or its token.
        $page->assertDontSee('data-qr-type="admission"', false);
        foreach ([$paid, $pending, $refunded, $cancelled] as $order) {
            $page->assertDontSee((string) $order->secure_ticket_token);
        }

        // Tabs work as plain links too.
        $refundedTab = $this->get(route('tickets.collection', ['tab' => 'refunded']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-collection-panel="refunded"\s*>/', $refundedTab, 'the asked-for tab is open');
        $this->assertMatchesRegularExpression('/data-collection-panel="upcoming"\s*hidden/', $refundedTab, 'the others are closed');
    }

    public function test_a_past_ticket_is_a_keepsake_and_never_admits_anyone(): void
    {
        $order = $this->order('paid');
        $this->endTheEvent();

        $page = $this->actingAs($this->attendee())->get(route('tickets.show', $order))->assertOk();
        $page->assertSee('is-keepsake', false)->assertSee('Keepsake · Not valid for entry')->assertSee('not valid for admission', false);
        $page->assertSee($order->ticket_code)->assertSee($order->order_number)->assertSee('Not scanned at the gate');
        $page->assertDontSee('data-qr-type="admission"', false)->assertDontSee((string) $order->secure_ticket_token);
        $page->assertDontSee('Replace QR code')->assertDontSee('Transfer these tickets');

        $this->get(route('tickets.collection'))->assertOk()->assertSee('data-state="past"', false)->assertSee('Keepsake');

        // A keepsake can't be transferred either.
        $this->expectException(\App\Services\OrderPlacementException::class);
        $this->event()->forceFill(['allow_ticket_transfers' => true])->save();
        app(\App\Services\Checkout\TicketTransferService::class)->assertTransferable($order->fresh());
    }

    public function test_the_keepsake_share_image_carries_no_personal_payment_or_admission_data(): void
    {
        $order = $this->order('paid');
        $this->endTheEvent();

        $html = $this->actingAs($this->attendee())->get(route('tickets.show', $order))->assertOk()->getContent();
        $this->assertSame(1, preg_match('/<button[^>]*data-keepsake-share[^>]*>/s', $html, $m), 'a share button');
        $button = $m[0];
        $this->assertStringContainsString('data-title="South Florida Jamaican Fish Fry"', $button);
        foreach ([$order->secure_ticket_token, $order->ticket_code, $order->order_number, $order->customer_name,
                     $order->customer_phone, $this->attendee()->email, 'cash', (string) $order->total_minor] as $private) {
            $this->assertStringNotContainsString((string) $private, $button);
        }
    }

    public function test_the_gate_rejects_a_ticket_for_an_event_that_is_over_even_without_an_end_time(): void
    {
        $organizer = Organizer::create(['name' => 'Gate Org', 'slug' => 'gate-org']);
        $event = Event::allTenants()->create([
            'organizer_id' => $organizer->id, 'slug' => 'gate-night', 'title' => 'Gate Night',
            'date_str' => 'Saturday', 'time_str' => '6:00 PM', 'venue_name' => 'Pier', 'venue_address' => 'Kingston',
            'organizer_name' => 'Gate Org', 'organizer_phone' => '+18765550000', 'advance_order_deadline' => now()->addWeek(),
            'status' => 'published', 'event_date_iso' => now()->subDays(2)->toDateString(),
        ]);
        $checker = User::create(['name' => 'Checker', 'email' => 'gate-checker@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        EventWorkerAssignment::create(['organizer_id' => $organizer->id, 'event_id' => $event->id, 'user_id' => $checker->id,
            'worker_role' => 'ticket_checker', 'status' => 'active', 'is_active' => true]);
        $token = Order::generateSecureTicketToken();
        $order = Order::allTenants()->create([
            'organizer_id' => $organizer->id, 'event_id' => $event->id, 'ticket_code' => 'GATE-1', 'order_number' => 'ORD-GATE-1',
            'customer_name' => 'Guest', 'customer_phone' => '+1 876 555 0101', 'method' => 'bank', 'subtotal' => 2000,
            'total_amount' => 2000, 'amount_paid' => 2000, 'currency' => 'JMD', 'status' => 'paid', 'secure_ticket_token' => $token,
        ]);

        $this->actingAs($checker)->postJson('/worker/events/' . $event->id . '/scan', ['ticket_code' => $token])->assertOk()
            ->assertJson(['success' => false, 'reason_code' => 'event_not_open']);
        $this->assertNull($order->fresh()->checked_in_at);
    }

    public function test_transferred_tickets_are_listed_for_the_sender_but_belong_to_the_recipient(): void
    {
        $order = $this->order('paid');
        $recipient = User::create(['name' => 'Recipient', 'email' => 'recipient@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        // As after an older transfer: the order still carries a contact detail of the sender.
        $order->forceFill(['user_id' => $recipient->id, 'email' => $recipient->email, 'customer_name' => 'Recipient'])->save();
        TicketTransfer::create(['order_id' => $order->id, 'from_user_id' => $this->attendee()->id, 'to_email' => $recipient->email,
            'to_user_id' => $recipient->id, 'token_hash' => hash('sha256', 'x'), 'status' => 'accepted',
            'expires_at' => now()->addDay(), 'accepted_at' => now()]);

        $html = $this->actingAs($this->attendee())->get(route('tickets.collection'))->assertOk()->getContent();
        $this->assertSame(1, $this->countIn($html, 'panel-transferred', 'data-state="transferred"'));
        $this->assertSame(0, $this->countIn($html, 'panel-upcoming', 'data-state='), 'not the sender’s ticket any more');
        $this->assertStringNotContainsString(route('tickets.show', $order), $html, 'the sender cannot open it from here');
        $this->assertStringNotContainsString('recipient@example.test', $html, 'the recipient’s email is masked');

        $this->actingAs($recipient)->get(route('tickets.collection'))->assertOk()->assertSee(route('tickets.show', $order), false);
    }

    /** How many times $needle appears inside the tab panel with the given id. */
    private function countIn(string $html, string $panelId, string $needle): int
    {
        $start = strpos($html, 'id="' . $panelId . '"');
        $this->assertNotFalse($start, "panel {$panelId}");
        $end = strpos($html, 'role="tabpanel"', $start + 1);

        return substr_count(substr($html, $start, $end === false ? null : $end - $start), $needle);
    }
}
