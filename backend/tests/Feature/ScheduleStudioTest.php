<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventActivity;
use App\Models\EventPerformer;
use App\Models\EventStage;
use App\Models\User;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The event schedule and activity builder: stages, a line-up with profiles, activities with times, places and
 * people, food service on the schedule, and the public schedule attendees see.
 */
class ScheduleStudioTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = 'evt-kingston-harbour-fry';
    private const TZ = 'America/New_York';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
        // Saturday 2027-06-12, 2 PM to 11 PM New York time; food served 4–9 PM.
        Event::allTenants()->whereKey(self::EVENT)->update([
            'status' => 'published', 'timezone' => self::TZ,
            'starts_at' => Carbon::parse('2027-06-12 14:00', self::TZ)->utc(), 'ends_at' => Carbon::parse('2027-06-12 23:00', self::TZ)->utc(),
            'event_date_iso' => '2027-06-12', 'food_service_start_time' => '16:00', 'food_service_end_time' => '21:00',
        ]);
    }

    private function host(): User
    {
        return User::where('email', 'host@islandevents.example')->firstOrFail();
    }

    private function activity(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->host())->post(route('events.schedule-studio.activities.store', self::EVENT), $overrides + [
            'title' => 'Sound system clash', 'kind' => 'dj_set', 'date' => '2027-06-12', 'start_time' => '18:00', 'end_time' => '19:30',
        ]);
    }

    public function test_hosts_build_stages_a_line_up_and_activities(): void
    {
        $host = $this->host();
        $this->actingAs($host)->get(route('events.schedule-studio', self::EVENT))->assertOk()->assertSee('Schedule studio')->assertSee('No activities yet');

        $this->actingAs($host)->post(route('events.schedule-studio.stages.store', self::EVENT), ['name' => 'Main stage', 'description' => 'By the water'])
            ->assertRedirect(route('events.schedule-studio', ['id' => self::EVENT, 'tab' => 'stages']))->assertSessionHas('status');
        $this->actingAs($host)->post(route('events.schedule-studio.stages.store', self::EVENT), ['name' => 'Main stage'])->assertSessionHasErrors('schedule');
        $stage = EventStage::where('event_id', self::EVENT)->firstOrFail();

        $this->actingAs($host)->post(route('events.schedule-studio.performers.store', self::EVENT), [
            'name' => 'Selector Ray', 'role' => 'dj', 'headline' => 'Roots and culture', 'bio' => 'Plays vinyl only.',
            'links' => [['label' => 'Mixes', 'url' => 'https://example.org/ray'], ['label' => 'Bad', 'url' => '']],
            'photo' => UploadedFile::fake()->image('ray.jpg', 600, 600), 'photo_alt_text' => 'Ray behind the decks',
        ])->assertSessionHas('status');
        $ray = EventPerformer::where('event_id', self::EVENT)->firstOrFail();
        $this->assertSame([['label' => 'Mixes', 'url' => 'https://example.org/ray']], $ray->links);
        $this->assertCount(3, $ray->photo_variants);
        $this->actingAs($host)->post(route('events.schedule-studio.performers.store', self::EVENT), ['name' => 'X', 'role' => 'dj', 'links' => [['url' => 'javascript:alert(1)']]])
            ->assertSessionHasErrors('schedule');

        $this->activity(['stage_id' => $stage->id, 'performer_ids' => [$ray->id], 'description' => 'Two crews, one winner.'])->assertSessionHas('status');
        $activity = EventActivity::where('event_id', self::EVENT)->firstOrFail();
        $this->assertSame('2027-06-12 22:00', $activity->starts_at->format('Y-m-d H:i'), 'Typed in New York time, stored in UTC.');
        $this->assertSame([$ray->id], $activity->performers->pluck('id')->all());
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'schedule_activity_added', 'target_id' => $activity->id]);
    }

    public function test_activities_must_fit_the_event_and_not_clash_on_a_stage(): void
    {
        $host = $this->host();
        $stage = EventStage::create(['event_id' => self::EVENT, 'name' => 'Main stage']);
        $other = EventStage::create(['event_id' => self::EVENT, 'name' => 'Food court']);
        $this->activity(['stage_id' => $stage->id])->assertSessionHas('status');

        $this->activity(['title' => 'Too early', 'start_time' => '10:00', 'end_time' => '11:00'])->assertSessionHasErrors('schedule');
        $this->activity(['title' => 'Overlap', 'stage_id' => $stage->id, 'start_time' => '19:00', 'end_time' => '20:00'])->assertSessionHasErrors('schedule');
        $this->assertStringContainsString("overlaps 'Sound system clash'", session('errors')->first('schedule'));
        $this->activity(['title' => 'Elsewhere', 'stage_id' => $other->id, 'start_time' => '19:00', 'end_time' => '20:00'])->assertSessionHas('status');
        $this->activity(['title' => 'Back to back', 'stage_id' => $stage->id, 'start_time' => '19:30', 'end_time' => '20:30'])->assertSessionHas('status');

        // An end before the start runs past midnight, and must still end with the event.
        $this->activity(['title' => 'After midnight', 'start_time' => '22:30', 'end_time' => '00:30'])->assertSessionHasErrors('schedule');

        // Stages and people from another event cannot be attached.
        $foreignStage = EventStage::create(['event_id' => 'evt-hellshire-sunset-fry', 'name' => 'Theirs']);
        $foreignPerson = EventPerformer::create(['event_id' => 'evt-hellshire-sunset-fry', 'name' => 'Theirs', 'role' => 'dj']);
        $this->activity(['title' => 'Bad stage', 'stage_id' => $foreignStage->id])->assertSessionHasErrors('schedule');
        $this->activity(['title' => 'Bad person', 'performer_ids' => [$foreignPerson->id]])->assertSessionHasErrors('schedule');
        $this->assertSame(3, EventActivity::where('event_id', self::EVENT)->count());
    }

    public function test_only_the_events_managers_can_change_its_schedule(): void
    {
        $outsider = User::create(['name' => 'Other Host', 'email' => 'other@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        $this->actingAs($outsider)->get(route('events.schedule-studio', self::EVENT))->assertNotFound();
        $this->actingAs($outsider)->post(route('events.schedule-studio.stages.store', self::EVENT), ['name' => 'Hijack'])->assertNotFound();
        $this->assertSame(0, EventStage::count());
    }

    public function test_attendees_see_the_public_schedule_food_service_and_line_up(): void
    {
        $stage = EventStage::create(['event_id' => self::EVENT, 'name' => 'Main stage']);
        EventStage::create(['event_id' => self::EVENT, 'name' => 'Kids tent']);
        $speaker = EventPerformer::create(['event_id' => self::EVENT, 'name' => 'Dr Ann Grant', 'role' => 'speaker', 'headline' => 'Food historian']);
        $this->activity(['title' => 'History of the fish fry', 'kind' => 'talk', 'stage_id' => $stage->id, 'start_time' => '15:00', 'end_time' => '15:45', 'performer_ids' => [$speaker->id]]);
        $this->activity(['title' => 'Crew briefing', 'kind' => 'activity', 'start_time' => '14:00', 'end_time' => '14:30', 'hidden' => '1']);

        $page = $this->actingAs(User::where('email', 'attendee@islandevents.example')->firstOrFail())->get(route('events.show', self::EVENT))->assertOk();
        $page->assertSee('History of the fish fry')->assertSee('3:00 PM')->assertSee('With Dr Ann Grant')->assertSee('Main stage');
        $page->assertSee('Food service')->assertSee('4:00 PM', false);
        $page->assertDontSee('Crew briefing');
        $page->assertSee('Line-up')->assertSee('Food historian');
        $page->assertSee('data-schedule-filter', false);
        $page->assertSee('class="eb-lineup-photo eb-lineup-initials"', false);
    }

    public function test_performer_photos_are_served_like_other_uploads_and_removed_with_the_profile(): void
    {
        $host = $this->host();
        $this->actingAs($host)->post(route('events.schedule-studio.performers.store', self::EVENT), [
            'name' => 'The Harbour Band', 'role' => 'band', 'photo' => UploadedFile::fake()->image('band.jpg', 800, 600),
        ]);
        $band = EventPerformer::where('event_id', self::EVENT)->firstOrFail();
        auth()->logout();
        $this->get("/api/v1/media/performers/{$band->id}/photo?variant=small")->assertOk()->assertHeader('Content-Type', 'image/webp');

        $this->actingAs($host)->delete(route('events.schedule-studio.performers.destroy', [self::EVENT, $band->id]))->assertSessionHas('status');
        Storage::disk('local')->assertMissing($band->photo_path);
        Storage::disk('local')->assertMissing($band->photo_variants['small']['webp']);
    }
}
