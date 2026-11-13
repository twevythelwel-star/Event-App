<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use OpenSSLAsymmetricKey;

/**
 * Google Wallet settings (config/wallet.php "google") and the service-account key. The key JSON comes from the
 * environment only (a file path outside the repository, or base64 in a secret variable) and its private key is held
 * in memory only: never logged, returned or stored.
 */
final class GoogleWalletConfig
{
    private ?array $account = null;

    public function enabled(): bool
    {
        return (bool) config('wallet.google.enabled');
    }

    public function configured(): bool
    {
        return $this->enabled() && $this->problems() === [];
    }

    /** Configured, publishing access granted by Google, and tested end to end by us. */
    public function available(): bool
    {
        return $this->configured() && (bool) config('wallet.google.publishing_approved') && (bool) config('wallet.google.verified');
    }

    public function issuerId(): string
    {
        return (string) config('wallet.google.issuer_id');
    }

    public function clientEmail(): string
    {
        return (string) ($this->account()['client_email'] ?? '');
    }

    public function privateKey(): ?OpenSSLAsymmetricKey
    {
        $pem = (string) ($this->account()['private_key'] ?? '');
        $key = $pem !== '' ? openssl_pkey_get_private($pem) : false;
        Jose::clearErrors();

        return $key ?: null;
    }

    /** @return list<string> */
    public function origins(): array
    {
        $origins = array_values(array_filter(array_map('trim', (array) config('wallet.google.origins'))));

        return $origins !== [] ? $origins : [rtrim((string) config('app.url'), '/')];
    }

    /** @return list<string> */
    public function problems(): array
    {
        $problems = [];
        if (!preg_match('/^\d{10,25}$/', $this->issuerId())) {
            $problems[] = 'GOOGLE_WALLET_ISSUER_ID must be the numeric issuer ID from the Google Pay & Wallet Console.';
        }
        if ($this->account() === []) {
            $problems[] = 'The service-account key was not found or is not valid JSON: set GOOGLE_WALLET_SERVICE_ACCOUNT_PATH or GOOGLE_WALLET_SERVICE_ACCOUNT_BASE64.';
        } else {
            if (!filter_var($this->clientEmail(), FILTER_VALIDATE_EMAIL)) {
                $problems[] = 'The service-account key has no client_email.';
            }
            if ($this->privateKey() === null) {
                $problems[] = 'The service-account private key could not be read.';
            }
        }

        return $problems;
    }

    /** What is still needed before the button may show, beyond configuration. @return list<string> */
    public function pendingGates(): array
    {
        return array_values(array_filter([
            config('wallet.google.publishing_approved') ? null : 'Google has not granted publishing access (GOOGLE_WALLET_PUBLISHING_APPROVED): the issuer is in demo mode.',
            config('wallet.google.verified') ? null : 'Not yet tested end to end on a device (GOOGLE_WALLET_VERIFIED).',
        ]));
    }

    private function account(): array
    {
        if ($this->account !== null) {
            return $this->account;
        }
        $json = null;
        $base64 = (string) config('wallet.google.service_account_base64');
        if ($base64 !== '') {
            $json = base64_decode(preg_replace('/\s+/', '', $base64), true) ?: null;
        } elseif (($path = (string) config('wallet.google.service_account_path')) !== '' && is_file($path) && is_readable($path)) {
            $json = file_get_contents($path) ?: null;
        }
        $data = $json !== null ? json_decode($json, true) : null;

        return $this->account = is_array($data) ? $data : [];
    }
}
