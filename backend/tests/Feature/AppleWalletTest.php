<?php

namespace Tests\Feature;

use App\Jobs\PushWalletPassUpdate;
use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletDeviceRegistration;
use App\Models\WalletPass;
use App\Services\OrderPlacementService;
use App\Services\Wallet\AppleWalletConfig;
use App\Services\Wallet\AppleWalletPackageValidator;
use App\Services\Wallet\AppleWalletPushService;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Support\WalletTestCertificates;
use Tests\TestCase;

/**
 * Apple Wallet: signed .pkpass packages from the ticket record, the PassKit web service (registration, changed
 * serials, latest pass), and updates when the ticket or event changes. Signed with throwaway test certificates
 * shaped like Apple's; a real device test needs the real Pass Type ID certificate.
 */
class AppleWalletTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = 'evt-kingston-harbour-fry';

    private const DEVICE = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const PUSH_TOKEN = 'f0e1d2c3b4a5968778695a4b3c2d1e0ff0e1d2c3b4a5968778695a4b3c2d1e0f';

    private WalletTestCertificates $certs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
        $this->certs = new WalletTestCertificates();
        config($this->certs->config());
        $this->event()->forceFill([
            'starts_at' => now()->addDays(5)->setTime(22, 0), 'ends_at' => now()->addDays(6)->setTime(3, 0),
            'event_date_iso' => now()->addDays(5)->toDateString(), 'status' => 'published',
        ])->save();
    }

    protected function tearDown(): void
    {
        $this->certs->cleanUp();
        parent::tearDown();
    }

    private function attendee(): User
    {
        return User::where('email', 'attendee@islandevents.example')->firstOrFail();
    }

    private function event(): Event
    {
        return Event::allTenants()->findOrFail(self::EVENT);
    }

    private function paidOrder(): Order
    {
        $item = MenuItem::withoutGlobalScopes()->where('event_id', self::EVENT)->where('is_active', true)->firstOrFail();
        $result = app(OrderPlacementService::class)->placeOrder($this->event(), [
            'customer_name' => 'Wallet Holder', 'customer_phone' => '+1 305 555 0142', 'method' => 'cash',
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ], $this->attendee());
        $this->assertTrue($result['success'], $result['error'] ?? 'order placed');
        $order = $result['order']->fresh();
        $order->forceFill(['status' => 'paid', 'amount_paid_minor' => $order->total_minor])->save();

        return $order->fresh();
    }

    /** @return array{0: array, 1: string, 2: WalletPass} the validated pass.json, the package and the pass record */
    private function download(Order $order): array
    {
        $response = $this->actingAs($this->attendee())->get(route('tickets.wallet', $order))->assertOk();
        $this->assertSame('application/vnd.apple.pkpass', $response->headers->get('Content-Type'));
        $bytes = $response->getContent();
        $result = app(AppleWalletPackageValidator::class)->validate($bytes, $this->certs->caPath);
        $this->assertTrue($result['valid'], implode("\n", $result['problems']));

        return [$result['pass'], $bytes, WalletPass::where('order_id', $order->id)->firstOrFail()];
    }

    private function auth(WalletPass $pass, ?string $token = null): array
    {
        return ['Authorization' => 'ApplePass ' . ($token ?? $pass->authentication_token)];
    }

    private function latest(WalletPass $pass, array $headers = [])
    {
        return $this->get('/wallet/v1/passes/' . WalletTestCertificates::PASS_TYPE_ID . '/' . $pass->serial_number, $this->auth($pass) + $headers);
    }

    private function passJsonOf(string $pkpass): array
    {
        return app(AppleWalletPackageValidator::class)->validate($pkpass, $this->certs->caPath)['pass'];
    }

    public function test_the_holder_downloads_a_valid_signed_event_ticket_pass(): void
    {
        $order = $this->paidOrder();
        [$pass, $bytes] = $this->download($order);

        $this->assertSame(WalletTestCertificates::PASS_TYPE_ID, $pass['passTypeIdentifier']);
        $this->assertSame(WalletTestCertificates::TEAM_ID, $pass['teamIdentifier']);
        $this->assertSame('South Florida Jamaican Fish Fry', $pass['eventTicket']['primaryFields'][0]['value']);
        $this->assertSame('Valid for entry', $pass['eventTicket']['headerFields'][0]['value']);
        $numbers = collect($pass['eventTicket']['auxiliaryFields'])->keyBy('key');
        $this->assertSame($order->ticket_code, $numbers['number']['value']);
        $this->assertSame('PKDateStyleMedium', collect($pass['eventTicket']['secondaryFields'])->firstWhere('key', 'when')['dateStyle']);
        $this->assertTrue($pass['sharingProhibited']);
        $this->assertArrayNotHasKey('voided', $pass);
        $this->assertSame('https://tickets.example.test/wallet', $pass['webServiceURL']);

        // The barcode is the order's current admission token: its hash is the one the gate looks up.
        $message = $pass['barcodes'][0]['message'];
        $this->assertSame('PKBarcodeFormatQR', $pass['barcodes'][0]['format']);
        $this->assertStringStartsWith('tok_', $message);
        $this->assertSame(Order::hashTicketToken($message), $order->fresh()->secure_ticket_token_hash);

        // Branding images are in; no strip without the host's own uploaded artwork.
        $zip = new \ZipArchive();
        $tmp = tempnam(sys_get_temp_dir(), 'pk');
        file_put_contents($tmp, $bytes);
        $zip->open($tmp);
        foreach (['icon.png', 'icon@2x.png', 'icon@3x.png', 'logo.png', 'logo@2x.png'] as $image) {
            $this->assertNotFalse($zip->locateName($image), $image);
        }
        $this->assertFalse($zip->locateName('strip.png'), 'no library or generated artwork on a pass');
        $zip->close();
        @unlink($tmp);
    }

    public function test_wallet_is_refused_unless_configured_paid_and_the_holders(): void
    {
        $order = $this->paidOrder();
        $stranger = User::create(['name' => 'Stranger', 'email' => 'stranger@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        $this->actingAs($stranger)->get(route('tickets.wallet', $order))->assertNotFound();

        $order->forceFill(['status' => 'awaiting_payment'])->save();
        $this->actingAs($this->attendee())->get(route('tickets.wallet', $order))
            ->assertRedirect(route('tickets.show', $order))->assertSessionHas('error');

        $order->forceFill(['status' => 'paid'])->save();
        config(['wallet.apple.enabled' => false]);
        $this->get(route('tickets.wallet', $order))->assertRedirect()->assertSessionHas('error');
        $this->get(route('tickets.show', $order))->assertOk()->assertDontSee('Add to Apple Wallet');

        config(['wallet.apple.enabled' => true]);
        $this->get(route('tickets.show', $order))->assertOk()->assertSee('Add to Apple Wallet')->assertSee(route('tickets.wallet', $order), false);

        // After the event the ticket is a keepsake: no Wallet pass.
        $this->event()->forceFill(['starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(3)->addHours(5)])->save();
        $this->get(route('tickets.wallet', $order))->assertRedirect()->assertSessionHas('error');
    }

    public function test_devices_register_and_fetch_updates_through_the_web_service(): void
    {
        Bus::fake([PushWalletPassUpdate::class]);
        $order = $this->paidOrder();
        [, , $pass] = $this->download($order);
        $base = '/wallet/v1/devices/' . self::DEVICE . '/registrations/' . WalletTestCertificates::PASS_TYPE_ID;

        $this->postJson("{$base}/{$pass->serial_number}", ['pushToken' => self::PUSH_TOKEN], $this->auth($pass, 'wrong-token-wrong-token'))->assertStatus(401);
        $this->postJson("{$base}/{$pass->serial_number}", ['pushToken' => self::PUSH_TOKEN], $this->auth($pass))->assertStatus(201);
        $this->postJson("{$base}/{$pass->serial_number}", ['pushToken' => self::PUSH_TOKEN], $this->auth($pass))->assertStatus(200);
        $this->assertSame(1, WalletDeviceRegistration::count());

        $list = $this->getJson($base)->assertOk()->assertJson(['serialNumbers' => [$pass->serial_number]]);
        $tag = $list->json('lastUpdated');
        $this->get("{$base}?passesUpdatedSince={$tag}")->assertNoContent();

        // The event moves: the pass is modified, its device is told, and it fetches the new time.
        $lastModified = $this->latest($pass)->assertOk()->headers->get('Last-Modified');
        $this->latest($pass, ['If-Modified-Since' => $lastModified])->assertStatus(304);
        $newStart = now()->addDays(7)->setTime(20, 0);
        $this->event()->forceFill(['starts_at' => $newStart, 'venue_name' => 'Harbour Pavilion'])->save();
        Bus::assertDispatched(PushWalletPassUpdate::class, fn ($job) => $job->walletPassId === $pass->id);
        $this->getJson("{$base}?passesUpdatedSince={$tag}")->assertOk()->assertJson(['serialNumbers' => [$pass->serial_number]]);

        $updated = $this->passJsonOf($this->latest($pass, ['If-Modified-Since' => $lastModified])->assertOk()->getContent());
        $fields = collect($updated['eventTicket']['secondaryFields'])->keyBy('key');
        $this->assertSame('Harbour Pavilion', $fields['venue']['value']);
        $this->assertSame($newStart->copy()->setTimezone($this->event()->timezone ?: 'America/New_York')->toIso8601String(), $fields['when']['value']);
        $this->assertSame('Venue changed to %@', $fields['venue']['changeMessage']);

        $this->deleteJson("{$base}/{$pass->serial_number}", [], $this->auth($pass))->assertOk();
        $this->assertSame(0, WalletDeviceRegistration::count());
    }

    public function test_reissue_revocation_refund_and_transfer_follow_the_ticket_record(): void
    {
        Bus::fake([PushWalletPassUpdate::class]);
        $order = $this->paidOrder();
        [$first, , $pass] = $this->download($order);
        WalletDeviceRegistration::create(['device_library_identifier' => self::DEVICE, 'push_token' => self::PUSH_TOKEN, 'wallet_pass_id' => $pass->id]);

        // Reissue: a new admission token through the order's own lifecycle; the pass carries the new one.
        $order->fresh()->reissueTicketToken($this->attendee(), 'Shared by mistake');
        Bus::assertDispatched(PushWalletPassUpdate::class);
        $reissued = $this->passJsonOf($this->latest($pass->fresh())->assertOk()->getContent());
        $this->assertNotSame($first['barcodes'][0]['message'], $reissued['barcodes'][0]['message']);
        $this->assertSame(Order::hashTicketToken($reissued['barcodes'][0]['message']), $order->fresh()->secure_ticket_token_hash);

        // Transfer: the sender's pass turns void with no barcode; the recipient gets their own pass.
        $recipient = User::create(['name' => 'Recipient', 'email' => 'recipient@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        $order->fresh()->forceFill(['user_id' => $recipient->id, 'email' => $recipient->email, 'customer_name' => 'Recipient'])->save();
        $this->assertNotNull($pass->fresh()->voided_at);
        $senders = $this->passJsonOf($this->latest($pass->fresh())->assertOk()->getContent());
        $this->assertTrue($senders['voided']);
        $this->assertArrayNotHasKey('barcodes', $senders);
        $this->assertSame('Transferred', $senders['eventTicket']['headerFields'][0]['value']);

        // Refund: the recipient's pass is void too.
        $this->actingAs($recipient)->get(route('tickets.wallet', $order))->assertOk();
        $theirs = WalletPass::where('order_id', $order->id)->where('user_id', $recipient->id)->firstOrFail();
        $order->fresh()->forceFill(['status' => 'refunded'])->save();
        $refunded = $this->passJsonOf($this->latest($theirs->fresh())->assertOk()->getContent());
        $this->assertTrue($refunded['voided']);
        $this->assertArrayNotHasKey('barcodes', $refunded);
        $this->assertSame('Refunded', $refunded['eventTicket']['headerFields'][0]['value']);
    }

    public function test_the_validator_catches_tampering_and_a_foreign_signer(): void
    {
        $order = $this->paidOrder();
        [, $bytes] = $this->download($order);
        $tmp = tempnam(sys_get_temp_dir(), 'pk');
        file_put_contents($tmp, $bytes);
        $zip = new \ZipArchive();
        $zip->open($tmp);
        $passJson = json_decode($zip->getFromName('pass.json'), true);
        $passJson['barcodes'][0]['message'] = 'tok_forged';
        $zip->addFromString('pass.json', json_encode($passJson));
        $zip->close();
        $tampered = app(AppleWalletPackageValidator::class)->validate((string) file_get_contents($tmp), $this->certs->caPath);
        @unlink($tmp);
        $this->assertFalse($tampered['valid']);
        $this->assertContains('pass.json does not match its manifest hash.', $tampered['problems']);

        $other = new WalletTestCertificates();
        $foreign = app(AppleWalletPackageValidator::class)->validate($bytes, $other->caPath);
        $other->cleanUp();
        $this->assertContains('The signing certificate was not issued by the WWDR intermediate.', $foreign['problems']);
    }

    public function test_pushes_go_to_each_device_and_unregistered_devices_are_removed(): void
    {
        $order = $this->paidOrder();
        [, , $pass] = $this->download($order);
        WalletDeviceRegistration::create(['device_library_identifier' => self::DEVICE, 'push_token' => self::PUSH_TOKEN, 'wallet_pass_id' => $pass->id]);
        WalletDeviceRegistration::create(['device_library_identifier' => 'b' . substr(self::DEVICE, 1), 'push_token' => str_repeat('ab', 32), 'wallet_pass_id' => $pass->id]);

        $push = new class(app(AppleWalletConfig::class)) extends AppleWalletPushService {
            public array $sent = [];

            protected function send(string $pushToken, string $pemFile): int
            {
                $this->sent[] = [$pushToken, str_contains((string) file_get_contents($pemFile), 'BEGIN CERTIFICATE')];

                return $pushToken === WalletTestTokens::$gone ? 410 : 200;
            }
        };
        WalletTestTokens::$gone = str_repeat('ab', 32);
        $result = $push->notify($pass);

        $this->assertSame(['sent' => 1, 'failed' => 0, 'removed' => 1], $result);
        $this->assertTrue($push->sent[0][1], 'authenticated with the pass certificate');
        $this->assertSame(1, WalletDeviceRegistration::count());
    }

    public function test_wallet_check_reports_the_setup_without_printing_secrets(): void
    {
        $this->artisan('wallet:check')->expectsOutputToContain('Apple Wallet is configured.')->assertExitCode(0);

        config(['wallet.apple.team_id' => 'ZZZZZ99999']);
        $this->artisan('wallet:check')->expectsOutputToContain('another Team ID')->assertExitCode(1);

        config(['wallet.apple.certificate_password' => 'not-the-password']);
        $this->artisan('wallet:check')->doesntExpectOutputToContain(WalletTestCertificates::PASSWORD)
            ->expectsOutputToContain('could not be opened')->assertExitCode(1);
    }
}

/** The push token the fake APNs answers 410 Unregistered for. */
final class WalletTestTokens
{
    public static string $gone = '';
}
