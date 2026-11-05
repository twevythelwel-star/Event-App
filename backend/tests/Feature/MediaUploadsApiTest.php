<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MenuItem;
use App\Models\OrganizerUser;
use App\Models\User;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\DemoOrdersSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaUploadsApiTest extends TestCase
{
    use RefreshDatabase;

    private const KINGSTON = 'evt-kingston-harbour-fry';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class, DemoOrdersSeeder::class]);
    }

    private function user(string $who): User
    {
        return User::where('email', "{$who}@islandevents.example")->firstOrFail();
    }

    private function uploadFlyer(User $user, UploadedFile $file, array $metadata = [])
    {
        return $this->actingAs($user)->post('/api/v1/host/events/' . self::KINGSTON . '/flyer', ['flyer' => $file, ...$metadata], ['Accept' => 'application/json']);
    }

    private function storedFiles(string $kind): array
    {
        return Storage::disk('local')->files("tenants/org-kingston-youth/{$kind}");
    }

    public function test_host_uploads_a_flyer_stored_privately_per_organizer(): void
    {
        $response = $this->uploadFlyer($this->user('host'), UploadedFile::fake()->image('flyer.png', 1200, 800), [
            'alt_text' => 'Crowd at the Kingston harbour stage',
            'position_x' => 64,
            'position_y' => 28,
        ])->assertOk();

        $files = $this->storedFiles('flyers');
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.png', $files[0]);
        $this->assertStringStartsWith('/api/v1/media/events/' . self::KINGSTON . '/flyer?v=', $response->json('data.flyerUrl'));
        $this->assertSame(['width' => 1200, 'height' => 800], $response->json('data.flyerDimensions'));
        $this->assertSame('image/png', $response->json('data.flyerMimeType'));
        $this->assertSame('Crowd at the Kingston harbour stage', $response->json('data.flyerAltText'));
        $this->assertSame(64, $response->json('data.flyerPositionX'));
        $this->assertSame(28, $response->json('data.flyerPositionY'));
        $this->assertStringContainsString('variant=thumbnail', $response->json('data.flyerThumbnailUrl'));
        $this->assertCount(1, $this->storedFiles('flyers-derivatives'));
        $this->get($response->json('data.flyerThumbnailUrl'))->assertOk()->assertHeader('Content-Type', 'image/webp');

        // A replacement deletes the previous file
        $this->uploadFlyer($this->user('host'), UploadedFile::fake()->image('new.jpg', 900, 600))->assertOk();
        $this->assertCount(1, $this->storedFiles('flyers'));
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'event_flyer_uploaded']);
    }

    public function test_uploads_are_reencoded_so_appended_payloads_are_dropped(): void
    {
        $jpeg = UploadedFile::fake()->image('clean.jpg', 800, 600)->getContent();
        $tampered = UploadedFile::fake()->createWithContent('flyer.jpg', $jpeg . '<?php system($_GET["c"]); ?>');

        $this->uploadFlyer($this->user('host'), $tampered)->assertOk();

        $stored = Storage::disk('local')->get($this->storedFiles('flyers')[0]);
        $this->assertStringNotContainsString('<?php', $stored);
    }

    public function test_invalid_flyers_are_rejected(): void
    {
        $host = $this->user('host');

        $this->uploadFlyer($host, UploadedFile::fake()->createWithContent('logo.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'))
            ->assertStatus(422);
        $this->uploadFlyer($host, UploadedFile::fake()->image('tiny.jpg', 120, 90))
            ->assertStatus(422)
            ->assertJsonPath('error', 'The image is 120x90px; the minimum is 300x200px.');
        $this->uploadFlyer($host, UploadedFile::fake()->image('anim.gif', 800, 600))->assertStatus(422);
        $this->uploadFlyer($host, UploadedFile::fake()->create('huge.jpg', 6000, 'image/jpeg'))
            ->assertStatus(422)
            ->assertJsonPath('error', 'Images must be 5 MB or smaller.');

        $this->assertCount(0, $this->storedFiles('flyers'));
        $this->assertCount(0, $this->storedFiles('flyers-derivatives'));
    }

    public function test_gallery_and_brand_images_are_private_validated_and_removable(): void
    {
        $host = $this->user('host');
        $base = '/api/v1/host/events/' . self::KINGSTON . '/media';
        $response = $this->actingAs($host)->post($base, [
            'photo' => UploadedFile::fake()->image('sponsor.webp', 800, 600),
            'media_type' => 'sponsor_logo',
            'alt_text' => 'Harbour Festival supporting sponsor',
            'position_x' => 40,
            'position_y' => 60,
        ], ['Accept' => 'application/json'])->assertCreated();

        $media = $response->json('data.eventMedia.0');
        $this->assertSame('sponsor_logo', $media['mediaType']);
        $this->assertSame('Harbour Festival supporting sponsor', $media['altText']);
        $this->assertSame(40, $media['positionX']);
        $this->assertSame(60, $media['positionY']);
        $this->assertCount(1, $this->storedFiles('event-media'));
        $this->assertCount(1, $this->storedFiles('event-media-derivatives'));
        $this->get($media['thumbnailUrl'])->assertOk()->assertHeader('Content-Type', 'image/webp');

        $this->actingAs($host)->deleteJson("{$base}/{$media['id']}")->assertOk()->assertJsonCount(0, 'data.eventMedia');
        $this->assertCount(0, $this->storedFiles('event-media'));
        $this->assertCount(0, $this->storedFiles('event-media-derivatives'));
    }

    public function test_only_the_events_owners_can_upload(): void
    {
        $verifier = User::create(['name' => 'Treasurer', 'email' => 'treasurer@example.test', 'password' => bcrypt('x'), 'status' => 'active']);
        OrganizerUser::create(['organizer_id' => 'org-kingston-youth', 'user_id' => $verifier->id, 'role' => 'payment_verifier']);

        $this->uploadFlyer($verifier, UploadedFile::fake()->image('f.jpg', 800, 600))->assertNotFound();
        $this->uploadFlyer($this->user('staff'), UploadedFile::fake()->image('f.jpg', 800, 600))->assertNotFound();

        $other = Event::withoutGlobalScopes()->where('organizer_id', 'org-hellshire-fishers')->firstOrFail();
        $this->actingAs($this->user('host'))
            ->post("/api/v1/host/events/{$other->id}/flyer", ['flyer' => UploadedFile::fake()->image('f.jpg', 800, 600)], ['Accept' => 'application/json'])
            ->assertNotFound();

        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_flyers_are_public_only_while_the_event_is_published(): void
    {
        $host = $this->user('host');
        $url = $this->uploadFlyer($host, UploadedFile::fake()->image('flyer.jpg', 800, 600))->json('data.flyerUrl');

        auth()->logout();
        $this->get($url)->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'image/jpeg');

        $this->actingAs($host)->postJson('/api/v1/host/events/' . self::KINGSTON . '/status', ['status' => 'unpublished'])->assertOk();

        auth()->logout();
        $this->get($url)->assertNotFound();

        $otherHost = User::create(['name' => 'Other Host', 'email' => 'other@example.test', 'password' => bcrypt('x'), 'status' => 'active']);
        OrganizerUser::create(['organizer_id' => 'org-hellshire-fishers', 'user_id' => $otherHost->id, 'role' => 'event_owner']);
        $this->actingAs($otherHost)->get($url)->assertNotFound();

        $response = $this->actingAs($host)->get($url)->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_removing_a_flyer_deletes_the_file(): void
    {
        $host = $this->user('host');
        $this->uploadFlyer($host, UploadedFile::fake()->image('flyer.jpg', 800, 600))->assertOk();

        $this->actingAs($host)->deleteJson('/api/v1/host/events/' . self::KINGSTON . '/flyer')
            ->assertOk()
            ->assertJsonPath('data.flyerUrl', null);

        $this->assertCount(0, $this->storedFiles('flyers'));
    }

    public function test_menu_photo_upload_replacement_and_visibility(): void
    {
        $host = $this->user('host');
        $item = MenuItem::withoutGlobalScopes()->where('event_id', self::KINGSTON)->firstOrFail();
        $base = '/api/v1/host/events/' . self::KINGSTON . "/menu/{$item->id}";

        $menu = $this->actingAs($host)
            ->post("{$base}/photo", ['photo' => UploadedFile::fake()->image('dish.webp', 600, 600)], ['Accept' => 'application/json'])
            ->assertOk()
            ->json('data.menu');
        $photoUrl = collect($menu)->firstWhere('id', $item->id)['imageUrl'];
        $this->assertStringStartsWith("/api/v1/media/menu/{$item->id}/photo?v=", $photoUrl);
        $this->assertCount(1, $this->storedFiles('menu'));

        auth()->logout();
        $this->get($photoUrl)->assertOk()->assertHeader('Content-Type', 'image/webp');

        // Editing the item without touching its photo keeps the file
        $this->actingAs($host)->putJson($base, ['price_jmd' => 1700, 'image_url' => $photoUrl])->assertOk();
        $this->assertCount(1, $this->storedFiles('menu'));

        // Switching to a link removes the stored photo
        $this->actingAs($host)->putJson($base, ['image_url' => '/images/oxtail.jpg'])->assertOk();
        $this->assertCount(0, $this->storedFiles('menu'));
        auth()->logout();
        $this->get($photoUrl)->assertNotFound();
    }

    public function test_image_links_must_be_http_or_site_paths(): void
    {
        $host = $this->user('host');
        $item = MenuItem::withoutGlobalScopes()->where('event_id', self::KINGSTON)->firstOrFail();
        $base = '/api/v1/host/events/' . self::KINGSTON . "/menu/{$item->id}";

        $this->actingAs($host)->putJson($base, ['image_url' => 'javascript:alert(1)'])->assertStatus(422);
        $this->actingAs($host)->putJson($base, ['image_url' => 'data:image/png;base64,AAAA'])->assertStatus(422);
        $this->actingAs($host)->putJson($base, ['image_url' => 'https://cdn.example.com/dish.jpg'])->assertOk();
    }

    public function test_cover_image_type_is_supported(): void
    {
        $host = $this->user('host');
        $base = '/api/v1/host/events/' . self::KINGSTON . '/media';

        $response = $this->actingAs($host)->post($base, [
            'photo' => UploadedFile::fake()->image('cover.webp', 1200, 630),
            'media_type' => 'cover',
            'alt_text' => 'Event cover image for social sharing',
            'position_x' => 50,
            'position_y' => 50,
        ], ['Accept' => 'application/json'])->assertCreated();

        $media = $response->json('data.eventMedia.0');
        $this->assertSame('cover', $media['mediaType']);
        $this->assertSame('Event cover image for social sharing', $media['altText']);
    }

    public function test_cropping_parameters_are_stored(): void
    {
        $host = $this->user('host');
        $base = '/api/v1/host/events/' . self::KINGSTON . '/media';

        $response = $this->actingAs($host)->post($base, [
            'photo' => UploadedFile::fake()->image('crop-test.jpg', 1200, 800),
            'media_type' => 'gallery',
            'alt_text' => 'Test image with crop',
            'position_x' => 30,
            'position_y' => 40,
            'crop_x' => 10,
            'crop_y' => 15,
            'crop_width' => 80,
            'crop_height' => 70,
        ], ['Accept' => 'application/json'])->assertCreated();

        $media = $response->json('data.eventMedia.0');
        $this->assertSame(10, $media['cropX']);
        $this->assertSame(15, $media['cropY']);
        $this->assertSame(80, $media['cropWidth']);
        $this->assertSame(70, $media['cropHeight']);
    }

    public function test_responsive_derivatives_are_generated(): void
    {
        $host = $this->user('host');
        $base = '/api/v1/host/events/' . self::KINGSTON . '/media';

        $response = $this->actingAs($host)->post($base, [
            'photo' => UploadedFile::fake()->image('responsive.jpg', 1600, 900),
            'media_type' => 'gallery',
            'alt_text' => 'Responsive test image',
        ], ['Accept' => 'application/json'])->assertCreated();

        $media = $response->json('data.eventMedia.0');
        $this->assertNotNull($media['smallThumbnailUrl']);
        $this->assertNotNull($media['mediumThumbnailUrl']);
        $this->assertNotNull($media['largeThumbnailUrl']);
        $this->assertStringContainsString('variant=small', $media['smallThumbnailUrl']);
        $this->assertStringContainsString('variant=medium', $media['mediumThumbnailUrl']);
        $this->assertStringContainsString('variant=large', $media['largeThumbnailUrl']);
    }

    public function test_flyer_responsive_derivatives_are_included(): void
    {
        $host = $this->user('host');
        $response = $this->uploadFlyer($host, UploadedFile::fake()->image('flyer-responsive.jpg', 1600, 900))->assertOk();

        $this->assertNotNull($response->json('data.flyerSmallUrl'));
        $this->assertNotNull($response->json('data.flyerMediumUrl'));
        $this->assertNotNull($response->json('data.flyerLargeUrl'));
        $this->assertStringContainsString('variant=small', $response->json('data.flyerSmallUrl'));
        $this->assertStringContainsString('variant=medium', $response->json('data.flyerMediumUrl'));
        $this->assertStringContainsString('variant=large', $response->json('data.flyerLargeUrl'));
    }

    public function test_media_deletion_removes_all_derivatives(): void
    {
        $host = $this->user('host');
        $base = '/api/v1/host/events/' . self::KINGSTON . '/media';

        $response = $this->actingAs($host)->post($base, [
            'photo' => UploadedFile::fake()->image('delete-test.jpg', 1200, 800),
            'media_type' => 'gallery',
            'alt_text' => 'Delete test image',
        ], ['Accept' => 'application/json'])->assertCreated();

        $media = $response->json('data.eventMedia.0');
        $this->assertCount(1, $this->storedFiles('event-media'));
        $this->assertCount(3, $this->storedFiles('event-media-derivatives'));

        $this->actingAs($host)->deleteJson("{$base}/{$media['id']}")->assertOk();
        $this->assertCount(0, $this->storedFiles('event-media'));
        $this->assertCount(0, $this->storedFiles('event-media-derivatives'));
    }

    public function test_original_image_is_never_overwritten(): void
    {
        $host = $this->user('host');
        $base = '/api/v1/host/events/' . self::KINGSTON . '/media';

        $response = $this->actingAs($host)->post($base, [
            'photo' => UploadedFile::fake()->image('original.jpg', 1200, 800),
            'media_type' => 'gallery',
            'alt_text' => 'Original test',
        ], ['Accept' => 'application/json'])->assertCreated();

        $media = $response->json('data.eventMedia.0');
        $originalUrl = $media['imageUrl'];
        $derivativeUrl = $media['largeThumbnailUrl'];

        // Original and derivative URLs should be different
        $this->assertNotSame($originalUrl, $derivativeUrl);

        // Derivative should be WebP
        $this->actingAs($host)->get($derivativeUrl)->assertHeader('Content-Type', 'image/webp');

        // Original should preserve its MIME type
        $this->actingAs($host)->get($originalUrl)->assertHeader('Content-Type', 'image/jpeg');
    }
}
