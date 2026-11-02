<?php

namespace Tests\Feature;

use App\Models\ChargeRule;
use App\Models\NotificationLog;
use App\Models\Order;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\DemoOrdersSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The schema keeps history and its own integrity: indexes for the filters the app runs, keys that point at real
 * rows, and historical amounts that keep the currency they were recorded in.
 */
class SchemaIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class, DemoOrdersSeeder::class]);
    }

    public function test_frequent_filters_are_indexed_and_duplicate_indexes_are_gone(): void
    {
        foreach ([
            'events' => 'events_status_date_idx', 'orders' => 'orders_event_status_idx', 'event_worker_assignments' => 'ewa_event_user_idx',
            'ticket_disputes' => 'disputes_order_status_idx', 'menu_items' => 'menu_items_event_active_idx', 'security_audit_logs' => 'audit_user_created_idx',
        ] as $table => $index) {
            $this->assertTrue(Schema::hasIndex($table, $index), "{$table}.{$index}");
        }
        // Unique indexes still guard these columns; the plain duplicates are removed.
        $this->assertFalse(Schema::hasIndex('orders', 'orders_ticket_code_index'));
        $this->assertTrue(Schema::hasIndex('orders', 'orders_ticket_code_unique'));
        $this->assertFalse(Schema::hasIndex('organizers', 'organizers_slug_index'));
        $this->assertTrue(Schema::hasIndex('organizers', 'organizers_slug_unique'));

        // Every index and key name fits MySQL's 64-character limit.
        foreach (Schema::getTables() as $table) {
            foreach (Schema::getIndexes($table['name']) as $index) {
                $this->assertLessThanOrEqual(64, strlen($index['name']), $index['name']);
            }
        }
    }

    public function test_history_survives_and_configuration_follows_its_owner(): void
    {
        $order = Order::withoutGlobalScopes()->whereHas('items')->firstOrFail();
        NotificationLog::withoutGlobalScopes()->create([
            'organizer_id' => $order->organizer_id, 'order_id' => $order->id, 'order_number' => $order->order_number,
            'ticket_code' => $order->ticket_code, 'customer_name' => 'History Guest', 'recipient' => '+13055550100',
            'channel' => 'sms', 'trigger' => 'order_received', 'title' => 'Order received', 'message' => 'Kept', 'status' => 'sent',
            'idempotency_key' => 'schema-test-' . $order->id,
        ]);

        // A hard-deleted order leaves its message history, which still names the order.
        DB::table('orders')->where('id', $order->id)->delete();
        $log = NotificationLog::withoutGlobalScopes()->where('idempotency_key', 'schema-test-' . $order->id)->firstOrFail();
        $this->assertNull($log->order_id);
        $this->assertSame($order->order_number, $log->order_number);

        // A fee rule cannot point at an organizer that does not exist.
        $this->expectException(\Illuminate\Database\QueryException::class);
        ChargeRule::create(['kind' => 'tax', 'label' => 'Orphan tax', 'method' => 'percent', 'rate_bp' => 500, 'applies_to' => 'all',
            'is_active' => true, 'organizer_id' => '00000000-0000-0000-0000-000000000000']);
    }

    public function test_historical_amounts_keep_their_original_currency(): void
    {
        // An order paid in Jamaican dollars before the move to USD...
        $event = \App\Models\Event::allTenants()->findOrFail('evt-kingston-harbour-fry');
        $legacy = Order::withoutGlobalScopes()->create([
            'organizer_id' => $event->organizer_id, 'event_id' => $event->id, 'order_number' => 'ORD-2025-000001', 'ticket_code' => 'SFF-LEGACY01',
            'customer_name' => 'Legacy Guest', 'customer_phone' => '+18765550100', 'method' => 'cash', 'currency' => 'JMD',
            'subtotal_minor' => 450000, 'total_minor' => 450000, 'amount_paid_minor' => 450000, 'status' => 'paid',
            'secure_ticket_token' => 'tok_legacy_jmd',
        ]);

        // ...keeps its amount and currency when the catalog is re-seeded in USD.
        $this->seed(FishFryEventsSeeder::class);
        $this->assertSame('USD', $event->fresh()->currency);
        $legacy = $legacy->fresh();
        $this->assertSame('JMD', $legacy->currency);
        $this->assertSame(450000, (int) $legacy->total_minor);
        $this->assertSame(450000, (int) $legacy->amount_paid_minor);
        // Every financial row carries an explicit currency.
        foreach (['orders', 'order_items', 'order_charges', 'order_adjustments', 'cash_payments', 'ticket_disputes'] as $table) {
            $this->assertSame(0, DB::table($table)->whereNull('currency')->orWhere('currency', '')->count(), $table);
        }
        // New sample records are in U.S. dollars.
        $this->assertSame(['USD'], DB::table('events')->distinct()->pluck('currency')->all());
    }
}
