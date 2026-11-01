<?php

namespace Tests\Feature;

use App\Models\ChargeRule;
use App\Models\Event;
use App\Models\HostPaymentPolicy;
use App\Models\TargetMarket;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Checkout\OrderQuoteService;
use App\Services\Discovery\HomeSections;
use App\Services\EventQr\EventQrService;
use App\Services\OrderPlacementService;
use App\Support\EventListing;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Homepage, discovery, event details and the purchase action all say only what the event data supports.
 */
class DiscoveryJourneyTest extends TestCase
{
    use RefreshDatabase;

    private const SOUTH_FLORIDA = 'evt-kingston-harbour-fry';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
    }

    private function event(string $id = self::SOUTH_FLORIDA): Event
    {
        return Event::allTenants()->with('menuItems')->findOrFail($id);
    }

    private function setTiers(string $id, array $tiers): void
    {
        Event::allTenants()->whereKey($id)->update(['ticket_tiers' => json_encode($tiers)]);
    }

    public function test_homepage_is_built_from_published_upcoming_events_only(): void
    {
        Event::allTenants()->whereKey('evt-us-tampa')->update(['status' => 'draft']);
        Event::allTenants()->whereKey('evt-us-queens')->update(['status' => 'cancelled']);
        Event::allTenants()->whereKey('evt-us-newark')->update(['event_date_iso' => '2020-01-04']);

        $page = $this->get('/')->assertOk()
            ->assertSee('Events, food and culture across the U.S.')
            ->assertSee('United States (all)')
            ->assertSee('Featured events')
            ->assertSee('Upcoming food festivals')
            ->assertSee('Live music &amp; nightlife', false)
            ->assertSee('Cultural &amp; community celebrations', false)
            ->assertSee('Family-friendly')
            ->assertSee('Popular cities')
            ->assertDontSee('Tampa Bay Cuban and Caribbean Food Festival')
            ->assertDontSee('Queens International Street Food Night')
            ->assertDontSee('Newark Global Flavors Festival');

        // Every card leads to a real event page.
        preg_match_all('#href="[^"]*/events/([A-Za-z0-9-]+)"#', $page->getContent(), $links);
        $this->assertNotEmpty($links[1]);
        foreach (array_unique($links[1]) as $id) {
            $this->get('/events/' . $id)->assertOk();
        }

        // Caribbean is one tradition among several.
        $traditions = HomeSections::build()['traditions'];
        $this->assertArrayHasKey('Caribbean', $traditions);
        $this->assertGreaterThanOrEqual(5, count($traditions));
    }

    public function test_choosing_a_city_adds_events_near_it(): void
    {
        $atlanta = TargetMarket::where('city', 'Atlanta')->firstOrFail();

        $this->get('/?market=' . $atlanta->id)->assertOk()
            ->assertSee('Near Atlanta, GA')
            ->assertSee('Reggae and Dancehall Night');
    }

    public function test_signed_in_users_still_go_to_their_dashboard(): void
    {
        $attendee = User::where('email', 'attendee@islandevents.example')->firstOrFail();
        $this->actingAs($attendee)->get('/')->assertRedirect(route('attendee.dashboard'));
    }

    public function test_discovery_lists_every_organizers_published_events_and_nothing_else(): void
    {
        Event::allTenants()->whereKey('evt-us-tampa')->update(['status' => 'draft']);
        $host = User::where('email', 'host@islandevents.example')->firstOrFail();

        foreach ([$this->get('/events'), $this->actingAs($host)->get('/events')] as $response) {
            $response->assertOk()
                ->assertSee('Boston World Cultures and Food Gathering') // another organizer's event
                ->assertSee('South Florida Jamaican Fish Fry')
                ->assertDontSee('Tampa Bay Cuban and Caribbean Food Festival');
        }
    }

    public function test_availability_and_price_follow_the_ticket_data(): void
    {
        $this->assertSame('Tickets on sale', EventListing::availability($this->event())['label']);
        $this->assertSame('From $10.00', EventListing::fromPriceText($this->event()));

        $this->setTiers(self::SOUTH_FLORIDA, [
            ['id' => 'a', 'name' => 'A', 'priceUSD' => 10, 'availableStock' => 0],
            ['id' => 'b', 'name' => 'B', 'priceUSD' => 20, 'availableStock' => 0],
        ]);
        $this->assertSame('Sold out', EventListing::availability($this->event())['label']);
        $this->assertNull(EventListing::fromPriceText($this->event()));

        // One tier without a stock limit means it is not sold out, whatever the others say.
        $this->setTiers(self::SOUTH_FLORIDA, [
            ['id' => 'a', 'name' => 'A', 'priceUSD' => 10, 'availableStock' => 0],
            ['id' => 'b', 'name' => 'B', 'priceUSD' => 20],
        ]);
        $this->assertSame('Tickets on sale', EventListing::availability($this->event())['label']);
        $this->assertSame('From $20.00', EventListing::fromPriceText($this->event()));

        $this->setTiers(self::SOUTH_FLORIDA, [['id' => 'a', 'name' => 'A', 'priceUSD' => 10, 'availableStock' => 50, 'saleEndIso' => '2020-01-01T00:00:00-05:00']]);
        $this->assertSame('Ticket sales ended', EventListing::availability($this->event())['label']);

        Event::allTenants()->whereKey(self::SOUTH_FLORIDA)->update(['is_ordering_open' => false]);
        $this->assertSame('Ordering closed', EventListing::availability($this->event())['label']);
    }

    public function test_tickets_outside_their_sale_window_cannot_be_bought(): void
    {
        $this->setTiers(self::SOUTH_FLORIDA, [
            ['id' => 'ended', 'name' => 'Early Bird', 'priceUSD' => 5, 'availableStock' => 50, 'saleEndIso' => '2020-01-01T00:00:00-05:00'],
            ['id' => 'later', 'name' => 'Door Sales', 'priceUSD' => 15, 'availableStock' => 50, 'saleStartIso' => '2099-01-01T00:00:00-05:00'],
        ]);
        $event = $this->event();

        $quote = app(OrderQuoteService::class)->quote($event, ['tiers' => ['ended' => 1, 'later' => 1]]);
        $this->assertContains('Sales for Early Bird have ended.', $quote['problems']);
        $this->assertContains('Door Sales is not on sale yet.', $quote['problems']);

        $attendee = User::where('email', 'attendee@islandevents.example')->firstOrFail();
        $result = app(OrderPlacementService::class)->placeOrder($event, [
            'customer_name' => 'Late Buyer', 'customer_phone' => '+1 305 555 0100', 'method' => 'cash',
            'items' => [['menu_item_id' => 'ended', 'quantity' => 1]],
        ], $attendee);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('have ended', $result['error']);

        $this->get('/events/' . self::SOUTH_FLORIDA)->assertOk()->assertSee('Sales ended')->assertSee('Not on sale yet');
    }

    public function test_event_page_shows_payment_fees_refunds_and_share_code_from_configuration(): void
    {
        ChargeRule::create(['kind' => 'tax', 'label' => 'Sales tax (configured)', 'method' => 'percent', 'rate_bp' => 700,
            'applies_to' => 'food', 'is_active' => true]);
        ChargeRule::create(['kind' => 'platform_fee', 'label' => 'JMD flat fee', 'method' => 'fixed_per_order', 'amount_minor' => 50000,
            'currency' => 'JMD', 'applies_to' => 'all', 'is_active' => true]);
        app(EventQrService::class)->ensureFor($this->event());

        $page = $this->get('/events/' . self::SOUTH_FLORIDA)->assertOk()
            ->assertSee('Accepted payment methods')
            ->assertSee('Bank transfer')
            ->assertSee('Packaging fee')
            ->assertSee('Sales tax (configured)')
            ->assertSee('7.00% of food')
            ->assertDontSee('JMD flat fee') // a fixed amount in another currency is never charged, so never shown
            ->assertSee('The organizer has not published a refund policy')
            ->assertSee('Share this event')
            ->assertSee('not a ticket')
            ->assertSee('From $10.00');
        $this->assertStringNotContainsString('Verified Host', $page->getContent());

        HostPaymentPolicy::where('organizer_id', 'org-kingston-youth')->update([
            'refund_policy_notes' => 'Full refunds until 48 hours before the event.', 'refund_policy_deadline_hours' => 48,
        ]);
        $this->get('/events/' . self::SOUTH_FLORIDA)->assertSee('Full refunds until 48 hours before the event.')
            ->assertSee('Refund requests close 48 hours before the event starts.');
    }

    public function test_no_purchase_action_when_nothing_can_be_bought(): void
    {
        $this->setTiers(self::SOUTH_FLORIDA, [['id' => 'a', 'name' => 'A', 'priceUSD' => 10, 'availableStock' => 0]]);
        Event::allTenants()->whereKey(self::SOUTH_FLORIDA)->update(['supports_food_ordering' => false]);

        $this->get('/events/' . self::SOUTH_FLORIDA)->assertOk()
            ->assertSee('Sold out')
            ->assertSee("Tickets can't be bought right now.", false)
            ->assertDontSee('btn-blade-signin-purchase', false);
    }
}
