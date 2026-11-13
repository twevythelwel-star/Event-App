<?php

declare(strict_types=1);

namespace App\Services\Wallet\Exceptions;

/** A Samsung Wallet operation failed (code safe to log; see WalletProviderException). */
final class SamsungWalletException extends WalletProviderException
{
    public static function because(string $errorCode, bool $retryable = false): self
    {
        return new self('samsung', $errorCode, $retryable);
    }
}
