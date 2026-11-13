<?php

namespace Tests\Feature;

use App\Jobs\ConfirmWalletPassSaved;
use App\Jobs\SyncWalletPass;
use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletPass;
use App\Services\OrderPlacementService;
use App\Services\Wallet\Exceptions\GoogleWalletException;
use App\Services\Wallet\Exceptions\WalletProviderException;
use App\Services\Wallet\GoogleWalletService;
use App\Services\Wallet\Jose;
use App\Services\Wallet\SamsungWalletService;
use App\Services\Wallet\WalletDecision;
use App\Services\Wallet\WalletPassManager;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event as EventBus;
use Illuminate\Support\Facades\Http;
use Tests\Support\WalletTestKeys;
use Tests\TestCase;

/**
 * The unified wallet layer: the WalletPassManager's decisions, the Google and Samsung adapters (with throwaway keys
 * and faked provider APIs), the provider-confirmed "saved" state, queued syncs, and that no button or link appears
 * for a provider that is not configured and approved. Apple's own flow is covered by AppleWalletTest.
 */
class WalletProvidersTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = 'evt-kingston-harbour-fry';

    private WalletTestKeys $keys;

    /** @var list<string> every log line written during the test, with its context */
    private array $logged = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
        $this->keys = new WalletTestKeys();
        config(['wallet.apple.enabled' => false, 'wallet.google.enabled' => false, 'wallet.samsung.enabled' => false,
            'wallet.tester_emails' => '', 'app.url' => 'https://tickets.example.test']);
        $this->event()->forceFill([
            'starts_at' => now()->addDays(5)->setTime(22, 0), 'ends_at' => now()->addDays(6)->setTime(3, 0),
            'event_date_iso' => now()->addDays(5)->toDateString(), 'status' => 'published',
        ])->save();
        EventBus::listen(MessageLogged::class, function (MessageLogged $m): void {
            $this->logged[] = $m->message . ' ' . json_encode($m->context);
        });
    }

    protected function tearDown(): void
    {
        $this->keys->cleanUp();
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

    private function manager(): WalletPassManager
    {
        return app(WalletPassManager::class);
    }

    private function order(string $status = 'paid'): Order
    {
        $item = MenuItem::withoutGlobalScopes()->where('event_id', self::EVENT)->where('is_active', true)->firstOrFail();
        $result = app(OrderPlacementService::class)->placeOrder($this->event(), [
            'customer_name' => 'Wallet Holder', 'customer_phone' => '+1 305 555 0142', 'method' => 'cash',
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ], $this->attendee());
        $this->assertTrue($result['success'], $result['error'] ?? 'order placed');
        $order = $result['order']->fresh();
        if ($status === 'paid') {
            $order->forceFill(['status' => 'paid', 'amount_paid_minor' => $order->total_minor])->save();
        }

        return $order->fresh(['items', 'event']);
    }

    private function fakeGoogle(array $overrides = []): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake($overrides + [
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test-access-token', 'expires_in' => 3600]),
            'walletobjects.googleapis.com/*' => Http::response(['ok' => true]),
        ]);
    }

    /** A call "from Samsung": signed with the stand-in Samsung key, bound to the method and path. */
    private function samsungAuth(string $method, string $path, ?int $utc = null): array
    {
        $key = openssl_pkey_get_private($this->keys->samsungKeyPem);
        $token = Jose::signRs256(['cty' => 'AUTH', 'ver' => '3', 'partnerId' => WalletTestKeys::PARTNER_ID,
            'certificateId' => 'SAMSUNG', 'utc' => $utc ?? (int) (microtime(true) * 1000)],
            ['API' => ['method' => $method, 'path' => $path]], $key);

        return ['Authorization' => 'Bearer ' . $token];
    }

    private function assertNothingSecretLogged(array $secrets): void
    {
        $all = implode("\n", $this->logged);
        foreach (array_filter($secrets) as $secret) {
            $this->assertStringNotContainsString($secret, $all, 'A secret value reached the log.');
        }
    }

    public function test_the_manager_decides_every_question_from_the_record(): void
    {
        config($this->keys->googleConfig());
        $google = app(GoogleWalletService::class);
        $order = $this->order();
        $user = $this->attendee();

        $first = $this->manager()->decide($google, $order, null, $user);
        $this->assertSame(WalletDecision::OFFER, $first->action);
        $this->assertTrue($first->eligible && $first->configured && $first->available);
        $this->assertFalse($first->passExists || $first->saved || $first->revoked);

        $unpaid = $this->manager()->decide($google, $this->order('pending'), null, $user);
        $this->assertSame(WalletDecision::INELIGIBLE, $unpaid->action);
        $this->assertSame('unpaid', $unpaid->ineligibleReason);
        $stranger = User::where('id', '!=', $user->id)->firstOrFail();
        $this->assertSame('not_holder', $this->manager()->decide($google, $order, null, $stranger)->ineligibleReason);

        // The holder saves: Google gets the object and the pass is recorded as in step with the ticket.
        $this->fakeGoogle();
        $this->manager()->startSave($google, $order, $user);
        $pass = WalletPass::where('provider', 'google')->firstOrFail();
        $this->assertSame(WalletDecision::UP_TO_DATE, $this->manager()->decide($google, $order->fresh(['items', 'event']), $pass)->action);

        // The event moves: update, and the decision says why.
        $this->event()->forceFill(['venue_name' => 'New Harbour Stage'])->saveQuietly();
        $moved = $this->manager()->decide($google, $order->fresh(['items', 'event']), $pass->fresh());
        $this->assertSame(WalletDecision::UPDATE, $moved->action);
        $this->assertTrue($moved->eventChanged && $moved->needsUpdate);
        $this->assertFalse($moved->reissued);

        // The QR is reissued (model events held back here, so the queued sync does not run first): update again,
        // flagged as a reissue.
        Order::withoutEvents(fn () => $order->fresh()->reissueTicketToken());
        $reissued = $this->manager()->decide($google, $order->fresh(['items', 'event']), $pass->fresh());
        $this->assertSame(WalletDecision::UPDATE, $reissued->action);
        $this->assertTrue($reissued->reissued);

        // Revoked: revoke once, then nothing more to send.
        Order::withoutEvents(fn () => $order->fresh()->revokeTicketToken());
        $revoked = $this->manager()->decide($google, $order->fresh(['items', 'event']), $pass->fresh());
        $this->assertSame(WalletDecision::REVOKE, $revoked->action);
        $this->assertTrue($revoked->revoked);
        $this->manager()->sync($pass->fresh());
        $this->assertSame(WalletPass::STATUS_REVOKED, $pass->fresh()->status);
        $this->assertSame(WalletDecision::UP_TO_DATE, $this->manager()->decide($google, $order->fresh(['items', 'event']), $pass->fresh())->action);

        // Switched off: nothing can be offered or sent.
        config(['wallet.google.enabled' => false]);
        $this->assertSame(WalletDecision::UNAVAILABLE, $this->manager()->decide(app(GoogleWalletService::class), $order, $pass->fresh())->action);
    }

    public function test_google_save_inserts_the_object_and_hands_over_a_signed_link_but_saved_waits_for_google(): void
    {
        config($this->keys->googleConfig());
        $this->fakeGoogle(['walletobjects.googleapis.com/walletobjects/v1/eventTicketClass/*' => Http::response([], 404)]);
        $order = $this->order();

        $response = $this->actingAs($this->attendee())->get(route('tickets.show', $order))->assertOk()
            ->assertSee('Add to Google Wallet')->assertDontSee('Samsung Wallet');
        $location = $this->get(route('tickets.wallet.add', [$order, 'google']))->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith('https://pay.google.com/gp/v/save/', $location);

        // The JWT is signed by the service account and names only the object.
        $jwt = substr($location, strlen('https://pay.google.com/gp/v/save/'));
        $verified = Jose::verifyRs256($jwt, openssl_pkey_get_public(openssl_pkey_get_details(openssl_pkey_get_private($this->keys->googleKeyPem))['key']));
        $this->assertNotNull($verified);
        $claims = json_decode($verified[1], true);
        $pass = WalletPass::where('provider', 'google')->firstOrFail();
        $this->assertSame(['aud' => 'google', 'typ' => 'savetowallet'], ['aud' => $claims['aud'], 'typ' => $claims['typ']]);
        $this->assertSame([['id' => $pass->external_id]], $claims['payload']['eventTicketObjects']);
        $this->assertStringNotContainsString($order->deliverTicketQrToken(), base64_decode(strtr(explode('.', $jwt)[1], '-_', '+/')));

        // The object went to Google with the QR; the class was created because it did not exist yet.
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/eventTicketClass'));
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/eventTicketObject')
            && $r['state'] === 'ACTIVE' && $r['barcode']['type'] === 'QR_CODE' && $r['barcode']['value'] !== '' && $r['ticketNumber'] === $order->ticket_code);

        // Not saved until Google says the pass has a user.
        $this->assertNull($pass->saved_at);
        $this->assertSame(WalletPass::STATUS_ISSUED, $pass->status);
        $this->fakeGoogle(['walletobjects.googleapis.com/*' => Http::response(['hasUsers' => false])]);
        (new ConfirmWalletPassSaved($pass->id, 99))->handle($this->manager());
        $this->assertNull($pass->fresh()->saved_at);
        $this->fakeGoogle(['walletobjects.googleapis.com/*' => Http::response(['hasUsers' => true])]);
        (new ConfirmWalletPassSaved($pass->id))->handle($this->manager());
        $this->assertNotNull($pass->fresh()->saved_at);
        $this->assertSame(WalletPass::STATUS_SAVED, $pass->fresh()->status);

        $this->assertNothingSecretLogged([$jwt, $order->deliverTicketQrToken(), 'ya29.test-access-token', 'PRIVATE KEY']);
    }

    public function test_google_changes_are_queued_and_provider_errors_are_retried_or_failed(): void
    {
        config($this->keys->googleConfig());
        $this->fakeGoogle();
        $order = $this->order();
        $this->manager()->startSave(app(GoogleWalletService::class), $order, $this->attendee());
        $pass = WalletPass::where('provider', 'google')->firstOrFail();

        // An event change reaches Google through the queued sync (sync queue in tests).
        $this->event()->forceFill(['title' => 'Harbour Fry, moved indoors'])->save();
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PUT' && str_contains($r->url(), '/eventTicketClass/')
            && $r['eventName']['defaultValue']['value'] === 'Harbour Fry, moved indoors');

        // Refunded: the object turns INACTIVE without a barcode.
        $order->fresh()->forceFill(['status' => 'refunded'])->save();
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PUT' && str_contains($r->url(), '/eventTicketObject/')
            && $r['state'] === 'INACTIVE' && !isset($r['barcode']));
        $this->assertSame(WalletPass::STATUS_REVOKED, $pass->fresh()->status);

        // A retryable error (503) is thrown for the queue to retry; the pass keeps a short error code.
        $pass->fresh()->forceFill(['sync_fingerprint' => null, 'status' => WalletPass::STATUS_ISSUED])->save();
        $this->fakeGoogle(['walletobjects.googleapis.com/*' => Http::response(['error' => ['message' => 'backend details']], 503)]);
        try {
            (new SyncWalletPass($pass->id))->handle($this->manager());
            $this->fail('A 503 must be retried.');
        } catch (WalletProviderException $e) {
            $this->assertTrue($e->retryable);
        }
        $this->assertSame('google.http_503', $pass->fresh()->last_error);

        // A permanent error (400) is not retried; when the job finally fails the pass is marked failed.
        $this->fakeGoogle(['walletobjects.googleapis.com/*' => Http::response([], 400)]);
        (new SyncWalletPass($pass->id))->handle($this->manager());
        $this->assertSame('google.http_400', $pass->fresh()->last_error);
        (new SyncWalletPass($pass->id))->failed(GoogleWalletException::http('google', 400));
        $this->assertSame(WalletPass::STATUS_FAILED, $pass->fresh()->status);
        $this->assertFalse(collect($this->logged)->contains(fn ($l) => str_contains($l, 'backend details')));
    }

    public function test_samsung_stays_hidden_until_configured_approved_and_verified(): void
    {
        $order = $this->order();
        $this->actingAs($this->attendee());

        // The button is the only thing checked (a refusal message may mention Samsung Wallet).
        // Off: no button, and the add route does not exist for it.
        $this->get(route('tickets.show', $order))->assertOk()->assertDontSee('data-wallet-provider="samsung"', false);
        $this->get(route('tickets.wallet.add', [$order, 'samsung']))->assertNotFound();
        $this->assertNotContains('samsung', array_column($this->manager()->offers($order, $this->attendee()), 'key'));

        // Configured but Samsung's approval is pending: still nothing for holders, and no link can be made.
        config($this->keys->samsungConfig(approved: false, verified: false));
        $samsung = app(SamsungWalletService::class);
        $this->assertTrue($samsung->configured());
        $this->assertFalse($samsung->available());
        $this->assertStringContainsString('Approval is pending', implode(' ', $samsung->problems()));
        $this->get(route('tickets.show', $order))->assertOk()->assertDontSee('data-wallet-provider="samsung"', false);
        $this->get(route('tickets.wallet.add', [$order, 'samsung']))->assertRedirect(route('tickets.show', $order))->assertSessionHas('error');
        $this->assertSame(0, WalletPass::where('provider', 'samsung')->count());

        // Approved but not yet tested by us: still hidden, except to listed testers (labelled as a test).
        config(['wallet.samsung.partner_approved' => true]);
        $this->get(route('tickets.show', $order))->assertOk()->assertDontSee('data-wallet-provider="samsung"', false);
        config(['wallet.tester_emails' => 'someone@example.com, ' . strtoupper($this->attendee()->email)]);
        $this->get(route('tickets.show', $order))->assertOk()->assertSee('data-wallet-provider="samsung"', false)->assertSee('<span> (test)</span>', false);

        // Approved and verified: shown to every eligible holder.
        config(['wallet.tester_emails' => '', 'wallet.samsung.verified' => true]);
        $this->get(route('tickets.show', $order))->assertOk()->assertSee('Add to Samsung Wallet')
            ->assertDontSee('<span> (test)</span>', false)->assertSee(route('tickets.wallet.add', [$order, 'samsung']), false);

        // But never for a ticket that is not valid for entry.
        $this->get(route('tickets.show', $this->order('pending')))->assertOk()->assertDontSee('data-wallet-provider="samsung"', false);
    }

    public function test_samsung_card_data_is_encrypted_to_samsung_and_signed_by_us(): void
    {
        config($this->keys->samsungConfig());
        $order = $this->order();
        $location = $this->actingAs($this->attendee())->get(route('tickets.wallet.add', [$order, 'samsung']))
            ->assertRedirect()->headers->get('Location');
        $prefix = 'https://a.swallet.link/atw/v3/' . WalletTestKeys::CARD_ID . '#Clip?cdata=';
        $this->assertStringStartsWith($prefix, $location);
        $cdata = substr($location, strlen($prefix));
        $pass = WalletPass::where('provider', 'samsung')->firstOrFail();
        $this->assertNull($pass->saved_at, 'Not saved until Samsung reports ADDED.');

        // Outer JWS: our key, Samsung's documented header.
        $ours = openssl_pkey_get_public(openssl_pkey_get_details(openssl_pkey_get_private($this->keys->partnerKeyPem))['key']);
        [$header, $jwe] = Jose::verifyRs256($cdata, $ours) ?? $this->fail('cdata must be signed with our partner key');
        $this->assertSame(['RS256', 'CARD', '3', WalletTestKeys::PARTNER_ID, WalletTestKeys::CERTIFICATE_ID],
            [$header['alg'], $header['cty'], $header['ver'], $header['partnerId'], $header['certificateId']]);
        $this->assertEqualsWithDelta(microtime(true) * 1000, $header['utc'], 60000);

        // Inner JWE: only Samsung's key opens it.
        $jweHeader = json_decode(Jose::b64urlDecode(explode('.', $jwe)[0]), true);
        $this->assertSame(['alg' => 'RSA1_5', 'enc' => 'A128GCM'], $jweHeader);
        $this->assertNull(Jose::decryptRsa15A128Gcm($jwe, openssl_pkey_get_private($this->keys->partnerKeyPem)));
        $card = json_decode(Jose::decryptRsa15A128Gcm($jwe, openssl_pkey_get_private($this->keys->samsungKeyPem)), true)['card'];
        $this->assertSame('ticket', $card['type']);
        $this->assertSame('entrances', $card['subType']);
        $data = $card['data'][0];
        $this->assertSame([$pass->external_id, 'ACTIVE'], [$data['refId'], $data['state']]);
        foreach (['title', 'mainImg', 'logoImage', 'providerName', 'issueDate', 'reservationNumber', 'startDate', 'noticeDesc', 'appLinkLogo', 'appLinkName', 'appLinkData'] as $required) {
            $this->assertArrayHasKey($required, $data['attributes'], "Samsung requires {$required}");
        }
        $this->assertLessThanOrEqual(32, mb_strlen($data['attributes']['title']));
        $this->assertSame($order->fresh()->deliverTicketQrToken(), $data['attributes']['barcode.value']);
        $this->assertSame('QR_CODE', $data['attributes']['barcode.ptSubFormat']);

        // A revoked ticket: CANCELED, no barcode.
        $order->fresh()->revokeTicketToken();
        [, $jwe] = Jose::verifyRs256(app(SamsungWalletService::class)->cdata($pass->fresh(), $order->fresh(['items', 'event'])), $ours);
        $data = json_decode(Jose::decryptRsa15A128Gcm($jwe, openssl_pkey_get_private($this->keys->samsungKeyPem)), true)['card']['data'][0];
        $this->assertSame('CANCELED', $data['state']);
        $this->assertArrayNotHasKey('barcode.value', $data['attributes']);

        $this->assertNothingSecretLogged([$cdata, $jwe, $order->fresh()->secure_ticket_token_hash, 'PRIVATE KEY']);
    }

    public function test_samsung_partner_endpoints_need_samsungs_signature_and_drive_saved_and_updates(): void
    {
        config($this->keys->samsungConfig());
        Http::fake(['tsapi-card.walletsvc.samsung.com/*' => Http::response([], 200)]);
        $order = $this->order();
        $this->actingAs($this->attendee())->get(route('tickets.wallet.add', [$order, 'samsung']))->assertRedirect();
        $pass = WalletPass::where('provider', 'samsung')->firstOrFail();
        $path = '/wallet/samsung/cards/' . WalletTestKeys::CARD_ID . '/' . $pass->external_id;

        // Unsigned, signed by someone else, stale, or for another path: 401, and nothing changes.
        $this->post($path . '?cc2=US&event=ADDED')->assertStatus(401);
        $forged = Jose::signRs256(['cty' => 'AUTH', 'partnerId' => WalletTestKeys::PARTNER_ID, 'utc' => (int) (microtime(true) * 1000)],
            ['API' => ['method' => 'POST', 'path' => $path]], openssl_pkey_get_private($this->keys->partnerKeyPem));
        $this->post($path . '?cc2=US&event=ADDED', [], ['Authorization' => 'Bearer ' . $forged])->assertStatus(401);
        $this->post($path . '?cc2=US&event=ADDED', [], $this->samsungAuth('POST', $path, (int) (microtime(true) * 1000) - 3_600_000))->assertStatus(401);
        $this->post($path . '?cc2=US&event=ADDED', [], $this->samsungAuth('GET', $path))->assertStatus(401);
        $this->assertNull($pass->fresh()->saved_at);

        // Samsung's ADDED: now the pass is saved, and we know where to send updates.
        $this->post($path . '?cc2=US&event=ADDED', [], $this->samsungAuth('POST', $path))->assertOk();
        $pass->refresh();
        $this->assertNotNull($pass->saved_at);
        $this->assertSame(['US', WalletPass::STATUS_SAVED], [$pass->provider_meta['cc2'], $pass->status]);

        // Get Card Data returns fresh cdata to Samsung.
        $this->get($path, $this->samsungAuth('GET', $path))->assertOk()->assertJsonStructure(['cdata']);
        $this->get('/wallet/samsung/cards/other-card/' . $pass->external_id, $this->samsungAuth('GET', '/wallet/samsung/cards/other-card/' . $pass->external_id))->assertNotFound();

        // The event changes: Samsung gets an Update Notification, signed by us and bound to its path.
        $this->event()->forceFill(['starts_at' => now()->addDays(6)->setTime(20, 0)])->save();
        $updatePath = '/us/wltex/cards/' . WalletTestKeys::CARD_ID . '/updates';
        Http::assertSent(function (HttpRequest $r) use ($pass, $updatePath) {
            if ($r->url() !== 'https://tsapi-card.walletsvc.samsung.com' . $updatePath) {
                return false;
            }
            $ours = openssl_pkey_get_public(openssl_pkey_get_details(openssl_pkey_get_private($this->keys->partnerKeyPem))['key']);
            $verified = Jose::verifyRs256(substr($r->header('Authorization')[0], 7), $ours);
            $claims = json_decode($verified[1] ?? '{}', true);

            return $r->header('x-smcs-partner-id')[0] === WalletTestKeys::PARTNER_ID && $r->header('x-request-id')[0] !== ''
                && $verified[0]['cty'] === 'AUTH' && $claims['API'] === ['method' => 'POST', 'path' => $updatePath]
                && $r['card']['data'][0]['refId'] === $pass->external_id;
        });

        // DELETED: no longer in the wallet.
        $this->post($path . '?cc2=US&event=DELETED', [], $this->samsungAuth('POST', $path))->assertOk();
        $this->assertNull($pass->fresh()->saved_at);

        // Switched off: the endpoints are gone.
        config(['wallet.samsung.enabled' => false]);
        $this->post($path . '?cc2=US&event=ADDED', [], $this->samsungAuth('POST', $path))->assertNotFound();
    }

    public function test_apple_passes_are_saved_only_once_a_validated_file_is_delivered(): void
    {
        $certs = new \Tests\Support\WalletTestCertificates();
        try {
            config($certs->config());
            $order = $this->order();
            $this->actingAs($this->attendee())->get(route('tickets.show', $order))->assertSee('Add to Apple Wallet');
            $this->get(route('tickets.wallet', $order))->assertOk();
            $pass = WalletPass::where('provider', 'apple')->firstOrFail();
            $this->assertNotNull($pass->saved_at);
            $this->assertSame(WalletPass::STATUS_SAVED, $pass->status);

            // A package that does not validate (signed under another chain than the configured WWDR) is never
            // delivered or counted as saved.
            WalletPass::query()->delete();
            $otherChain = new \Tests\Support\WalletTestCertificates();
            config(['wallet.apple.wwdr_certificate_path' => $otherChain->caPath]);
            $this->get(route('tickets.wallet', $order))->assertRedirect(route('tickets.show', $order))->assertSessionHas('error');
            $this->assertSame(0, WalletPass::whereNotNull('saved_at')->count());
            $otherChain->cleanUp();
        } finally {
            $certs->cleanUp();
        }
    }

    public function test_wallet_status_reports_each_provider_without_secrets(): void
    {
        config($this->keys->samsungConfig(approved: false, verified: false));
        config($this->keys->googleConfig());
        $this->artisan('wallet:status')
            ->expectsOutputToContain('Approval is pending')
            ->doesntExpectOutputToContain('PRIVATE KEY')
            ->doesntExpectOutputToContain(base64_encode($this->keys->partnerKeyPem))
            ->assertExitCode(0);
    }
}
