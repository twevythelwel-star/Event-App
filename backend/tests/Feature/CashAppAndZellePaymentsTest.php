<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\HostPaymentPolicy;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Organizer;
use App\Models\SystemPlatformSetting;
use App\Models\User;
use App\Payments\PaymentMethods;
use App\Services\Checkout\PaymentOptions;
use App\Support\PaymentStatusPresenter;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Payment methods are Cash App, Zelle and cash in person. The host enters their $Cashtag and Zelle email or
 * phone; the buyer sees them at checkout, pays, submits a confirmation number, and the order waits for the
 * host to confirm. Bank transfer is retired: never offered, never accepted, older orders stay readable.
 */
class CashAppAndZellePaymentsTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizer;
    private Event $event;
    private User $buyer;

    // 2 × $20 tickets + $10 dish
    private const TOTAL_MINOR = 5000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizer = Organizer::create(['name' => 'Pay Org', 'slug' => 'pay-org']);
        $this->event = Event::allTenants()->create([
            'organizer_id' => $this->organizer->id,
            'slug' => 'pay-night',
            'title' => 'Pay Night',
            'date_str' => 'Saturday, November 28, 2026',
            'time_str' => '6:00 PM - 11:00 PM',
            'currency' => 'USD',
            'venue_name' => 'Harbor Hall',
            'venue_address' => 'Miami',
            'organizer_name' => 'Pay Org',
            'organizer_phone' => '+13055550000',
            'advance_order_deadline' => now()->addWeek(),
            'status' => 'published',
            'is_ordering_open' => true,
            'ticket_tiers' => [
                ['id' => 'tier-ga', 'name' => 'General Admission', 'price_usd' => 20, 'quantity_available' => 50, 'max_per_order' => 4],
            ],
        ]);
        MenuItem::allTenants()->create([
            'organizer_id' => $this->organizer->id, 'event_id' => $this->event->id, 'name' => 'Fried Fish',
            'category' => 'Mains', 'pricing_type' => 'fixed', 'price_usd' => 10, 'price_jmd' => 1500,
            'available_stock' => 10, 'is_active' => true,
        ]);
        HostPaymentPolicy::create([
            'organizer_id' => $this->organizer->id,
            'enable_cash' => true,
            'enable_cashapp' => true,
            'cash_app_cashtag' => 'PayOrgDemo',
            'cash_app_instructions' => 'Put your order code in the Cash App note.',
            'enable_zelle' => true,
            'zelle_recipient' => 'Pay@Pay-Org.example',
            'zelle_instructions' => 'Put your order code in the Zelle memo.',
            // Set up before bank transfer was retired: must never be offered again.
            'enable_bank' => true,
            'bank_transfer_instructions' => "Bank: Example Bank\nAccount: 000-000",
        ]);
        $this->buyer = User::create(['name' => 'Pay Buyer', 'email' => 'pay@example.test', 'phone' => '+13055550123', 'password' => bcrypt('password'), 'status' => 'active']);
    }

    private function fillCart(): void
    {
        $dish = MenuItem::allTenants()->where('event_id', $this->event->id)->sole();
        $this->actingAs($this->buyer)
            ->post(route('checkout.tickets.save', $this->event), ['tiers' => ['tier-ga' => 2]])
            ->assertRedirect(route('checkout.food', $this->event));
        $this->actingAs($this->buyer)
            ->post(route('checkout.food.save', $this->event), ['items' => [$dish->id => ['quantity' => 1]]])
            ->assertRedirect(route('checkout.review', $this->event));
    }

    private function place(string $method, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->buyer)->post(route('checkout.place', $this->event), [
            'customer_phone' => '+1 305 555 0123', 'method' => $method, 'quoted_total_minor' => self::TOTAL_MINOR,
        ] + $extra);
    }

    private function policy(): HostPaymentPolicy
    {
        return HostPaymentPolicy::where('organizer_id', $this->organizer->id)->firstOrFail();
    }

    public function test_checkout_offers_exactly_cash_app_zelle_and_cash_with_the_hosts_handles(): void
    {
        $this->assertSame(['zelle', 'cashapp', 'cash'], array_column(PaymentOptions::forEvent($this->event), 'value'));

        $this->fillCart();
        $page = $this->actingAs($this->buyer)->get(route('checkout.review', $this->event))->assertOk()
            ->assertSee('Cash App')->assertSee('$PayOrgDemo')->assertSee('Put your order code in the Cash App note.')
            ->assertSee('Zelle')->assertSee('pay@pay-org.example')->assertSee('Put your order code in the Zelle memo.')
            ->assertSee('Cash at pickup')
            ->assertDontSee('Bank transfer')->assertDontSee('Account: 000-000');

        preg_match_all('/name="method" value="([^"]+)"/', $page->getContent(), $offered);
        $this->assertSame(['zelle', 'cashapp', 'cash'], $offered[1]);
    }

    public function test_only_the_methods_the_host_enabled_and_set_up_are_offered(): void
    {
        $this->policy()->forceFill(['enable_cashapp' => false])->save();
        $this->assertSame(['zelle', 'cash'], array_column(PaymentOptions::forEvent($this->event), 'value'));

        // Zelle switched on but with no email or phone to send to: not offered.
        $this->policy()->forceFill(['zelle_recipient' => null, 'enable_cash' => false])->save();
        $this->assertSame([], PaymentOptions::forEvent($this->event));
        $this->assertSame('The organizer has not added their Zelle email or phone.', PaymentMethods::evaluate($this->event)['zelle']['reason']);

        $this->fillCart();
        $this->place('zelle')->assertSessionHasErrors('method');
        $this->place('cashapp')->assertSessionHasErrors('method');
        $this->assertSame(0, Order::allTenants()->count());
    }

    public function test_cash_app_and_zelle_are_not_offered_for_events_priced_outside_us_dollars(): void
    {
        $this->event->forceFill(['currency' => 'JMD'])->save();

        $this->assertSame(['cash'], array_column(PaymentOptions::forEvent($this->event), 'value'));
    }

    public function test_bank_transfer_is_never_offered_or_accepted(): void
    {
        $this->assertNotContains('bank', PaymentMethods::offerable());
        $this->assertArrayNotHasKey('bank', PaymentMethods::evaluate($this->event));

        $this->fillCart();
        $this->place('bank')->assertSessionHasErrors('method');

        // The order API refuses it too.
        $this->actingAs($this->buyer)->postJson('/api/v1/events/' . $this->event->slug . '/orders', [
            'customer_name' => 'Pay Buyer', 'customer_phone' => '+1 305 555 0123', 'method' => 'bank', 'currency' => 'USD',
            'items' => [['menu_item_id' => 'tier-ga', 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('method');

        $this->assertSame(0, Order::allTenants()->count());

        // A platform that had allowed bank transfer can't be set to allow it again.
        $this->assertFalse((new HostPaymentPolicy())->canEnableMethod('bank', 'US'));
        $this->assertFalse((new HostPaymentPolicy())->canEnableMethod('bank_transfer', 'US'));
    }

    public function test_a_cash_app_order_waits_for_the_host_after_the_buyer_sends_a_confirmation(): void
    {
        $this->fillCart();
        $this->place('cashapp')->assertSessionHasNoErrors();
        $order = Order::allTenants()->sole();

        $this->assertSame('cashapp', $order->method);
        $this->assertSame('awaiting_payment', $order->status);
        $this->assertFalse((bool) $order->authorized_unpaid);
        $this->assertNotNull($order->reservation_expires_at, 'an unpaid Cash App order is held only briefly');

        $this->actingAs($this->buyer)->get(route('tickets.show', $order))->assertOk()
            ->assertSee('How to pay (Cash App)')
            ->assertSee('$PayOrgDemo')
            ->assertSee('Put your order code in the Cash App note.')
            ->assertSee('Payment confirmation number')
            ->assertSee('I’ve sent the payment')
            ->assertDontSee('QR code for ticket', false);

        $this->actingAs($this->buyer)->post(route('tickets.payment-reference', $order), ['payment_reference' => ''])
            ->assertSessionHasErrors('payment_reference');
        $this->actingAs($this->buyer)->post(route('tickets.payment-reference', $order), ['payment_reference' => 'CASHAPP-77812'])
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('payment_submitted', $order->status);
        $this->assertSame('CASHAPP-77812', $order->payment_reference);
        $this->assertSame(0, (int) $order->amount_paid_minor, 'a confirmation number is not proof the money arrived');
        $this->actingAs($this->buyer)->get(route('tickets.show', $order))->assertSee('Payment being checked');
    }

    public function test_a_zelle_order_placed_with_its_confirmation_goes_straight_to_the_hosts_check(): void
    {
        $this->fillCart();
        $this->place('zelle', ['payment_reference' => 'ZELLE-55120', 'sender_name' => 'Pay Buyer'])->assertSessionHasNoErrors();
        $order = Order::allTenants()->sole();

        $this->assertSame('zelle', $order->method);
        $this->assertSame('payment_submitted', $order->status);
        $this->assertSame(0, (int) $order->amount_paid_minor);

        $this->actingAs($this->buyer)->get(route('tickets.show', $order))->assertOk()
            ->assertSee('pay@pay-org.example')
            ->assertSee('Payment being checked');
    }

    public function test_the_host_confirms_a_zelle_payment_from_the_demo_seed(): void
    {
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
        $attendee = User::where('email', 'attendee@islandevents.example')->firstOrFail();
        $host = User::where('email', 'host@islandevents.example')->firstOrFail();

        $id = $this->actingAs($attendee)->postJson('/api/v1/events/south-florida-jamaican-fish-fry/orders', [
            'customer_name' => 'Zelle Guest', 'customer_phone' => '305 555 0123', 'method' => 'zelle', 'currency' => 'USD',
            'payment_reference' => 'ZELLE-DEMO-1', 'items' => [['menu_item_id' => 'kgn-fried-chicken', 'quantity' => 1]],
        ])->assertCreated()->json('data.id');
        $order = Order::withoutGlobalScopes()->findOrFail($id);
        $this->assertSame('payment_submitted', $order->status);

        $this->actingAs($host)->postJson("/api/v1/operations/orders/{$order->id}/payment", ['status' => 'paid'])->assertOk();

        $order->refresh();
        $this->assertSame('paid', $order->status);
        $this->assertSame((int) $order->total_minor, (int) $order->amount_paid_minor);
        $this->assertSame('Zelle', PaymentStatusPresenter::methodLabel($order->method));
    }

    public function test_demo_host_offers_cash_app_zelle_and_cash_with_clearly_fake_handles(): void
    {
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);

        $policy = HostPaymentPolicy::where('organizer_id', 'org-kingston-youth')->firstOrFail();
        $this->assertFalse((bool) $policy->enable_bank);
        $this->assertStringStartsWith('$DEMO', $policy->cash_app_cashtag);
        $this->assertStringEndsWith('.example', $policy->zelle_recipient);

        $event = Event::withoutGlobalScopes()->findOrFail('evt-kingston-harbour-fry');
        $this->assertSame(['zelle', 'cashapp', 'cash'], array_column(PaymentOptions::forEvent($event), 'value'));
    }

    public function test_the_host_sets_up_cash_app_and_zelle_on_the_settings_page(): void
    {
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
        $host = User::where('email', 'host@islandevents.example')->firstOrFail();

        $this->actingAs($host)->get('/settings')->assertOk()
            ->assertSee('Your $Cashtag')->assertSee('Zelle email or U.S. mobile number')
            ->assertDontSee('Bank transfer instructions');

        $form = ['enable_cash' => '1', 'enable_cashapp' => '1', 'cash_app_cashtag' => 'KingstonYouthDemo',
            'enable_zelle' => '1', 'zelle_recipient' => '(305) 555-0142', 'refund_policy_mode' => 'manual_full_only'];
        $this->actingAs($host)->post('/settings/host', $form)->assertSessionHasNoErrors()->assertSessionHas('status');

        $policy = HostPaymentPolicy::where('organizer_id', 'org-kingston-youth')->firstOrFail();
        $this->assertTrue($policy->enable_cashapp);
        $this->assertSame('$KingstonYouthDemo', $policy->cash_app_cashtag, 'the $ is added when the host leaves it off');
        $this->assertTrue($policy->enable_zelle);
        $this->assertSame('(305) 555-0142', $policy->zelle_recipient);

        // A switched-on method needs its handle, and the handle must look right.
        $this->actingAs($host)->post('/settings/host', ['cash_app_cashtag' => ''] + $form)->assertSessionHasErrors('cash_app_cashtag');
        $this->actingAs($host)->post('/settings/host', ['cash_app_cashtag' => '$12 3'] + $form)->assertSessionHasErrors('cash_app_cashtag');
        $this->actingAs($host)->post('/settings/host', ['zelle_recipient' => 'not an email'] + $form)->assertSessionHasErrors('zelle_recipient');
        $this->actingAs($host)->post('/settings/host', ['zelle_recipient' => '555-01'] + $form)->assertSessionHasErrors('zelle_recipient');

        // Switching a method off is allowed without its handle; bank transfer can't be set up at all.
        $this->actingAs($host)->post('/settings/host', ['enable_cashapp' => '0', 'cash_app_cashtag' => ''] + $form)->assertSessionHasNoErrors();
        $this->assertFalse($policy->fresh()->enable_cashapp);
        $this->assertNull($policy->fresh()->cash_app_cashtag);
        $this->actingAs($host)->post('/settings/host', ['bank_transfer_instructions' => 'Bank: Example'] + $form)
            ->assertSessionHasErrors('bank_transfer_instructions');
    }

    public function test_the_host_payment_settings_api_configures_cash_app_and_zelle_but_not_bank_transfer(): void
    {
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
        $host = User::where('email', 'host@islandevents.example')->firstOrFail();

        $settings = $this->actingAs($host)->getJson('/api/v1/host/payment-settings')->assertOk()->json('settings');
        $this->assertArrayHasKey('cash_app_cashtag', $settings);
        $this->assertArrayHasKey('zelle_recipient', $settings);
        $this->assertArrayNotHasKey('enable_bank', $settings);
        $this->assertArrayNotHasKey('bank_transfer_instructions', $settings);

        $this->actingAs($host)->putJson('/api/v1/host/payment-settings', ['enable_zelle' => true, 'zelle_recipient' => 'Tickets@Kingston-Youth.example', 'cash_app_cashtag' => '$KY_Demo'])
            ->assertOk();
        $policy = HostPaymentPolicy::where('organizer_id', 'org-kingston-youth')->firstOrFail();
        $this->assertSame('tickets@kingston-youth.example', $policy->zelle_recipient);
        $this->assertSame('$KY_Demo', $policy->cash_app_cashtag);

        $this->actingAs($host)->putJson('/api/v1/host/payment-settings', ['enable_bank' => true])->assertUnprocessable()->assertJsonValidationErrors('enable_bank');
        $this->actingAs($host)->putJson('/api/v1/host/payment-settings', ['bank_transfer_instructions' => 'Bank: Example'])->assertUnprocessable();
        $this->actingAs($host)->putJson('/api/v1/host/payment-settings', ['enable_cashapp' => true, 'cash_app_cashtag' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('cash_app_cashtag');
        $this->assertFalse((bool) $policy->fresh()->enable_bank);
    }

    public function test_an_existing_bank_transfer_order_stays_readable(): void
    {
        $order = Order::allTenants()->create([
            'organizer_id' => $this->organizer->id, 'event_id' => $this->event->id, 'user_id' => $this->buyer->id,
            'ticket_code' => 'SFF-OLD001', 'order_number' => 'ORD-2026-100001', 'customer_name' => 'Pay Buyer',
            'customer_phone' => '+13055550123', 'method' => 'bank', 'payment_reference' => 'ACH-OLD-1',
            'subtotal' => 20, 'total_amount' => 20, 'amount_paid' => 20, 'currency' => 'USD', 'status' => 'paid',
            'secure_ticket_token' => 'tok_old_bank_order_0001',
        ]);

        $this->assertSame('Bank transfer', PaymentStatusPresenter::methodLabel('bank'));
        $this->assertSame('Bank transfer', PaymentMethods::label('bank'));
        $this->actingAs($this->buyer)->get(route('tickets.show', $order))->assertOk()
            ->assertSee('by Bank transfer')
            ->assertSee('your reference ACH-OLD-1');
    }

    public function test_the_migration_retires_bank_transfer_and_keeps_handles_written_in_old_instructions(): void
    {
        $other = Organizer::create(['name' => 'Old Org', 'slug' => 'old-org']);
        DB::table('host_payment_policies')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(), 'organizer_id' => $other->id, 'enable_cash' => true, 'enable_bank' => true,
            'cash_app_instructions' => 'Send $5 deposits to $OldOrgTickets with your order code.',
            'zelle_instructions' => 'Zelle tickets@old-org.example',
            'bank_transfer_instructions' => 'Bank: Example', 'created_at' => now(), 'updated_at' => now(),
        ]);
        SystemPlatformSetting::create(['supported_currencies' => ['USD'], 'supported_payment_methods' => ['bank', 'cash']]);

        $migration = require database_path('migrations/2026_11_13_000072_add_cash_app_and_zelle_handles_retire_bank_transfer.php');
        $migration->up();

        $row = DB::table('host_payment_policies')->where('organizer_id', $other->id)->first();
        $this->assertSame(0, (int) $row->enable_bank);
        $this->assertSame('$OldOrgTickets', $row->cash_app_cashtag, 'a dollar amount is not mistaken for a $Cashtag');
        $this->assertSame('tickets@old-org.example', $row->zelle_recipient);
        $this->assertSame('Bank: Example', $row->bank_transfer_instructions, 'old details are kept, just never offered');
        $this->assertEqualsCanonicalizing(['cash', 'zelle', 'cashapp'], SystemPlatformSetting::first()->supported_payment_methods);
    }
}
