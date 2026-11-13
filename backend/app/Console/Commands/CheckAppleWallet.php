<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Wallet\AppleWalletConfig;
use Illuminate\Console\Command;

/** Is Apple Wallet ready? Checks the settings and the pass certificate without printing anything secret. */
class CheckAppleWallet extends Command
{
    protected $signature = 'wallet:check {--warn-days=30 : Fail when the certificate expires within this many days}';

    protected $description = 'Check the Apple Wallet configuration and pass certificate (expiry, Pass Type ID, Team ID)';

    public function handle(AppleWalletConfig $config): int
    {
        $this->line('Enabled (APPLE_WALLET_ENABLED): ' . (config('wallet.apple.enabled') ? 'yes' : 'no'));
        $this->line('Pass Type ID: ' . ($config->passTypeId() ?: '(not set)'));
        $this->line('Team ID: ' . ($config->teamId() ?: '(not set)'));
        $this->line('Web service: ' . ($config->webServiceUrl() ?? '(none: APP_URL or APPLE_WALLET_WEB_SERVICE_URL must be HTTPS for live updates)'));

        $problems = $config->problems();
        $expires = $config->certificateExpiresAt();
        if ($expires !== null) {
            $days = (int) floor(($expires->getTimestamp() - time()) / 86400);
            $this->line('Certificate expires: ' . $expires->format('Y-m-d') . " ({$days} days)");
            if ($days < (int) $this->option('warn-days')) {
                $problems[] = "The pass certificate expires in {$days} days: renew it (docs/eventbite-apple-wallet.md).";
            }
        }

        if ($problems !== []) {
            foreach (array_unique($problems) as $problem) {
                $this->error($problem);
            }

            return self::FAILURE;
        }
        $this->info('Apple Wallet is configured.');

        return self::SUCCESS;
    }
}
