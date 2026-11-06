<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\MediaStorageService;
use App\Support\ImageSubjects;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Performance and accessibility of images: optimized sample copies, cache headers a CDN can use, a preloaded main
 * image, broken-image fallbacks, an accessible photo viewer, and commands that add variants without touching
 * originals and report every image relationship.
 */
class ImagePerformanceTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = 'evt-kingston-harbour-fry';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
        Event::allTenants()->whereKey(self::EVENT)->update(['status' => 'published']);
    }

    private function host(): User
    {
        return User::where('email', 'host@islandevents.example')->firstOrFail();
    }

    public function test_sample_photos_are_served_as_small_optimized_copies(): void
    {
        $sources = ImageSubjects::optimizedSources('/images/jerk_chicken.jpg');
        $this->assertArrayHasKey('image/webp', $sources, 'Run php artisan images:optimize-samples to make the copies.');
        $this->assertStringContainsString('/images/optimized/jerk_chicken-small.webp 400w', $sources['image/webp']);
        $this->assertLessThan(filesize(public_path('images/jerk_chicken.jpg')) / 10, filesize(public_path('images/optimized/jerk_chicken-small.webp')));

        $item = MenuItem::withoutGlobalScopes()->where('event_id', self::EVENT)->firstOrFail()->replicate(['id'])
            ->forceFill(['name' => 'Jerk Chicken Plate', 'image_url' => '/images/jerk_chicken.jpg', 'image_path' => null]);
        $image = $item->responsiveImage();
        $this->assertSame([1200, 896], [$image->width, $image->height], 'Intrinsic size, so the card does not jump.');
        $this->assertNotEmpty($image->sources);
        $this->assertNull(ImageSubjects::sample('/images/optimized/jerk_chicken-small.webp'), 'Copies are not samples in their own right.');
    }

    public function test_cache_headers_let_a_cdn_keep_public_images_and_never_private_ones(): void
    {
        $this->actingAs($this->host())->post('/api/v1/host/events/' . self::EVENT . '/flyer',
            ['flyer' => UploadedFile::fake()->image('f.jpg', 900, 600)], ['Accept' => 'application/json'])->assertOk();
        auth()->logout();
        $base = '/api/v1/media/events/' . self::EVENT . '/flyer';

        $this->get($base . '?variant=medium&v=abc')->assertOk()->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
        $this->get($base . '?variant=medium')->assertOk()->assertHeader('Cache-Control', 'max-age=300, public');

        Event::allTenants()->whereKey(self::EVENT)->update(['status' => 'unpublished']);
        $this->actingAs($this->host())->get($base . '?v=abc')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame('local', MediaStorageService::diskName(), 'The private local disk unless MEDIA_DISK says otherwise.');
    }

    public function test_the_event_page_preloads_its_main_image_and_offers_an_accessible_photo_viewer(): void
    {
        $host = $this->host();
        $this->actingAs($host)->post(route('events.flyer.upload', self::EVENT), ['flyer' => UploadedFile::fake()->image('f.jpg', 1600, 900)]);
        $this->actingAs($host)->post(route('events.photos.store', self::EVENT), ['photo' => UploadedFile::fake()->image('p.jpg', 1200, 800), 'alt_text' => 'Tables by the water']);

        $page = $this->actingAs(User::where('email', 'attendee@islandevents.example')->firstOrFail())->get(route('events.show', self::EVENT))->assertOk();
        $page->assertSee('<link rel="preload" as="image"', false)->assertSee('imagesrcset="/api/v1/media/events/' . self::EVENT . '/flyer', false);
        $page->assertSee('fetchpriority="high"', false);
        $page->assertSee('<dialog class="eb-lightbox" id="photo-lightbox"', false);
        // The host's gallery photo comes first; the total includes the flyer and matching dish samples.
        $this->assertMatchesRegularExpression('/aria-label="Open photo 1 of \d+: Tables by the water"/', $page->getContent());
        $page->assertSee('aria-haspopup="dialog"', false);
        $page->assertSee('aria-label="Previous photo"', false)->assertSee('aria-label="Next photo"', false);
        $page->assertDontSee("showDetailToast('Viewing: '", false);
    }

    public function test_a_stored_flyer_that_fails_to_load_falls_back_to_the_events_own_artwork(): void
    {
        $event = Event::allTenants()->whereKey(self::EVENT)->firstOrFail()->forceFill(['flyer_path' => 'tenants/x/flyers/gone.jpg', 'flyer_variants' => null]);
        $html = Blade::render('<x-responsive-image :image="$image" />', ['image' => $event->coverImage()]);

        $this->assertStringContainsString('onerror="this.onerror=null;', $html);
        $this->assertStringContainsString('/events/' . self::EVENT . '/flyer.svg', $html);

        // Callers that handle errors themselves (food cards, gallery tiles) keep their own handler.
        $own = Blade::render('<x-responsive-image :image="$image" onerror="custom()" />', ['image' => $event->coverImage()]);
        $this->assertStringContainsString('onerror="custom()"', $own);
        $this->assertStringNotContainsString('this.onerror=null', $own);
    }

    public function test_variant_backfill_keeps_originals_and_the_audit_reports_relationships(): void
    {
        // An upload from before variants existed: an original with no variants recorded.
        $item = MenuItem::withoutGlobalScopes()->where('event_id', self::EVENT)->firstOrFail();
        $original = 'tenants/org-kingston-youth/menu/legacy.jpg';
        Storage::disk('local')->put($original, UploadedFile::fake()->image('legacy.jpg', 800, 800)->getContent());
        $before = sha1(Storage::disk('local')->get($original));
        $item->forceFill(['image_path' => $original, 'image_variants' => null])->save();

        Artisan::call('images:make-variants', ['--dry-run' => true]);
        $this->assertNull($item->fresh()->image_variants, 'A dry run changes nothing.');

        Artisan::call('images:make-variants');
        $variants = $item->fresh()->image_variants;
        $this->assertCount(3, $variants);
        Storage::disk('local')->assertExists($variants['small']['webp']);
        $this->assertSame($before, sha1(Storage::disk('local')->get($original)), 'The original is untouched.');

        $other = MenuItem::withoutGlobalScopes()->where('event_id', self::EVENT)->where('id', '!=', $item->id)->firstOrFail();
        $other->forceFill(['name' => 'Grilled Lobster Tail', 'image_url' => '/images/escovitch_fish.jpg', 'image_path' => null])->save();
        Artisan::call('images:audit', ['--only-problems' => true]);
        $report = Artisan::output();
        $this->assertStringContainsString('Grilled Lobster Tail', $report);
        $this->assertStringContainsString('not this dish: fallback shows', $report);
    }
}
