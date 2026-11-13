<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Models\Event;
use App\Models\Order;
use App\Models\WalletPass;
use App\Services\Wallet\Contracts\WalletProvider;
use App\Services\Wallet\Exceptions\GoogleWalletException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Google Wallet event tickets through the Google Wallet API (walletobjects v1), with a service account:
 *
 *   - one EventTicketClass per event ({issuerId}.event_{eventId}): name, venue, date
 *   - one EventTicketObject per pass ({issuerId}.{serial}): holder, ticket number, state, QR barcode
 *   - "Add to Google Wallet" is a link to pay.google.com/gp/v/save/{JWT}, the JWT signed with the service-account key
 *     and naming only the object id (the object is inserted through the API first). Made on click, never stored.
 *   - saved = the object's hasUsers flag, as reported by Google (confirmSaved).
 *
 * Errors become GoogleWalletException codes (google.http_404, google.connection_failed…); response bodies, tokens and
 * links are never logged.
 */
final class GoogleWalletService implements WalletProvider
{
    private const SCOPE = 'https://www.googleapis.com/auth/wallet_object.issuer';

    private ?string $accessToken = null;

    private int $accessTokenExpires = 0;

    public function __construct(private readonly GoogleWalletConfig $config) {}

    public function key(): string
    {
        return 'google';
    }

    public function label(): string
    {
        return 'Google Wallet';
    }

    public function configured(): bool
    {
        return $this->config->configured();
    }

    public function available(): bool
    {
        return $this->config->available();
    }

    public function problems(): array
    {
        if (!$this->config->enabled()) {
            return ['GOOGLE_WALLET_ENABLED is off.'];
        }

        return [...$this->config->problems(), ...$this->config->pendingGates()];
    }

    public function passTypeId(): string
    {
        return $this->config->issuerId() . '.eventbite';
    }

    public function objectId(WalletPass $pass): string
    {
        return $this->config->issuerId() . '.' . preg_replace('/[^A-Za-z0-9._-]/', '', (string) $pass->serial_number);
    }

    public function classId(?Event $event): string
    {
        return $this->config->issuerId() . '.event_' . preg_replace('/[^A-Za-z0-9._-]/', '_', (string) ($event?->id ?? 'unknown'));
    }

    public function save(WalletPass $pass, Order $order): WalletSaveAction
    {
        $this->guard();
        $this->upsertClass($order->event);
        $object = $this->objectBody($pass, $order);
        $response = $this->request('post', '/eventTicketObject', $object);
        if ($response->status() === 409) {
            // Already inserted on an earlier click: bring it up to date instead.
            $response = $this->request('put', '/eventTicketObject/' . rawurlencode($object['id']), $object);
        }
        $this->expectOk($response);

        return WalletSaveAction::redirect((string) config('wallet.google.save_url') . $this->saveJwt($object['id']));
    }

    public function update(WalletPass $pass, Order $order): void
    {
        $this->guard();
        $this->upsertClass($order->event);
        $object = $this->objectBody($pass, $order);
        $response = $this->request('put', '/eventTicketObject/' . rawurlencode($object['id']), $object);
        if ($response->status() === 404) {
            // Never inserted (the holder's save never reached Google): nothing in a wallet to update.
            return;
        }
        $this->expectOk($response);
    }

    public function revoke(WalletPass $pass, Order $order): void
    {
        // The object body for a void ticket is INACTIVE with no barcode (WalletTicketState).
        $this->update($pass, $order);
    }

    public function confirmSaved(WalletPass $pass): ?bool
    {
        $this->guard();
        $response = $this->request('get', '/eventTicketObject/' . rawurlencode($this->objectId($pass)));
        if ($response->status() === 404) {
            return false;
        }
        $this->expectOk($response);

        return $response->json('hasUsers') === true;
    }

    /** @return array<string, mixed> The EventTicketObject for the pass, rebuilt from the ticket record. */
    public function objectBody(WalletPass $pass, Order $order): array
    {
        $c = WalletTicketContent::of($pass, $order);
        $object = [
            'id' => $this->objectId($pass),
            'classId' => $this->classId($order->event),
            'state' => match (true) {
                $c['voided'] => 'INACTIVE',
                $c['state'] === 'past' => 'EXPIRED',
                default => 'ACTIVE',
            },
            'ticketHolderName' => $c['holder'] !== '' ? $c['holder'] : null,
            'ticketNumber' => $c['ticket_code'],
            'ticketType' => ['defaultValue' => ['language' => 'en-US', 'value' => mb_substr($c['ticket_type'], 0, 100)]],
            'textModulesData' => [
                ['id' => 'status', 'header' => 'Status', 'body' => $c['status']],
                ['id' => 'order', 'header' => 'Order', 'body' => $c['order_number']],
            ],
            'linksModuleData' => ['uris' => [['id' => 'ticket', 'uri' => $c['ticket_url'], 'description' => 'Open in EventBite']]],
        ];
        if (($value = WalletTicketContent::barcodeValue($pass, $order)) !== null) {
            $object['barcode'] = ['type' => 'QR_CODE', 'value' => $value, 'alternateText' => $c['ticket_code']];
        }
        if ($c['over_at'] !== null) {
            $object['validTimeInterval'] = ['end' => ['date' => $c['over_at']->toIso8601String()]];
        }

        return array_filter($object, fn ($v) => $v !== null);
    }

