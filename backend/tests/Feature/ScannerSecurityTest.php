<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventWorkerAssignment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organizer;
use App\Models\SecurityAuditLog;
use App\Models\User;
use App\Services\OrderOperationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Scanner security & fraud-prevention coverage that complements TicketCheckInTest:
 * token entropy, every rejection reason code, cross-event and role boundaries,
 * concurrency semantics, replay/forgery resistance, rate limiting and audit evidence.
 */
class ScannerSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private Organizer $organizer;
    private User $checker;

    /** @var array<string, string> order id => raw token used at creation */
    private array $tokens = [];
    private int $tokenSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizer = Organizer::create(['name' => 'Sec Org', 'slug' => 'sec-org']);
        $this->event = Event::allTenants()->create([
            'organizer_id' => $this->organizer->id,
            'slug' => 'sec-night',
            'title' => 'Security Night',
            'date_str' => 'Saturday, November 28, 2026',
            'time_str' => '6:00 PM - 11:00 PM',
            'venue_name' => 'Pier',
            'venue_address' => 'Kingston',
            'organizer_name' => 'Sec Org',
            'organizer_phone' => '+18765550000',
            'advance_order_deadline' => now()->addWeek(),
            'status' => 'published',
        ]);

        $this->checker = $this->worker('sec-checker@example.test', 'ticket_checker');
    }

    private function worker(string $email, string $role, array $overrides = []): User
    {
        $user = User::create(['name' => ucfirst(str_replace('_', ' ', $role)), 'email' => $email, 'password' => bcrypt('password'), 'status' => 'active']);
        EventWorkerAssignment::create(array_merge([
            'organizer_id' => $this->organizer->id,
            'event_id' => $this->event->id,
            'user_id' => $user->id,
            'worker_role' => $role,
            'status' => 'active',
            'is_active' => true,
        ], $overrides));

        return $user;
    }

    private function order(string $status = 'paid', string $code = 'SEC-1', ?Event $event = null): Order
    {
        $event ??= $this->event;
        $token = Order::generateSecureTicketToken();

        $order = Order::allTenants()->create([
            'organizer_id' => $event->organizer_id,
            'event_id' => $event->id,
            'ticket_code' => $code,
            'order_number' => "ORD-{$code}",
            'customer_name' => 'Sec Guest',
            'customer_phone' => '+1 876 555 0101',
            'method' => 'bank',
            'subtotal' => 2000,
            'total_amount' => 2000,
            'amount_paid' => $status === 'paid' ? 2000 : 0,
            'currency' => 'JMD',
            'status' => $status,
            'secure_ticket_token' => $token,
        ]);
        $this->tokens[$order->id] = $token;

        return $order;
    }

    private function tokenFor(Order $order): string
    {
        return $this->tokens[$order->id];
    }

    private function scan(string $code, ?User $as = null, ?Event $event = null, array $extra = [])
    {
        return $this->actingAs($as ?? $this->checker)->postJson('/worker/events/' . ($event ?? $this->event)->id . '/scan', array_merge([
            'ticket_code' => $code,
        ], $extra));
    }

    // ── 10.1 Token generation ────────────────────────────────────────────────

    public function test_generated_tokens_are_unique_high_entropy_and_never_sequential(): void
    {
        $tokens = [];
        for ($i = 0; $i < 60; $i++) {
            $tokens[] = Order::generateSecureTicketToken();
        }

        $this->assertCount(60, array_unique($tokens), 'CSPRNG produced a duplicate token');
        foreach ($tokens as $token) {
            $this->assertMatchesRegularExpression('/^tok_[a-f0-9]{64}$/', $token);
        }

        $hashes = array_map(fn ($t) => Order::hashTicketToken($t), $tokens);
        $this->assertCount(60, array_unique($hashes));
        foreach ($hashes as $hash) {
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
        }
    }

    public function test_token_hash_lookup_resolves_only_the_matching_order(): void
    {
        $order = $this->order('paid', 'SEC-HASH');
        $raw = $this->tokenFor($order);

        $this->assertSame($order->id, Order::withoutGlobalScopes()->whereTicketToken($raw)->value('id'));
        $this->assertNull(Order::withoutGlobalScopes()->whereTicketToken('tok_' . str_repeat('b', 64))->first());
        $this->assertNull(Order::withoutGlobalScopes()->whereTicketToken('garbage')->first());
        // The stored column never holds the raw token — only its hash.
        $this->assertStringStartsWith('sha256:', (string) $order->fresh()->getRawOriginal('secure_ticket_token'));
        $this->assertSame(Order::hashTicketToken($raw), $order->fresh()->secure_ticket_token_hash);
    }

    // ── 10.3 Ticket verification — every rejection reason ────────────────────

    public function test_refunded_rejected_and_cancelled_tickets_are_refused(): void
    {
        foreach (['refunded', 'rejected', 'cancelled'] as $status) {
            $order = $this->order($status, "SEC-{$status}");
            $this->scan($this->tokenFor($order))->assertOk()->assertJson(['success' => false, 'reason_code' => 'ticket_revoked']);
            $this->assertNull($order->fresh()->checked_in_at);
        }
    }

    public function test_expired_ticket_and_ended_event_are_refused(): void
    {
        $expired = $this->order('expired', 'SEC-EXP');
        $this->scan($this->tokenFor($expired))->assertOk()
            ->assertJson(['success' => false, 'reason_code' => 'ticket_expired']);

        $this->event->forceFill(['ends_at' => now()->subHour()])->save();
        $live = $this->order('paid', 'SEC-END');
        $this->scan($this->tokenFor($live))->assertOk()
            ->assertJson(['success' => false, 'reason_code' => 'event_not_open']);
        $this->assertNull($live->fresh()->checked_in_at);
    }

    public function test_revoked_token_is_refused_and_audited(): void
    {
        $order = $this->order('paid', 'SEC-REV');
        $order->revokeTicketToken($this->checker, 'Compromised QR screenshot');

        // Revocation swaps the stored hash for a sentinel, so the old QR no longer resolves at all.
        $this->scan($this->tokenFor($order))->assertNotFound()->assertJson(['reason_code' => 'invalid_ticket']);
        // The dead token never resolves to an order, so the rejection is audited against the event gate.
        $this->assertDatabaseHas('security_audit_logs', [
            'action' => 'ticket_redemption_rejected',
            'target_entity' => 'events',
            'target_id' => $this->event->id,
        ]);
    }

    public function test_wrong_event_and_manipulated_event_id_fail_closed(): void
    {
        $siblingEvent = Event::allTenants()->create([
            'organizer_id' => $this->organizer->id, 'slug' => 'sibling-night', 'title' => 'Sibling Night',
            'date_str' => 'Sunday, November 29, 2026', 'time_str' => '6:00 PM - 11:00 PM',
            'venue_name' => 'Hall', 'venue_address' => 'Spanish Town',
            'organizer_name' => 'Sec Org', 'organizer_phone' => '+18765550000',
            'advance_order_deadline' => now()->addWeek(), 'status' => 'published',
        ]);
        $foreign = $this->order('paid', 'SEC-FGN', $siblingEvent);

        // Scanning another event's ticket at this gate finds nothing (event-scoped lookup).
        $this->scan($this->tokenFor($foreign))->assertNotFound()->assertJson(['reason_code' => 'invalid_ticket']);

        // Service-level: an org-wide worker authorized for this organizer but gating the wrong
        // event is refused with wrong_event — the event id is never taken from the client.
        $orgWide = $this->worker('sec-orgwide@example.test', 'ticket_checker', ['event_id' => null]);
        $result = \App\Services\EventWorkerService::scanAndValidateTicket(
            $orgWide, $foreign->fresh(), (string) $this->event->id);
        $this->assertFalse($result['success']);
        $this->assertSame('wrong_event', $result['reason_code']);
        $this->assertNull($foreign->fresh()->checked_in_at);
    }

    public function test_expired_staff_assignment_and_wrong_role_are_refused(): void
    {
        $order = $this->order('paid', 'SEC-EXPIRY');

        // Expired assignment: middleware resolves no active assignment.
        $expiredWorker = $this->worker('sec-expired@example.test', 'ticket_checker', ['expires_at' => now()->subHour()]);
        $this->scan($this->tokenFor($order), $expiredWorker)->assertForbidden();

        // Wrong role: a payment clerk has no scan_qr_tickets permission.
        $clerk = $this->worker('sec-clerk@example.test', 'payment_clerk');
        $this->scan($this->tokenFor($order), $clerk)->assertForbidden();

        // Food station worker likewise cannot scan tickets.
        $food = $this->worker('sec-food@example.test', 'food_station_worker');
        $this->scan($this->tokenFor($order), $food)->assertForbidden();

        $this->assertNull($order->fresh()->checked_in_at);
    }

    public function test_unauthenticated_and_direct_api_access_are_rejected(): void
    {
        $order = $this->order('paid', 'SEC-GUEST');

        $this->postJson('/worker/events/' . $this->event->id . '/scan', ['ticket_code' => $this->tokenFor($order)])
            ->assertUnauthorized();

        // An attendee with no assignment cannot reach the scan endpoint.
        $attendee = User::create(['name' => 'Guest', 'email' => 'guest@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        $this->actingAs($attendee)->postJson('/worker/events/' . $this->event->id . '/scan', ['ticket_code' => 'tok_x'])
            ->assertForbidden();
        $this->assertNull($order->fresh()->checked_in_at);
    }

    public function test_forged_client_fields_cannot_mark_payment_or_admission(): void
    {
        $order = $this->order('payment_submitted', 'SEC-FORGE');

        $this->scan($this->tokenFor($order), $this->checker, $this->event, [
            'status' => 'paid',
            'checked_in_at' => now()->toDateTimeString(),
            'payment_status' => 'verified',
            'event_id' => 'attacker-controlled',
        ])->assertOk()->assertJson(['success' => false]);

        $fresh = $order->fresh();
        $this->assertSame('payment_submitted', $fresh->status);
        $this->assertNull($fresh->checked_in_at);
        $this->assertNull($fresh->checked_in_by);
    }

    // ── 10.4 Concurrency ─────────────────────────────────────────────────────

    public function test_two_scanners_cannot_both_admit_the_same_ticket(): void
    {
        $order = $this->order('paid', 'SEC-RACE');
        $secondChecker = $this->worker('sec-checker2@example.test', 'ticket_checker');

        // Two independent request-path scans, as two gate devices would send.
        $this->scan($this->tokenFor($order), $this->checker)->assertOk()->assertJson(['success' => true]);
        $this->scan($this->tokenFor($order), $secondChecker)->assertOk()->assertJson([
            'success' => false, 'already_admitted' => true,
        ]);

        $fresh = $order->fresh();
        $this->assertNotNull($fresh->checked_in_at);
        $this->assertSame($this->checker->id, (int) $fresh->checked_in_by);

        // Exactly one successful admission audit; the loser is recorded as a rejection.
        $this->assertSame(1, SecurityAuditLog::where('action', 'ticket_redemption_verified')->where('target_id', $order->id)->count());
        $this->assertSame(1, SecurityAuditLog::where('action', 'ticket_redemption_rejected')->where('target_id', $order->id)->count());
    }

    public function test_concurrent_food_handover_cannot_over_collect(): void
    {
        $order = $this->order('paid', 'SEC-FOOD');
        $item = OrderItem::create([
            'order_id' => $order->id,
            'name' => 'Jerk Chicken',
            'category' => 'Mains',
            'line_type' => OrderItem::ITEM,
            'base_price' => 800,
            'final_item_price' => 800,
            'unit_price_minor' => 80000,
            'line_total_minor' => 240000,
            'currency' => 'JMD',
            'quantity' => 3,
            'collected_quantity' => 0,
        ]);

        $workerA = $this->worker('sec-food-a@example.test', 'food_station_worker');
        $workerB = $this->worker('sec-food-b@example.test', 'food_station_worker');
        $service = app(OrderOperationsService::class);

        // Worker A collects 2 of 3 — allowed.
        $service->recordHandover($workerA, $order->fresh(), $item->id, 2, ['action_type' => 'collection', 'station' => 'mains']);
        $this->assertSame(2, (int) $item->fresh()->collected_quantity);

        // Worker B tries to collect 2 more — only 1 remains; the locked recheck must refuse.
        try {
            $service->recordHandover($workerB, $order->fresh(), $item->id, 2, ['action_type' => 'collection', 'station' => 'mains']);
            $this->fail('Over-collection was not refused.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Cannot change by 2', $e->getMessage());
        }

        $this->assertSame(2, (int) $item->fresh()->collected_quantity);
        // Final portion still collectable exactly once.
        $service->recordHandover($workerB, $order->fresh(), $item->id, 1, ['action_type' => 'collection', 'station' => 'mains']);
        $this->assertSame(3, (int) $item->fresh()->collected_quantity);
    }

    // ── 10.6 Fraud & abuse ───────────────────────────────────────────────────

    public function test_repeated_invalid_tokens_are_rejected_and_audited_without_leaking_them(): void
    {
        foreach (['tok_fake1', 'tok_fake2', 'tok_fake3'] as $bogus) {
            $this->scan($bogus)->assertNotFound()->assertJson(['reason_code' => 'invalid_ticket']);
        }

        $rejections = SecurityAuditLog::where('action', 'ticket_redemption_rejected')
            ->where('event_id', $this->event->id)->count();
        $this->assertGreaterThanOrEqual(3, $rejections);

        // No raw token material appears in the audit trail.
        foreach (SecurityAuditLog::latest()->limit(10)->get() as $log) {
            $this->assertStringNotContainsString('tok_fake', (string) $log->details);
        }
    }

    public function test_scan_endpoint_is_rate_limited(): void
    {
        // throttle:30,1 — the 31st request inside a minute must be refused before reaching the controller.
        for ($i = 0; $i < 30; $i++) {
            $this->scan('tok_probe_' . $i)->assertNotFound();
        }
        $this->scan('tok_probe_final')->assertStatus(429);
    }

    public function test_duplicate_admission_attempt_is_rejected_not_silently_ignored(): void
    {
        $order = $this->order('paid', 'SEC-DUP');
        $this->scan($this->tokenFor($order))->assertOk()->assertJson(['success' => true]);

        // A second device scanning the same QR gets a structured refusal, not another admission.
        $this->scan($this->tokenFor($order))->assertOk()->assertJson([
            'success' => false, 'already_admitted' => true,
        ]);

        $this->assertSame(1, SecurityAuditLog::where('action', 'ticket_redemption_verified')->where('target_id', $order->id)->count());
    }

    public function test_unauthorized_reissue_and_revocation_are_blocked(): void
    {
        $order = $this->order('paid', 'SEC-AUTH');
        $attacker = User::create(['name' => 'Attacker', 'email' => 'attacker@example.test', 'password' => bcrypt('password'), 'status' => 'active']);

        // Not the owner and no verify capability: reissue aborts 404, revoke aborts 403.
        $this->actingAs($attacker)->post("/tickets/{$order->id}/reissue", ['reason' => 'stolen'])->assertNotFound();
        $this->actingAs($attacker)->post("/tickets/{$order->id}/revoke", ['reason' => 'fraud'])->assertForbidden();

        // Token unchanged and still verifiable.
        $this->scan($this->tokenFor($order))->assertOk()->assertJson(['success' => true]);
    }

    public function test_scanner_session_header_is_correlated_into_audit_without_token_leak(): void
    {
        $sessionId = (string) \Illuminate\Support\Str::uuid();
        $order = $this->order('paid', 'SEC-SESS');

        $this->actingAs($this->checker)
            ->withHeader('X-EventBite-Scanner-Session', $sessionId)
            ->postJson('/worker/events/' . $this->event->id . '/scan', ['ticket_code' => $this->tokenFor($order)])
            ->assertOk()->assertJson(['success' => true]);

        $log = SecurityAuditLog::where('action', 'ticket_redemption_verified')->where('target_id', $order->id)->latest()->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString("scanner_session:{$sessionId}", (string) $log->details);
        $this->assertStringNotContainsString($this->tokenFor($order), (string) $log->details);
    }
}
