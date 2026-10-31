<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Organizer;
use App\Models\OrganizerUser;
use App\Models\User;
use App\Models\UserRole;
use App\Services\CashLedgerService;
use App\Services\OrderOperationsService;
use App\Services\OrderPlacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Payment decisions follow a fixed transition table, decided on the locked order row, and a full refund puts
 * unused tickets and food back on sale. Fixtures are self-contained (no demo seed data).
 */
class PaymentStatusAndRefundTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private MenuItem $dish;
    private User $host;
    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $organizer = Organizer::create(['name' => 'Refund Org', 'slug' => 'refund-org']);
        $this->event = Event::allTenants()->create([
            'organizer_id' => $organizer->id,
            'slug' => 'refund-night',
            'title' => 'Refund Night',
            'date_str' => 'Saturday, November 28, 2026',
            'time_str' => '6:00 PM - 11:00 PM',
            'currency' => 'JMD',
            'venue_name' => 'Pier',
            'venue_address' => 'Kingston',
            'organizer_name' => 'Refund Org',
            'organizer_phone' => '+18765550000',
            'advance_order_deadline' => now()->addWeek(),
            'status' => 'published',
            'is_ordering_open' => true,
            'ticket_tiers' => [
                ['id' => 'tier-ga', 'name' => 'General Admission', 'priceJMD' => 2000, 'priceUSD' => 13, 'availableStock' => 10, 'maxPerCustomer' => 6],
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
            'available_stock' => 20,
            'is_active' => true,
        ]);

        $this->host = User::create(['name' => 'Refund Host', 'email' => 'refund-host@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        UserRole::create(['user_id' => $this->host->id, 'role' => 'event_host', 'is_active' => true, 'assigned_at' => now()]);
        OrganizerUser::create(['organizer_id' => $organizer->id, 'user_id' => $this->host->id, 'role' => 'event_owner', 'is_active' => true]);

        $this->buyer = User::create(['name' => 'Refund Guest', 'email' => 'refund-guest@example.test', 'phone' => '+18765550140', 'password' => bcrypt('password'), 'status' => 'active']);
    }

    private function place(int $tickets, int $portions): Order
    {
        $items = array_values(array_filter([
            $tickets > 0 ? ['menu_item_id' => 'tier-ga', 'quantity' => $tickets] : null,
            $portions > 0 ? ['menu_item_id' => $this->dish->id, 'quantity' => $portions] : null,
        ]));
        $result = app(OrderPlacementService::class)->placeOrder(Event::allTenants()->findOrFail($this->event->id), [
            'customer_name' => 'Refund Guest',
            'customer_phone' => '+1 876 555 0140',
            'method' => 'bank',
            'currency' => 'JMD',
            'items' => $items,
        ], $this->buyer);
        $this->assertTrue($result['success'], $result['error'] ?? '');

        return $result['order'];
    }

    private function orderIn(string $status): Order
    {
        $order = $this->place(0, 1);
        $order->forceFill(['status' => $status, 'amount_paid' => in_array($status, ['paid', 'refunded'], true) ? $order->total_amount : 0])->save();

        return $order;
    }

    private function decide(Order $order, string $status, array $extra = [])
    {
        return $this->actingAs($this->host)->postJson("/api/v1/operations/orders/{$order->id}/payment", array_merge(['status' => $status], $extra));
    }

    public function test_payment_decisions_follow_the_transition_table(): void
    {
        $extra = ['partially_paid' => ['amount_paid' => 100], 'rejected' => ['rejection_reason' => 'Reference not found on the statement']];
        $this->dish->forceFill(['available_stock' => 500])->save(); // 9 states x 4 decisions = 36 orders

        foreach (['awaiting_payment', 'payment_submitted', 'under_verification', 'partially_paid', 'rejected', 'paid', 'refunded', 'cancelled', 'expired'] as $from) {
            foreach (OrderOperationsService::MANUAL_PAYMENT_STATUSES as $to) {
                $order = $this->orderIn($from);
                $allowed = in_array($to, OrderOperationsService::PAYMENT_TRANSITIONS[$from] ?? [], true);

                $response = $this->decide($order, $to, $extra[$to] ?? []);

                if ($allowed) {
                    $response->assertOk();
                    $this->assertSame($to, $order->fresh()->status, "{$from} -> {$to}");
                } else {
                    $response->assertStatus(422);
                    $this->assertSame($from, $order->fresh()->status, "{$from} -> {$to} must be refused");
                }
            }
        }
    }

    public function test_the_rules_that_matter_most(): void
    {
        // A rejected payment cannot jump straight to paid; it goes back to review first.
        $order = $this->orderIn('rejected');
        $this->decide($order, 'paid')->assertStatus(422);
        $this->decide($order, 'under_verification')->assertOk();
        $this->decide($order, 'paid')->assertOk();

        // Cancelled and expired orders can never be marked paid.
        $this->decide($this->orderIn('cancelled'), 'paid')->assertStatus(422);
        $this->decide($this->orderIn('expired'), 'paid')->assertStatus(422);

        // A paid order cannot be rejected; marking it paid again changes nothing.
        $paid = $this->orderIn('paid');
        $paid->forceFill(['verified_at' => now()->subDay(), 'verified_by' => 'Original Verifier'])->save();
        $this->decide($paid, 'rejected', ['rejection_reason' => 'oops'])->assertStatus(422);
        $this->decide($paid, 'paid')->assertOk();
        $this->assertSame('Original Verifier', $paid->fresh()->verified_by);
    }

    public function test_the_decision_uses_the_stored_status_not_a_stale_copy(): void
    {
        // A copy loaded while the order was awaiting payment; meanwhile the order was refunded.
        $order = $this->orderIn('awaiting_payment');
        $stale = Order::allTenants()->findOrFail($order->id);
        $order->forceFill(['status' => 'refunded'])->save();

        try {
            app(OrderOperationsService::class)->updatePaymentStatus($this->host, $stale, ['status' => 'paid']);
            $this->fail('A refunded order was marked paid through a stale copy.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('refunded', $e->getMessage());
        }
        $this->assertSame('refunded', $order->fresh()->status);
    }

    public function test_refunds_never_exceed_what_was_received(): void
    {
        $order = $this->orderIn('paid');
        $total = (float) $order->total_amount;
        $ledger = app(CashLedgerService::class);

        $ledger->refundOrder($this->host, $order->id, $total - 100, 'Most of it');
        try {
            $ledger->refundOrder($this->host, $order->id, 200, 'More than is left');
            $this->fail('Refunded more than was received.');
        } catch (\InvalidArgumentException) {
        }
        $ledger->refundOrder($this->host, $order->id, 100, 'The rest');

        $fresh = $order->fresh();
        $this->assertSame('refunded', $fresh->status);
        $this->assertEqualsWithDelta($total, array_sum(array_column(CashLedgerService::refundEntries($fresh), 'amount')), 0.001);

        try {
            $ledger->refundOrder($this->host, $order->id, 1, 'Again');
            $this->fail('Refunded a fully refunded order.');
        } catch (\InvalidArgumentException) {
        }
    }

    public function test_a_full_refund_returns_unused_tickets_and_food_to_stock(): void
    {
        $order = $this->paidOrder(2, 3);
        $ledger = app(CashLedgerService::class);

        $this->assertSame(8, $this->tierStock());
        $this->assertSame(17, (int) $this->dish->fresh()->available_stock);

        // A partial refund is an amount, not items: nothing comes back.
        $ledger->refundOrder($this->host, $order->id, 100, 'Goodwill');
        $this->assertSame(8, $this->tierStock());
        $this->assertSame(17, (int) $this->dish->fresh()->available_stock);

        // One portion was already handed over; the other two come back with both tickets.
        $order->items()->whereNotNull('menu_item_id')->update(['collected_quantity' => 1]);
        $ledger->refundOrder($this->host, $order->id, (float) $order->fresh()->amount_paid - 100, 'Event postponed');

        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame(10, $this->tierStock());
        $this->assertSame(19, (int) $this->dish->fresh()->available_stock);
        $this->assertTrue(\App\Models\SecurityAuditLog::where('action', 'order_refunded')->where('target_id', $order->id)
            ->where('details', 'like', '%Returned to stock: 2 ticket(s), 2 portion(s)%')->exists());
    }

    public function test_a_used_ticket_is_not_put_back_on_sale(): void
    {
        $order = $this->paidOrder(2, 0);
        $order->forceFill(['checked_in_at' => now()])->save();

        app(CashLedgerService::class)->refundOrder($this->host, $order->id, (float) $order->amount_paid, 'Left early');

        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame(8, $this->tierStock());
    }

    private function paidOrder(int $tickets, int $portions): Order
    {
        $order = $this->place($tickets, $portions);
        $this->decide($order, 'paid')->assertOk();

        return $order->fresh();
    }

    private function tierStock(): int
    {
        return (int) Event::allTenants()->findOrFail($this->event->id)->ticket_tiers[0]['availableStock'];
    }
}
