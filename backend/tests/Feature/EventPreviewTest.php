<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\OrganizerUser;
use App\Models\User;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventPreviewTest extends TestCase
{
    use RefreshDatabase;

    private const KINGSTON = 'evt-kingston-harbour-fry';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
    }

    private function user(string $who): User
    {
        return User::where('email', "{$who}@islandevents.example")->firstOrFail();
    }

    public function test_event_owner_can_access_preview(): void
    {
        $host = $this->user('host');

        $response = $this->actingAs($host)
            ->get(route('events.preview', self::KINGSTON))
            ->assertOk();

        $response->assertSee('Preview Mode');
        $response->assertSee('This is how your event will appear to the public');
    }

    public function test_non_owner_cannot_access_preview(): void
    {
        $other = User::create([
            'name' => 'Other User',
            'email' => 'other@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $this->actingAs($other)
            ->get(route('events.preview', self::KINGSTON))
            ->assertNotFound();
    }

    public function test_guest_cannot_access_preview(): void
    {
        $this->get(route('events.preview', self::KINGSTON))
            ->assertRedirectToRoute('login');
    }

    public function test_preview_shows_all_event_components(): void
    {
        $host = $this->user('host');

        $response = $this->actingAs($host)
            ->get(route('events.preview', self::KINGSTON))
            ->assertOk();

        $event = Event::find(self::KINGSTON);

        // Check for key components
        $response->assertSee($event->title);
        $response->assertSee($event->category);
        $response->assertSee($event->venue_name);
        $response->assertSee($event->date_str);
        $response->assertSee($event->time_str);
        $response->assertSee($event->organizer_name);
    }

    public function test_preview_has_device_toggle_buttons(): void
    {
        $host = $this->user('host');

        $response = $this->actingAs($host)
            ->get(route('events.preview', self::KINGSTON))
            ->assertOk();

        $response->assertSee('Desktop');
        $response->assertSee('Tablet');
        $response->assertSee('Mobile');
    }

    public function test_preview_shows_edit_and_publish_buttons(): void
    {
        $host = $this->user('host');

        $response = $this->actingAs($host)
            ->get(route('events.preview', self::KINGSTON))
            ->assertOk();

        $response->assertSee('Edit Event');
        $response->assertSee('Publish');
    }

    public function test_preview_disables_purchase_buttons(): void
    {
        $host = $this->user('host');

        $response = $this->actingAs($host)
            ->get(route('events.preview', self::KINGSTON))
            ->assertOk();

        $response->assertSee('preview-mode');
    }

    public function test_preview_works_for_unpublished_events(): void
    {
        $host = $this->user('host');

        // Unpublish the event
        $event = Event::find(self::KINGSTON);
        $event->status = 'draft';
        $event->save();

        $response = $this->actingAs($host)
            ->get(route('events.preview', self::KINGSTON))
            ->assertOk();

        $response->assertSee('Preview Mode');
    }

    public function test_system_admin_can_preview_any_event(): void
    {
        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'admin@system.test',
            'password' => bcrypt('password'),
            'status' => 'active',
            'account_type' => 'system_admin',
        ]);

        $this->actingAs($admin)
            ->get(route('events.preview', self::KINGSTON))
            ->assertOk();
    }

    public function test_preview_includes_media_gallery(): void
    {
        $host = $this->user('host');

        $response = $this->actingAs($host)
            ->get(route('events.preview', self::KINGSTON))
            ->assertOk();

        $event = Event::find(self::KINGSTON);

        if ($event->flyer_path) {
            $response->assertSee('Photos');
        }
    }

    public function test_preview_shows_cancellation_policy(): void
    {
        $host = $this->user('host');

        $response = $this->actingAs($host)
            ->get(route('events.preview', self::KINGSTON))
            ->assertOk();

        $response->assertSee('Cancellations and refunds');
    }
}
