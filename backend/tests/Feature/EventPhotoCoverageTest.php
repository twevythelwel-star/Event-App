<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventMedia;
use App\Models\LibraryPhoto;
use App\Models\User;
use App\Services\PhotoLibrary;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Every event is advertised by a realistic photo once the library holds approved photos of its kind: the host's own
 * upload first, otherwise an approved library photo of the kind of event it is (declared, or from its category),
 * never one of some other kind and never one chosen at random.
 */
class EventPhotoCoverageTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['media.disk' => 'local']);
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
        $this->host = User::where('email', 'host@islandevents.example')->firstOrFail();
    }

    /** An approved library photo of one kind of event, stored with its resized copies as the admin upload does. */
    private function approvedPhoto(string $subject): LibraryPhoto
    {
        $label = PhotoLibrary::subjects()[$subject]['label'];
        $photo = app(PhotoLibrary::class)->store(UploadedFile::fake()->image("{$subject}.jpg", 1800, 1200), [
            'subject' => $subject, 'title' => $label,
            'alt_text' => "Guests enjoying a {$label} outdoors in the early evening",
            'source' => 'commissioned', 'source_detail' => 'Commissioned shoot', 'licence' => 'Work for hire, all rights assigned',
            'model_release' => 'not_needed',
        ], $this->host->id);
        $photo->forceFill(['status' => 'approved', 'reviewed_at' => now()])->save();
        app()->forgetScopedInstances();

        return $photo->fresh();
    }

    /** @return \Illuminate\Support\Collection<int, Event> */
    private function seededEvents()
    {
        return Event::withoutGlobalScopes()->whereIn('id', array_column(array_map(fn ($r) => $r['event'],
            json_decode(file_get_contents(database_path('seeders/data/us_cultural_events.json')), true)), 'id'))->get();
    }

    public function test_every_seeded_event_declares_a_kind_of_event_the_library_illustrates(): void
    {
        $events = $this->seededEvents();
        $this->assertCount(20, $events);
        foreach ($events as $event) {
            $this->assertTrue(PhotoLibrary::isSubject($event->photo_subject), "{$event->id} ({$event->title}) declares its kind of event");
            $this->assertNull($event->hasFlyer() ? $event->id : null, 'seeded events have no flyer of their own');
        }
    }

    public function test_every_seeded_event_shows_a_realistic_photo_of_its_own_kind_everywhere(): void
    {
        $events = $this->seededEvents();
        $photos = $events->pluck('photo_subject')->unique()->mapWithKeys(fn ($subject) => [$subject => $this->approvedPhoto($subject)]);

        foreach ($events as $event) {
            $cover = $event->coverImage();
            $photo = $photos[$event->photo_subject];
            $this->assertFalse($cover->generated, "{$event->id} shows a photo, not generated artwork");
            $this->assertTrue($cover->illustrative);
            $this->assertSame('Illustrative photo', $cover->label);
            $this->assertStringContainsString('/media/library/' . $photo->id, $cover->src, "{$event->id} shows a photo of its own kind");
            // Responsive copies (AVIF/WebP at several widths) and its size, so pages keep their performance behaviour.
            $this->assertNotEmpty($cover->sources);
            $this->assertNotNull($cover->width);
            // The alt text describes the photo, and says it is not of this event.
            $this->assertStringStartsWith($photo->alt_text, $cover->alt);
            $this->assertStringContainsString('not of this event', $cover->alt);
        }

        $event = $events->firstWhere('id', 'evt-ochorios-fishermans-fry');
        $event->forceFill(['status' => 'published', 'visibility' => 'public'])->save();
        $src = e($event->coverImage()->src);
        app('auth')->forgetGuards();

        // Event card (lazy, with srcset), the event page hero, and the ordering/ticket header all use it.
        $card = view('components.event-card', ['event' => $event])->render();
        $this->assertStringContainsString($src, $card);
        $this->assertStringContainsString('loading="lazy"', $card);
        $this->assertStringContainsString('srcset=', $card);
        $this->get(route('events.show', $event->id))->assertOk()->assertSee($src, false)->assertSee('Illustrative photo');
        $this->assertStringContainsString($src, view('checkout._event-header', ['event' => $event])->render());

        // The API tells other clients the same thing.
        $json = collect($this->getJson(route('api.events.index'))->assertOk()->json('data'))->firstWhere('id', $event->id);
        $this->assertSame('library', $json['coverImage']['kind']);
        $this->assertStringStartsWith($photos['dancehall_event']->alt_text, $json['coverImage']['alt']);
    }

    public function test_the_phone_homepage_leads_with_photos(): void
    {
        foreach ($this->seededEvents()->pluck('photo_subject')->unique() as $subject) {
            $this->approvedPhoto($subject);
        }
        app('auth')->forgetGuards();

        $this->get('/')->assertOk()->assertSee('/media/library/', false)->assertSee('Illustrative photo');
    }

    public function test_a_new_event_without_an_upload_gets_a_photo_of_its_category(): void
    {
        $music = $this->approvedPhoto('live_band');
        $this->approvedPhoto('reggae_concert');

        $this->actingAs($this->host)->post(route('events.store'), $this->form(['title' => 'Harbour Lights Live', 'category' => 'Music']))
            ->assertSessionHasNoErrors()->assertRedirect();
        $event = Event::withoutGlobalScopes()->where('title', 'Harbour Lights Live')->firstOrFail();

        $this->assertNull($event->photo_subject, 'the host did not choose a kind');
        $this->assertFalse($event->hasFlyer());
        $this->assertSame('live_band', PhotoLibrary::subjectFor($event));
        $cover = $event->coverImage();
        $this->assertFalse($cover->generated);
        $this->assertStringContainsString('/media/library/' . $music->id, $cover->src);
    }

    public function test_the_kind_the_host_declares_is_never_overridden_by_the_category_or_title(): void
    {
        $this->approvedPhoto('live_band');
        $event = Event::withoutGlobalScopes()->findOrFail('evt-us-sunrise');

        // A wedding filed under Music is still a wedding: with no wedding photo it keeps its artwork.
        $event->forceFill(['photo_subject' => 'wedding', 'category' => 'Music', 'title' => 'Live Band Night']);
        $this->assertNull(app(PhotoLibrary::class)->forEvent($event));
        $this->assertTrue($event->coverImage()->generated);

        // A category that names no kind of event (and a title that does) gets no photo rather than a random one.
        $event->forceFill(['photo_subject' => null, 'category' => 'Workshop', 'title' => 'Live Band Workshop']);
        $this->assertNull(PhotoLibrary::subjectFor($event));
        $this->assertTrue($event->coverImage()->generated);
    }

    public function test_the_hosts_own_photo_or_flyer_still_wins_over_the_library(): void
    {
        $this->approvedPhoto('jamaican_fish_fry');
        $id = 'evt-kingston-harbour-fry';
        $this->assertTrue(Event::withoutGlobalScopes()->findOrFail($id)->coverImage()->illustrative);

        // The host's main photo of the event.
        $this->actingAs($this->host)->post(route('events.photos.store', $id), [
            'kind' => 'cover', 'photo' => UploadedFile::fake()->image('main.jpg', 1600, 1000),
            'alt_text' => 'Cooks at the frying pots by the water', 'position_x' => 50, 'position_y' => 40,
        ])->assertSessionHasNoErrors();
        $main = EventMedia::where('event_id', $id)->where('media_type', 'cover')->firstOrFail();
        $cover = Event::withoutGlobalScopes()->findOrFail($id)->coverImage();
        $this->assertFalse($cover->illustrative);
        $this->assertSame($main->responsiveImage()->src, $cover->src);

        // The host's uploaded flyer comes first of all.
        Event::withoutGlobalScopes()->whereKey($id)->update(['flyer_path' => 'tenants/org-kingston-youth/flyers/own.jpg', 'flyer_moderation_status' => 'approved']);
        $cover = Event::withoutGlobalScopes()->findOrFail($id)->coverImage();
        $this->assertTrue($cover->uploaded);
        $this->assertFalse($cover->illustrative);
        $this->assertStringContainsString('/api/v1/media/events/' . $id . '/flyer', $cover->src);
    }

    private function form(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Harbour Lights Food Night',
            'description' => 'An evening of Caribbean food, live music and dancing by the water.',
            'event_date_iso' => now()->addMonth()->toDateString(), 'start_time' => '18:00', 'end_time' => '23:00',
            'timezone' => 'America/New_York', 'venue_name' => 'Bayfront Park', 'street_address' => '301 Biscayne Blvd',
            'city' => 'Miami', 'state_code' => 'FL', 'zip_code' => '33132', 'country_code' => 'US',
            'organizer_name' => 'Kingston Youth Collective', 'organizer_phone' => '+1 305 555 0142', 'organizer_email' => 'hello@example.test',
            'advance_order_deadline' => now()->addWeeks(3)->format('Y-m-d\TH:i'), 'supports_food_ordering' => '0',
            'serves_alcohol' => '0', 'alcohol_permit_status' => 'no_alcohol',
            'refund_policy_deadline_hours' => 48, 'refund_policy_notes' => 'Refunds up to two days before the event.',
            'ticket_tiers_submitted' => '1',
            'ticket_tiers' => [['name' => 'General Admission', 'price' => '15.00', 'available_stock' => 100, 'max_per_customer' => 6]],
            'creation_key' => (string) Str::uuid(),
        ], $this->eventComplianceFields(), $overrides);
    }
}
