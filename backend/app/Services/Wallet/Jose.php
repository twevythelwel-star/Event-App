<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use OpenSSLAsymmetricKey;

/**
 * The small part of JOSE the wallet providers need, on PHP's OpenSSL extension only:
 *
 *   - JWS compact, RS256 (Google save links and service-account grants; Samsung card data and API tokens)
 *   - JWE compact, RSA1_5 key wrap + A128GCM content encryption (Samsung card data, per Samsung's Card Data Token spec)
 *
 * Keys are passed in by the caller and never logged or kept.
 */
final class Jose
{
    public static function b64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function b64urlDecode(string $text): string|false
    {
        $pad = strlen($text) % 4;

        return base64_decode(strtr($text, '-_', '+/') . ($pad ? str_repeat('=', 4 - $pad) : ''), true);
    }

    public static function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** JWS compact serialization, RS256. The payload is a JSON object or an already-serialized string. */
    public static function signRs256(array $header, array|string $payload, OpenSSLAsymmetricKey $privateKey): string
    {
        $input = self::b64url(self::json(['alg' => 'RS256'] + $header)) . '.'
            . self::b64url(is_array($payload) ? self::json($payload) : $payload);
        if (!openssl_sign($input, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            self::clearErrors();
            throw new \RuntimeException('jose.sign_failed');
        }

        return $input . '.' . self::b64url($signature);
    }

    /**
     * Verify an RS256 JWS against a public key. Returns [header, payload string] or null when the signature, the
     * algorithm or the encoding is wrong.
     *
     * @return array{0: array, 1: string}|null
     */
    public static function verifyRs256(string $jws, OpenSSLAsymmetricKey $publicKey): ?array
    {
        $parts = explode('.', $jws);
        if (count($parts) !== 3) {
            return null;
        }
        [$h, $p, $s] = $parts;
        $header = json_decode((string) self::b64urlDecode($h), true);
        $payload = self::b64urlDecode($p);
        $signature = self::b64urlDecode($s);
        if (!is_array($header) || ($header['alg'] ?? null) !== 'RS256' || $payload === false || $signature === false) {
            return null;
        }
        $ok = openssl_verify($h . '.' . $p, $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
        self::clearErrors();

        return $ok ? [$header, $payload] : null;
    }

    /** JWE compact serialization: a random 128-bit key wrapped with RSA1_5, content encrypted with AES-128-GCM. */
    public static function encryptRsa15A128Gcm(string $plaintext, OpenSSLAsymmetricKey $publicKey, array $extraHeader = []): string
    {
        $header = self::b64url(self::json(['alg' => 'RSA1_5', 'enc' => 'A128GCM'] + $extraHeader));
        $cek = random_bytes(16);
        $iv = random_bytes(12);
        if (!openssl_public_encrypt($cek, $wrapped, $publicKey, OPENSSL_PKCS1_PADDING)) {
            self::clearErrors();
            throw new \RuntimeException('jose.key_wrap_failed');
        }
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $iv, $tag, $header, 16);
        if ($ciphertext === false) {
            self::clearErrors();
            throw new \RuntimeException('jose.encrypt_failed');
        }

        return implode('.', [$header, self::b64url($wrapped), self::b64url($iv), self::b64url($ciphertext), self::b64url($tag)]);
    }

    /** Decrypt a JWE made by encryptRsa15A128Gcm (used to verify our own output in tests and diagnostics). */
    public static function decryptRsa15A128Gcm(string $jwe, OpenSSLAsymmetricKey $privateKey): ?string
    {
        $parts = explode('.', $jwe);
        if (count($parts) !== 5) {
            return null;
        }
        [$header, $wrapped, $iv, $ciphertext, $tag] = $parts;
        if (!openssl_private_decrypt((string) self::b64urlDecode($wrapped), $cek, $privateKey, OPENSSL_PKCS1_PADDING)) {
            self::clearErrors();

            return null;
        }
        $plain = openssl_decrypt((string) self::b64urlDecode($ciphertext), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA,
            (string) self::b64urlDecode($iv), (string) self::b64urlDecode($tag), $header);
        self::clearErrors();

        return $plain === false ? null : $plain;
    }

    public static function clearErrors(): void
    {
        while (openssl_error_string() !== false) {
            // OpenSSL keeps an error queue per process; empty it so nothing about a key leaks into later messages.
        }
    }
}
