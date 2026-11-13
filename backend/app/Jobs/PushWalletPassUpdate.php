<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WalletPass;
use App\Services\Wallet\AppleWalletPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Tell the devices holding an Apple Wallet pass that it changed (they then fetch the new version). */
class PushWalletPassUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(public readonly string $walletPassId) {}

    public function handle(AppleWalletPushService $push): void
    {
        $pass = WalletPass::find($this->walletPassId);
        if ($pass !== null) {
            $push->notify($pass);
        }
    }

    public function failed(?\Throwable $e): void
    {
        $pass = WalletPass::find($this->walletPassId);
        if ($pass !== null) {
            app(\App\Services\Wallet\WalletPassManager::class)->recordFailure($pass, $e ?? new \RuntimeException('failed'));
        }
    }
}
