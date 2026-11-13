<?php

declare(strict_types=1);

namespace App\Services\Wallet;

/**
 * Apple Wallet settings (config/wallet.php) and the pass certificate. The certificate's private key and password are
 * read from the environment only and never logged, returned or stored in the database.
 */
final class AppleWalletConfig
{
    /** @var array{cert: string, pkey: string, extracerts?: array<int, string>}|null */
    private ?array $certificate = null;

    public function enabled(): bool
    {
        return (bool) config('wallet.apple.enabled') && $this->problems() === [];
    }

    public function passTypeId(): string
    {
        return (string) config('wallet.apple.pass_type_id');
    }

    public function teamId(): string
    {
        return (string) config('wallet.apple.team_id');
    }

    public function organizationName(): string
    {
        return (string) (config('wallet.apple.organization_name') ?: 'EventBite');
    }

    public function certificatePassword(): string
    {
        return (string) config('wallet.apple.certificate_password');
    }

    /** The .p12 bytes, from the file path or base64 setting. */
    public function certificateP12(): ?string
    {
        $base64 = (string) config('wallet.apple.certificate_base64');
        if ($base64 !== '') {
            $bytes = base64_decode(preg_replace('/\s+/', '', $base64), true);

            return $bytes === false ? null : $bytes;
        }
        $path = (string) config('wallet.apple.certificate_path');
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            return null;
        }
        $bytes = file_get_contents($path);

        return $bytes === false ? null : $bytes;
    }

    public function wwdrCertificatePath(): string
    {
        $path = (string) config('wallet.apple.wwdr_certificate_path');

        return $path !== '' ? $path : base_path('vendor/pkpass/pkpass/src/Certificate/AppleWWDRCA.pem');
    }

    /** HTTPS base URL for the PassKit web service, or null when updates can't be offered. */
    public function webServiceUrl(): ?string
    {
        $url = (string) (config('wallet.apple.web_service_url') ?: rtrim((string) config('app.url'), '/') . '/wallet');

        return str_starts_with($url, 'https://') ? rtrim($url, '/') : null;
    }

    /** @return array{cert: string, pkey: string}|null The decoded certificate and key (in memory only). */
    public function certificate(): ?array
    {
        if ($this->certificate !== null) {
            return $this->certificate;
        }
        $p12 = $this->certificateP12();
        if ($p12 === null) {
            return null;
        }
        $certs = [];
        if (!@openssl_pkcs12_read($p12, $certs, $this->certificatePassword())) {
            while (openssl_error_string() !== false) {
                // Clear OpenSSL's error queue; the message may mention the file but never the password.
            }

            return null;
        }

        return $this->certificate = $certs;
    }

    /**
     * What is missing or wrong, in plain words (empty when ready). Checks the certificate is readable, current,
     * and issued for the configured Pass Type ID and Team ID.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $problems = [];
        if ($this->passTypeId() === '') {
            $problems[] = 'APPLE_WALLET_PASS_TYPE_ID is not set.';
        }
        if (!preg_match('/^[A-Z0-9]{10}$/', $this->teamId())) {
            $problems[] = 'APPLE_WALLET_TEAM_ID must be the 10-character Team ID.';
        }
        if ($this->certificateP12() === null) {
            $problems[] = 'The pass certificate (.p12) was not found: set APPLE_WALLET_CERTIFICATE_PATH or APPLE_WALLET_CERTIFICATE_BASE64.';
        } elseif (($certs = $this->certificate()) === null) {
            $problems[] = 'The pass certificate could not be opened: check APPLE_WALLET_CERTIFICATE_PASSWORD and that the .p12 includes the private key.';
        } else {
            $info = openssl_x509_parse($certs['cert']) ?: [];
            $subject = $info['subject'] ?? [];
            if (($info['validTo_time_t'] ?? 0) < time()) {
                $problems[] = 'The pass certificate has expired: renew it (docs/eventbite-apple-wallet.md).';
            }
            if (isset($subject['UID']) && $this->passTypeId() !== '' && $subject['UID'] !== $this->passTypeId()) {
                $problems[] = 'The certificate is for ' . $subject['UID'] . ', not the configured Pass Type ID.';
            }
            if (isset($subject['OU']) && $this->teamId() !== '' && $subject['OU'] !== $this->teamId()) {
                $problems[] = 'The certificate belongs to another Team ID than APPLE_WALLET_TEAM_ID.';
            }
        }
        if (!is_file($this->wwdrCertificatePath())) {
            $problems[] = 'The Apple WWDR intermediate certificate was not found.';
        }

        return $problems;
    }

    /** When the certificate expires, for renewal reminders. */
    public function certificateExpiresAt(): ?\DateTimeImmutable
    {
        $certs = $this->certificate();
        $info = $certs ? openssl_x509_parse($certs['cert']) : false;

        return $info ? (new \DateTimeImmutable())->setTimestamp((int) $info['validTo_time_t']) : null;
    }
}
