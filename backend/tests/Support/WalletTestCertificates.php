<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Throwaway certificates for testing Apple Wallet signing: a stand-in "WWDR" CA and a pass certificate issued by it,
 * shaped like Apple's (UID = Pass Type ID, OU = Team ID). Created fresh in a temp folder per test run; never real.
 */
final class WalletTestCertificates
{
    public const PASS_TYPE_ID = 'pass.test.eventbite.ticket';

    public const TEAM_ID = 'ABCDE12345';

    public const PASSWORD = 'test-only-password';

    public string $dir;

    public string $p12Path;

    public string $caPath;

    /** $days: how long the pass certificate is valid; well past wallet:check's 30-day renewal warning by default. */
    public function __construct(int $days = 365)
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'eb-wallet-test-' . bin2hex(random_bytes(4));
        @mkdir($this->dir, 0700, true);
        $cnf = $this->dir . DIRECTORY_SEPARATOR . 'openssl.cnf';
        file_put_contents($cnf, "[ req ]\ndistinguished_name = req_dn\n[ req_dn ]\n"
            . "[ v3_ca ]\nbasicConstraints = critical,CA:true\nkeyUsage = critical, keyCertSign, cRLSign\nsubjectKeyIdentifier = hash\n"
            . "[ v3_leaf ]\nbasicConstraints = CA:false\nkeyUsage = critical, digitalSignature\n");
        $cfg = ['config' => $cnf, 'digest_alg' => 'sha256'];

        $caKey = openssl_pkey_new(['private_key_bits' => 2048] + $cfg);
        $caCsr = openssl_csr_new(['commonName' => 'Test WWDR stand-in CA', 'organizationName' => 'EventBite tests'], $caKey, $cfg);
        $ca = openssl_csr_sign($caCsr, null, $caKey, 365, ['x509_extensions' => 'v3_ca'] + $cfg);
        openssl_x509_export($ca, $caPem);
        $this->caPath = $this->dir . DIRECTORY_SEPARATOR . 'wwdr.pem';
        file_put_contents($this->caPath, $caPem);

        $key = openssl_pkey_new(['private_key_bits' => 2048] + $cfg);
        $csr = openssl_csr_new(['commonName' => 'Pass Type ID: ' . self::PASS_TYPE_ID, 'UID' => self::PASS_TYPE_ID,
            'organizationalUnitName' => self::TEAM_ID, 'organizationName' => 'EventBite tests'], $key, $cfg);
        $cert = openssl_csr_sign($csr, $ca, $caKey, $days, ['x509_extensions' => 'v3_leaf'] + $cfg);
        openssl_pkcs12_export($cert, $p12, $key, self::PASSWORD);
        $this->p12Path = $this->dir . DIRECTORY_SEPARATOR . 'pass.p12';
        file_put_contents($this->p12Path, $p12);
        while (openssl_error_string() !== false) {
        }
    }

    /** config() values for these certificates. */
    public function config(): array
    {
        return [
            'wallet.apple.enabled' => true,
            'wallet.apple.pass_type_id' => self::PASS_TYPE_ID,
            'wallet.apple.team_id' => self::TEAM_ID,
            'wallet.apple.certificate_path' => $this->p12Path,
            'wallet.apple.certificate_base64' => null,
            'wallet.apple.certificate_password' => self::PASSWORD,
            'wallet.apple.wwdr_certificate_path' => $this->caPath,
            'wallet.apple.web_service_url' => 'https://tickets.example.test/wallet',
            'wallet.apple.image_cache_path' => $this->dir . DIRECTORY_SEPARATOR . 'images',
        ];
    }

    public function cleanUp(): void
    {
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '{,images' . DIRECTORY_SEPARATOR . '}*', GLOB_BRACE) ?: [] as $file) {
            is_file($file) && @unlink($file);
        }
        @rmdir($this->dir . DIRECTORY_SEPARATOR . 'images');
        @rmdir($this->dir);
    }
}
