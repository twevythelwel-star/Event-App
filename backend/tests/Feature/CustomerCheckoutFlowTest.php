<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\HostPaymentPolicy;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Organizer;
use App\Models\User;
use App\Support\QrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Blade ordering flow: event → tickets → food → checkout → ticket, and its failure paths.
 */
class CustomerCheckoutFlowTest extends TestCase
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

        $this->organizer = Organizer::create(['name' => 'Flow Org', 'slug' => 'flow-org']);
        $this->event = Event::allTenants()->create([
            'organizer_id' => $this->organizer->id,
            'slug' => 'flow-night',
            'title' => 'Flow Night',
            'date_str' => 'Saturday, November 28, 2026',
            'time_str' => '6:00 PM - 11:00 PM',
            'currency' => 'JMD',
            'venue_name' => 'Kingston Pier',
            'venue_address' => 'Kingston',
            'organizer_name' => 'Flow Org',
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
        HostPaymentPolicy::create([
            'organizer_id' => $this->organizer->id,
            'enable_cash' => true,
            'bank_transfer_instructions' => "Bank: Example Bank\nAccount: 000-000",
        ]);
        $this->buyer = User::create(['name' => 'Flow Buyer', 'email' => 'flow@example.test', 'phone' => '+18765550123', 'password' => bcrypt('password'), 'status' => 'active']);
    }

    private function fillCart(): void
    {
        $this->actingAs($this->buyer)
            ->post(route('checkout.tickets.save', $this->event), ['tiers' => ['tier-ga' => 2, 'tier-vip' => 3]])
            ->assertRedirect(route('checkout.food', $this->event));
        $this->actingAs($this->buyer)
            ->post(route('checkout.food.save', $this->event), ['items' => [
                $this->dish->id => ['quantity' => 1],
                $this->plate->id => ['quantity' => 1, 'choices' => ['size' => 1]],
            ]])
            ->assertRedirect(route('checkout.review', $this->event));
    }

    // 2 × 2000 + 1500 + (1200 + 300) + 100 packaging
    private const EXPECTED_TOTAL = 7100.0;

    public function test_guests_must_sign_in(): void
    {
        $this->get(route('checkout.tickets', $this->event))->assertRedirect(route('login'));
    }

    public function test_full_flow_places_one_order_and_shows_a_scannable_ticket(): void
    {
        $this->actingAs($this->buyer)->get(route('checkout.tickets', $this->event))->assertOk()
            ->assertSee('General Admission')->assertSee('J$2,000.00');
        $this->fillCart();

        $this->actingAs($this->buyer)->get(route('checkout.review', $this->event))->assertOk()
            ->assertSee('2 × General Admission')
            ->assertSee('Size: Large')
            ->assertSee('Packaging fee')
            ->assertSee('J$7,100.00')
            ->assertSee('Bank transfer')->assertSee('Cash at pickup')
            ->assertDontSee('Zelle');

        $response = $this->actingAs($this->buyer)->post(route('checkout.place', $this->event), [
            'customer_phone' => '+1 876 555 0123', 'method' => 'bank', 'quoted_total_minor' => 710000,
        ]);

        $order = Order::allTenants()->with('items')->sole();
        $response->assertRedirect(route('tickets.show', $order));
        $this->assertEquals(self::EXPECTED_TOTAL, (float) $order->total_amount);
        $this->assertSame('awaiting_payment', $order->status);
        $this->assertNotNull($order->idempotency_key);
        $this->assertSame(3, $order->items->count());
        $this->assertSame(48, (int) Event::allTenants()->find($this->event->id)->ticket_tiers[0]['quantity_available']);

        $this->actingAs($this->buyer)->get(route('tickets.show', $order))->assertOk()
            ->assertSee('Waiting for your payment')
            ->assertSee($order->ticket_code)
            ->assertSee('<svg', false)
            ->assertSee('Account: 000-000')
            ->assertSee('I’ve sent the payment')
            ->assertDontSee($order->secure_ticket_token);

        // The cart is cleared: posting again cannot create a second order.
        $this->actingAs($this->buyer)->post(route('checkout.place', $this->event), [
            'customer_phone' => '+1 876 555 0123', 'method' => 'bank', 'quoted_total_minor' => 710000,
        ])->assertRedirect(route('checkout.tickets', $this->event))->assertSessionHas('error');
        $this->assertSame(1, Order::allTenants()->count());
    }

    public function test_changed_price_is_shown_before_placing(): void
    {
        $this->fillCart();
        $this->dish->forceFill(['price_jmd' => 1700])->save();

        $this->actingAs($this->buyer)->from(route('checkout.review', $this->event))->post(route('checkout.place', $this->event), [
            'customer_phone' => '+1 876 555 0123', 'method' => 'cash', 'quoted_total_minor' => 710000,
        ])->assertRedirect(route('checkout.review', $this->event))->assertSessionHas('error');

        $this->assertSame(0, Order::allTenants()->count());
        $this->actingAs($this->buyer)->get(route('checkout.review', $this->event))->assertSee('J$7,300.00');
    }

    public function test_item_selling_out_mid_checkout_blocks_the_order_with_a_reason(): void
    {
        $this->fillCart();
        $this->dish->forceFill(['available_stock' => 0])->save();

        $this->actingAs($this->buyer)->get(route('checkout.review', $this->event))->assertOk()
            ->assertSee('Escovitch Fish is sold out.');
        $this->actingAs($this->buyer)->from(route('checkout.review', $this->event))->post(route('checkout.place', $this->event), [
            'customer_phone' => '+1 876 555 0123', 'method' => 'cash', 'quoted_total_minor' => 710000,
        ])->assertSessionHas('error');
        $this->assertSame(0, Order::allTenants()->count());
    }

    public function test_only_methods_the_host_set_up_are_accepted(): void
    {
        $this->fillCart();
        $this->actingAs($this->buyer)->post(route('checkout.place', $this->event), [
            'customer_phone' => '+1 876 555 0123', 'method' => 'zelle', 'quoted_total_minor' => 710000,
        ])->assertSessionHasErrors('method');

        HostPaymentPolicy::where('organizer_id', $this->organizer->id)->update(['bank_transfer_instructions' => null, 'enable_cash' => true]);
        $this->actingAs($this->buyer)->get(route('checkout.review', $this->event))->assertDontSee('Bank transfer')->assertSee('Cash at pickup');
        $this->assertSame(0, Order::allTenants()->count());
    }

    public function test_paused_and_unpublished_events_cannot_be_ordered(): void
    {
        $this->event->forceFill(['is_ordering_open' => false])->save();
        $this->actingAs($this->buyer)->get(route('checkout.tickets', $this->event))
            ->assertRedirect(route('events.show', $this->event->id))->assertSessionHas('error');

        $this->event->forceFill(['is_ordering_open' => true, 'status' => 'unpublished'])->save();
        $this->actingAs($this->buyer)->get(route('checkout.tickets', $this->event))->assertNotFound();
        $this->actingAs($this->buyer)->post(route('checkout.place', $this->event), ['method' => 'cash'])->assertNotFound();
    }

    public function test_empty_or_expired_cart_starts_over(): void
    {
        $this->actingAs($this->buyer)->get(route('checkout.review', $this->event))
            ->assertRedirect(route('checkout.tickets', $this->event))->assertSessionHas('error');
    }

    public function test_tickets_are_private_to_their_owner(): void
    {
        $this->fillCart();
        $this->actingAs($this->buyer)->post(route('checkout.place', $this->event), [
            'customer_phone' => '+1 876 555 0123', 'method' => 'bank', 'quoted_total_minor' => 710000,
        ]);
        $order = Order::allTenants()->sole();
        $stranger = User::create(['name' => 'Other', 'email' => 'other@example.test', 'password' => bcrypt('password'), 'status' => 'active']);

        $this->app['auth']->forgetGuards(); // signed out
        $this->get(route('tickets.show', $order))->assertRedirect(route('login'));

        $this->actingAs($stranger)->get(route('tickets.show', $order))->assertNotFound();
        $this->actingAs($stranger)->post(route('tickets.payment-reference', $order), ['payment_reference' => 'X123'])->assertNotFound();
    }

    public function test_customer_reference_marks_confirmation_submitted_not_paid(): void
    {
        $this->fillCart();
        $this->actingAs($this->buyer)->post(route('checkout.place', $this->event), [
            'customer_phone' => '+1 876 555 0123', 'method' => 'bank', 'quoted_total_minor' => 710000,
        ]);
        $order = Order::allTenants()->sole();

        $this->actingAs($this->buyer)->post(route('tickets.payment-reference', $order), ['payment_reference' => ''])
            ->assertSessionHasErrors('payment_reference');
        $this->actingAs($this->buyer)->post(route('tickets.payment-reference', $order), ['payment_reference' => 'EXB-55501'])
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('payment_submitted', $order->status);
        $this->assertSame('EXB-55501', $order->payment_reference);
        $this->assertEquals(0, (float) $order->amount_paid);
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'payment_reference_submitted', 'target_id' => $order->id]);
        $this->actingAs($this->buyer)->get(route('tickets.show', $order))->assertSee('Payment being checked');
    }

    public function test_cash_orders_have_no_transfer_reference(): void
    {
        $this->fillCart();
        $this->actingAs($this->buyer)->post(route('checkout.place', $this->event), [
            'customer_phone' => '+1 876 555 0123', 'method' => 'cash', 'quoted_total_minor' => 710000,
        ]);
        $order = Order::allTenants()->sole();

        $this->actingAs($this->buyer)->get(route('tickets.show', $order))->assertSee('Pay at pickup')->assertDontSee('I’ve sent the payment');
        $this->actingAs($this->buyer)->post(route('tickets.payment-reference', $order), ['payment_reference' => 'X1234'])->assertSessionHas('error');
        $this->assertSame('awaiting_payment', $order->fresh()->status);
    }

    public function test_refunded_ticket_hides_the_qr_code(): void
    {
        $this->fillCart();
        $this->actingAs($this->buyer)->post(route('checkout.place', $this->event), [
            'customer_phone' => '+1 876 555 0123', 'method' => 'bank', 'quoted_total_minor' => 710000,
        ]);
        $order = Order::allTenants()->sole();
        $order->forceFill(['status' => 'refunded'])->save();

        $this->actingAs($this->buyer)->get(route('tickets.show', $order))->assertOk()
            ->assertSee('Refunded')->assertDontSee('<svg xmlns', false);
    }

    public function test_event_page_and_wallet_use_real_data(): void
    {
        $this->actingAs($this->buyer)->get(route('events.show', $this->event->id))->assertOk()
            ->assertSee('General Admission')
            ->assertSee('J$2,000.00')
            ->assertSee(route('checkout.tickets', $this->event), false)
            ->assertDontSee('$15.00')
            ->assertDontSee('VIP Waterfront Lounge')
            ->assertDontSee('preorder-modal', false);

        $this->fillCart();
        $this->actingAs($this->buyer)->post(route('checkout.place', $this->event), [
            'customer_phone' => '+1 876 555 0123', 'method' => 'bank', 'quoted_total_minor' => 710000,
        ]);
        $order = Order::allTenants()->sole();

        $this->actingAs($this->buyer)->get(route('attendee.dashboard'))->assertOk()
            ->assertSee(route('tickets.show', $order), false)
            ->assertSee('J$7,100.00')
            ->assertDontSee('QR Code Simulated', false);
    }

    public function test_qr_code_rejects_content_it_cannot_encode(): void
    {
        $this->assertCount(29, QrCode::matrix('tok_' . str_repeat('a', 32)));
        $this->assertStringContainsString('role="img"', QrCode::svg('SFF-123456'));
        $this->expectException(\InvalidArgumentException::class);
        QrCode::matrix(str_repeat('x', 106));
    }
}
