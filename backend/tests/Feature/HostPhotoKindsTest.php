<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventMedia;
use App\Models\User;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A host's photos by kind: a main photo (the event's picture when there is no flyer, one at a time) and venue photos
 * (shown with the venue), alongside the gallery.
 */
class HostPhotoKindsTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = 'evt-kingston-harbour-fry';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['media.disk' => 'local']);
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
        Event::allTenants()->whereKey(self::EVENT)->update(['status' => 'published', 'flyer_path' => null]);
    }

    private function host(): User
    {
        return User::where('email', 'host@islandevents.example')->firstOrFail();
    }

    private function upload(string $kind, string $alt)
    {
        return $this->actingAs($this->host())->post(route('events.photos.store', self::EVENT), [
            'kind' => $kind, 'photo' => UploadedFile::fake()->image('photo.jpg', 1600, 1000), 'alt_text' => $alt,
            'position_x' => 50, 'position_y' => 40,
        ]);
    }

    public function test_the_main_photo_is_the_events_picture_until_a_flyer_is_uploaded(): void
    {
        $this->upload('cover', 'Crowd at the pavilion during last year\'s fish fry')->assertSessionHasNoErrors();
        $main = EventMedia::where('event_id', self::EVENT)->where('media_type', 'cover')->firstOrFail();

        $cover = Event::allTenants()->findOrFail(self::EVENT)->coverImage();
        $this->assertFalse($cover->illustrative, 'the host\'s own photo, not an illustration');
        $this->assertFalse($cover->generated);
        $this->assertSame($main->responsiveImage()->src, $cover->src);

        // A second main photo replaces the first.
        $this->upload('cover', 'Cooks at the frying pots by the water')->assertSessionHasNoErrors();
        $this->assertSame(1, EventMedia::where('event_id', self::EVENT)->where('media_type', 'cover')->count());
        $this->assertNull(EventMedia::find($main->id));

        // The host's flyer still comes first.
        Event::allTenants()->whereKey(self::EVENT)->update(['flyer_path' => 'https://example.test/our-flyer.jpg']);
        $this->assertSame('https://example.test/our-flyer.jpg', Event::allTenants()->findOrFail(self::EVENT)->coverImage()->src);
    }

    public function test_venue_photos_appear_with_the_venue_and_not_in_the_gallery(): void
    {
        $this->upload('venue', 'The covered pavilion with picnic tables')->assertSessionHasNoErrors();
        app('auth')->forgetGuards();

        $page = $this->get(route('events.show', self::EVENT))->assertOk();
        $page->assertSee('Photos of the venue', false);
        $page->assertSee('The covered pavilion with picnic tables', false);
        $this->assertCount(0, Event::allTenants()->findOrFail(self::EVENT)->visibleGallery());
    }

    public function test_only_the_known_kinds_are_accepted_and_others_cannot_upload(): void
    {
        $this->upload('poster', 'A poster of some kind')->assertSessionHasErrors('kind');

        $attendee = User::where('email', 'attendee@islandevents.example')->firstOrFail();
        $this->actingAs($attendee)->post(route('events.photos.store', self::EVENT), [
            'kind' => 'venue', 'photo' => UploadedFile::fake()->image('p.jpg', 1200, 800), 'alt_text' => 'Not my event',
        ])->assertNotFound();
        $this->assertSame(0, EventMedia::where('event_id', self::EVENT)->count());
    }
}
