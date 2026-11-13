<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Throwaway keys for testing the Google and Samsung Wallet adapters, made fresh in a temp folder per test:
 *
 *   - a Google service-account key JSON (client_email + RSA private key)
 *   - our Samsung partner private key
 *   - a stand-in "Samsung" key pair and certificate: the adapter encrypts card data to it and verifies "Samsung's"
 *     calls with it; the test holds its private key to decrypt and to sign those calls.
 *
 * Never real credentials.
 */
final class WalletTestKeys
{
    public const ISSUER_ID = '3388000000012345678';

    public const SERVICE_ACCOUNT = 'wallet-test@eventbite-test.iam.gserviceaccount.com';

    public const PARTNER_ID = 'EBTEST01';

    public const CARD_ID = 'card-test-0001';

    public const CERTIFICATE_ID = 'CERT01';

    public string $dir;

    public string $googleKeyPem;

    public string $googleJsonPath;

    public string $partnerKeyPem;

    public string $samsungKeyPem;

    public string $samsungCertPem;

    public function __construct()
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'eb-wallet-keys-' . bin2hex(random_bytes(4));
        @mkdir($this->dir, 0700, true);
        $cnf = $this->dir . DIRECTORY_SEPARATOR . 'openssl.cnf';
        file_put_contents($cnf, "[ req ]\ndistinguished_name = req_dn\n[ req_dn ]\n");
        $cfg = ['config' => $cnf, 'digest_alg' => 'sha256', 'private_key_bits' => 2048];

        $this->googleKeyPem = $this->newKey($cfg);
        $this->googleJsonPath = $this->dir . DIRECTORY_SEPARATOR . 'service-account.json';
        file_put_contents($this->googleJsonPath, json_encode([
            'type' => 'service_account', 'client_email' => self::SERVICE_ACCOUNT, 'private_key' => $this->googleKeyPem,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));

        $this->partnerKeyPem = $this->newKey($cfg);
        $this->samsungKeyPem = $this->newKey($cfg);
        $key = openssl_pkey_get_private($this->samsungKeyPem);
        $csr = openssl_csr_new(['commonName' => 'Samsung Wallet test stand-in'], $key, $cfg);
        $cert = openssl_csr_sign($csr, null, $key, 30, $cfg);
        openssl_x509_export($cert, $certPem);
        $this->samsungCertPem = $certPem;
        while (openssl_error_string() !== false) {
        }
    }

    public function googleConfig(bool $approved = true): array
    {
        return [
            'wallet.google.enabled' => true,
            'wallet.google.publishing_approved' => $approved,
            'wallet.google.verified' => $approved,
            'wallet.google.issuer_id' => self::ISSUER_ID,
            'wallet.google.service_account_path' => $this->googleJsonPath,
            'wallet.google.service_account_base64' => null,
            'wallet.google.origins' => ['https://tickets.example.test'],
        ];
    }

    public function samsungConfig(bool $approved = true, bool $verified = true): array
    {
        return [
            'wallet.samsung.enabled' => true,
            'wallet.samsung.partner_approved' => $approved,
            'wallet.samsung.verified' => $verified,
            'wallet.samsung.partner_id' => self::PARTNER_ID,
            'wallet.samsung.card_id' => self::CARD_ID,
            'wallet.samsung.certificate_id' => self::CERTIFICATE_ID,
            'wallet.samsung.private_key_path' => null,
            'wallet.samsung.private_key_base64' => base64_encode($this->partnerKeyPem),
            'wallet.samsung.samsung_certificate_path' => null,
            'wallet.samsung.samsung_certificate_base64' => base64_encode($this->samsungCertPem),
        ];
    }

    public function cleanUp(): void
    {
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    private function newKey(array $cfg): string
    {
        $key = openssl_pkey_new($cfg);
        openssl_pkey_export($key, $pem, null, $cfg);

        return $pem;
    }
}