    /** @return array<string, mixed> */
    public function classBody(?Event $event): array
    {
        $title = (string) ($event?->title ?: 'EventBite event');
        $class = [
            'id' => $this->classId($event),
            'issuerName' => 'EventBite',
            'reviewStatus' => 'UNDER_REVIEW',
            'eventName' => ['defaultValue' => ['language' => 'en-US', 'value' => $title]],
            'hexBackgroundColor' => '#07111f',
        ];
        $venue = (string) ($event?->venue_name ?? '');
        $address = $event ? collect([$event->street_address, $event->city, $event->state_code, $event->zip_code])->filter()->implode(', ') : '';
        if ($venue !== '' || $address !== '') {
            $class['venue'] = array_filter([
                'name' => $venue !== '' ? ['defaultValue' => ['language' => 'en-US', 'value' => $venue]] : null,
                'address' => $address !== '' ? ['defaultValue' => ['language' => 'en-US', 'value' => $address]] : null,
            ]);
        }
        if ($event?->starts_at) {
            $class['dateTime'] = array_filter([
                'start' => $event->starts_at->toIso8601String(),
                'end' => $event->ends_at?->toIso8601String(),
            ]);
        }

        return $class;
    }

    /** The signed "save to Google Wallet" JWT naming only the object id. Never logged. */
    public function saveJwt(string $objectId): string
    {
        $key = $this->config->privateKey() ?? throw GoogleWalletException::notConfigured('google');

        return Jose::signRs256(['typ' => 'JWT'], [
            'iss' => $this->config->clientEmail(),
            'aud' => 'google',
            'typ' => 'savetowallet',
            'iat' => time(),
            'origins' => $this->config->origins(),
            'payload' => ['eventTicketObjects' => [['id' => $objectId]]],
        ], $key);
    }

    private function upsertClass(?Event $event): void
    {
        $class = $this->classBody($event);
        $response = $this->request('put', '/eventTicketClass/' . rawurlencode($class['id']), $class);
        if ($response->status() === 404) {
            $response = $this->request('post', '/eventTicketClass', $class);
        }
        $this->expectOk($response);
    }

    private function guard(): void
    {
        if (!$this->configured()) {
            throw GoogleWalletException::notConfigured('google');
        }
    }

    private function request(string $method, string $path, ?array $body = null): Response
    {
        $url = rtrim((string) config('wallet.google.api_base'), '/') . $path;
        try {
            $client = Http::withToken($this->accessToken())->acceptJson()->timeout((int) config('wallet.google.timeout', 10));

            return $body === null ? $client->{$method}($url) : $client->{$method}($url, $body);
        } catch (ConnectionException) {
            throw GoogleWalletException::because('connection_failed', true);
        }
    }

    private function expectOk(Response $response): void
    {
        if (!$response->successful()) {
            throw GoogleWalletException::http('google', $response->status());
        }
    }

    /** OAuth 2.0 access token from a service-account JWT grant; kept in this process's memory only. */
    private function accessToken(): string
    {
        if ($this->accessToken !== null && time() < $this->accessTokenExpires) {
            return $this->accessToken;
        }
        $key = $this->config->privateKey() ?? throw GoogleWalletException::notConfigured('google');
        $tokenUrl = (string) config('wallet.google.token_url');
        $assertion = Jose::signRs256(['typ' => 'JWT'], [
            'iss' => $this->config->clientEmail(), 'scope' => self::SCOPE, 'aud' => $tokenUrl,
            'iat' => time(), 'exp' => time() + 3600,
        ], $key);
        try {
            $response = Http::asForm()->timeout((int) config('wallet.google.timeout', 10))->post($tokenUrl, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $assertion,
            ]);
        } catch (ConnectionException) {
            throw GoogleWalletException::because('auth_connection_failed', true);
        }
        if (!$response->successful() || !is_string($response->json('access_token'))) {
            $e = GoogleWalletException::http('google', $response->status());
            throw GoogleWalletException::because('auth_' . $e->errorCode, $e->retryable);
        }
        $this->accessToken = (string) $response->json('access_token');
        $this->accessTokenExpires = time() + max(60, (int) $response->json('expires_in', 3600) - 60);

        return $this->accessToken;
    }
}
