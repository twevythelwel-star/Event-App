<?php

namespace Tests\Feature;

use App\Jobs\RenderAttendeeTicketQrCodes;
use App\Models\AttendeeTicket;
use App\Models\Event;
use App\Models\EventWorkerAssignment;
use App\Models\HostPaymentPolicy;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\Organizer;
use App\Models\Payment;
use App\Models\TicketAdmission;
use App\Models\TicketCredentialEvent;
use App\Models\User;
use App\Models\UserRole;
use App\Services\EventQr\QrArtworkRenderer;
use App\Services\EventQr\TicketQrDesignSystem;
use App\Services\EventQr\TicketQrService;
use App\Services\EventWorkerService;
use App\Services\OrderPlacementService;
use App\Services\Tickets\AttendeeTicketService;
use App\Support\TicketStatuses;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The ticket lifecycle (pending payment to admitted, cancellation, revocation, reissue with history, refund,
 * chargeback, event cancellation and completion), the queued QR render, and scanning under pressure: concurrent and
 * repeated scans, staff who are not allowed to scan, cross-event attempts, and codes read off phone screens and paper.
 */
class TicketLifecycleAndScanningTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizer;
    private Event $event;
    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organizer = Organizer::create(['name' => 'Lifecycle Org', 'slug' => 'lifecycle-org']);
        $this->event = $this->makeEvent('lifecycle-night', 'Port Royal Soca Night');
        MenuItem::allTenants()->create([
            'organizer_id' => $this->organizer->id, 'event_id' => $this->event->id, 'name' => 'Festival',
            'category' => 'Sides', 'pricing_type' => 'fixed', 'price_usd' => 3, 'price_jmd' => 400, 'available_stock' => 50, 'is_active' => true,
        ]);
        HostPaymentPolicy::create(['organizer_id' => $this->organizer->id, 'enable_cash' => true, 'bank_transfer_instructions' => 'Bank']);
        $this->buyer = User::create(['name' => 'Life Buyer', 'email' => 'life-buyer@example.test', 'phone' => '+18765550111', 'password' => bcrypt('password'), 'status' => 'active']);
    }

    private function makeEvent(string $slug, string $title, ?Organizer $organizer = null): Event
    {
        return Event::allTenants()->create([
            'organizer_id' => ($organizer ?? $this->organizer)->id, 'slug' => $slug, 'title' => $title,
            'date_str' => 'Saturday, November 28, 2026', 'time_str' => '6:00 PM - 11:00 PM', 'currency' => 'JMD',
            'venue_name' => 'Port Royal', 'venue_address' => 'Kingston', 'organizer_name' => 'Lifecycle Org',
            'organizer_phone' => '+18765550000', 'advance_order_deadline' => now()->addWeek(), 'status' => 'published',
            'is_ordering_open' => true,
            'ticket_tiers' => [['id' => 'tier-ga', 'name' => 'General Admission', 'price_jmd' => 2000, 'quantity_available' => 80, 'max_per_order' => 6]],
        ]);
    }

    private function tickets(): AttendeeTicketService
    {
        return app(AttendeeTicketService::class);
    }

    private function order(int $tickets = 2, bool $paid = true, ?Event $event = null): Order
    {
        $result = app(OrderPlacementService::class)->placeOrder($event ?? $this->event, [
            'customer_name' => 'Life Buyer', 'customer_phone' => '+1 876 555 0111', 'method' => 'bank',
            'items' => [['menu_item_id' => 'tier-ga', 'quantity' => $tickets]],
        ], $this->buyer);
        $this->assertTrue($result['success'], $result['error'] ?? '');
        $order = $result['order'];
        if ($paid) {
            $order->forceFill(['status' => 'paid', 'amount_paid_minor' => (int) $order->total_minor, 'verified_at' => now()])->save();
        }

        return $order->fresh();
    }

    private function guest(Order $order, int $sequence = 1): AttendeeTicket
    {
        return AttendeeTicket::where('order_id', $order->id)->where('sequence', $sequence)->firstOrFail();
    }

    private function staff(string $role, ?Event $event = null, bool $active = true, ?string $email = null): User
    {
        $user = User::create(['name' => ucfirst($role), 'email' => $email ?? uniqid($role) . '@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        EventWorkerAssignment::create([
            'organizer_id' => ($event ?? $this->event)->organizer_id, 'event_id' => ($event ?? $this->event)->id, 'user_id' => $user->id,
            'worker_role' => $role, 'station_assignment' => 'all', 'status' => $active ? 'active' : 'revoked', 'is_active' => $active,
        ]);

        return $user;
    }

    private function scan(User $as, string $code, ?Event $event = null, ?string $requestId = null)
    {
        return $this->actingAs($as)->postJson('/worker/events/' . ($event ?? $this->event)->id . '/scan',
            array_filter(['ticket_code' => $code, 'scan_request_id' => $requestId]));
    }

    private function admin(): User
    {
        $admin = User::create(['name' => 'Root Admin', 'email' => 'life-root@example.test', 'password' => bcrypt('password'), 'status' => 'active', 'is_system_admin' => true]);
        UserRole::create(['user_id' => $admin->id, 'role' => 'system_admin', 'is_active' => true, 'assigned_at' => now()]);

        return $admin;
    }

    // ---- Lifecycle -------------------------------------------------------------------------------------------

    public function test_lifecycle_runs_from_pending_payment_to_valid_to_admitted(): void
    {
        $order = $this->order(2, false);
        $guest = $this->guest($order);
        $this->assertSame('pending_payment', TicketStatuses::for($order, $guest)['lifecycle'], 'activated on order creation, not yet admissible');

        $order->forceFill(['status' => 'paid', 'amount_paid_minor' => (int) $order->total_minor])->save();
        $this->assertSame('valid', TicketStatuses::for($order->fresh(), $guest)['lifecycle']);

        $this->scan($this->staff('ticket_checker'), $this->tickets()->credential($guest))->assertJson(['success' => true]);
        $statuses = TicketStatuses::for($order->fresh(), $guest->fresh());
        $this->assertSame(['valid', 'admitted', 'admitted'], [$statuses['ticket'], $statuses['lifecycle'], $statuses['admission']]);
        $this->assertSame('valid', TicketStatuses::for($order->fresh(), $this->guest($order, 2))['lifecycle'], 'the other guest is not in yet');

        foreach (TicketStatuses::for($order->fresh(), $guest) as $key => $value) {
            if ($key !== 'admitted_at') {
                $this->assertIsString($value);
            }
        }
    }

    public function test_event_cancellation_and_completion_change_the_ticket_state(): void
    {
        $order = $this->order(1);
        $guest = $this->guest($order);

        Event::allTenants()->whereKey($this->event->id)->update(['status' => 'cancelled']);
        $this->assertSame('event_cancelled', TicketStatuses::for($order->fresh(), $guest)['ticket']);
        $this->scan($this->staff('ticket_checker'), $this->tickets()->credential($guest))->assertJson(['success' => false, 'reason_code' => 'event_not_active']);

        Event::allTenants()->whereKey($this->event->id)->update(['status' => 'published', 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(2)]);
        $this->assertSame('completed', TicketStatuses::for($order->fresh()->load('event'), $guest)['ticket']);
        $this->scan($this->staff('ticket_checker'), $this->tickets()->credential($guest))->assertJson(['success' => false, 'reason_code' => 'event_not_open']);
    }

    public function test_reissuing_one_guest_records_reason_time_and_actor_and_kills_the_old_code(): void
    {
        $order = $this->order(2);
        $checker = $this->staff('ticket_checker');
        $guest = $this->guest($order, 2);
        $old = $this->tickets()->credential($guest);
        $otherBefore = $this->guest($order, 1)->token_hash;

        // A reason is required.
        $this->actingAs($this->buyer)->post(route('tickets.guests.reissue', [$order, $guest]), [])->assertSessionHasErrors('reason');

        $this->actingAs($this->buyer)->post(route('tickets.guests.reissue', [$order, $guest]), ['reason' => 'Screenshot was shared in a group chat'])
            ->assertSessionHas('success');

        $guest->refresh();
        $this->assertSame(2, $guest->token_version);
        $history = TicketCredentialEvent::where('attendee_ticket_id', $guest->id)->where('action', 'reissued')->sole();
        $this->assertSame('Screenshot was shared in a group chat', $history->reason);
        $this->assertSame($this->buyer->id, (int) $history->actor_user_id);
        $this->assertSame(2, $history->token_version);
        $this->assertNotNull($history->occurred_at);
        $this->assertSame(1, TicketCredentialEvent::where('attendee_ticket_id', $guest->id)->where('action', 'issued')->count(), 'issue history kept');

        $this->scan($checker, $old)->assertNotFound();
        $this->scan($checker, $this->tickets()->credential($guest))->assertJson(['success' => true]);
        $this->assertSame($otherBefore, $this->guest($order, 1)->token_hash, 'the other guest keeps their code');

        // An admitted guest cannot be reissued.
        $this->actingAs($this->buyer)->post(route('tickets.guests.reissue', [$order, $guest]), ['reason' => 'Again please'])->assertSessionHas('error');
        $this->assertSame(2, $guest->fresh()->token_version);

        // History is append-only.
        $this->expectException(\LogicException::class);
        $history->forceFill(['reason' => 'edited'])->save();
    }

    public function test_only_the_holder_or_payment_staff_may_reissue_a_guest(): void
    {
        $order = $this->order(1);
        $guest = $this->guest($order);
        $stranger = User::create(['name' => 'Stranger', 'email' => 'life-stranger@example.test', 'password' => bcrypt('password'), 'status' => 'active']);

        $this->actingAs($stranger)->post(route('tickets.guests.reissue', [$order, $guest]), ['reason' => 'Not mine'])->assertForbidden();
        $this->actingAs($this->staff('ticket_checker'))->post(route('tickets.guests.reissue', [$order, $guest]), ['reason' => 'Checker'])->assertForbidden();
        $this->assertSame(1, $guest->fresh()->token_version);

        // A guest of another order cannot be reached through this order's URL.
        $other = $this->order(1);
        $this->actingAs($this->buyer)->post(route('tickets.guests.reissue', [$order, $this->guest($other)]), ['reason' => 'Mixing orders'])->assertNotFound();

        // The admin console (system administrator) may.
        $this->actingAs($this->admin())->post(route('tickets.guests.reissue', [$order, $guest]), ['reason' => 'Support request'])->assertSessionHas('success');
        $this->assertSame(2, $guest->fresh()->token_version);
    }

    public function test_order_wide_replacement_and_revocation_are_recorded_per_guest(): void
    {
        $order = $this->order(2);
        $order->reissueTicketToken($this->buyer, 'Phone stolen');
        $this->assertSame(2, TicketCredentialEvent::where('order_id', $order->id)->where('action', 'reissued')->where('reason', 'Phone stolen')->where('actor_user_id', $this->buyer->id)->count());

        $admin = $this->admin();
        $order->fresh()->revokeTicketToken($admin, 'Fraud review');
        $this->assertSame(2, TicketCredentialEvent::where('order_id', $order->id)->where('action', 'revoked')->where('reason', 'Fraud review')->count());
        $this->assertSame('revoked', TicketStatuses::for($order->fresh(), $this->guest($order))['ticket']);
    }

    public function test_a_chargeback_revokes_every_ticket_and_appends_history_without_touching_payments(): void
    {
        $order = $this->order(2);
        $payment = Payment::create([
            'order_id' => $order->id, 'organizer_id' => $order->organizer_id, 'provider' => 'stripe', 'method' => 'card', 'status' => Payment::SUCCEEDED,
            'amount_minor' => (int) $order->total_minor, 'amount_received_minor' => (int) $order->total_minor, 'currency' => $order->currency,
            'idempotency_key' => 'life-pay-1',
        ]);
        $paymentBefore = $payment->fresh()->toArray();
        $orderPaidBefore = (int) $order->amount_paid_minor;
        $credential = $this->tickets()->credential($this->guest($order));
        $admin = $this->admin();

        // Confirmation is required, and a non-administrator cannot do it.
        $this->actingAs($admin)->post(route('admin.console.orders.chargeback', $order->id), ['amount' => '40.00', 'reason' => 'Disputed with bank'])
            ->assertSessionHasErrors('confirm');
        $this->actingAs($this->buyer)->post(route('admin.console.orders.chargeback', $order->id), ['amount' => '40.00', 'reason' => 'x', 'confirm' => '1'])
            ->assertForbidden();

        $this->actingAs($admin)->post(route('admin.console.orders.chargeback', $order->id), [
            'amount' => '40.00', 'reason' => 'Cardholder disputed the charge', 'provider_reference' => 'dp_123', 'confirm' => '1',
        ])->assertSessionHas('success');

        $adjustment = OrderAdjustment::where('order_id', $order->id)->where('type', OrderAdjustment::TYPE_CHARGEBACK)->sole();
        $this->assertSame(4000, (int) $adjustment->amount_minor);
        $this->assertSame(['provider_reference' => 'dp_123'], $adjustment->metadata);
        $this->assertSame($paymentBefore, $payment->fresh()->toArray(), 'the payment record is untouched');
        $this->assertSame($orderPaidBefore, (int) $order->fresh()->amount_paid_minor, 'amounts received are not rewritten');

        $statuses = TicketStatuses::for($order->fresh(), $this->guest($order));
        $this->assertSame(['charged_back', 'charged_back'], [$statuses['payment'], $statuses['ticket']]);
        $this->assertSame(2, TicketCredentialEvent::where('order_id', $order->id)->where('action', 'chargeback')->count());
        $this->scan($this->staff('ticket_checker'), $credential)->assertJson(['success' => false, 'reason_code' => 'ticket_revoked']);

        // Recorded once only; adjustments refuse edits.
        $this->actingAs($admin)->post(route('admin.console.orders.chargeback', $order->id), ['amount' => '1.00', 'reason' => 'Again', 'confirm' => '1'])
            ->assertSessionHas('error');
        $this->assertSame(1, OrderAdjustment::where('order_id', $order->id)->where('type', 'chargeback')->count());
        $this->expectException(\LogicException::class);
        $adjustment->forceFill(['amount_minor' => 1])->save();
    }

    // ---- Queued rendering ------------------------------------------------------------------------------------

    public function test_a_successful_order_queues_its_qr_render_and_a_failed_one_does_not(): void
    {
        config(['tickets.prerender_qr' => true]);
        Queue::fake();

        $order = $this->order(2, false);
        Queue::assertPushed(RenderAttendeeTicketQrCodes::class, fn ($job) => $job->orderId === (string) $order->id);

        $failed = app(OrderPlacementService::class)->placeOrder($this->event, [
            'customer_name' => 'Life Buyer', 'customer_phone' => '+1 876 555 0111', 'method' => 'bank',
            'items' => [['menu_item_id' => 'tier-ga', 'quantity' => 500]],
        ], $this->buyer);
        $this->assertFalse($failed['success']);
        Queue::assertPushed(RenderAttendeeTicketQrCodes::class, 1);

        // The job itself draws the artwork, and running it twice changes nothing.
        (new RenderAttendeeTicketQrCodes((string) $order->id))->handle(app(TicketQrService::class));
        (new RenderAttendeeTicketQrCodes((string) $order->id))->handle(app(TicketQrService::class));
        $this->assertSame([AttendeeTicket::QR_READY, AttendeeTicket::QR_READY], AttendeeTicket::where('order_id', $order->id)->orderBy('sequence')->pluck('qr_status')->all());
        $this->assertSame(2, AttendeeTicket::where('order_id', $order->id)->count());
    }

    // ---- Scanning under pressure ------------------------------------------------------------------------------

    public function test_concurrent_scans_of_one_ticket_admit_it_once(): void
    {
        $order = $this->order(1);
        $guest = $this->guest($order);
        $checkerA = $this->staff('ticket_checker');
        $checkerB = $this->staff('ticket_checker');

        // Two gates read the same, not-yet-admitted ticket at the same moment; each then runs its admission.
        $staleA = AttendeeTicket::find($guest->id);
        $staleB = AttendeeTicket::find($guest->id);
        $first = EventWorkerService::scanAndValidateTicket($checkerA, $order, (string) $this->event->id, attendee: $staleA);
        $second = EventWorkerService::scanAndValidateTicket($checkerB, $order->fresh(), (string) $this->event->id, attendee: $staleB);

        $this->assertTrue($first['success']);
        $this->assertFalse($second['success'], 'the second gate re-reads the locked row and refuses');
        $this->assertSame('already_admitted', $second['reason_code']);
        $this->assertSame(1, TicketAdmission::where('attendee_ticket_id', $guest->id)->count());
    }

    public function test_repeated_scans_and_retried_requests(): void
    {
        $order = $this->order(1);
        $checker = $this->staff('ticket_checker');
        $credential = $this->tickets()->credential($this->guest($order));
        $requestId = (string) \Illuminate\Support\Str::uuid();

        $this->scan($checker, $credential, null, $requestId)->assertJson(['success' => true, 'replayed' => false]);
        // The scanner lost the response and retried the same request: confirmed, not admitted twice.
        $this->scan($checker, $credential, null, $requestId)->assertJson(['success' => true, 'replayed' => true]);
        // A fresh scan of the same code is a duplicate.
        foreach (range(1, 3) as $_) {
            $this->scan($checker, $credential)->assertJson(['success' => false, 'reason_code' => 'already_admitted', 'result' => 'reject']);
        }
        $this->assertSame(1, TicketAdmission::count());
    }

    public function test_staff_without_a_live_scanning_assignment_for_this_event_cannot_scan(): void
    {
        $order = $this->order(1);
        $credential = $this->tickets()->credential($this->guest($order));
        $otherEvent = $this->makeEvent('other-night', 'Other Night');

        $this->scan($this->staff('ticket_checker', $otherEvent), $credential)->assertForbidden();
        $this->scan($this->staff('ticket_checker', null, false), $credential)->assertForbidden();
        $this->scan($this->staff('payment_clerk'), $credential)->assertForbidden();
        $this->scan($this->staff('food_station_worker'), $credential)->assertForbidden();
        $nobody = User::create(['name' => 'Nobody', 'email' => 'life-nobody@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        $this->scan($nobody, $credential)->assertForbidden();
        // Not signed in at all: refused before anything is looked up.
        $anonymous = $this->postJson('/worker/events/' . $this->event->id . '/scan', ['ticket_code' => $credential]);
        $this->assertContains($anonymous->status(), [401, 403]);

        $this->assertSame(0, TicketAdmission::count());
        $this->assertNull($this->guest($order)->checked_in_at);
    }

    public function test_cross_event_attempts_are_refused(): void
    {
        $order = $this->order(1);
        $credential = $this->tickets()->credential($this->guest($order));

        // Another organizer's event, with its own checker.
        $otherOrganizer = Organizer::create(['name' => 'Other Org', 'slug' => 'other-org']);
        $foreign = $this->makeEvent('foreign-night', 'Foreign Night', $otherOrganizer);
        $foreignChecker = $this->staff('ticket_checker', $foreign);
        $this->scan($foreignChecker, $credential, $foreign)->assertNotFound();
        $this->scan($foreignChecker, $credential)->assertForbidden();

        // A checker working both of this organizer's events still cannot use this ticket at the other one.
        $sibling = $this->makeEvent('sibling-night', 'Sibling Night');
        $both = $this->staff('ticket_checker');
        EventWorkerAssignment::create(['organizer_id' => $this->organizer->id, 'event_id' => $sibling->id, 'user_id' => $both->id,
            'worker_role' => 'ticket_checker', 'station_assignment' => 'all', 'status' => 'active', 'is_active' => true]);
        $this->scan($both, $credential, $sibling)->assertNotFound();
        $this->scan($both, $order->secure_ticket_token, $sibling)->assertNotFound();

        // Even called directly, the service refuses the wrong event.
        $result = EventWorkerService::scanAndValidateTicket($both, $order, (string) $sibling->id, attendee: $this->guest($order));
        $this->assertSame('wrong_event', $result['reason_code']);
        $this->assertSame(0, TicketAdmission::count());
    }

    // ---- Codes read off phone screens and paper ---------------------------------------------------------------

    /** Every preset still decodes when photographed off a phone screen (small, soft, dimmed, compressed). */
    public function test_every_design_scans_from_a_phone_screen(): void
    {
        $designs = app(TicketQrDesignSystem::class);
        $renderer = app(QrArtworkRenderer::class);
        foreach (array_keys(TicketQrDesignSystem::PRESETS) as $preset) {
            $design = $designs->resolve($this->event, ['preset' => $preset]);
            $credential = 'tkt_' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $img = imagecreatefromstring($renderer->png($credential, $design, 4));
            // A camera a little out of focus, at an angle-free distance, with screen glare taking contrast away.
            $img = imagescale($img, (int) (imagesx($img) * 1.6), -1, IMG_BILINEAR_FIXED);
            imagefilter($img, IMG_FILTER_GAUSSIAN_BLUR);
            imagefilter($img, IMG_FILTER_BRIGHTNESS, 25);
            imagefilter($img, IMG_FILTER_CONTRAST, 20);
            $this->assertSame($credential, $this->decode($this->jpeg($img, 45)), "{$preset}: phone screen");
        }
    }

    /** Every preset still decodes printed in black and white: greyscale, ink spread and paper contrast. */
    public function test_every_design_scans_from_a_black_and_white_print(): void
    {
        $designs = app(TicketQrDesignSystem::class);
        $renderer = app(QrArtworkRenderer::class);
        foreach (array_keys(TicketQrDesignSystem::PRESETS) as $preset) {
            foreach ($designs->resolve($this->event, ['preset' => $preset])['logo'] === 'none' ? [['preset' => $preset], ['preset' => $preset, 'logo' => 'monogram']] : [['preset' => $preset]] as $choice) {
                $design = $designs->resolve($this->event, $choice);
                $credential = 'tkt_' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
                $img = imagecreatefromstring($renderer->png($credential, $design, 6));
                imagefilter($img, IMG_FILTER_GRAYSCALE);
                imagefilter($img, IMG_FILTER_GAUSSIAN_BLUR); // ink spread
                imagefilter($img, IMG_FILTER_CONTRAST, 15);  // paper and toner contrast
                $this->assertSame($credential, $this->decode($this->jpeg($img, 70)), "{$preset} ({$design['logo']}): print");
            }
        }
    }

    private function decode(string $image): string
    {
        return (string) (new QRCode(new QROptions(['readerGrayscale' => true])))->readFromBlob($image);
    }

    private function jpeg(\GdImage $img, int $quality): string
    {
        ob_start();
        imagejpeg($img, null, $quality);

        return (string) ob_get_clean();
    }
}
