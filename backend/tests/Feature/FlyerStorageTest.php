<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organizer;
use App\Models\OrganizerUser;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A flyer uploaded through the host's event form is stored where the public flyer route reads it, so it
 * actually shows on the event page. (The form wrote to an "s3_flyers" disk that was never configured.)
 */
class FlyerStorageTest extends TestCase
{
    use RefreshDatabase;

    private ?string $storedPath = null;

    protected function tearDown(): void
    {
        if ($this->storedPath) {
            Storage::disk('local')->delete($this->storedPath);
        }
        parent::tearDown();
    }

    public function test_the_flyer_disk_shares_the_private_media_root(): void
    {
        $this->assertSame('local', config('filesystems.disks.s3_flyers.driver'));
        $this->assertSame(config('filesystems.disks.local.root'), config('filesystems.disks.s3_flyers.root'));
    }

    public function test_a_flyer_uploaded_through_the_event_form_is_served_on_the_event_page(): void
    {
        $organizer = Organizer::create(['name' => 'Flyer Org', 'slug' => 'flyer-org']);
        $event = Event::allTenants()->create([
            'organizer_id' => $organizer->id,
            'slug' => 'flyer-night',
            'title' => 'Flyer Night',
            'date_str' => 'Saturday, November 28, 2026',
            'time_str' => '6:00 PM - 11:00 PM',
            'venue_name' => 'Pier',
            'venue_address' => 'Kingston',
            'organizer_name' => 'Flyer Org',
            'organizer_phone' => '+18765550000',
            'advance_order_deadline' => now()->addWeek(),
            'status' => 'published',
        ]);
        $host = User::create(['name' => 'Flyer Host', 'email' => 'flyer-host@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        UserRole::create(['user_id' => $host->id, 'role' => 'event_host', 'is_active' => true, 'assigned_at' => now()]);
        OrganizerUser::create(['organizer_id' => $organizer->id, 'user_id' => $host->id, 'role' => 'event_owner', 'is_active' => true]);

        $this->actingAs($host)->post("/events/{$event->id}/flyer", ['flyer' => UploadedFile::fake()->image('flyer.jpg', 800, 600)])
            ->assertRedirect();

        $this->storedPath = Event::allTenants()->find($event->id)->flyer_path;
        $this->assertStringStartsWith("tenants/{$organizer->id}/flyers/", (string) $this->storedPath);
        Storage::disk('local')->assertExists($this->storedPath);

        $this->app['auth']->forgetGuards();
        $served = $this->get(route('api.media.flyer', $event->id));
        $served->assertOk();
        $this->assertStringStartsWith('image/', (string) $served->headers->get('Content-Type'));
    }
}
