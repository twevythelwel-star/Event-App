<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletPass;
use App\Services\OrderPlacementService;
use App\Services\Wallet\Jose;
use App\Services\Wallet\WalletPassManager;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\WalletTestCertificates;
use Tests\Support\WalletTestKeys;
use Tests\TestCase;

/**
 * The customer's wallet flow on the ticket page: which "Add to …" buttons show (only providers the central decision
 * makes available for this holder and ticket), the success and failure messages around saving, the EventBite ticket
 * as the fallback, and that saving a pass never marks a ticket paid or admitted.
 */
class WalletCustomerFlowTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = 'evt-kingston-harbour-fry';

    private WalletTestKeys $keys;

    private ?WalletTestCertificates $certs = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
        $this->keys = new WalletTestKeys();
        config(['wallet.apple.enabled' => false, 'wallet.google.enabled' => false, 'wallet.samsung.enabled' => false,
            'wallet.tester_emails' => '', 'app.url' => 'https://tickets.example.test']);
        Event::allTenants()->findOrFail(self::EVENT)->forceFill([
            'starts_at' => now()->addDays(5)->setTime(22, 0), 'ends_at' => now()->addDays(6)->setTime(3, 0),
            'event_date_iso' => now()->addDays(5)->toDateString(), 'status' => 'published',
        ])->save();
    }

    protected function tearDown(): void
    {
        $this->keys->cleanUp();
        $this->certs?->cleanUp();
        parent::tearDown();
    }

    private function attendee(): User
    {
        return User::where('email', 'attendee@islandevents.example')->firstOrFail();
    }

    private function order(string $status = 'paid'): Order
    {
        $item = MenuItem::withoutGlobalScopes()->where('event_id', self::EVENT)->where('is_active', true)->firstOrFail();
        $result = app(OrderPlacementService::class)->placeOrder(Event::allTenants()->findOrFail(self::EVENT), [
            'customer_name' => 'Wallet Holder', 'customer_phone' => '+1 305 555 0142', 'method' => 'cash',
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ], $this->attendee());
        $this->assertTrue($result['success'], $result['error'] ?? 'order placed');
        $order = $result['order']->fresh();
        if ($status === 'paid') {
            $order->forceFill(['status' => 'paid', 'amount_paid_minor' => $order->total_minor])->save();
        }

        return $order->fresh();
    }

    private function enableApple(): void
    {
        $this->certs ??= new WalletTestCertificates();
        config($this->certs->config());
    }

    private function fakeGoogle(array $overrides = []): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake($overrides + [
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.flow-test-token', 'expires_in' => 3600]),
            'walletobjects.googleapis.com/*' => Http::response(['ok' => true]),
        ]);
    }

    /** @return list<string> provider keys with a button on the ticket page */
    private function buttons(Order $order): array
    {
        $html = $this->actingAs($this->attendee())->get(route('tickets.show', $order))->assertOk()->getContent();
        preg_match_all('/<a [^>]*data-wallet-provider="([a-z]+)"/', $html, $m);

        return $m[1];
    }

    public function test_each_button_shows_only_when_its_provider_is_available_for_this_ticket(): void
    {
        $order = $this->order();

        // Nothing configured: no wallet buttons, and the EventBite ticket is offered as the way in.
        $this->assertSame([], $this->buttons($order));
        $this->get(route('tickets.show', $order))->assertSee('No wallet app? You don’t need one', false)
            ->assertSee('Print ticket')->assertDontSee('Add to Apple Wallet')->assertDontSee('Add to Google Wallet')->assertDontSee('Add to Samsung Wallet');

        $this->enableApple();
        $this->assertSame(['apple'], $this->buttons($order));

        config($this->keys->googleConfig(approved: false));
        $this->assertSame(['apple'], $this->buttons($order), 'Google in demo mode is not shown to customers.');
        config($this->keys->googleConfig());
        $this->assertSame(['apple', 'google'], $this->buttons($order));

        config($this->keys->samsungConfig(approved: false, verified: false));
        $this->assertSame(['apple', 'google'], $this->buttons($order), 'Samsung awaiting approval is not shown.');
        config($this->keys->samsungConfig());
        $this->assertSame(['apple', 'google', 'samsung'], $this->buttons($order));
        $this->get(route('tickets.show', $order))->assertSee('Add to Apple Wallet')->assertSee('Add to Google Wallet')->assertSee('Add to Samsung Wallet')
            ->assertSee(route('tickets.wallet', $order), false)
            ->assertSee(route('tickets.wallet.add', [$order, 'google']), false)
            ->assertSee(route('tickets.wallet.add', [$order, 'samsung']), false);

        // Every provider on, but the ticket isn't valid for entry: no buttons at all.
        $this->assertSame([], $this->buttons($this->order('pending')));
        $order->fresh()->forceFill(['checked_in_at' => now()])->save();
        $this->assertSame([], $this->buttons($order->fresh()));
        $refunded = $this->order();
        $refunded->forceFill(['status' => 'refunded'])->save();
        $this->assertSame([], $this->buttons($refunded->fresh()));

        // Someone else's ticket: not found, and no way to add it.
        $stranger = User::where('id', '!=', $this->attendee()->id)->where('email', 'like', '%@islandevents.example')->firstOrFail();
        $this->actingAs($stranger)->get(route('tickets.wallet.add', [$this->order(), 'google']))->assertNotFound();
    }

    public function test_official_badges_are_used_once_installed(): void
    {
        $this->enableApple();
        $order = $this->order();
        $badge = 'images/wallet/test-apple-badge-' . bin2hex(random_bytes(3)) . '.svg';
        @mkdir(public_path('images/wallet'), 0755, true);
        file_put_contents(public_path($badge), '<svg xmlns="http://www.w3.org/2000/svg"/>');
        try {
            config(['wallet.badges.apple' => $badge]);
            $this->actingAs($this->attendee())->get(route('tickets.show', $order))->assertOk()
                ->assertSee('alt="Add to Apple Wallet"', false)->assertSee(asset($badge), false);
        } finally {
            @unlink(public_path($badge));
        }
    }

    public function test_customer_flow_from_ticket_to_wallet_and_back_with_success_messages(): void
    {
        config($this->keys->googleConfig());
        config($this->keys->samsungConfig());
        $this->fakeGoogle();
        $order = $this->order();
        $this->actingAs($this->attendee());

        // My tickets → the ticket → the wallet buttons.
        $this->get(route('tickets.collection'))->assertOk()->assertSee(route('tickets.show', $order), false);
        $this->get(route('tickets.show', $order))->assertOk()->assertSee('Save to your phone');

        // Google: off to Google's save page; back on the ticket, a clear message and a pending status.
        $this->get(route('tickets.wallet.add', [$order, 'google']))->assertRedirect()
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'sent to Google Wallet'));
        $this->get(route('tickets.show', $order))->assertOk()->assertSee('Your ticket was sent to Google Wallet')
            ->assertSee('data-wallet-state="pending"', false);

        // Google confirms it was saved: the ticket page says so.
        $this->fakeGoogle(['walletobjects.googleapis.com/*' => Http::response(['hasUsers' => true])]);
        app(WalletPassManager::class)->confirmSaved(WalletPass::where('provider', 'google')->firstOrFail());
        $this->get(route('tickets.show', $order))->assertSee('Saved in your Google Wallet')
            ->assertSee('data-wallet-status="google" data-wallet-state="saved"', false);

        // Samsung: saved only once Samsung reports ADDED.
        $this->get(route('tickets.wallet.add', [$order, 'samsung']))->assertRedirect();
        $pass = WalletPass::where('provider', 'samsung')->firstOrFail();
        $this->get(route('tickets.show', $order))->assertSee('data-wallet-status="samsung" data-wallet-state="pending"', false);
        $path = '/wallet/samsung/cards/' . WalletTestKeys::CARD_ID . '/' . $pass->external_id;
        $token = Jose::signRs256(['cty' => 'AUTH', 'ver' => '3', 'partnerId' => WalletTestKeys::PARTNER_ID, 'utc' => (int) (microtime(true) * 1000)],
            ['API' => ['method' => 'POST', 'path' => $path]], openssl_pkey_get_private($this->keys->samsungKeyPem));
        $this->post($path . '?cc2=US&event=ADDED', [], ['Authorization' => 'Bearer ' . $token])->assertOk();
        $this->get(route('tickets.show', $order))->assertSee('Saved in your Samsung Wallet');
    }

    public function test_a_failed_save_says_so_and_the_eventbite_ticket_still_works(): void
    {
        config($this->keys->googleConfig());
        $this->fakeGoogle(['walletobjects.googleapis.com/*' => Http::response(['error' => 'unavailable'], 503)]);
        $order = $this->order();

        $this->actingAs($this->attendee())->get(route('tickets.wallet.add', [$order, 'google']))
            ->assertRedirect(route('tickets.show', $order))
            ->assertSessionHas('error', 'We couldn’t reach Google Wallet just now. Please try again in a minute.');
        $this->get(route('tickets.show', $order))->assertOk()
            ->assertSee('We couldn’t reach Google Wallet just now', false)
            ->assertSee('data-wallet-state="failed"', false)
            ->assertSee('QR code for ticket ' . $order->ticket_code, false)
            ->assertSee('Add to Google Wallet');
        $this->assertNull(WalletPass::where('provider', 'google')->firstOrFail()->saved_at);

        // An unpaid ticket is refused with the reason, and nothing is created.
        $unpaid = $this->order('pending');
        $this->get(route('tickets.wallet.add', [$unpaid, 'google']))->assertRedirect(route('tickets.show', $unpaid))
            ->assertSessionHas('error', 'Your ticket can be added to Google Wallet once the payment is confirmed.');
        $this->assertSame(0, WalletPass::where('order_id', $unpaid->id)->count());
    }

    public function test_saving_a_pass_never_marks_a_ticket_paid_or_admitted(): void
    {
        $this->enableApple();
        config($this->keys->googleConfig());
        config($this->keys->samsungConfig());
        $this->fakeGoogle(['walletobjects.googleapis.com/*' => Http::response(['hasUsers' => true])]);
        $watched = ['status', 'amount_paid_minor', 'checked_in_at', 'token_revoked_at', 'token_version', 'secure_ticket_token_hash'];

        // Paid: every provider saves, and the order is exactly as it was: still not checked in.
        $order = $this->order();
        $order->deliverTicketQrToken();
        $before = $order->fresh()->only($watched);
        $this->actingAs($this->attendee());
        $this->get(route('tickets.wallet', $order))->assertOk();
        $this->get(route('tickets.wallet.add', [$order, 'google']))->assertRedirect();
        $this->get(route('tickets.wallet.add', [$order, 'samsung']))->assertRedirect();
        foreach (WalletPass::where('order_id', $order->id)->get() as $pass) {
            app(WalletPassManager::class)->markSaved($pass, 'test');
        }
        $this->assertSame(3, WalletPass::where('order_id', $order->id)->whereNotNull('saved_at')->count());
        $this->assertEquals($before, $order->fresh()->only($watched));
        $this->assertNull($order->fresh()->checked_in_at);

        // Unpaid: no provider will take it, and it stays unpaid.
        $unpaid = $this->order('pending');
        $before = $unpaid->fresh()->only($watched);
        foreach (['tickets.wallet' => [$unpaid], 'tickets.wallet.add' => [$unpaid, 'google']] as $route => $params) {
            $this->get(route($route, $params))->assertRedirect(route('tickets.show', $unpaid));
        }
        $this->get(route('tickets.wallet.add', [$unpaid, 'samsung']))->assertRedirect(route('tickets.show', $unpaid));
        $this->assertEquals($before, $unpaid->fresh()->only($watched));
        $this->assertNotContains($unpaid->fresh()->status, ['paid', 'partially_refunded']);
        $this->assertSame(0, WalletPass::where('order_id', $unpaid->id)->count());
    }
}
