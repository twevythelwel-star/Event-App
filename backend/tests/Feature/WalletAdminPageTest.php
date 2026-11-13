<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Models\UserRole;
use App\Models\WalletPass;
use App\Models\WalletPassEvent;
use App\Services\OrderPlacementService;
use App\Services\Wallet\GoogleWalletService;
use App\Services\Wallet\SamsungWalletService;
use App\Services\Wallet\WalletPassManager;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\WalletTestCertificates;
use Tests\Support\WalletTestKeys;
use Tests\TestCase;

/**
 * The admin wallet page (/admin/console/wallets): only system administrators reach it; it shows each provider's
 * state, totals, errors and history; it warns about missing or expired certificates, bad credentials, an unapproved
 * Samsung integration, failed creations and updates, and provider outages; and it never shows a secret.
 */
class WalletAdminPageTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = 'evt-kingston-harbour-fry';

    private WalletTestKeys $keys;

    /** @var list<WalletTestCertificates> */
    private array $certs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
        $this->keys = new WalletTestKeys();
        config(['wallet.apple.enabled' => false, 'wallet.google.enabled' => false, 'wallet.samsung.enabled' => false,
            'wallet.samsung.partner_approved' => false, 'app.url' => 'https://tickets.example.test']);
    }

    protected function tearDown(): void
    {
        $this->keys->cleanUp();
        foreach ($this->certs as $certs) {
            $certs->cleanUp();
        }
        parent::tearDown();
    }

    private ?User $root = null;

    private function systemAdmin(): User
    {
        if ($this->root !== null) {
            return $this->root;
        }
        $user = User::create(['name' => 'Root Admin', 'email' => 'wallet-root@example.test', 'password' => bcrypt('password'), 'status' => 'active', 'is_system_admin' => true]);
        UserRole::create(['user_id' => $user->id, 'role' => 'system_admin', 'is_active' => true, 'assigned_at' => now()]);

        return $this->root = $user->fresh();
    }

    private function delegatedAdmin(): User
    {
        $user = User::create(['name' => 'Scoped Admin', 'email' => 'wallet-scoped@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        UserRole::create(['user_id' => $user->id, 'role' => 'admin', 'is_active' => true, 'assigned_at' => now()]);
        \App\Models\AdminPermission::create(['user_id' => $user->id, 'scope' => 'all', 'can_manage_events' => true, 'can_moderate_content' => true,
            'can_support_orders' => true, 'can_view_payments' => true, 'can_view_reports' => true, 'can_access_audit_logs' => true, 'assigned_organizer_ids' => []]);

        return $user->fresh();
    }

    private function page()
    {
        return $this->actingAs($this->systemAdmin())->get(route('admin.console.wallets.index'))->assertOk();
    }

    private function pass(string $provider = 'google'): WalletPass
    {
        return WalletPass::create(['order_id' => 'ord-' . Str::random(8), 'user_id' => null, 'provider' => $provider,
            'pass_type_id' => 'x', 'serial_number' => (string) Str::uuid(), 'authentication_token' => Str::random(48),
            'last_modified_at' => now()]);
    }

    private function event(WalletPass $pass, string $action, string $outcome, ?string $code = null, bool $retryable = false, $at = null): void
    {
        $e = WalletPassEvent::create(['wallet_pass_id' => $pass->id, 'provider' => $pass->provider, 'action' => $action,
            'outcome' => $outcome, 'error_code' => $code, 'retryable' => $retryable]);
        if ($at !== null) {
            $e->forceFill(['created_at' => $at])->save();
        }
    }

    public function test_only_system_administrators_can_open_the_wallet_page(): void
    {
        $this->get(route('admin.console.wallets.index'))->assertRedirect();
        $attendee = User::where('email', 'attendee@islandevents.example')->firstOrFail();
        $this->actingAs($attendee)->get(route('admin.console.wallets.index'))->assertForbidden();
        $this->actingAs($this->delegatedAdmin())->get(route('admin.console.wallets.index'))->assertForbidden();

        $this->page()->assertSee('Wallet Passes')->assertSee('Apple Wallet')->assertSee('Google Wallet')->assertSee('Samsung Wallet')
            ->assertSee(route('admin.console.wallets.index'), false);
    }

    public function test_status_totals_last_sync_and_history(): void
    {
        $this->certs[] = $certs = new WalletTestCertificates();
        config($certs->config());
        config($this->keys->googleConfig());
        $saved = $this->pass('google');
        $saved->forceFill(['saved_at' => now(), 'status' => WalletPass::STATUS_SAVED, 'last_synced_at' => now()->subHour()])->save();
        $this->pass('google');
        $this->event($saved, 'create', 'success');
        $this->event($saved, 'saved', 'success');
        $this->event($saved, 'update', 'failure', 'google.http_400');

        $html = $this->page()
            ->assertSee('valid until')->assertSee(WalletTestCertificates::PASS_TYPE_ID)->assertSee(WalletTestKeys::ISSUER_ID)
            ->assertSee('Shown to customers')
            ->assertSee('Partner approval')->assertSee('pending')
            ->assertSee($saved->last_synced_at->format('j M Y H:i'))
            ->assertSee('google.http_400')->assertSee('Pass update history')
            ->getContent();
        $this->assertMatchesRegularExpression('/data-total="successful-google">\s*2</', $html);
        $this->assertMatchesRegularExpression('/data-total="failed-google">\s*1</', $html);
        $this->assertMatchesRegularExpression('/data-total="pending-google">\s*1</', $html);
    }

    public function test_warns_about_missing_and_expired_apple_certificates(): void
    {
        config(['wallet.apple.enabled' => true, 'wallet.apple.pass_type_id' => 'pass.test.x', 'wallet.apple.team_id' => 'ABCDE12345',
            'wallet.apple.certificate_path' => null, 'wallet.apple.certificate_base64' => null]);
        $this->page()->assertSee('The pass certificate is missing');

        // A certificate valid for zero days, seen a minute later.
        $this->certs[] = $expired = new WalletTestCertificates(days: 0);
        config($expired->config());
        $this->travel(2)->minutes();
        $this->page()->assertSee('The pass certificate has expired')->assertSee('expired on');
    }

    public function test_warns_about_invalid_credentials_and_unapproved_samsung(): void
    {
        config($this->keys->googleConfig());
        config(['wallet.google.issuer_id' => 'not-a-number']);
        $this->page()->assertSee('Google Wallet:</strong> Invalid or missing credentials', false);

        config($this->keys->samsungConfig(approved: false, verified: false));
        config(['wallet.samsung.samsung_certificate_base64' => base64_encode('not a certificate')]);
        $this->page()->assertSee('Samsung Wallet:</strong> Invalid or missing credentials', false)
            ->assertSee('The Samsung Wallet integration is not approved by Samsung');

        config($this->keys->samsungConfig());
        $this->page()->assertDontSee('not approved by Samsung');
    }

    public function test_warns_about_failed_creations_failed_updates_and_outages(): void
    {
        $pass = $this->pass('samsung');
        $this->page()->assertDontSee('failed pass creation')->assertDontSee('Possible provider outage');

        $this->event($pass, 'create', 'failure', 'samsung.cdata_failed');
        $this->page()->assertSee('1 failed pass creation in the last 7 days');

        $this->event($pass, 'update', 'failure', 'samsung.http_400');
        $this->event($pass, 'revoke', 'failure', 'samsung.http_400');
        $this->page()->assertSee('2 failed pass updates in the last 7 days');

        // Old failures do not count.
        $old = $this->pass('google');
        $this->event($old, 'update', 'failure', 'google.http_400', at: now()->subDays(10));
        $this->page()->assertDontSee('Google Wallet:</strong> 1 failed pass update', false);

        // A pass that gave up after its retries.
        $pass->forceFill(['status' => WalletPass::STATUS_FAILED])->save();
        $this->page()->assertSee('gave up after retries');

        // Several connection or server errors within half an hour: a likely outage.
        $google = $this->pass('google');
        foreach (range(1, 3) as $i) {
            $this->event($google, 'update', 'failure', 'google.http_503', retryable: true);
        }
        $this->page()->assertSee('Google Wallet:</strong> Possible provider outage', false);
    }

    public function test_real_failures_are_recorded_and_no_secret_ever_appears(): void
    {
        $this->certs[] = $certs = new WalletTestCertificates();
        config($certs->config());
        config($this->keys->googleConfig());
        config($this->keys->samsungConfig());
        $attendee = User::where('email', 'attendee@islandevents.example')->firstOrFail();
        $item = MenuItem::withoutGlobalScopes()->where('event_id', self::EVENT)->where('is_active', true)->firstOrFail();
        Event::allTenants()->findOrFail(self::EVENT)->forceFill(['starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(5)->addHours(4), 'status' => 'published'])->save();
        $order = app(OrderPlacementService::class)->placeOrder(Event::allTenants()->findOrFail(self::EVENT), [
            'customer_name' => 'Wallet Holder', 'customer_phone' => '+1 305 555 0142', 'method' => 'cash',
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ], $attendee)['order']->fresh();
        $order->forceFill(['status' => 'paid', 'amount_paid_minor' => $order->total_minor])->save();
        $order = Order::withoutGlobalScopes()->with('items')->find($order->id);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.admin-page-secret', 'expires_in' => 3600]),
            'walletobjects.googleapis.com/*' => Http::response(['error' => ['message' => 'issuer secret detail']], 503),
        ]);
        $manager = app(WalletPassManager::class);
        try {
            $manager->startSave(app(GoogleWalletService::class), $order, $attendee);
        } catch (\App\Services\Wallet\Exceptions\WalletProviderException) {
        }
        $samsungLink = $manager->startSave(app(SamsungWalletService::class), $order, $attendee)->payload();
        $rawToken = $order->fresh()->deliverTicketQrToken();

        $html = $this->page()->assertSee('google.http_503')->assertSee('1 failed pass creation')->getContent();
        foreach ([
            WalletTestCertificates::PASSWORD,
            base64_encode($this->keys->partnerKeyPem), base64_encode($this->keys->samsungCertPem),
            'PRIVATE KEY', 'BEGIN CERTIFICATE', 'ya29.admin-page-secret', 'issuer secret detail',
            $rawToken, $samsungLink, substr($samsungLink, strpos($samsungLink, 'cdata=') + 6, 40),
            WalletPass::where('provider', 'samsung')->firstOrFail()->authentication_token,
            $this->keys->googleJsonPath, WalletTestKeys::SERVICE_ACCOUNT,
        ] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }
    }
}
