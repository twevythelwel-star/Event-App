<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organizer;
use App\Models\Payment;
use App\Models\User;
use App\Services\Checkout\InventoryReservationExpiryService;
use App\Services\Payments\StripePaymentReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductionResilienceTest extends TestCase
{
    use RefreshDatabase;

    private function baseOrder(array $overrides = []): array
    {
        $organizer = Organizer::create(['name' => 'Resilience Org', 'slug' => 'resilience-org']);
        $event = Event::allTenants()->create([
            'organizer_id' => $organizer->id, 'slug' => 'resilience-event', 'title' => 'Resilience Event',
            'date_str' => 'Saturday', 'time_str' => '6 PM', 'venue_name' => 'Hall', 'venue_address' => 'Main Street',
            'organizer_name' => 'Resilience Org', 'organizer_phone' => '+12025550123', 'currency' => 'USD',
            'advance_order_deadline' => now()->addWeek(),
            'status' => 'published', 'is_ordering_open' => true,
            'ticket_tiers' => [['id' => 'tier-last', 'name' => 'Last ticket', 'price_usd' => 10, 'available_stock' => 0]],
        ]);
        $order = Order::allTenants()->create(array_merge([
            'organizer_id' => $organizer->id, 'event_id' => $event->id,
            'ticket_code' => 'RES-100', 'order_number' => 'ORD-RES-100', 'customer_name' => 'Test Guest',
            'customer_phone' => '+12025550123', 'method' => 'card', 'subtotal' => 10, 'total_amount' => 10,
            'amount_paid' => 0, 'subtotal_minor' => 1000, 'total_minor' => 1000, 'amount_paid_minor' => 0,
            'currency' => 'USD', 'status' => 'awaiting_payment', 'secure_ticket_token' => 'tok_resilience-test-100',
            'reservation_expires_at' => now()->subMinute(),
        ], $overrides));
        return [$organizer, $event, $order];
    }

    public function test_expired_reservation_returns_ticket_and_food_stock_only_once(): void
    {
        [, $event, $order] = $this->baseOrder();
        $menu = MenuItem::allTenants()->create([
            'organizer_id' => $event->organizer_id, 'event_id' => $event->id, 'name' => 'Final plate',
            'category' => 'Mains', 'pricing_type' => 'fixed', 'price_usd' => 10, 'available_stock' => 0, 'is_active' => true,
        ]);
        OrderItem::create(['order_id' => $order->id, 'menu_item_id' => $menu->id, 'name' => 'Final plate', 'category' => 'Mains',
            'pricing_type' => 'fixed', 'base_price' => 10, 'final_item_price' => 10, 'unit_price_minor' => 1000,
            'line_total_minor' => 2000, 'currency' => 'USD', 'quantity' => 2, 'collected_quantity' => 0]);
        OrderItem::create(['order_id' => $order->id, 'ticket_tier_id' => 'tier-last', 'name' => 'Last ticket', 'category' => 'Tickets',
            'pricing_type' => 'fixed', 'base_price' => 10, 'final_item_price' => 10, 'unit_price_minor' => 1000,
            'line_total_minor' => 1000, 'currency' => 'USD', 'quantity' => 1, 'collected_quantity' => 0]);

        $service = app(InventoryReservationExpiryService::class);
        $this->assertSame(['expired' => 1, 'inventory_released' => 1, 'held_for_provider' => 0], $service->expire());
        $this->assertSame('expired', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->inventory_released_at);
        $this->assertSame(2, (int) $menu->fresh()->available_stock);
        $this->assertSame(1, (int) Event::allTenants()->find($event->id)->ticket_tiers[0]['available_stock']);
        $this->assertSame(['expired' => 0, 'inventory_released' => 0, 'held_for_provider' => 0], $service->expire());
        $this->assertSame(2, (int) $menu->fresh()->available_stock);
    }

    public function test_open_or_succeeded_provider_payment_prevents_expiry_release(): void
    {
        [$organizer, , $order] = $this->baseOrder();
        Payment::create(['order_id' => $order->id, 'organizer_id' => $organizer->id, 'provider' => 'stripe', 'method' => 'card',
            'status' => Payment::PROCESSING, 'amount_minor' => 1000, 'currency' => 'USD', 'amount_received_minor' => 0,
            'provider_reference' => 'pi_resilience_open', 'idempotency_key' => 'idem_resilience_open']);

        $result = app(InventoryReservationExpiryService::class)->expire();
        $this->assertSame(1, $result['held_for_provider']);
        $this->assertSame('awaiting_payment', $order->fresh()->status);
        $this->assertNull($order->fresh()->inventory_released_at);
    }

    public function test_reconciliation_uses_provider_state_and_settles_matching_order(): void
    {
        [$organizer, , $order] = $this->baseOrder();
        Payment::create(['order_id' => $order->id, 'organizer_id' => $organizer->id, 'provider' => 'stripe', 'method' => 'card',
            'status' => Payment::PROCESSING, 'amount_minor' => 1000, 'currency' => 'USD', 'amount_received_minor' => 0,
            'provider_reference' => 'pi_resilience_match', 'idempotency_key' => 'idem_resilience_match']);
        config(['payments.stripe.secret_key' => 'sk_test_fake_for_http_fake', 'payments.stripe.api_base' => 'https://api.stripe.test']);
        Http::fake(['api.stripe.test/v1/payment_intents/pi_resilience_match*' => Http::response([
            'id' => 'pi_resilience_match', 'status' => 'succeeded', 'amount' => 1000, 'amount_received' => 1000,
            'currency' => 'usd', 'metadata' => ['eventbite_order_id' => $order->id],
            'latest_charge' => ['id' => 'ch_resilience_match', 'amount_refunded' => 0],
        ])]);

        $result = app(StripePaymentReconciliationService::class)->reconcile();
        $this->assertSame(1, $result['checked']);
        $this->assertSame(1, $result['applied']);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(Payment::SUCCEEDED, Payment::query()->firstOrFail()->status);
        $this->assertSame('matched', Payment::query()->firstOrFail()->reconciliation_status);
    }

    public function test_reconciliation_flags_amount_mismatch_without_marking_order_paid(): void
    {
        [$organizer, , $order] = $this->baseOrder();
        Payment::create(['order_id' => $order->id, 'organizer_id' => $organizer->id, 'provider' => 'stripe', 'method' => 'card',
            'status' => Payment::PROCESSING, 'amount_minor' => 1000, 'currency' => 'USD', 'amount_received_minor' => 0,
            'provider_reference' => 'pi_resilience_mismatch', 'idempotency_key' => 'idem_resilience_mismatch']);
        config(['payments.stripe.secret_key' => 'sk_test_fake_for_http_fake', 'payments.stripe.api_base' => 'https://api.stripe.test']);
        Http::fake(['api.stripe.test/v1/payment_intents/pi_resilience_mismatch*' => Http::response([
            'id' => 'pi_resilience_mismatch', 'status' => 'succeeded', 'amount' => 1200, 'amount_received' => 1200,
            'currency' => 'usd', 'metadata' => ['eventbite_order_id' => $order->id],
            'latest_charge' => ['id' => 'ch_resilience_mismatch', 'amount_refunded' => 0],
        ])]);

        $result = app(StripePaymentReconciliationService::class)->reconcile();
        $this->assertSame(1, $result['mismatches']);
        $this->assertSame('awaiting_payment', $order->fresh()->status);
        $this->assertSame('mismatch', Payment::query()->firstOrFail()->reconciliation_status);
    }
}
