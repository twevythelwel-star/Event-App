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

    public function test_meal_package_quotes_once_reserves_components_and_rejects_insufficient_inventory(): void
    {
        $side = MenuItem::allTenants()->create([
            'organizer_id' => $this->organizer->id,
            'event_id' => $this->event->id,
            'name' => 'Festival',
            'category' => 'Sides',
            'pricing_type' => 'fixed',
            'price_usd' => '4.00',
            'price_jmd' => 0,
            'available_stock' => 5,
            'is_active' => true,
        ]);
        $package = MenuItem::allTenants()->create([
            'organizer_id' => $this->organizer->id,
            'event_id' => $this->event->id,
            'name' => 'Sunday Dinner',
            'category' => 'Meal Packages',
            'pricing_type' => 'fixed',
            'price_usd' => '20.00',
            'price_jmd' => 0,
            'item_type' => 'package',
            'package_config' => [
                'components' => [[
                    'menu_item_id' => $this->dish->id,
                    'quantity' => 1,
                    'substitutes' => [['menu_item_id' => $side->id, 'extra_minor' => 500]],
                ]],
                'choice_groups' => [[
                    'id' => 'main-choice',
                    'title' => 'Choose a main',
                    'min' => 1,
                    'max' => 1,
                    'options' => [['menu_item_id' => $this->dish->id, 'extra_minor' => 200]],
                ]],
                'add_ons' => [['menu_item_id' => $side->id, 'price_minor' => 100, 'max' => 2]],
            ],
            'is_active' => true,
        ]);
        $cart = [
            'packages' => [[
                'menu_item_id' => $package->id,
                'quantity' => 1,
                'selection' => [
                    'substitutions' => [$side->id],
                    'choices' => ['main-choice' => [$this->dish->id]],
                    'add_ons' => [$side->id => 1],
                ],
            ]],
        ];

        $quote = app(OrderQuoteService::class)->quote($this->event, $cart);
        $this->assertSame([], $quote['problems']);
        $this->assertSame(2800, $quote['totals']->subtotalMinor);
        $this->assertSame(2950, $quote['total_minor'], 'The package is charged once, plus the event packaging fee.');

        $result = app(OrderPlacementService::class)->placeOrder($this->event, [
            'customer_name' => 'Charges Buyer',
            'customer_phone' => '+1 305 555 0100',
            'method' => 'cash',
            'currency' => 'USD',
            'items' => app(OrderQuoteService::class)->toOrderItems($cart),
        ], $this->buyer);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $order = $result['order']->fresh('items');
        $packageLine = $order->items->firstWhere('line_type', 'package');
        $components = $order->items->where('line_type', 'component');
        $this->assertSame(2800, (int) $packageLine->line_total_minor);
        $this->assertCount(3, $components);
        $this->assertSame(0, (int) $components->sum('line_total_minor'));
        $this->assertTrue($components->every(fn ($line) => $line->package_line_id === $packageLine->id));
        $this->assertSame(3, (int) $side->fresh()->available_stock);
        $this->assertSame(49, (int) $this->dish->fresh()->available_stock);

        $tooMany = array_replace_recursive($cart, ['packages' => [['quantity' => 2]]]);
        $rejectedQuote = app(OrderQuoteService::class)->quote($this->event, $tooMany);
        $this->assertNotEmpty($rejectedQuote['problems']);
        $rejectedOrder = app(OrderPlacementService::class)->placeOrder($this->event, [
            'customer_name' => 'Charges Buyer',
            'customer_phone' => '+1 305 555 0100',
            'method' => 'cash',
            'currency' => 'USD',
            'items' => app(OrderQuoteService::class)->toOrderItems($tooMany),
        ], $this->buyer);
        $this->assertFalse($rejectedOrder['success']);
        $this->assertSame(3, (int) $side->fresh()->available_stock, 'A rejected order reserves no stock.');
    }

    public function test_food_quote_reports_full_event_and_pickup_window_capacities(): void
    {
        $existingOrder = $this->place();
        $window = ['start' => '12:00', 'end' => '13:00', 'max_orders' => 1];
        $windowKey = \App\Services\Events\EventSchedule::windowKey($window);
        $existingOrder->forceFill(['pickup_window' => $windowKey])->save();
        $this->event->forceFill([
            'food_max_orders' => 1,
            'food_pickup_windows' => [$window],
        ])->save();

        $quote = app(OrderQuoteService::class)->quote($this->event->fresh(), [
            'items' => [$this->dish->id => ['quantity' => 1]],
            'food' => ['method' => 'pickup', 'pickup_window' => $windowKey],
        ]);

        $this->assertContains('Food orders for this event are full.', $quote['problems']);
        $this->assertContains('The 12:00–13:00 pickup time is full. Choose another.', $quote['problems']);
    }

    public function test_refunds_are_auditable_rows_and_reports_keep_currencies_apart(): void
    {
        $order = $this->place();
        $order->forceFill(['status' => 'paid', 'amount_paid_minor' => $order->total_minor])->save();
        $ledger = app(CashLedgerService::class);

        $ledger->refundOrder($this->host, $order->id, '10.25', 'Missed the first set', 'charges-refund-1');
        $ledger->refundOrder($this->host, $order->id, '53.75', 'Event cancelled', 'charges-refund-2');

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

    public function test_migration_rejects_unknown_historical_currency_before_schema_changes(): void
    {
        $orderId = (string) \Illuminate\Support\Str::uuid();
        DB::table('orders')->insert([
            'id' => $orderId, 'organizer_id' => $this->organizer->id, 'event_id' => $this->event->id,
            'ticket_code' => 'SFF-UNKNOWN-CURRENCY', 'order_number' => 'ORD-UNKNOWN-CURRENCY', 'customer_name' => 'Legacy', 'customer_phone' => '1',
            'method' => 'bank', 'subtotal' => '19.99', 'packaging_fee' => '0', 'total_amount' => '19.99', 'amount_paid' => '19.99',
            'currency' => 'EUR', 'status' => 'paid', 'fulfillment_status' => 'unprepared', 'secure_ticket_token' => 'tok_unknown_currency',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_10_30_000020_money_minor_units_and_charges.php');
        try {
            (new \ReflectionMethod($migration, 'assertCurrenciesAreKnown'))->invoke($migration);
            $this->fail('Unknown historical currencies must block the backfill.');
        } catch (\ReflectionException $exception) {
            throw $exception;
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('orders.currency contains unsupported currency', $exception->getMessage());
        }

        $this->assertSame('EUR', DB::table('orders')->where('id', $orderId)->value('currency'));
        $this->assertFalse(Schema::hasColumn('orders', 'subtotal_minor'), 'Preflight must stop before adding or changing columns.');
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
