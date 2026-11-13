<?php

declare(strict_types=1);

namespace App\Services\Wallet\Exceptions;

/** A Apple Wallet operation failed (code safe to log; see WalletProviderException). */
final class AppleWalletException extends WalletProviderException
{
    public static function because(string $errorCode, bool $retryable = false): self
    {
        return new self('apple', $errorCode, $retryable);
    }
}
