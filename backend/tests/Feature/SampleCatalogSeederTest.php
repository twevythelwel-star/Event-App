<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\SecurityAuditLog;
use App\Models\TargetMarket;
use App\Services\Checkout\PaymentOptions;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\DemoOrdersSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The sample catalogue: one clearly labelled demo event per target market, in USD, and a seeder that can be
 * re-run over a development database without rewriting orders, resetting reserved stock or adding duplicates.
 */
class SampleCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    private const SOUTH_FLORIDA = 'evt-kingston-harbour-fry';

    public function test_every_target_market_has_one_labelled_usd_sample_event(): void
    {
        $this->seed(FishFryEventsSeeder::class);

        $events = Event::withoutGlobalScopes()->get();
        $this->assertCount(20, $events);
        $this->assertSame(TargetMarket::count(), $events->pluck('target_market_id')->filter()->unique()->count(), 'one event per market');

        foreach ($events as $event) {
            $this->assertSame('USD', $event->currency, $event->id);
            $this->assertStringStartsWith('SAMPLE EVENT:', $event->description, $event->id);
            $this->assertNotEmpty($event->street_address, "{$event->id} needs a street address so hosts can edit it");
            $this->assertSame('published', $event->status);
            $this->assertTrue($event->event_date_iso >= '2026-11-01', "{$event->id} is dated in the past");

            $this->assertNotEmpty($event->ticket_tiers, $event->id);
            foreach ($event->ticket_tiers as $tier) {
                $this->assertGreaterThan(0, $tier['priceUSD'], "{$event->id} {$tier['id']}");
                $this->assertArrayNotHasKey('price_usd', $tier);
            }

            $menu = MenuItem::withoutGlobalScopes()->where('event_id', $event->id)->where('is_active', true)->get();
            $this->assertNotEmpty($menu, $event->id);
            $this->assertTrue($menu->every(fn (MenuItem $item) => $item->currency === 'USD' && (int) $item->price_minor > 0), $event->id);
        }

        // Event 3 is held the Saturday after Jamaica's Independence Day (August 6).
        $this->assertSame('2027-08-07', Event::withoutGlobalScopes()->where('title', 'Jamaican Independence Celebration')->value('event_date_iso'));
    }

    public function test_reseeding_keeps_existing_orders_and_reserved_stock_and_flags_the_event(): void
    {
        $this->seed(FishFryEventsSeeder::class);

        // The event as an older catalogue left it: a sold-down tier with a stale price key, a tier no longer
        // sold, a menu item a host added, and an order placed in another currency.
        Event::withoutGlobalScopes()->whereKey(self::SOUTH_FLORIDA)->update(['ticket_tiers' => json_encode([
            ['id' => 'tier-ga', 'name' => 'Old GA', 'price_usd' => 15, 'price_jmd' => 2250, 'availableStock' => 198, 'maxPerCustomer' => 6],
            ['id' => 'tier-vip', 'name' => 'Old VIP Lounge', 'price_usd' => 45, 'availableStock' => 45, 'maxPerCustomer' => 4],
        ])]);
        $hostItem = MenuItem::withoutGlobalScopes()->create([
            'organizer_id' => 'org-kingston-youth', 'event_id' => self::SOUTH_FLORIDA, 'name' => 'Host Special',
            'category' => 'Mains', 'pricing_type' => 'fixed', 'price_usd' => '9.00', 'price_jmd' => 0, 'is_active' => true,
        ]);
        $orderId = (string) Str::uuid();
        DB::table('orders')->insert([
            'id' => $orderId, 'organizer_id' => 'org-kingston-youth', 'event_id' => self::SOUTH_FLORIDA,
            'ticket_code' => 'SFF-OLD1', 'order_number' => 'ORD-OLD-1', 'customer_name' => 'Earlier Customer', 'customer_phone' => '1',
            'method' => 'cash', 'subtotal' => '4500.00', 'packaging_fee' => 0, 'total_amount' => '4500.00', 'amount_paid' => 0,
            'subtotal_minor' => 450000, 'fees_minor' => 0, 'total_minor' => 450000, 'amount_paid_minor' => 0,
            'currency' => 'JMD', 'status' => 'awaiting_payment', 'fulfillment_status' => 'unprepared', 'secure_ticket_token' => 'tok_old_1',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->seed(FishFryEventsSeeder::class);

        $event = Event::withoutGlobalScopes()->findOrFail(self::SOUTH_FLORIDA);
        $tiers = collect($event->ticket_tiers)->keyBy('id');
        $this->assertSame(198, $tiers['tier-ga']['availableStock'], 'remaining stock reflects reservations already made');
        $this->assertSame(10, $tiers['tier-ga']['priceUSD']);
        $this->assertArrayNotHasKey('price_usd', $tiers['tier-ga'], 'no stale price key survives to override the new price');
        $this->assertFalse($tiers->has('tier-vip'), 'tiers no longer in the catalogue go off sale');
        $this->assertTrue($tiers->has('tier-bundle-jerk'));

        $order = Order::withoutGlobalScopes()->findOrFail($orderId);
        $this->assertSame(['JMD', 450000, 'awaiting_payment'], [$order->currency, (int) $order->total_minor, $order->status], 'orders are never rewritten');

        $this->assertFalse($hostItem->fresh()->is_active, 'items outside the catalogue are taken off sale');
        $this->assertNotNull($hostItem->fresh(), 'and never deleted');

        $flag = SecurityAuditLog::where('action', 'event_review_required')->where('target_id', self::SOUTH_FLORIDA)->firstOrFail();
        $this->assertStringContainsString('1 existing order(s) in JMD', $flag->details);
        $this->assertStringContainsString('Host Special', $flag->details);
        $this->assertSame(1, SecurityAuditLog::where('action', 'event_review_required')->count(), 'only events with orders are flagged');
    }

    public function test_demo_orders_are_placed_once(): void
    {
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class, DemoOrdersSeeder::class]);
        $count = Order::withoutGlobalScopes()->count();
        $this->assertSame(4, $count);

        $this->seed(DemoOrdersSeeder::class);
        $this->assertSame($count, Order::withoutGlobalScopes()->count());
    }

    public function test_east_orange_demo_offers_cash_and_manual_transfers_only(): void
    {
        $this->seed(FishFryEventsSeeder::class);

        $eastOrange = Event::withoutGlobalScopes()->where('city', 'East Orange')->firstOrFail();
        $this->assertEqualsCanonicalizing(['zelle', 'bank', 'cash'], array_column(PaymentOptions::forEvent($eastOrange), 'value'));
    }
}
