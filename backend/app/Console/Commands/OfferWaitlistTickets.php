<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\WaitlistEntry;
use App\Services\Checkout\WaitlistService;
use Illuminate\Console\Command;

/**
 * Offers freed tickets to waitlists: covers releases that opened, cancelled orders, stock the host added, and offers
 * that ran out (which pass to the next person). Expired unpaid orders also trigger offers straight away.
 */
class OfferWaitlistTickets extends Command
{
    protected $signature = 'tickets:offer-waitlist';

    protected $description = 'Offer tickets that became free to buyers waiting for them';

    public function handle(WaitlistService $waitlists): int
    {
        $offers = 0;
        $eventIds = WaitlistEntry::whereIn('status', ['waiting', 'offered'])->distinct()->pluck('event_id');
        foreach (Event::withoutGlobalScopes()->whereIn('id', $eventIds)->where('status', 'published')->get() as $event) {
            $offers += $waitlists->offerFreedTickets($event);
        }
        $this->info("{$offers} waitlist offer(s) made.");

        return self::SUCCESS;
    }
}
