<?php

declare(strict_types=1);

namespace App\Services\Wallet\Exceptions;

use RuntimeException;

/**
 * A wallet provider operation failed. The message is a short code safe to log and store (e.g. "google.http_503",
 * "samsung.not_configured"): never a provider response body, URL, token, key or credential.
 */
class WalletProviderException extends RuntimeException
{
    public function __construct(
        public readonly string $provider,
        public readonly string $errorCode,
        public readonly bool $retryable = false,
    ) {
        parent::__construct($provider . '.' . $errorCode);
    }

    public static function notConfigured(string $provider): static
    {
        return new static($provider, 'not_configured');
    }

    /** Map an HTTP status from the provider: 408, 429 and 5xx are worth retrying, other 4xx are not. */
    public static function http(string $provider, int $status): static
    {
        $retryable = $status === 0 || $status === 408 || $status === 429 || $status >= 500;

        return new static($provider, $status === 0 ? 'connection_failed' : 'http_' . $status, $retryable);
    }

    public function code(): string
    {
        return $this->getMessage();
    }
}
