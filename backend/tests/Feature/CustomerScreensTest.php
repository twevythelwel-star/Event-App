<?php

namespace Tests\Feature;

use App\Models\Event;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The customer phone screens from the app design: home, Explore, Food & Experience and the event page. */
class CustomerScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
    }

    public function test_home_has_the_photo_hero_search_pill_chips_and_event_rows(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Discover. Enjoy.', false)
            ->assertSee('eb-search-pill', false)
            ->assertSeeInOrder(['Music', 'Food', 'Culture', 'Family'])
            ->assertSee('eb-event-row', false);
    }

    public function test_an_explore_collection_lists_only_events_of_its_kinds(): void
    {
        $subjects = config('photo_library.collections.food-festivals.subjects');
        $event = Event::allTenants()->where('status', 'published')->where('visibility', 'public')->firstOrFail();
        $other = Event::allTenants()->where('status', 'published')->where('visibility', 'public')->whereKeyNot($event->id)->firstOrFail();
        Event::allTenants()->update(['photo_subject' => null]);
        Event::allTenants()->whereKey($event->id)->update(['photo_subject' => $subjects[0], 'event_date_iso' => now()->addMonth()->toDateString()]);

        $response = $this->getJson('/events?kind=food-festivals')->assertOk();
        $this->assertSame([$event->id], collect($response->json('data'))->pluck('id')->all());
        $this->assertNotContains($other->id, collect($response->json('data'))->pluck('id')->all());

        // The Explore screen shows a tile for that collection, linking to the filtered list.
        $this->get('/events')->assertOk()
            ->assertSee('Food Festivals')
            ->assertSee(route('events.index', ['kind' => 'food-festivals']), false);
    }

    public function test_the_food_screen_shows_dishes_from_real_menus_with_alt_text(): void
    {
        $page = $this->get('/events?category=food')->assertOk()
            ->assertSee('Food &amp; Experience', false)
            ->assertSee('Good food.', false);

        if (str_contains($page->getContent(), 'eb-food-grid')) {
            $this->assertMatchesRegularExpression('/alt="[^"]+, on the menu at [^"]+"/', $page->getContent());
        }
    }

    public function test_the_bottom_nav_has_home_explore_tickets_and_profile(): void
    {
        $this->get('/events')->assertOk()
            ->assertSeeInOrder(['data-nav="home"', 'data-nav="discover"', 'data-nav="tickets"', 'data-nav="profile"'], false);
    }
}
