<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\Discovery\HomeSections;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Database\Seeders\UsLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * No culture is the default: hosts say which traditions an event celebrates, events without artwork get a flyer of
 * their own rather than another culture's photo, and the homepage says what EventBite does for everyone.
 */
class InclusiveIdentityTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = 'evt-kingston-harbour-fry';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([UsLocationSeeder::class, FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
    }

    private function host(): User
    {
        return User::where('email', 'host@islandevents.example')->firstOrFail();
    }

    private function form(array $overrides = []): array
    {
        $event = Event::allTenants()->findOrFail(self::EVENT);

        return array_merge([
            'title' => $event->title, 'date_str' => $event->date_str, 'time_str' => $event->time_str,
            'event_date_iso' => substr((string) $event->event_date_iso, 0, 10), 'timezone' => $event->timezone,
            'venue_name' => $event->venue_name, 'street_address' => $event->street_address, 'city' => $event->city,
            'state_code' => $event->state_code, 'zip_code' => $event->zip_code, 'country_code' => 'US',
            'organizer_name' => $event->organizer_name, 'organizer_phone' => $event->organizer_phone,
            'advance_order_deadline' => '2027-01-10 18:00',
        ], $overrides);
    }

    public function test_hosts_choose_the_traditions_their_event_celebrates(): void
    {
        $this->actingAs($this->host())->get('/events/' . self::EVENT . '/edit')->assertOk()
            ->assertSee('Traditions and cuisines')
            ->assertSee('Indigenous &amp; Native American', false)
            ->assertSee('value="caribbean" checked', false);

        $this->actingAs($this->host())->put('/events/' . self::EVENT, $this->form([
            'traditions_submitted' => '1', 'traditions' => ['caribbean', 'southern_us'],
        ]))->assertSessionHasNoErrors();

        $event = Event::allTenants()->findOrFail(self::EVENT);
        $this->assertSame(['caribbean', 'southern_us'], $event->traditions);
        $this->get('/events/' . self::EVENT)->assertOk()->assertSee('Southern U.S.');
        $this->assertSame(['Caribbean', 'Southern U.S.'], HomeSections::traditions($event));

        // Unticking everything is respected: no tradition is guessed back in.
        $this->actingAs($this->host())->put('/events/' . self::EVENT, $this->form(['traditions_submitted' => '1']))->assertSessionHasNoErrors();
        $event->refresh();
        $this->assertSame([], $event->traditions);
        $this->assertSame([], $event->traditionKeys());

        // Only known traditions, at most four.
        $this->actingAs($this->host())->put('/events/' . self::EVENT, $this->form(['traditions_submitted' => '1', 'traditions' => ['made_up']]))
            ->assertSessionHasErrors('traditions.0');
        $this->actingAs($this->host())->put('/events/' . self::EVENT, $this->form(['traditions_submitted' => '1',
            'traditions' => ['african', 'caribbean', 'european', 'south_asian', 'southern_us']]))->assertSessionHasErrors('traditions');
    }

    public function test_events_without_artwork_get_their_own_flyer_not_another_cultures_photo(): void
    {
        $event = Event::allTenants()->findOrFail(self::EVENT);
        $this->assertFalse($event->hasFlyer());
        $this->assertSame(route('events.generated-flyer', $event->id), $event->display_image_url);

        $svg = $this->get('/events/' . self::EVENT . '/flyer.svg')->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')->getContent();
        $this->assertStringContainsString('Lauderhill, FL', $svg);
        $this->assertStringContainsString('2026', $svg);
        $this->assertStringNotContainsString('.jpg', $svg);

        // Titles are escaped, and drafts never show their name through this URL.
        Event::allTenants()->whereKey(self::EVENT)->update(['title' => 'Night <script>alert(1)</script> & Co']);
        $svg = $this->get('/events/' . self::EVENT . '/flyer.svg')->getContent();
        $this->assertStringNotContainsString('<script>', $svg);
        $this->assertStringContainsString('&amp; Co', $svg);

        Event::allTenants()->whereKey(self::EVENT)->update(['status' => 'draft']);
        $this->assertStringNotContainsString('Night', $this->get('/events/' . self::EVENT . '/flyer.svg')->assertOk()->getContent());
    }

    public function test_sample_catalog_uses_accurate_imagery_and_host_chosen_traditions(): void
    {
        $events = Event::allTenants()->get();
        $this->assertCount(20, $events);
        foreach ($events as $event) {
            $this->assertIsArray($event->traditions, "{$event->id} has chosen traditions");
            $this->assertNotEmpty($event->traditions);
            $this->assertFalse(str_contains((string) $event->flyer_path, 'island_'), "{$event->id} shows no stock island photo");
        }
        // Several traditions are represented, and Caribbean is one of them rather than all of them.
        $this->assertGreaterThanOrEqual(7, collect($events->pluck('traditions')->flatten())->unique()->count());
        $this->assertGreaterThan(0, $events->filter(fn ($e) => !in_array('caribbean', $e->traditions, true))->count());

        // A photo is only used for the dish it shows.
        $this->assertSame(['Mango Passion Smoothie'], MenuItem::withoutGlobalScopes()->where('image_url', '/images/tropical_island_smoothie.jpg')->distinct()->pluck('name')->all());
        $this->assertFalse(MenuItem::withoutGlobalScopes()->where('name', 'Jerk Pork Plate')->whereNotNull('image_url')->exists());
    }

    public function test_homepage_presents_every_culture_and_what_eventbite_does(): void
    {
        $this->get('/')->assertOk()
            ->assertDontSee('Reggae &amp; dancehall', false)
            ->assertDontSee('jerk chicken, Tampa')
            ->assertSee('Live music &amp; nightlife', false)
            ->assertSeeInOrder(['Discover', 'Find events near you', 'Buy tickets', 'Order food', 'Pay your way', 'Get your ticket'])
            ->assertSee('Bank transfer, Zelle, Cash App or cash, wherever the host accepts it.');

        $traditions = HomeSections::build()['traditions'];
        $this->assertArrayHasKey('Caribbean', $traditions);
        $this->assertGreaterThanOrEqual(7, count($traditions));
    }

    public function test_phone_buyers_always_have_the_price_and_the_buy_button_in_reach(): void
    {
        $attendee = User::where('email', 'attendee@islandevents.example')->firstOrFail();
        $html = $this->actingAs($attendee)->get('/events/' . self::EVENT)->assertOk()->getContent();
        $this->assertStringContainsString('class="eb-buy-bar"', $html);
        $this->assertMatchesRegularExpression('#eb-buy-bar.*?From \$\d+\.\d{2}.*?/events/' . self::EVENT . '/order/tickets#s', $html);

        // Nothing to buy: no bar promising otherwise
        Event::allTenants()->whereKey(self::EVENT)->update(['is_ordering_open' => false]);
        $this->assertStringNotContainsString('class="eb-buy-bar"', $this->actingAs($attendee)->get('/events/' . self::EVENT)->getContent());

        // Checkout says only what will happen: updates by text, the ticket once payment is confirmed
        Event::allTenants()->whereKey(self::EVENT)->update(['is_ordering_open' => true]);
        $this->actingAs($attendee)->post('/events/' . self::EVENT . '/order/tickets', ['tiers' => ['tier-ga' => 1]]);
        $this->actingAs($attendee)->get('/events/' . self::EVENT . '/order/checkout')->assertOk()
            ->assertSee('We text order and payment updates here.')
            ->assertDontSee('We text your ticket here.')
            ->assertDontSee('organiser');
    }

    public function test_new_accounts_are_offered_us_dollars_only(): void
    {
        $this->get('/register')->assertOk()->assertSee('USD — US dollar')->assertDontSee('Jamaican Dollar');
    }
}
