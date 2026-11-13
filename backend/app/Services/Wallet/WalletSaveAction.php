<?php

declare(strict_types=1);

namespace App\Services\Wallet;

/**
 * How a pass reaches the holder's wallet: a signed pass file the app delivers itself (Apple), or a redirect to the
 * provider's own save page (Google, Samsung). A redirect URL can contain a signed token: it is handed to the browser
 * once and is never stored or logged (__debugInfo hides it).
 */
final class WalletSaveAction
{
    private function __construct(
        public readonly string $kind,
        private readonly string $payload,
        public readonly array $headers = [],
    ) {}

    public static function file(string $bytes, array $headers): self
    {
        return new self('file', $bytes, $headers);
    }

    public static function redirect(string $url): self
    {
        return new self('redirect', $url);
    }

    public function isFile(): bool
    {
        return $this->kind === 'file';
    }

    public function payload(): string
    {
        return $this->payload;
    }

    public function __debugInfo(): array
    {
        return ['kind' => $this->kind, 'payload' => '[hidden]'];
    }
}
