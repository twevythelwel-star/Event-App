<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organizer;
use App\Models\TicketHold;
use App\Models\User;
use App\Services\Checkout\InventorySnapshot;
use App\Services\Checkout\TicketInventory;
use App\Services\Checkout\TicketPricing;
use App\Services\Checkout\TicketTierCatalog;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Listing pages read ticket inventory and photos for all their events at once, instead of once per event and
 * ticket type, and the bulk numbers are never stale or used where checkout needs live counts.
 */
class ListingQueryCountTest extends TestCase
{
    use RefreshDatabase;

    private function event(string $slug, int $stock): Event
    {
        $organizer = Organizer::firstOrCreate(['slug' => 'count-org'], ['name' => 'Count Org']);

        return Event::allTenants()->create([
            'organizer_id' => $organizer->id, 'slug' => $slug, 'title' => "Event {$slug}", 'currency' => 'USD',
            'date_str' => 'Saturday, November 28, 2026', 'time_str' => '6:00 PM - 11:00 PM',
            'event_date_iso' => now()->addMonth()->toDateString(),
            'venue_name' => 'Hall', 'venue_address' => 'Miami', 'organizer_name' => 'Count Org', 'organizer_phone' => '+13055550000',
            'advance_order_deadline' => now()->addWeeks(3), 'status' => 'published', 'visibility' => 'public', 'is_ordering_open' => true,
            'ticket_tiers' => [
                ['id' => 'ga', 'name' => 'General', 'price_usd' => 20, 'quantity_available' => $stock, 'max_per_order' => 4],
                ['id' => 'vip', 'name' => 'VIP', 'price_usd' => 50, 'quantity_available' => $stock, 'max_per_order' => 4],
            ],
        ]);
    }

    private function queriesFor(string $uri): int
    {
        $count = 0;
        DB::listen(function () use (&$count) { $count++; });
        $this->get($uri)->assertOk();

        return $count;
    }

    public function test_listing_pages_do_not_query_per_event(): void
    {
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
        foreach (range(1, 12) as $i) {
            $this->event("count-{$i}", 50);
        }

        $this->assertLessThan(40, $this->queriesFor('/'), 'homepage');
        $this->actingAs(User::where('email', 'attendee@islandevents.example')->firstOrFail());
        $this->assertLessThan(40, $this->queriesFor('/events'), 'events index');
    }

    public function test_prefetched_numbers_match_live_counts_and_are_dropped_after_a_hold(): void
    {
        $event = $this->event('live', 5);
        $buyer = User::create(['name' => 'B', 'email' => 'b@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        TicketHold::create(['event_id' => $event->id, 'ticket_tier_id' => 'ga', 'quantity' => 2, 'user_id' => $buyer->id, 'expires_at' => now()->addMinutes(10)]);

        $live = collect(TicketTierCatalog::sellable($event))->pluck('max', 'id')->all();
        InventorySnapshot::instance()->prefetch([$event]);
        $this->assertSame($live, collect(TicketTierCatalog::sellable($event))->pluck('max', 'id')->all());
        $this->assertSame(3, TicketInventory::availableFor($event, $event->ticket_tiers[0], null));

        // A new hold invalidates the snapshot, so the listing never shows tickets that are already held.
        TicketHold::create(['event_id' => $event->id, 'ticket_tier_id' => 'ga', 'quantity' => 3, 'user_id' => $buyer->id, 'expires_at' => now()->addMinutes(10)]);
        $this->assertNull(InventorySnapshot::instance()->soldByTier($event));
        $this->assertSame(0, TicketInventory::availableFor($event, $event->ticket_tiers[0], null));
    }

    public function test_checkout_inside_a_transaction_always_counts_live(): void
    {
        $event = $this->event('tx', 5);
        InventorySnapshot::instance()->prefetch([$event]);
        $this->assertSame([], InventorySnapshot::instance()->soldByTier($event));

        DB::transaction(function () use ($event) {
            $this->assertNull(InventorySnapshot::instance()->soldByTier($event));
            $this->assertNull(InventorySnapshot::instance()->heldByAnyone($event, 'ga'));
            $this->assertSame([], TicketPricing::soldByTier($event));
        });
    }
}
