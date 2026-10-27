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

    private function uploadFlyer(User $user, UploadedFile $file)
    {
        return $this->actingAs($user)->post('/api/v1/host/events/' . self::KINGSTON . '/flyer', ['flyer' => $file], ['Accept' => 'application/json']);
    }

    private function storedFiles(string $kind): array
    {
        return Storage::disk('local')->files("tenants/org-kingston-youth/{$kind}");
    }

    public function test_host_uploads_a_flyer_stored_privately_per_organizer(): void
    {
        $response = $this->uploadFlyer($this->user('host'), UploadedFile::fake()->image('flyer.png', 1200, 800))->assertOk();

        $files = $this->storedFiles('flyers');
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.png', $files[0]);
        $this->assertStringStartsWith('/api/v1/media/events/' . self::KINGSTON . '/flyer?v=', $response->json('data.flyerUrl'));
        $this->assertSame(['width' => 1200, 'height' => 800], $response->json('data.flyerDimensions'));
        $this->assertSame('image/png', $response->json('data.flyerMimeType'));

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
}
