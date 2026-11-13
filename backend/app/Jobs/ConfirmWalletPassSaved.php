<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WalletPass;
use App\Services\Wallet\Exceptions\WalletProviderException;
use App\Services\Wallet\WalletPassManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Ask the provider whether the holder actually saved the pass (Google's hasUsers). Not yet: ask again later, a few
 * times, then stop; the pass stays "issued" and is never marked saved without the provider saying so.
 */
class ConfirmWalletPassSaved implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** Minutes to wait before each further check. */
    public const SCHEDULE = [10, 60, 360];

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public readonly string $walletPassId, public readonly int $check = 0) {}

    public function handle(WalletPassManager $manager): void
    {
        $pass = WalletPass::find($this->walletPassId);
        if ($pass === null || $pass->saved_at !== null) {
            return;
        }
        try {
            $saved = $manager->confirmSaved($pass);
        } catch (WalletProviderException $e) {
            $manager->recordFailure($pass, $e, operation: 'confirm');
            if ($e->retryable) {
                throw $e;
            }

            return;
        }
        if ($saved === false && isset(self::SCHEDULE[$this->check])) {
            self::dispatch($pass->id, $this->check + 1)->onQueue($manager->queue())->delay(now()->addMinutes(self::SCHEDULE[$this->check]));
        }
    }
}
