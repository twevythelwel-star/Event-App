<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventMedia;
use App\Models\MenuItem;
use App\Models\User;
use App\Models\UserRole;
use App\Services\MediaStorageService;
use App\Support\ResponsiveImage;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\DemoOrdersSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Image management across the website: uploads go through one secure path (validated, re-encoded, original kept,
 * responsive AVIF/WebP variants), hosts manage a gallery with alt text and focal points, administrators can hide an
 * image without deleting it, and pages render every image through <x-responsive-image>.
 */
class ImageManagementTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = 'evt-kingston-harbour-fry';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('s3_flyers');
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class, DemoOrdersSeeder::class]);
        Event::allTenants()->whereKey(self::EVENT)->update(['status' => 'published']);
    }

    private function user(string $who): User
    {
        return User::where('email', "{$who}@islandevents.example")->firstOrFail();
    }

    private function event(): Event
    {
        return Event::allTenants()->whereKey(self::EVENT)->firstOrFail();
    }

    private function systemAdmin(): User
    {
        $user = User::create(['name' => 'Root Admin', 'email' => 'root@islandevents.example', 'password' => bcrypt('password'), 'status' => 'active', 'is_system_admin' => true]);
        UserRole::create(['user_id' => $user->id, 'role' => 'system_admin', 'is_active' => true, 'assigned_at' => now()]);

        return $user;
    }

    public function test_the_websites_flyer_upload_is_sanitized_keeps_the_original_and_makes_every_variant(): void
    {
        $jpeg = UploadedFile::fake()->image('flyer.jpg', 1600, 1000)->getContent();
        $tampered = UploadedFile::fake()->createWithContent('flyer.jpg', $jpeg . '<?php echo "payload"; ?>');

        $this->actingAs($this->user('host'))->post(route('events.flyer.upload', self::EVENT), ['flyer' => $tampered])
            ->assertRedirect()->assertSessionHas('status');

        $event = $this->event();
        $this->assertStringStartsWith('tenants/', $event->flyer_path);
        $this->assertStringNotContainsString('payload', Storage::disk('local')->get($event->flyer_path), 'Re-encoded: appended bytes are gone.');
        $this->assertSame([1600, 1000], [$event->flyer_width, $event->flyer_height], 'Dimensions are recorded.');
        foreach (MediaStorageService::SIZES as $size => $edge) {
            $this->assertSame($edge, max($event->flyer_variants[$size]['width'], $event->flyer_variants[$size]['height']));
            foreach (MediaStorageService::variantFormats() as $format) {
                Storage::disk('local')->assertExists($event->flyer_variants[$size][$format]);
            }
        }
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'event_flyer_uploaded']);

        // Each size and format is served with its own type; anything else is the untouched original.
        $this->get("/api/v1/media/events/" . self::EVENT . '/flyer?variant=small&format=webp')->assertOk()->assertHeader('Content-Type', 'image/webp');
        if (in_array('avif', MediaStorageService::variantFormats(), true)) {
            $this->get("/api/v1/media/events/" . self::EVENT . '/flyer?variant=large&format=avif')->assertOk()->assertHeader('Content-Type', 'image/avif');
        }
        $this->get("/api/v1/media/events/" . self::EVENT . '/flyer')->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        // Removing deletes the original and every variant.
        $paths = collect($event->flyer_variants)->flatMap(fn ($v) => array_values(array_filter($v, 'is_string')))->push($event->flyer_path)->all();
        $this->actingAs($this->user('host'))->delete(route('events.flyer.remove', self::EVENT))->assertRedirect();
        foreach ($paths as $path) {
            Storage::disk('local')->assertMissing($path);
        }
    }

    public function test_a_non_image_flyer_is_refused_without_losing_the_rest_of_the_event(): void
    {
        $this->actingAs($this->user('host'))
            ->post(route('events.flyer.upload', self::EVENT), ['flyer' => UploadedFile::fake()->create('flyer.jpg', 20, 'image/jpeg')])
            ->assertSessionHasErrors();
        $this->assertNull($this->event()->flyer_variants);
    }

    public function test_crop_is_read_in_percent_and_only_shapes_the_variants(): void
    {
        $this->actingAs($this->user('host'))->post('/api/v1/host/events/' . self::EVENT . '/flyer', [
            'flyer' => UploadedFile::fake()->image('wide.jpg', 2000, 1000),
            'crop_x' => 25, 'crop_y' => 0, 'crop_width' => 50, 'crop_height' => 100,
        ], ['Accept' => 'application/json'])->assertOk();

        $event = $this->event();
        // Half the width, full height: a 1000 × 1000 square, scaled to each size.
        $this->assertSame([1000, 1000], [$event->flyer_variants['large']['width'], $event->flyer_variants['large']['height']]);
        $this->assertSame([2000, 1000], [$event->flyer_width, $event->flyer_height], 'The original keeps its full size.');
        $this->assertSame([2000, 1000], array_slice(getimagesizefromstring(Storage::disk('local')->get($event->flyer_path)), 0, 2));
    }

    public function test_hosts_manage_a_gallery_that_guests_see_on_the_event_page(): void
    {
        $host = $this->user('host');
        $this->actingAs($host)->post(route('events.photos.store', self::EVENT), [
            'photo' => UploadedFile::fake()->image('venue.jpg', 1200, 800), 'alt_text' => '', 'position_x' => 30, 'position_y' => 70,
        ])->assertSessionHasErrors('alt_text');

        $this->actingAs($host)->post(route('events.photos.store', self::EVENT), [
            'photo' => UploadedFile::fake()->image('venue.jpg', 1200, 800), 'alt_text' => 'Long tables by the harbour at dusk', 'position_x' => 30, 'position_y' => 70,
        ])->assertRedirect(route('events.edit', self::EVENT) . '#event-photos');

        $media = EventMedia::where('event_id', self::EVENT)->where('media_type', 'gallery')->firstOrFail();
        $this->assertSame((string) $host->id, (string) $media->uploaded_by);
        $this->assertSame([30, 70], [$media->position_x, $media->position_y]);
        $this->assertCount(3, $media->variants);

        // The edit page lists it; the event page shows it to a guest, with its alt text and focal point.
        $this->actingAs($host)->get(route('events.edit', self::EVENT))->assertOk()->assertSee('Long tables by the harbour at dusk')->assertSee('Event photos (1 of 12)');
        auth()->logout();
        $guest = $this->user('attendee');
        $page = $this->actingAs($guest)->get(route('events.show', self::EVENT))->assertOk();
        // The photo is named by the button that opens it (the image inside is alt="", so it is not read twice).
        $this->assertMatchesRegularExpression('/aria-label="Open photo 1 of \d+: Long tables by the harbour at dusk"/', $page->getContent());
        $page->assertSee('data-lightbox-caption="Long tables by the harbour at dusk"', false)->assertSee('object-position: 30% 70%', false);
        $page->assertSee('/api/v1/media/event-media/' . $media->id . '?variant=small', false);
        $this->get('/api/v1/media/event-media/' . $media->id . '?variant=medium')->assertOk()->assertHeader('Content-Type', 'image/webp');

        // Another host cannot change or remove it.
        $other = User::create(['name' => 'Other Host', 'email' => 'other-host@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        $this->actingAs($other)->delete(route('events.photos.destroy', [self::EVENT, $media->id]))->assertNotFound();

        // The host edits its details, then removes it with every file.
        $this->actingAs($host)->patch(route('events.photos.update', [self::EVENT, $media->id]), ['alt_text' => 'Harbour tables', 'position_x' => 50, 'position_y' => 40])->assertRedirect();
        $this->assertSame('Harbour tables', $media->fresh()->alt_text);
        $files = Storage::disk('local')->allFiles('tenants');
        $this->actingAs($host)->delete(route('events.photos.destroy', [self::EVENT, $media->id]))->assertRedirect();
        $this->assertDatabaseMissing('event_media', ['id' => $media->id]);
        $this->assertCount(count($files) - 1 - 3 * count(MediaStorageService::variantFormats()), Storage::disk('local')->allFiles('tenants'));
    }

    public function test_administrators_hide_and_restore_an_image_without_deleting_it(): void
    {
        $host = $this->user('host');
        $this->actingAs($host)->post(route('events.flyer.upload', self::EVENT), ['flyer' => UploadedFile::fake()->image('flyer.png', 1200, 800)]);
        $this->actingAs($host)->post(route('events.photos.store', self::EVENT), ['photo' => UploadedFile::fake()->image('p.jpg', 800, 600), 'alt_text' => 'The stage']);
        $media = EventMedia::where('event_id', self::EVENT)->firstOrFail();
        $flyerUrl = '/api/v1/media/events/' . self::EVENT . '/flyer';
        $admin = $this->systemAdmin();

        $this->actingAs($admin)->get(route('admin.console.events.show', self::EVENT))->assertOk()->assertSee('Uploaded images (2)');
        $this->actingAs($admin)->post(route('admin.console.events.images.moderate', self::EVENT), ['kind' => 'flyer', 'decision' => 'hide', 'reason' => 'Shows a private phone number'])
            ->assertRedirect()->assertSessionHas('success');
        $this->actingAs($admin)->post(route('admin.console.events.images.moderate', self::EVENT), ['kind' => 'gallery', 'target_id' => $media->id, 'decision' => 'hide', 'reason' => 'Not from this event'])
            ->assertSessionHas('success');
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'image_hidden', 'target_id' => $media->id]);

        $event = $this->event();
        Storage::disk('local')->assertExists($event->flyer_path);
        $this->assertFalse($event->hasFlyer());
        $this->assertStringContainsString('flyer.svg', $event->display_image_url, 'The generated cover shows instead.');

        // Guests no longer get either image; the host and the administrator still can.
        auth()->logout();
        $this->get($flyerUrl)->assertNotFound();
        $this->get('/api/v1/media/event-media/' . $media->id)->assertNotFound();
        $this->actingAs($host)->get($flyerUrl)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($host)->get(route('events.edit', self::EVENT))->assertSee('Hidden by an administrator')->assertSee('Not from this event');
        $this->actingAs($this->user('attendee'))->get(route('events.show', self::EVENT))->assertDontSee('event-media/' . $media->id, false);

        $this->actingAs($admin)->post(route('admin.console.events.images.moderate', self::EVENT), ['kind' => 'flyer', 'decision' => 'restore', 'reason' => 'Host blurred the number'])
            ->assertSessionHas('success');
        auth()->logout();
        $this->get($flyerUrl)->assertOk();
        $this->assertTrue($this->event()->hasFlyer());
    }

    public function test_hosts_cannot_moderate_and_a_new_upload_replaces_a_hidden_flyer(): void
    {
        $host = $this->user('host');
        $this->actingAs($host)->post(route('admin.console.events.images.moderate', self::EVENT), ['kind' => 'flyer', 'decision' => 'restore', 'reason' => 'I want it back'])
            ->assertStatus(403);

        $this->actingAs($host)->post(route('events.flyer.upload', self::EVENT), ['flyer' => UploadedFile::fake()->image('a.png', 900, 600)]);
        Event::allTenants()->whereKey(self::EVENT)->update(['flyer_moderation_status' => 'hidden']);
        $this->actingAs($host)->post(route('events.flyer.upload', self::EVENT), ['flyer' => UploadedFile::fake()->image('b.png', 900, 600)]);
        $this->assertSame('approved', $this->event()->flyer_moderation_status, 'A new flyer is a new image, shown until reviewed.');
    }

    public function test_menu_photos_get_variants_and_alt_text_and_render_responsively(): void
    {
        $host = $this->user('host');
        $item = MenuItem::withoutGlobalScopes()->where('event_id', self::EVENT)->firstOrFail();
        $this->actingAs($host)->post("/api/v1/host/events/" . self::EVENT . "/menu/{$item->id}/photo",
            ['photo' => UploadedFile::fake()->image('dish.jpg', 900, 900)], ['Accept' => 'application/json'])->assertOk();
        $item->refresh()->forceFill(['image_alt_text' => 'Fried snapper with pickled peppers', 'image_position' => '50% 20%'])->save();

        $image = $item->responsiveImage();
        $this->assertTrue($image->uploaded);
        $this->assertSame('Fried snapper with pickled peppers', $image->alt);
        $this->assertSame('50% 20%', $image->objectPosition());
        $this->assertArrayHasKey('image/webp', $image->sources);
        $this->assertSame(3, substr_count($image->sources['image/webp'], 'w,') + 1);

        $html = view('components.food-card', ['item' => $item, 'currency' => 'USD', 'layout' => 'card',
            'slot' => new \Illuminate\Support\HtmlString(''), 'attributes' => new \Illuminate\View\ComponentAttributeBag()])->render();
        $this->assertStringContainsString('<picture', $html);
        $this->assertStringContainsString('type="image/webp"', $html);
        $this->assertStringContainsString('alt="Fried snapper with pickled peppers"', $html);
        $this->assertStringNotContainsString('Illustrative photo', $html);

        // Hidden by an administrator: the card falls back to the plain placeholder.
        $item->forceFill(['image_moderation_status' => 'hidden'])->save();
        $this->assertNull($item->fresh()->responsiveImage());
    }

    public function test_the_image_component_renders_sources_size_alt_and_focal_point(): void
    {
        $image = new ResponsiveImage('/a-large.webp', ['image/avif' => '/a-400.avif 400w, /a-800.avif 800w', 'image/webp' => '/a-400.webp 400w'],
            1600, 900, 'A crowd by the stage', 30, 70, uploaded: true);
        $html = Blade::render('<x-responsive-image :image="$image" sizes="50vw" class="hero" />', ['image' => $image]);

        $this->assertStringContainsString('<source type="image/avif" srcset="/a-400.avif 400w, /a-800.avif 800w" sizes="50vw">', $html);
        $this->assertStringContainsString('width="1600" height="900"', $html);
        $this->assertStringContainsString('alt="A crowd by the stage"', $html);
        $this->assertStringContainsString('object-position: 30% 70%', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertMatchesRegularExpression('/<img[^>]*class="hero"/', $html, 'Classes land on the image, not the wrapper.');

        $decorative = Blade::render('<x-responsive-image :image="$image" alt="" :eager="true" />', ['image' => $image]);
        $this->assertStringContainsString('alt=""', $decorative);
        $this->assertStringContainsString('fetchpriority="high"', $decorative);
    }

    public function test_events_without_an_upload_never_pretend_to_have_a_photo(): void
    {
        $event = $this->event()->forceFill(['flyer_path' => null, 'flyer_variants' => null]);
        $cover = $event->coverImage();
        $this->assertFalse($cover->uploaded);
        $this->assertSame([], $cover->sources);
        $this->assertStringContainsString('flyer.svg', $cover->src);
    }
}
