<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Models\WalletDeviceRegistration;
use App\Models\WalletPass;
use Illuminate\Support\Facades\Log;

/**
 * Tells Wallet a pass changed: an empty push to each registered device over Apple Push Notification service (HTTP/2),
 * authenticated with the pass certificate, topic = Pass Type ID. The device then asks the web service for the serials
 * that changed and downloads them. Apple's "Unregistered" answer (410) removes that device registration.
 */
class AppleWalletPushService
{
    public function __construct(private readonly AppleWalletConfig $config) {}

    /** @return array{sent: int, failed: int, removed: int} */
    public function notify(WalletPass $pass): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'removed' => 0];
        if (!config('wallet.apple.push.enabled') || !$this->config->enabled()) {
            return $result;
        }
        $tokens = $pass->registrations()->pluck('push_token')->unique()->values();
        if ($tokens->isEmpty()) {
            return $result;
        }

        $pemFile = $this->clientCertificateFile();
        if ($pemFile === null) {
            Log::warning('Apple Wallet push skipped: the pass certificate could not be loaded.');

            return $result;
        }

        try {
            foreach ($tokens as $token) {
                $status = $this->send((string) $token, $pemFile);
                if ($status === 200) {
                    $result['sent']++;
                } elseif ($status === 410) {
                    // The device no longer has the pass or turned off updates.
                    $result['removed'] += WalletDeviceRegistration::where('push_token', $token)->delete();
                } else {
                    $result['failed']++;
                    Log::notice('Apple Wallet push not accepted', ['status' => $status, 'pass' => $pass->serial_number]);
                }
            }
        } finally {
            @unlink($pemFile);
        }

        return $result;
    }

    /** One push; returns the HTTP status (0 when the connection failed). */
    protected function send(string $pushToken, string $pemFile): int
    {
        if (!preg_match('/^[A-Fa-f0-9]{16,200}$/', $pushToken)) {
            return 400;
        }
        $host = rtrim((string) config('wallet.apple.push.host', 'https://api.push.apple.com'), '/');
        $ch = curl_init($host . '/3/device/' . $pushToken);
        curl_setopt_array($ch, [
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '{}',
            CURLOPT_HTTPHEADER => ['apns-topic: ' . $this->config->passTypeId(), 'content-type: application/json'],
            CURLOPT_SSLCERT => $pemFile,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) config('wallet.apple.push.timeout', 10),
        ]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return $status;
    }

    /**
     * The certificate and key as a PEM file for curl, readable only by this process user (0600) and deleted as soon
     * as the pushes are sent.
     */
    private function clientCertificateFile(): ?string
    {
        $certs = $this->config->certificate();
        if ($certs === null || empty($certs["pkey"])) {
            return null;
        }
        $file = tempnam(sys_get_temp_dir(), "apns");
        if ($file === false) {
            return null;
        }
        @chmod($file, 0600);
        file_put_contents($file, $certs["cert"] . "\n" . $certs["pkey"]);

        return $file;
    }
}