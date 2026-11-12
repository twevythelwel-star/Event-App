<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\SavedEvent;
use App\Models\User;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Saved events: attendees bookmark published events and revisit them from the Saved tab.
 */
class SavedEventsTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT_ID = 'evt-kingston-harbour-fry';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
    }

    private function attendee(): User
    {
        return User::where('email', 'attendee@islandevents.example')->firstOrFail();
    }

    public function test_guest_is_redirected_from_saved_pages(): void
    {
        $this->get('/saved')->assertRedirect();
        $this->post('/events/' . self::EVENT_ID . '/save')->assertRedirect();
    }

    public function test_attendee_can_save_and_unsave_an_event(): void
    {
        $attendee = $this->attendee();

        $this->actingAs($attendee)
            ->post('/events/' . self::EVENT_ID . '/save')
            ->assertRedirect();

        $this->assertDatabaseHas('saved_events', [
            'user_id' => $attendee->id,
            'event_id' => self::EVENT_ID,
        ]);

        $response = $this->actingAs($attendee)->get('/saved');
        $response->assertOk()->assertSee('Saved events');

        $this->actingAs($attendee)
            ->post('/events/' . self::EVENT_ID . '/save')
            ->assertRedirect();

        $this->assertDatabaseMissing('saved_events', [
            'user_id' => $attendee->id,
            'event_id' => self::EVENT_ID,
        ]);
    }

    public function test_saves_are_private_per_user(): void
    {
        $attendee = $this->attendee();
        $other = User::where('email', '!=', 'attendee@islandevents.example')->firstOrFail();

        $this->actingAs($attendee)->post('/events/' . self::EVENT_ID . '/save')->assertRedirect();
        $this->assertSame(0, SavedEvent::where('user_id', $other->id)->count());
    }

    public function test_cannot_save_unpublished_event(): void
    {
        $draft = Event::allTenants()->where('status', '!=', 'published')->first();
        if (! $draft) {
            $this->markTestSkipped('Seeder has no unpublished event.');
        }

        $this->actingAs($this->attendee())
            ->post('/events/' . $draft->id . '/save')
            ->assertNotFound();
    }

    public function test_bottom_nav_shows_saved_tab_for_attendees(): void
    {
        $this->actingAs($this->attendee())
            ->get('/events')
            ->assertOk()
            ->assertSee('Saved', false)
            ->assertSee('Discover', false);
    }
}
