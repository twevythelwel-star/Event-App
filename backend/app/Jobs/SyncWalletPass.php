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
use Throwable;

/**
 * Bring one wallet pass up to date at its provider (Google, Samsung): update or revoke, as WalletPassManager decides
 * when the job runs. Retryable provider errors (timeouts, 429, 5xx) are retried with backoff; others fail at once.
 * A pass that still fails is marked failed with a short error code and lands in failed_jobs.
 */
class SyncWalletPass implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 120, 600, 1800];

    public int $timeout = 60;

    public function __construct(public readonly string $walletPassId) {}

    public function handle(WalletPassManager $manager): void
    {
        $pass = WalletPass::find($this->walletPassId);
        if ($pass === null) {
            return;
        }
        try {
            $manager->sync($pass);
        } catch (WalletProviderException $e) {
            $manager->recordFailure($pass, $e);
            if (!$e->retryable) {
                $this->fail($e);

                return;
            }
            throw $e;
        }
    }

    public function failed(?Throwable $e): void
    {
        $pass = WalletPass::find($this->walletPassId);
        if ($pass !== null) {
            // Each attempt's failure is already in the history; this marks the pass failed for good.
            app(WalletPassManager::class)->recordFailure($pass, $e ?? new \RuntimeException('failed'), final: true, history: !($e instanceof WalletProviderException));
        }
    }
}
