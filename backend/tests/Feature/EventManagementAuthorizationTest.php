<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\OrganizerUser;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The host's event screens (EventController) only act on events the signed-in user manages.
 * Anyone else gets "not found" and the event is left untouched.
 */
class EventManagementAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const KINGSTON = 'evt-kingston-harbour-fry'; // org-kingston-youth, managed by the demo host

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('s3_flyers');
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
    }

    private function user(string $who): User
    {
        return User::where('email', "{$who}@islandevents.example")->firstOrFail();
    }

    private function hostOf(?string $organizerId, string $email): User
    {
        $user = User::create(['name' => 'Host ' . $email, 'email' => $email, 'password' => bcrypt('password'), 'status' => 'active']);
        UserRole::create(['user_id' => $user->id, 'role' => 'event_host', 'is_active' => true, 'assigned_at' => now()]);
        if ($organizerId) {
            OrganizerUser::create(['organizer_id' => $organizerId, 'user_id' => $user->id, 'role' => 'event_owner', 'is_active' => true]);
        }

        return $user;
    }

    private function kingston(): Event
    {
        return Event::withoutGlobalScopes()->findOrFail(self::KINGSTON);
    }

    private function form(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Harbour Lights Fish Fry',
            'date_str' => 'Saturday, December 5, 2026',
            'time_str' => '5:00 PM - 11:00 PM',
            'timezone' => 'America/New_York',
            'venue_name' => 'Atlanta Community Park',
            'street_address' => '100 Park Place',
            'city' => 'Atlanta',
            'state_code' => 'GA',
            'zip_code' => '30303',
            'country_code' => 'US',
            'venue_address' => '100 Park Place, Atlanta, GA, 30303, United States',
            'organizer_name' => 'Atlanta Community Foundation',
            'organizer_phone' => '+14045550100',
            'advance_order_deadline' => '2026-12-04 18:00',
        ], $overrides);
    }

    /** @return array<string, array{string, string, array<string, mixed>}> */
    private function actions(): array
    {
        $id = self::KINGSTON;

        return [
            'edit' => ['get', "/events/{$id}/edit", []],
            'update' => ['put', "/events/{$id}", $this->form(['title' => 'Hijacked Title', 'status' => 'cancelled'])],
            'publish' => ['post', "/events/{$id}/publish", []],
            'unpublish' => ['post', "/events/{$id}/unpublish", []],
            'toggle ordering' => ['post', "/events/{$id}/toggle-ordering", []],
            'upload flyer' => ['post', "/events/{$id}/flyer", ['flyer' => UploadedFile::fake()->image('flyer.jpg', 800, 600)]],
            'remove flyer' => ['delete', "/events/{$id}/flyer", []],
        ];
    }

    public function test_people_who_do_not_manage_the_event_cannot_view_or_change_it(): void
    {
        Event::withoutGlobalScopes()->whereKey(self::KINGSTON)->update(['flyer_path' => 'tenants/org-kingston-youth/flyers/original.jpg']);
        $before = $this->kingston()->only(['title', 'status', 'is_ordering_open', 'flyer_path']);

        $outsiders = [
            'attendee' => $this->user('attendee'),
            'other organizer host' => $this->hostOf('org-hellshire-fishers', 'hellshire-host@example.test'),
            'host without an organizer' => $this->hostOf(null, 'lone-host@example.test'),
        ];

        foreach ($outsiders as $who => $user) {
            foreach ($this->actions() as $action => [$method, $url, $data]) {
                $this->actingAs($user)->{$method}($url, $data)->assertNotFound("{$who}: {$action}");
            }
        }

        $this->assertSame($before, $this->kingston()->only(['title', 'status', 'is_ordering_open', 'flyer_path']));
    }

    public function test_the_events_own_host_can_manage_it(): void
    {
        $host = $this->user('host');

        $this->actingAs($host)->get('/events/' . self::KINGSTON . '/edit')->assertOk();

        $this->actingAs($host)->post('/events/' . self::KINGSTON . '/unpublish')->assertRedirect();
        $this->assertSame('unpublished', $this->kingston()->status);

        $this->actingAs($host)->post('/events/' . self::KINGSTON . '/publish')->assertRedirect();
        $this->assertSame('published', $this->kingston()->status);

        $this->actingAs($host)->put('/events/' . self::KINGSTON, $this->form(['title' => 'Kingston Harbour Fish Fry (Updated)']))->assertRedirect();
        $this->assertSame('Kingston Harbour Fish Fry (Updated)', $this->kingston()->title);

        $open = $this->kingston()->is_ordering_open;
        $this->actingAs($host)->post('/events/' . self::KINGSTON . '/toggle-ordering')->assertRedirect();
        $this->assertNotSame((bool) $open, (bool) $this->kingston()->is_ordering_open);

        $this->actingAs($host)->post('/events/' . self::KINGSTON . '/flyer', ['flyer' => UploadedFile::fake()->image('flyer.jpg', 800, 600)])->assertRedirect();
        $this->assertNotNull($this->kingston()->flyer_path);
        $this->actingAs($host)->delete('/events/' . self::KINGSTON . '/flyer')->assertRedirect();
        $this->assertNull($this->kingston()->flyer_path);
    }

    public function test_new_events_belong_to_the_hosts_own_organizer(): void
    {
        $this->actingAs($this->user('host'))->post('/events', $this->form(['title' => 'New Kingston Night']))->assertRedirect();

        $event = Event::withoutGlobalScopes()->where('title', 'New Kingston Night')->firstOrFail();
        $this->assertSame('org-kingston-youth', $event->organizer_id);
    }

    public function test_a_host_without_an_organizer_cannot_create_events(): void
    {
        $this->actingAs($this->hostOf(null, 'lone-host@example.test'))
            ->post('/events', $this->form(['title' => 'Orphan Night']))
            ->assertStatus(403);

        $this->assertFalse(Event::withoutGlobalScopes()->where('title', 'Orphan Night')->exists());
    }
}
