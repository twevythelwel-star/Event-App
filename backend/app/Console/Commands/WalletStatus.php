<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\WalletPass;
use App\Services\Wallet\WalletPassManager;
use Illuminate\Console\Command;

/** Each wallet provider's state (configured, approved, offered) and pass counts, without printing anything secret. */
class WalletStatus extends Command
{
    protected $signature = 'wallet:status';

    protected $description = 'Show Apple, Google and Samsung Wallet configuration, approval status and pass counts';

    public function handle(WalletPassManager $manager): int
    {
        $rows = [];
        foreach ($manager->providers() as $key => $provider) {
            $counts = WalletPass::where('provider', $key)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
            $rows[] = [
                $provider->label(),
                $provider->configured() ? 'yes' : 'no',
                $provider->available() ? 'yes' : 'no',
                $counts->map(fn ($n, $s) => "{$s}: {$n}")->implode(', ') ?: '-',
            ];
        }
        $this->table(['Provider', 'Configured', 'Shown to holders', 'Passes'], $rows);
        foreach ($manager->providers() as $provider) {
            if ($provider->available()) {
                continue;
            }
            foreach ($provider->problems() as $problem) {
                $this->line($provider->label() . ': ' . $problem);
            }
        }

        return self::SUCCESS;
    }
}
