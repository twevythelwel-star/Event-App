<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Payments\StripePaymentReconciliationService;
use Illuminate\Console\Command;

class ReconcileStripePayments extends Command
{
    protected $signature = 'payments:reconcile-stripe {--limit=100} {--stale-minutes=5}';
    protected $description = 'Compare pending and recently settled internal Stripe attempts with Stripe PaymentIntents';

    public function handle(StripePaymentReconciliationService $service): int
    {
        $result = $service->reconcile((int) $this->option('limit'), (int) $this->option('stale-minutes'));
        $this->table(['Checked', 'Applied', 'Mismatches', 'Errors', 'Skipped'], [[
            $result['checked'], $result['applied'], $result['mismatches'], $result['errors'], $result['skipped'],
        ]]);
        return $result['errors'] > 0 || $result['mismatches'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
