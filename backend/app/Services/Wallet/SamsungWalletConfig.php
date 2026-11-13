<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use OpenSSLAsymmetricKey;

/**
 * Samsung Wallet partner settings (config/wallet.php "samsung") and keys. Our private key and Samsung's certificate
 * come from the environment only and are held in memory only: never logged, returned or stored.
 *
 * Three gates before a real user sees "Add to Samsung Wallet" (available()):
 *   1. configured  - enabled, IDs set, our private key and Samsung's certificate readable
 *   2. approved    - Samsung has approved the partner account and the ticket card is launched (SAMSUNG_WALLET_PARTNER_APPROVED)
 *   3. verified    - we have tested add, update and revoke end to end on a Galaxy device (SAMSUNG_WALLET_VERIFIED)
 */
final class SamsungWalletConfig
{
    public const SUB_TYPES = ['performances', 'sports', 'movies', 'entrances', 'others'];

    public function enabled(): bool
    {
        return (bool) config('wallet.samsung.enabled');
    }

    public function configured(): bool
    {
        return $this->enabled() && $this->problems() === [];
    }

    public function available(): bool
    {
        return $this->configured() && $this->pendingGates() === [];
    }

    public function partnerId(): string
    {
        return (string) config('wallet.samsung.partner_id');
    }

    public function cardId(): string
    {
        return (string) config('wallet.samsung.card_id');
    }

    public function certificateId(): string
    {
        return (string) config('wallet.samsung.certificate_id');
    }

    public function subType(): string
    {
        $type = (string) config('wallet.samsung.card_sub_type', 'entrances');

        return in_array($type, self::SUB_TYPES, true) ? $type : 'entrances';
    }

    public function privateKey(): ?OpenSSLAsymmetricKey
    {
        $pem = $this->read('private_key');
        $key = $pem !== null ? openssl_pkey_get_private($pem, (string) config('wallet.samsung.private_key_password') ?: null) : false;
        Jose::clearErrors();

        return $key ?: null;
    }

    /** Samsung's public key, from the certificate Samsung issued at onboarding. */
    public function samsungPublicKey(): ?OpenSSLAsymmetricKey
    {
        $pem = $this->read('samsung_certificate');
        $key = $pem !== null ? openssl_pkey_get_public($pem) : false;
        Jose::clearErrors();

        return $key ?: null;
    }

    /** @return list<string> */
    public function problems(): array
    {
        $problems = [];
        if ($this->partnerId() === '') {
            $problems[] = 'SAMSUNG_WALLET_PARTNER_ID is not set (Samsung Wallet Partners Portal).';
        }
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $this->cardId())) {
            $problems[] = 'SAMSUNG_WALLET_CARD_ID is not set (the ticket card created in the Partners Portal).';
        }
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $this->certificateId())) {
            $problems[] = 'SAMSUNG_WALLET_CERTIFICATE_ID is not set (issued when the CSR is registered).';
        }
        if ($this->privateKey() === null) {
            $problems[] = 'Our Samsung Wallet private key was not found or could not be opened: set SAMSUNG_WALLET_PRIVATE_KEY_PATH or SAMSUNG_WALLET_PRIVATE_KEY_BASE64.';
        }
        if ($this->samsungPublicKey() === null) {
            $problems[] = 'Samsung\'s certificate was not found or could not be read: set SAMSUNG_WALLET_SAMSUNG_CERTIFICATE_PATH or SAMSUNG_WALLET_SAMSUNG_CERTIFICATE_BASE64.';
        }

        return $problems;
    }

    /** @return list<string> */
    public function pendingGates(): array
    {
        return array_values(array_filter([
            config('wallet.samsung.partner_approved') ? null : 'Samsung has not approved the partner account and launched the ticket card (SAMSUNG_WALLET_PARTNER_APPROVED). Approval is pending.',
            config('wallet.samsung.verified') ? null : 'Not yet tested end to end on a Galaxy device (SAMSUNG_WALLET_VERIFIED).',
        ]));
    }

    private function read(string $name): ?string
    {
        $base64 = (string) config("wallet.samsung.{$name}_base64");
        if ($base64 !== '') {
            $pem = base64_decode(preg_replace('/\s+/', '', $base64), true);

            return $pem === false ? null : $pem;
        }
        $path = (string) config("wallet.samsung.{$name}_path");
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            return null;
        }
        $pem = file_get_contents($path);

        return $pem === false ? null : $pem;
    }
}
