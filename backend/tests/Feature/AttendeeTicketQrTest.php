<?php

namespace Tests\Feature;

use App\Models\AttendeeTicket;
use App\Models\Event;
use App\Models\EventWorkerAssignment;
use App\Models\HostPaymentPolicy;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Organizer;
use App\Models\User;
use App\Services\EventQr\QrArtworkRenderer;
use App\Services\EventQr\TicketQrService;
use App\Services\OrderPlacementService;
use App\Services\Tickets\AttendeeTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Attendee ticket QR codes: each person on a successful order gets their own unique, secure credential and QR code,
 * created only when the order is created; duplicates and retries create nothing extra; and the gate, food lookup,
 * QR replacement and the ticket page all work with them.
 */
class AttendeeTicketQrTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizer;
    private Event $event;
    private MenuItem $dish;
    private MenuItem $plate;
    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizer = Organizer::create(['name' => 'QR Org', 'slug' => 'qr-org']);
        $this->event = Event::allTenants()->create([
            'organizer_id' => $this->organizer->id,
            'slug' => 'qr-night',
            'title' => 'Kingston Reggae Night',
            'date_str' => 'Saturday, November 28, 2026',
            'time_str' => '6:00 PM - 11:00 PM',
            'currency' => 'JMD',
            'venue_name' => 'Kingston Pier',
            'venue_address' => 'Kingston',
            'organizer_name' => 'QR Org',
            'organizer_phone' => '+18765550000',
            'advance_order_deadline' => now()->addWeek(),
            'status' => 'published',
            'is_ordering_open' => true,
            'packaging_fee_jmd' => 100,
            'ticket_tiers' => [
                ['id' => 'tier-ga', 'name' => 'General Admission', 'price_jmd' => 2000, 'quantity_available' => 50, 'max_per_order' => 4],
            ],
        ]);
        $this->dish = MenuItem::allTenants()->create([
            'organizer_id' => $this->organizer->id, 'event_id' => $this->event->id, 'name' => 'Escovitch Fish',
            'category' => 'Mains', 'pricing_type' => 'fixed', 'price_usd' => 10, 'price_jmd' => 1500,
            'available_stock' => 10, 'is_active' => true,
        ]);
        $this->plate = MenuItem::allTenants()->create([
            'organizer_id' => $this->organizer->id, 'event_id' => $this->event->id, 'name' => 'Jerk Plate',
            'category' => 'Mains', 'pricing_type' => 'customizable', 'price_usd' => 8, 'price_jmd' => 1200,
            'is_active' => true,
            'custom_option_groups' => [
                ['id' => 'size', 'title' => 'Size', 'choices' => [['label' => 'Regular', 'extraPriceJMD' => 0], ['label' => 'Large', 'extraPriceJMD' => 300]]],
            ],
        ]);
        HostPaymentPolicy::create(['organizer_id' => $this->organizer->id, 'enable_cash' => true, 'bank_transfer_instructions' => "Bank: Example Bank\nAccount: 000-000"]);
        $this->buyer = User::create(['name' => 'QR Buyer', 'email' => 'qr-buyer@example.test', 'phone' => '+18765550123', 'password' => bcrypt('password'), 'status' => 'active']);
    }

    private function credentials(): AttendeeTicketService
    {
        return app(AttendeeTicketService::class);
    }

    /** An order for $tickets General Admission tickets and one dish, through the real placement service. */
    private function place(int $tickets = 3, ?string $key = null, int $dishes = 1): array
    {
        return app(OrderPlacementService::class)->placeOrder($this->event, array_filter([
            'customer_name' => 'QR Buyer',
            'customer_phone' => '+1 876 555 0123',
            'method' => 'bank',
            'idempotency_key' => $key,
            'items' => [
                ['menu_item_id' => 'tier-ga', 'quantity' => $tickets],
                ['menu_item_id' => $this->dish->id, 'quantity' => $dishes],
            ],
        ], fn ($v) => $v !== null), $this->buyer);
    }

    private function paidOrder(int $tickets = 3): Order
    {
        $result = $this->place($tickets);
        $this->assertTrue($result['success'], $result['error'] ?? '');
        $order = $result['order'];
        $order->forceFill(['status' => 'paid', 'amount_paid_minor' => (int) $order->total_minor, 'verified_at' => now()])->save();

        return $order->fresh();
    }

    private function checker(): User
    {
        $checker = User::create(['name' => 'Gate Checker', 'email' => 'qr-gate@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        EventWorkerAssignment::create([
            'organizer_id' => $this->organizer->id, 'event_id' => $this->event->id, 'user_id' => $checker->id,
            'worker_role' => 'ticket_checker', 'status' => 'active', 'is_active' => true,
        ]);

        return $checker;
    }

    private function scan(User $checker, string $code)
    {
        return $this->actingAs($checker)->postJson("/worker/events/{$this->event->id}/scan", ['ticket_code' => $code]);
    }

    // ---- 1. Generation trigger -------------------------------------------------------------------------------

    public function test_placing_an_order_issues_one_unique_secure_credential_per_ticket(): void
    {
        $result = $this->place(3);
        $this->assertTrue($result['success']);
        $order = $result['order'];

        $tickets = AttendeeTicket::where('order_id', $order->id)->orderBy('sequence')->get();
        $this->assertCount(3, $tickets, 'three tickets bought, three attendee tickets');
        $this->assertSame([1, 2, 3], $tickets->pluck('sequence')->all());
        $this->assertCount(3, $tickets->pluck('token_hash')->unique());
        $this->assertCount(3, $tickets->pluck('ticket_number')->unique());
        $this->assertStringStartsWith($order->ticket_code . '-', $tickets[0]->ticket_number);

        $credentials = $tickets->map(fn ($t) => $this->credentials()->credential($t));
        $this->assertCount(3, $credentials->unique());
        foreach ($tickets as $i => $ticket) {
            $credential = $credentials[$i];
            $this->assertMatchesRegularExpression('/^tkt_[A-Za-z0-9_-]{43}$/', $credential, '256-bit credential');
            $this->assertSame(hash('sha256', $credential), $ticket->token_hash);
            // Only the hash is stored: the credential itself appears in no column.
            $row = (array) \DB::table('attendee_tickets')->where('id', $ticket->id)->first();
            $this->assertNotContains($credential, array_map('strval', $row));
            $this->assertTrue($this->credentials()->findByCredential($credential, (string) $this->event->id)->is($ticket));
        }
        // A food-only line never becomes an attendee ticket.
        $this->assertSame(1, $order->items->whereNotNull('ticket_tier_id')->count());
    }

    public function test_opening_checkout_creates_no_ticket_or_credential(): void
    {
        $this->actingAs($this->buyer)->get(route('checkout.tickets', $this->event))->assertOk();
        $this->actingAs($this->buyer)->post(route('checkout.tickets.save', $this->event), ['tiers' => ['tier-ga' => 2]]);
        $this->actingAs($this->buyer)->post(route('checkout.food.save', $this->event), ['items' => [$this->dish->id => ['quantity' => 1]]]);
        $this->actingAs($this->buyer)->get(route('checkout.review', $this->event))->assertOk();

        $this->assertSame(0, Order::allTenants()->count());
        $this->assertSame(0, AttendeeTicket::count());
    }

    public function test_a_failed_order_creates_no_credential(): void
    {
        $result = $this->place(3, null, 11); // only 10 dishes left: the whole order fails

        $this->assertFalse($result['success']);
        $this->assertSame(0, Order::allTenants()->count());
        $this->assertSame(0, AttendeeTicket::count(), 'credentials roll back with the order');
    }

    public function test_place_order_over_http_then_ticket_page_then_double_submit(): void
    {
        $this->actingAs($this->buyer)->post(route('checkout.tickets.save', $this->event), ['tiers' => ['tier-ga' => 2]]);
        $this->actingAs($this->buyer)->post(route('checkout.food.save', $this->event), ['items' => [
            $this->dish->id => ['quantity' => 1],
            $this->plate->id => ['quantity' => 1, 'choices' => ['size' => 1]],
        ]]);
        $response = $this->actingAs($this->buyer)->post(route('checkout.place', $this->event), [
            'customer_phone' => '+1 876 555 0123', 'method' => 'bank', 'quoted_total_minor' => 710000,
        ]);
        $order = Order::allTenants()->sole();
        $response->assertRedirect(route('tickets.show', $order));
        $this->assertSame(2, AttendeeTicket::where('order_id', $order->id)->count());

        // A second Place Order (double click after the first succeeded) creates nothing.
        $this->actingAs($this->buyer)->post(route('checkout.place', $this->event), [
            'customer_phone' => '+1 876 555 0123', 'method' => 'bank', 'quoted_total_minor' => 710000,
        ])->assertRedirect(route('checkout.tickets', $this->event));
        $this->assertSame(1, Order::allTenants()->count());
        $this->assertSame(2, AttendeeTicket::count());
    }

    public function test_retried_requests_with_the_same_key_create_nothing_extra(): void
    {
        $first = $this->place(3, 'cart-key-123');
        $second = $this->place(3, 'cart-key-123');
        $third = $this->place(3, 'cart-key-123');

        $this->assertSame($first['order']->id, $second['order']->id);
        $this->assertSame($first['order']->id, $third['order']->id);
        $this->assertSame(1, Order::allTenants()->count());
        $this->assertSame(3, AttendeeTicket::count());
    }

    public function test_issuing_again_never_duplicates_and_fills_in_missing_seats(): void
    {
        $order = $this->place(3)['order'];
        $hashes = AttendeeTicket::where('order_id', $order->id)->orderBy('sequence')->pluck('token_hash')->all();

        $this->credentials()->issueFor($order);
        $this->credentials()->issueFor($order);
        $this->assertSame($hashes, AttendeeTicket::where('order_id', $order->id)->orderBy('sequence')->pluck('token_hash')->all());

        // A seat lost part-way (as if issuing had been interrupted) is re-created alone; the others are untouched.
        AttendeeTicket::where('order_id', $order->id)->where('sequence', 3)->delete();
        $this->credentials()->issueFor($order);
        $after = AttendeeTicket::where('order_id', $order->id)->orderBy('sequence')->get();
        $this->assertCount(3, $after);
        $this->assertSame(array_slice($hashes, 0, 2), $after->take(2)->pluck('token_hash')->all());
    }

    public function test_a_failed_qr_render_is_retried_without_new_tickets(): void
    {
        $order = $this->paidOrder(2);
        $hashes = AttendeeTicket::where('order_id', $order->id)->orderBy('sequence')->pluck('token_hash')->all();

        // The renderer fails (say GD is unavailable for a moment).
        $this->app->instance(QrArtworkRenderer::class, new class extends QrArtworkRenderer {
            public function png(string $data, array $design, int $pixelsPerModule = 16): string
            {
                throw new \RuntimeException('GD is unavailable');
            }
        });
        $this->actingAs($this->buyer)->get(route('tickets.show', $order))->assertOk()
            ->assertSee('still being prepared')
            ->assertSee(route('tickets.qr.retry', $order), false);
        $this->assertSame(2, AttendeeTicket::where('qr_status', AttendeeTicket::QR_FAILED)->count());
        $this->assertNotNull(AttendeeTicket::first()->qr_failure_reason);

        // Back to normal: Try again draws them; the tickets and their credentials are the same ones.
        $this->app->forgetInstance(QrArtworkRenderer::class);
        $this->app->forgetInstance(TicketQrService::class);
        $this->app->offsetUnset(QrArtworkRenderer::class);
        $this->actingAs($this->buyer)->post(route('tickets.qr.retry', $order))->assertRedirect(route('tickets.show', $order));

        $tickets = AttendeeTicket::where('order_id', $order->id)->orderBy('sequence')->get();
        $this->assertCount(2, $tickets);
        $this->assertSame($hashes, $tickets->pluck('token_hash')->all());
        $this->assertSame([AttendeeTicket::QR_READY, AttendeeTicket::QR_READY], $tickets->pluck('qr_status')->all());
        $this->assertGreaterThanOrEqual(2, $tickets[0]->qr_attempts);
    }

    // ---- Ticket page ---------------------------------------------------------------------------------------

    public function test_ticket_page_shows_one_scannable_qr_per_attendee_after_payment(): void
    {
        $result = $this->place(3);
        $order = $result['order'];

        // Before payment: credentials exist, but no QR code is shown.
        $this->actingAs($this->buyer)->get(route('tickets.show', $order))->assertOk()
            ->assertDontSee('QR code for ticket', false);

        $order->forceFill(['status' => 'paid', 'amount_paid_minor' => (int) $order->total_minor])->save();
        $html = $this->actingAs($this->buyer)->get(route('tickets.show', $order))->assertOk()
            ->assertSee('data-attendee-count="3"', false)
            ->assertSee('Guest 1 of 3')->assertSee('Guest 3 of 3')
            ->getContent();

        $this->assertSame(3, substr_count($html, 'class="eb-ticket-qr-art"'));
        foreach (AttendeeTicket::where('order_id', $order->id)->get() as $ticket) {
            $this->assertStringContainsString('QR code for ticket ' . $ticket->ticket_number, $html);
            $this->assertStringNotContainsString($this->credentials()->credential($ticket), $html, 'the credential is only in the QR modules');
            $this->assertSame(AttendeeTicket::QR_READY, $ticket->qr_status);
            $this->assertSame('ticket', $ticket->qr_design['system']);
        }

        // The SVG on the page decodes to the attendee's credential.
        $ticket = AttendeeTicket::where('order_id', $order->id)->where('sequence', 1)->first();
        $png = app(QrArtworkRenderer::class)->png($this->credentials()->credential($ticket), $ticket->qr_design, 6);
        $decoded = (string) (new \chillerlan\QRCode\QRCode(new \chillerlan\QRCode\QROptions(['readerGrayscale' => true])))->readFromBlob($png);
        $this->assertSame($this->credentials()->credential($ticket), $decoded);
    }

    // ---- Admission, food and QR replacement ----------------------------------------------------------------

    public function test_each_attendee_is_admitted_once_on_their_own_qr(): void
    {
        $order = $this->paidOrder(3);
        $checker = $this->checker();
        $tickets = AttendeeTicket::where('order_id', $order->id)->orderBy('sequence')->get();
        $first = $this->credentials()->credential($tickets[0]);

        $this->scan($checker, $first)->assertOk()->assertJson(['success' => true, 'ticket' => ['attendee' => ['sequence' => 1, 'of' => 3]]]);
        $this->scan($checker, $first)->assertJson(['success' => false, 'already_admitted' => true, 'reason_code' => 'already_admitted']);
        $this->assertNull($order->fresh()->checked_in_at, 'the order is not fully in yet');

        // The order's own pass may no longer admit the whole party.
        $this->scan($checker, $order->secure_ticket_token)->assertJson(['success' => false, 'already_admitted' => true]);

        $this->scan($checker, $this->credentials()->credential($tickets[1]))->assertJson(['success' => true]);
        $this->scan($checker, $this->credentials()->credential($tickets[2]))->assertJson(['success' => true]);
        $this->assertNotNull($order->fresh()->checked_in_at, 'every attendee is in');
        $this->assertSame(3, AttendeeTicket::where('order_id', $order->id)->whereNotNull('checked_in_at')->count());
    }

    public function test_the_order_pass_admits_the_party_and_uses_up_every_attendee_ticket(): void
    {
        $order = $this->paidOrder(2);
        $checker = $this->checker();

        $this->scan($checker, $order->secure_ticket_token)->assertJson(['success' => true]);
        $ticket = AttendeeTicket::where('order_id', $order->id)->first();
        $this->assertNotNull($ticket->checked_in_at);
        $this->scan($checker, $this->credentials()->credential($ticket))
            ->assertJson(['success' => false, 'already_admitted' => true]);
    }

    public function test_unpaid_and_wrong_event_attendee_tickets_are_refused(): void
    {
        $order = $this->place(2)['order']; // awaiting payment
        $checker = $this->checker();
        $credential = $this->credentials()->credential(AttendeeTicket::where('order_id', $order->id)->first());

        $this->scan($checker, $credential)->assertJson(['success' => false, 'reason_code' => 'payment_pending']);

        $other = Event::allTenants()->create([
            'organizer_id' => $this->organizer->id, 'slug' => 'other-night', 'title' => 'Other Night', 'date_str' => 'Sunday',
            'time_str' => '6 PM', 'venue_name' => 'Elsewhere', 'venue_address' => 'Kingston', 'organizer_name' => 'QR Org',
            'organizer_phone' => '+18765550000', 'advance_order_deadline' => now()->addWeek(), 'status' => 'published',
        ]);
        EventWorkerAssignment::create(['organizer_id' => $this->organizer->id, 'event_id' => $other->id, 'user_id' => $checker->id,
            'worker_role' => 'ticket_checker', 'status' => 'active', 'is_active' => true]);
        $this->actingAs($checker)->postJson("/worker/events/{$other->id}/scan", ['ticket_code' => $credential])->assertNotFound();
        $this->assertSame(0, AttendeeTicket::whereNotNull('checked_in_at')->count());
    }

    public function test_replacing_the_qr_code_retires_every_attendee_credential(): void
    {
        $order = $this->paidOrder(2);
        $checker = $this->checker();
        $old = AttendeeTicket::where('order_id', $order->id)->orderBy('sequence')->get()->map(fn ($t) => $this->credentials()->credential($t));

        $this->actingAs($this->buyer)->post(route('tickets.reissue', $order))->assertSessionHas('success');

        $new = AttendeeTicket::where('order_id', $order->id)->orderBy('sequence')->get();
        $this->assertSame(2, $new->count(), 'same tickets, new credentials');
        $this->assertSame([2, 2], $new->pluck('token_version')->all());
        $this->assertNotContains($this->credentials()->credential($new[0]), $old->all());

        $this->scan($checker, $old[0])->assertNotFound();
        $this->scan($checker, $this->credentials()->credential($new[0]))->assertJson(['success' => true]);
    }

    public function test_food_and_staff_lookups_find_the_order_from_an_attendee_qr(): void
    {
        $order = $this->paidOrder(2);
        $credential = $this->credentials()->credential(AttendeeTicket::where('order_id', $order->id)->first());

        $this->assertSame($order->id, Order::allTenants()->whereTicketToken($credential)->value('id'));
        $this->assertNull(Order::allTenants()->whereTicketToken('tkt_' . str_repeat('A', 43))->value('id'));

        $found = app(OrderPlacementService::class)->retrieveOrderSecurely($credential, null, $this->buyer);
        $this->assertTrue($found['success']);
        $this->assertSame($order->id, $found['order']->id);
    }
}
