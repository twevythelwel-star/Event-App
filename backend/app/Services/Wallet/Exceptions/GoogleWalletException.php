<?php

declare(strict_types=1);

namespace App\Services\Wallet\Exceptions;

/** A Google Wallet operation failed (code safe to log; see WalletProviderException). */
final class GoogleWalletException extends WalletProviderException
{
    public static function because(string $errorCode, bool $retryable = false): self
    {
        return new self('google', $errorCode, $retryable);
    }
}
