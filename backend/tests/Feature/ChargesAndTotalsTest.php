<?php

namespace Tests\Feature;

use App\Models\ChargeRule;
use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\Organizer;
use App\Models\OrganizerUser;
use App\Models\User;
use App\Models\UserRole;
use App\Services\CashLedgerService;
use App\Services\Checkout\OrderQuoteService;
use App\Services\OrderPlacementService;
use App\Services\Pricing\RevenueSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Totals are integer minor units with item subtotal, discounts, fees, taxes and total kept separate;
 * fees and taxes come only from configuration; refunds are auditable rows; reports never mix currencies.
 */
class ChargesAndTotalsTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizer;
    private Event $event;
    private MenuItem $dish;
    private User $host;
    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('us_states')->insert([
            ['code' => 'FL', 'name' => 'Florida', 'default_timezone' => 'America/New_York', 'is_state_or_dc' => true, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'GA', 'name' => 'Georgia', 'default_timezone' => 'America/New_York', 'is_state_or_dc' => true, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->organizer = Organizer::create(['name' => 'Charges Org', 'slug' => 'charges-org']);
        $this->event = $this->makeEvent('FL', 'charges-night');
        $this->dish = MenuItem::allTenants()->create([
            'organizer_id' => $this->organizer->id,
            'event_id' => $this->event->id,
            'name' => 'Jerk Chicken',
            'category' => 'Mains',
            'pricing_type' => 'fixed',
            'price_usd' => '12.50',
            'price_jmd' => 0,
            'available_stock' => 50,
            'is_active' => true,
        ]);

        $this->host = User::create(['name' => 'Charges Host', 'email' => 'charges-host@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        UserRole::create(['user_id' => $this->host->id, 'role' => 'event_host', 'is_active' => true, 'assigned_at' => now()]);
        OrganizerUser::create(['organizer_id' => $this->organizer->id, 'user_id' => $this->host->id, 'role' => 'event_owner', 'is_active' => true]);
        $this->buyer = User::create(['name' => 'Charges Buyer', 'email' => 'charges-buyer@example.test', 'phone' => '+13055550100', 'password' => bcrypt('password'), 'status' => 'active']);
    }

    private function makeEvent(string $state, string $slug): Event
    {
        return Event::allTenants()->create([
            'organizer_id' => $this->organizer->id,
            'slug' => $slug,
            'title' => ucwords(str_replace('-', ' ', $slug)),
            'date_str' => 'Saturday, November 28, 2026',
            'time_str' => '6:00 PM - 11:00 PM',
            'currency' => 'USD',
            'state_code' => $state,
            'venue_name' => 'Pier',
            'venue_address' => 'Miami',
            'organizer_name' => 'Charges Org',
            'organizer_phone' => '+13055550000',
            'advance_order_deadline' => now()->addWeek(),
            'status' => 'published',
            'is_ordering_open' => true,
            'packaging_fee_usd' => '1.50',
            'ticket_tiers' => [['id' => 'tier-ga', 'name' => 'General Admission', 'priceUSD' => 25, 'availableStock' => 100, 'maxPerCustomer' => 6]],
        ]);
    }

    private function cart(): array
    {
        return ['tiers' => ['tier-ga' => 2], 'items' => [$this->dish->id => ['quantity' => 1]]];
    }

    private function place(?Event $event = null): Order
    {
        $event ??= $this->event;
        $result = app(OrderPlacementService::class)->placeOrder(Event::allTenants()->findOrFail($event->id), [
            'customer_name' => 'Charges Buyer', 'customer_phone' => '+1 305 555 0100', 'method' => 'cash', 'currency' => 'JMD',
            'items' => app(OrderQuoteService::class)->toOrderItems($this->cart()),
        ], $this->buyer);
        $this->assertTrue($result['success'], $result['error'] ?? '');

        return $result['order']->fresh(['charges', 'items']);
    }

    private function rule(array $attributes): ChargeRule
    {
        return ChargeRule::create($attributes + ['applies_to' => 'all', 'is_active' => true]);
    }

    public function test_without_rules_the_order_is_items_plus_the_events_packaging_fee(): void
    {
        $order = $this->place();

        // 2 × $25.00 tickets + 1 × $12.50 dish = $62.50; packaging fee $1.50 because there is food.
        $this->assertSame('USD', $order->currency, 'the event currency wins over the requested one');
        $this->assertSame(6250, $order->subtotal_minor);
        $this->assertSame(0, $order->discount_minor);
        $this->assertSame(150, $order->fees_minor);
        $this->assertSame(0, $order->tax_minor);
        $this->assertSame(6400, $order->total_minor);
        $this->assertSame('64.00', $order->total_amount, 'legacy decimal kept in step exactly');
        $this->assertSame([['host_fee', 'Packaging fee', 150]], $order->charges->map(fn ($c) => [$c->kind, $c->label, $c->amount_minor])->all());
        $this->assertEqualsCanonicalizing([5000, 1250], $order->items->pluck('line_total_minor')->map(fn ($v) => (int) $v)->all());
    }

    public function test_configured_discount_fees_and_tax_are_separate_and_exact(): void
    {
        $this->rule(['kind' => 'discount', 'label' => 'Early bird', 'method' => 'fixed_per_order', 'amount_minor' => 200, 'currency' => 'USD', 'applies_to' => 'tickets']);
        $this->rule(['kind' => 'platform_fee', 'label' => 'Service fee', 'method' => 'percent', 'rate_bp' => 500]);
        $this->rule(['kind' => 'tax', 'label' => 'Sales tax (configured)', 'method' => 'percent', 'rate_bp' => 700, 'applies_to' => 'food', 'state_code' => 'FL']);

        // tickets 5000 − discount 200 = 4800; food 1250. Service fee 5% of 6050 = 302.5 → 303.
        // Packaging 150. Tax 7% of food 1250 = 87.5 → 88. Total 6250 − 200 + 150 + 303 + 88 = 6591.
        $quote = app(OrderQuoteService::class)->quote($this->event, $this->cart());
        $this->assertSame(6591, $quote['total_minor']);

        $order = $this->place();
        $this->assertSame(6250, $order->subtotal_minor);
        $this->assertSame(200, $order->discount_minor);
        $this->assertSame(453, $order->fees_minor);
        $this->assertSame(88, $order->tax_minor);
        $this->assertSame(6591, $order->total_minor);
        $this->assertSame($quote['total_minor'], $order->total_minor, 'the order charges exactly what the preview showed');
        $this->assertEqualsCanonicalizing(
            [['discount', 200], ['host_fee', 150], ['platform_fee', 303], ['tax', 88]],
            $order->charges->map(fn ($c) => [$c->kind, $c->amount_minor])->all(),
        );

        // A later rule change never alters an order already placed.
        ChargeRule::query()->update(['is_active' => false]);
        $this->assertSame(6591, $order->fresh()->total_minor);
        $this->assertCount(4, $order->fresh()->charges);
    }

    public function test_rules_respect_their_scope_and_currency(): void
    {
        $this->rule(['kind' => 'tax', 'label' => 'FL tax', 'method' => 'percent', 'rate_bp' => 700, 'state_code' => 'FL']);
        $this->rule(['kind' => 'platform_fee', 'label' => 'JMD flat fee', 'method' => 'fixed_per_order', 'amount_minor' => 50000, 'currency' => 'JMD']);
        $other = Organizer::create(['name' => 'Other Org', 'slug' => 'other-org']);
        $this->rule(['kind' => 'host_fee', 'label' => 'Other organizer fee', 'method' => 'fixed_per_order', 'amount_minor' => 999, 'currency' => 'USD', 'organizer_id' => $other->id]);

        $georgia = $this->makeEvent('GA', 'georgia-night');
        MenuItem::allTenants()->create(['organizer_id' => $this->organizer->id, 'event_id' => $georgia->id, 'name' => 'Jerk Chicken', 'category' => 'Mains', 'pricing_type' => 'fixed', 'price_usd' => '12.50', 'price_jmd' => 0, 'is_active' => true]);
        $quote = app(OrderQuoteService::class)->quote($georgia, ['tiers' => ['tier-ga' => 2]]);

        $this->assertSame(0, $quote['totals']->taxMinor, 'a Florida tax does not apply in Georgia');
        $this->assertSame(0, $quote['totals']->platformFeeMinor, 'a fixed JMD fee is never applied to a USD order');
        $this->assertSame(0, $quote['totals']->hostFeeMinor, "another organizer's fee does not apply");
        $this->assertSame(5000, $quote['total_minor']);
    }

    public function test_checkout_shows_every_charge_before_confirming_and_rejects_a_changed_total(): void
    {
        $this->rule(['kind' => 'platform_fee', 'label' => 'Service fee', 'method' => 'percent', 'rate_bp' => 500]);
        $this->rule(['kind' => 'tax', 'label' => 'Sales tax (configured)', 'method' => 'percent', 'rate_bp' => 700, 'applies_to' => 'food']);

        $this->actingAs($this->buyer)->post(route('checkout.tickets.save', $this->event), ['tiers' => ['tier-ga' => 2]])->assertRedirect();
        $this->actingAs($this->buyer)->post(route('checkout.food.save', $this->event), ['items' => [$this->dish->id => ['quantity' => 1]]]);
        $review = $this->actingAs($this->buyer)->get(route('checkout.review', $this->event))->assertOk();

        // Items $62.50, packaging $1.50, service fee 5% of 6250 = $3.13 (312.5 → 313), tax 7% of 1250 = $0.88.
        $review->assertSee('Item subtotal')->assertSee('$62.50')
            ->assertSee('Packaging fee')->assertSee('$1.50')
            ->assertSee('Service fee')->assertSee('$3.13')
            ->assertSee('Sales tax (configured)')->assertSee('$0.88')
            ->assertSee('$68.01')
            ->assertSee('It is not tax advice');

        $this->actingAs($this->buyer)->post(route('checkout.place', $this->event), [
            'customer_phone' => '+1 305 555 0100', 'method' => 'cash', 'quoted_total_minor' => 6800,
        ])->assertSessionHas('error');
        $this->assertSame(0, Order::allTenants()->count(), 'no order when the total differs from what was shown');

        $this->actingAs($this->buyer)->post(route('checkout.place', $this->event), [
            'customer_phone' => '+1 305 555 0100', 'method' => 'cash', 'quoted_total_minor' => 6801,
        ])->assertRedirect();
        $this->assertSame(6801, Order::allTenants()->firstOrFail()->total_minor);
    }

    public function test_refunds_are_auditable_rows_and_reports_keep_currencies_apart(): void
    {
        $order = $this->place();
        $order->forceFill(['status' => 'paid', 'amount_paid_minor' => $order->total_minor])->save();
        $ledger = app(CashLedgerService::class);

        $ledger->refundOrder($this->host, $order->id, '10.25', 'Missed the first set');
        $ledger->refundOrder($this->host, $order->id, '53.75', 'Event cancelled');

        $order->refresh();
        $this->assertSame('refunded', $order->status);
        $this->assertSame(6400, $order->refunded_minor);
        $this->assertSame([1025, 5375], OrderAdjustment::where('order_id', $order->id)->orderBy('amount_minor')->pluck('amount_minor')->all());
        $this->assertSame($this->host->id, (int) OrderAdjustment::where('order_id', $order->id)->value('actor_user_id'));

        // A JMD order alongside: each currency is summed on its own.
        $jmd = $order->replicate(['id', 'order_number', 'ticket_code', 'secure_ticket_token', 'idempotency_key']);
        $jmd->forceFill(['order_number' => 'ORD-JMD-1', 'ticket_code' => 'SFF-JMD1', 'secure_ticket_token' => 'tok_jmd_1', 'currency' => 'JMD',
            'total_minor' => 500000, 'amount_paid_minor' => 500000, 'refunded_minor' => 0, 'subtotal_minor' => 500000, 'fees_minor' => 0, 'status' => 'paid'])->save();

        $summary = RevenueSummary::byCurrency(Order::allTenants()->get());
        $this->assertSame(['USD', 'JMD'], array_keys($summary));
        $this->assertSame(6400, $summary['USD']['received']['amount_minor']);
        $this->assertSame(0, $summary['USD']['net']['amount_minor']);
        $this->assertSame('J$5,000.00', $summary['JMD']['received']['formatted']);
    }

    public function test_existing_decimal_rows_are_converted_exactly_by_the_migration(): void
    {
        $orderId = (string) \Illuminate\Support\Str::uuid();
        DB::table('orders')->insert([
            'id' => $orderId, 'organizer_id' => $this->organizer->id, 'event_id' => $this->event->id,
            'ticket_code' => 'SFF-LEGACY', 'order_number' => 'ORD-LEGACY-1', 'customer_name' => 'Legacy', 'customer_phone' => '1',
            'method' => 'bank', 'subtotal' => '19.99', 'packaging_fee' => '0.29', 'total_amount' => '20.28', 'amount_paid' => '20.28',
            'currency' => 'USD', 'status' => 'paid', 'fulfillment_status' => 'unprepared', 'secure_ticket_token' => 'tok_legacy',
            'refund_details' => json_encode([['amount' => 5.1, 'reason' => 'Old refund', 'refundedAt' => '2026-10-01T10:00:00Z', 'refundedBy' => 'Host']]),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_10_30_000020_money_minor_units_and_charges.php');
        (new \ReflectionMethod($migration, 'backfill'))->invoke($migration);

        $row = DB::table('orders')->where('id', $orderId)->first();
        $this->assertSame([1999, 29, 2028, 2028, 510], [(int) $row->subtotal_minor, (int) $row->fees_minor, (int) $row->total_minor, (int) $row->amount_paid_minor, (int) $row->refunded_minor]);
        $this->assertSame(510, (int) DB::table('order_adjustments')->where('order_id', $orderId)->value('amount_minor'));
        $this->assertSame(29, (int) DB::table('order_charges')->where('order_id', $orderId)->value('amount_minor'));
    }

    public function test_only_settings_admins_configure_rules_and_rates_are_exact(): void
    {
        $root = User::create(['name' => 'Root', 'email' => 'root-charges@example.test', 'password' => bcrypt('password'), 'status' => 'active', 'is_system_admin' => true]);
        UserRole::create(['user_id' => $root->id, 'role' => 'system_admin', 'is_active' => true, 'assigned_at' => now()]);

        $this->actingAs($this->host)->get(route('admin.console.charges.index'))->assertForbidden();
        $this->actingAs($this->host)->post(route('admin.console.charges.store'), ['kind' => 'tax', 'label' => 'X', 'method' => 'percent', 'rate_percent' => '5', 'applies_to' => 'all'])->assertForbidden();

        $this->actingAs($root)->get(route('admin.console.charges.index'))->assertOk()->assertSee('not verified by EventBite');
        $this->actingAs($root)->post(route('admin.console.charges.store'), [
            'kind' => 'tax', 'label' => 'Sales tax (configured)', 'method' => 'percent', 'rate_percent' => '7.25', 'applies_to' => 'food', 'state_code' => 'FL', 'notes' => 'Confirmed by accountant 2026-10-30',
        ])->assertRedirect();
        $this->actingAs($root)->post(route('admin.console.charges.store'), [
            'kind' => 'platform_fee', 'label' => 'Booking fee', 'method' => 'fixed_per_item', 'amount' => '0.99', 'currency' => 'USD', 'applies_to' => 'tickets',
        ])->assertRedirect();
        $this->actingAs($root)->post(route('admin.console.charges.store'), [
            'kind' => 'tax', 'label' => 'Too precise', 'method' => 'percent', 'rate_percent' => '6.625', 'applies_to' => 'all',
        ])->assertSessionHasErrors('rate_percent');

        $tax = ChargeRule::where('label', 'Sales tax (configured)')->firstOrFail();
        $this->assertSame(725, $tax->rate_bp);
        $this->assertSame(99, ChargeRule::where('label', 'Booking fee')->value('amount_minor'));
        $this->assertSame(2, ChargeRule::count());
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'charge_rule_created', 'target_id' => $tax->id]);

        $this->actingAs($root)->post(route('admin.console.charges.toggle', $tax))->assertRedirect();
        $this->assertFalse($tax->fresh()->is_active);
    }
}
