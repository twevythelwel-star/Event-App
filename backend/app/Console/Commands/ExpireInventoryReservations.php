<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Checkout\InventoryReservationExpiryService;
use Illuminate\Console\Command;

class ExpireInventoryReservations extends Command
{
    protected $signature = 'commerce:expire-reservations {--batch=200 : Maximum reservations processed per run}';
    protected $description = 'Expire unpaid inventory reservations and safely return tracked stock';

    public function handle(InventoryReservationExpiryService $expiry): int
    {
        $result = $expiry->expire((int) $this->option('batch'));
        $this->info("Expired {$result['expired']} reservation(s); inventory released for {$result['inventory_released']}; {$result['held_for_provider']} held for provider reconciliation.");
        return self::SUCCESS;
    }
}
