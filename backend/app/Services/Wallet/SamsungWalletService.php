<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Models\Order;
use App\Models\WalletPass;
use App\Services\Wallet\Contracts\WalletProvider;
use App\Services\Wallet\Exceptions\SamsungWalletException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Samsung Wallet "Add to Samsung Wallet" for event tickets (card type "ticket"), following Samsung's partner
 * documentation (developer.samsung.com/wallet, see docs/eventbite-samsung-wallet.md):
 *
 *   - Data Transmit Link: https://a.swallet.link/atw/v3/{cardId}#Clip?cdata={cdata}, made on click (cdata is
 *     time-sensitive) and never stored or logged.
 *   - cdata (Card Data Token): a JWS (RS256, our private key; header cty CARD, ver 3, certificateId, partnerId, utc)
 *     around a JWE (RSA1_5 + A128GCM, Samsung's public key) of the card JSON.
 *   - Samsung tells our partner server when the card was added or deleted (Send Card State) and fetches the latest
 *     card data (Get Card Data): SamsungWalletPartnerController. A pass is "saved" only on Samsung's ADDED.
 *   - Updates and revocations: Update Notification, POST {api}/{cc2}/wltex/cards/{cardId}/updates, authorized with a
 *     JWS token (cty AUTH) bound to the method and path. Samsung then calls Get Card Data for the new state.
 *
 * Nothing here can produce a link unless the integration is configured; the button is shown only when available().
 */
final class SamsungWalletService implements WalletProvider
{
    public function __construct(private readonly SamsungWalletConfig $config) {}

    public function key(): string
    {
        return 'samsung';
    }

    public function label(): string
    {
        return 'Samsung Wallet';
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
            return ['SAMSUNG_WALLET_ENABLED is off.', ...$this->config->pendingGates()];
        }

        return [...$this->config->problems(), ...$this->config->pendingGates()];
    }

    public function passTypeId(): string
    {
        return 'samsung:' . $this->config->cardId();
    }

    /** The refId Samsung knows the card by: our random serial, never the order id or token. */
    public function refId(WalletPass $pass): string
    {
        return 'eb-' . str_replace('-', '', (string) $pass->serial_number);
    }

    public function save(WalletPass $pass, Order $order): WalletSaveAction
    {
        $this->guard();
        $link = rtrim((string) config('wallet.samsung.link_base'), '/') . '/' . rawurlencode($this->config->cardId())
            . '#Clip?cdata=' . $this->cdata($pass, $order);

        return WalletSaveAction::redirect($link);
    }

    public function update(WalletPass $pass, Order $order): void
    {
        $this->notify($pass, 'UPDATED');
    }

    /** Samsung fetches the card again and gets it CANCELED with no barcode (cardData()). */
    public function revoke(WalletPass $pass, Order $order): void
    {
        $this->notify($pass, 'UPDATED');
    }

    /** Samsung confirms with its own Send Card State call (ADDED), not by being asked. */
    public function confirmSaved(WalletPass $pass): ?bool
    {
        return null;
    }

    /** The Card Data Token for the pass. */
    public function cdata(WalletPass $pass, Order $order): string
    {
        $this->guard();
        $publicKey = $this->config->samsungPublicKey() ?? throw SamsungWalletException::notConfigured('samsung');
        $privateKey = $this->config->privateKey() ?? throw SamsungWalletException::notConfigured('samsung');
        try {
            $jwe = Jose::encryptRsa15A128Gcm(Jose::json($this->cardData($pass, $order)), $publicKey);

            return Jose::signRs256($this->header('CARD'), $jwe, $privateKey);
        } catch (\RuntimeException) {
            throw SamsungWalletException::because('cdata_failed');
        }
    }

    /**
     * The card object (Samsung event-ticket spec: type "ticket"), rebuilt from the ticket record. A void ticket is
     * CANCELED and an over event EXPIRED, both without a barcode.
     *
     * @return array{card: array}
     */
    public function cardData(WalletPass $pass, Order $order): array
    {
        $c = WalletTicketContent::of($pass, $order);
        $now = (int) (microtime(true) * 1000);
        $logo = asset('images/brand/eventbite-logo-600.png');
        $mark = asset('images/brand/eventbite-mark-256.png');
        $attributes = [
            'title' => mb_substr($c['title'], 0, 32),
            'subtitle1' => mb_substr($c['status'], 0, 32),
            'providerName' => 'EventBite',
            'issueDate' => $pass->created_at ? $pass->created_at->getTimestampMs() : $now,
            'reservationNumber' => mb_substr($c['ticket_code'], 0, 32),
            'orderId' => mb_substr($c['order_number'], 0, 32),
            'startDate' => $c['starts_at']?->getTimestampMs(),
            'startDate.utcOffset' => $c['starts_at']?->copy()->setTimezone($c['timezone'])->format('P'),
            'endDate' => ($c['ends_at'] ?? $c['over_at'])?->getTimestampMs(),
            'holderName' => $c['holder'] !== '' ? mb_substr($c['holder'], 0, 64) : null,
            'grade' => mb_substr($c['ticket_type'], 0, 32),
            'mainImg' => $logo,
            'logoImage' => $mark,
            'logoImage.lightUrl' => $mark,
            'logoImage.darkUrl' => $mark,
            'bgColor' => '#07111F',
            'fontColor' => 'light',
            'noticeDesc' => e('Show this ticket at the gate. The QR code admits one entry: only the first scan is accepted. '
                . 'If the organizer replaces or cancels your ticket, this card updates.')
                . ($c['address'] !== '' ? '<br>' . e($c['venue'] . ', ' . $c['address']) : ''),
            'appLinkLogo' => $mark,
            'appLinkName' => 'EventBite',
            'appLinkData' => $c['ticket_url'],
            'preventCaptureYn' => 'Y',
        ];
        if (($value = WalletTicketContent::barcodeValue($pass, $order)) !== null) {
            $attributes += [
                'barcode.value' => $value,
                'barcode.serialType' => 'QRCODE',
                'barcode.ptFormat' => 'QRCODESERIAL',
                'barcode.ptSubFormat' => 'QR_CODE',
                'barcode.errorCorrectionLevel' => 'M',
            ];
        }

        return ['card' => [
            'type' => 'ticket',
            'subType' => $this->config->subType(),
            'data' => [[
                'refId' => $this->refId($pass),
                'createdAt' => $pass->created_at ? $pass->created_at->getTimestampMs() : $now,
                'updatedAt' => $pass->last_modified_at ? $pass->last_modified_at->getTimestampMs() : $now,
                'state' => match (true) {
                    $c['voided'] => 'CANCELED',
                    $c['state'] === 'past' => 'EXPIRED',
                    default => 'ACTIVE',
                },
                'language' => 'en',
                'attributes' => array_filter($attributes, fn ($v) => $v !== null && $v !== ''),
            ]],
        ]];
    }

    /**
     * Verify the Authorization token on a call Samsung makes to our partner server: signed by Samsung (RS256, its
     * certificate), content type AUTH, our partner id, fresh, and bound to this exact method and path.
     */
    public function verifyInboundToken(?string $authorization, string $method, string $path): bool
    {
        if (!$this->configured() || !is_string($authorization) || $authorization === '') {
            return false;
        }
        $token = preg_replace('/^Bearer\s+/i', '', trim($authorization));
        $publicKey = $this->config->samsungPublicKey();
        $verified = $publicKey ? Jose::verifyRs256((string) $token, $publicKey) : null;
        if ($verified === null) {
            return false;
        }
        [$header, $payload] = $verified;
        $claims = json_decode($payload, true);
        $utc = (int) ($header['utc'] ?? 0);
        $maxAge = (int) config('wallet.samsung.token_max_age', 300) * 1000;
        $nowMs = (int) (microtime(true) * 1000);

        return ($header['cty'] ?? null) === 'AUTH'
            && (string) ($header['partnerId'] ?? '') === $this->config->partnerId()
            && abs($nowMs - $utc) <= $maxAge
            && is_array($claims)
            && strtoupper((string) ($claims['API']['method'] ?? '')) === strtoupper($method)
            && '/' . ltrim((string) ($claims['API']['path'] ?? ''), '/') === '/' . ltrim($path, '/');
    }

    /** Update Notification: tell Samsung the card changed; it then fetches the card data from us. */
    private function notify(WalletPass $pass, string $state): void
    {
        $this->guard();
        $cc2 = (string) ($pass->provider_meta['cc2'] ?? '');
        if (!preg_match('/^[A-Za-z]{2}$/', $cc2)) {
            // Samsung never told us the card was added (no country code): there is nothing in a wallet to update.
            return;
        }
        $path = '/' . strtolower($cc2) . '/wltex/cards/' . rawurlencode($this->config->cardId()) . '/updates';
        $url = rtrim((string) config('wallet.samsung.api_base'), '/') . $path;
        $privateKey = $this->config->privateKey() ?? throw SamsungWalletException::notConfigured('samsung');
        $auth = Jose::signRs256($this->header('AUTH'), [
            'API' => ['method' => 'POST', 'path' => $path],
            'refId' => $this->refId($pass),
        ], $privateKey);
        try {
            $response = Http::withToken($auth)->acceptJson()->timeout((int) config('wallet.samsung.timeout', 10))
                ->withHeaders(['x-smcs-partner-id' => $this->config->partnerId(), 'x-request-id' => str_replace('-', '', (string) Str::uuid())])
                ->post($url, ['card' => ['type' => 'ticket', 'data' => [['refId' => $this->refId($pass), 'state' => $state]]]]);
        } catch (ConnectionException) {
            throw SamsungWalletException::because('connection_failed', true);
        }
        if (!$response->successful()) {
            throw SamsungWalletException::http('samsung', $response->status());
        }
    }

    private function header(string $contentType): array
    {
        return [
            'cty' => $contentType,
            'ver' => '3',
            'certificateId' => $this->config->certificateId(),
            'partnerId' => $this->config->partnerId(),
            'utc' => (int) (microtime(true) * 1000),
        ];
    }

    private function guard(): void
    {
        if (!$this->configured()) {
            throw SamsungWalletException::notConfigured('samsung');
        }
    }
}
