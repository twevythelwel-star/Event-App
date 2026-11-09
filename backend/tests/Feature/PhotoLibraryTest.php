<?php

namespace Tests\Feature;

use App\Models\AdminPermission;
use App\Models\Event;
use App\Models\LibraryPhoto;
use App\Models\User;
use App\Models\UserRole;
use App\Services\PhotoLibrary;
use App\Support\ResponsiveImage;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The photo library: photographs of kinds of events, uploaded with their provenance, approved by a second system
 * administrator against the photography standards, and shown (labelled as illustrative) only on events of that kind
 * that have no flyer of their own.
 */
class PhotoLibraryTest extends TestCase
{
    use RefreshDatabase;

    private const FISH_FRY = 'evt-kingston-harbour-fry';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['media.disk' => 'local']);
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
    }

    private function user(string $email, ?string $role, array $permissions = []): User
    {
        $user = User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => bcrypt('password'), 'status' => 'active', 'is_system_admin' => $role === 'system_admin']);
        if ($role) {
            UserRole::create(['user_id' => $user->id, 'role' => $role, 'is_active' => true, 'assigned_at' => now()]);
        }
        if ($permissions) {
            AdminPermission::create(['user_id' => $user->id, 'scope' => 'all', 'assigned_organizer_ids' => []] + $permissions);
        }

        return $user->fresh();
    }

    private function upload(User $who, array $overrides = [], ?UploadedFile $file = null)
    {
        return $this->actingAs($who)->post(route('admin.console.photo-library.store'), $overrides + [
            'photo' => $file ?? UploadedFile::fake()->image('fish-fry.jpg', 1800, 1200),
            'subject' => 'jamaican_fish_fry',
            'title' => 'Evening fish fry by the water',
            'alt_text' => 'Fried fish and festival dumplings served on paper plates at an evening outdoor fish fry',
            'source' => 'commissioned',
            'source_detail' => 'Commissioned shoot, photographer on contract',
            'licence' => 'Work for hire, all rights assigned',
            'identifiable_people' => '0',
            'model_release' => 'not_needed',
            'rights_confirmed' => '1',
        ]);
    }

    private function standards(): array
    {
        return array_fill_keys(array_keys((array) config('photo_library.standards')), '1');
    }

    /** A photo uploaded by one system administrator and approved by another. */
    private function approvedPhoto(string $subject = 'jamaican_fish_fry', string $title = 'Evening fish fry by the water'): LibraryPhoto
    {
        $uploader = User::where('email', 'uploader@example.test')->first() ?? $this->user('uploader@example.test', 'system_admin');
        $reviewer = User::where('email', 'reviewer@example.test')->first() ?? $this->user('reviewer@example.test', 'system_admin');
        $this->upload($uploader, ['subject' => $subject, 'title' => $title])->assertSessionHasNoErrors();
        $photo = LibraryPhoto::latest('id')->firstOrFail();
        $this->actingAs($reviewer)->post(route('admin.console.photo-library.review', $photo), ['decision' => 'approve', 'standards' => $this->standards()])
            ->assertSessionHasNoErrors();
        app()->forgetScopedInstances();

        return $photo->fresh();
    }

    public function test_an_upload_needs_a_real_large_photo_and_its_provenance(): void
    {
        $admin = $this->user('uploader@example.test', 'system_admin');

        $this->upload($admin, [], UploadedFile::fake()->image('small.jpg', 800, 600))->assertSessionHasErrors('photo');
        $this->upload($admin, [], UploadedFile::fake()->create('flyer.pdf', 100, 'application/pdf'))->assertSessionHasErrors('photo');
        $this->upload($admin, ['source_detail' => '', 'licence' => '', 'rights_confirmed' => ''])
            ->assertSessionHasErrors(['source_detail', 'licence', 'rights_confirmed']);
        $this->upload($admin, ['subject' => 'sushi_night'])->assertSessionHasErrors('subject');
        $this->upload($admin, ['alt_text' => 'A photo'])->assertSessionHasErrors('alt_text');
        $this->assertSame(0, LibraryPhoto::count(), 'nothing is kept from a refused upload');

        $this->upload($admin)->assertSessionHasNoErrors();
        $photo = LibraryPhoto::firstOrFail();
        $this->assertSame('pending_review', $photo->status);
        $this->assertTrue(\App\Services\MediaStorageService::isStoredPath($photo->path), 'stored as a file, referenced by path');
        $this->assertNotEmpty($photo->variants, 'resized copies are made');
        Storage::disk('local')->assertExists($photo->path);
    }

    public function test_only_a_second_system_administrator_can_approve_and_only_against_every_standard(): void
    {
        $uploader = $this->user('uploader@example.test', 'system_admin');
        $moderator = $this->user('moderator@example.test', 'admin', ['can_moderate_content' => true]);
        $reviewer = $this->user('reviewer@example.test', 'system_admin');
        $attendee = User::where('email', 'attendee@islandevents.example')->firstOrFail();

        $this->actingAs($attendee)->get(route('admin.console.photo-library.index'))->assertForbidden();
        $this->actingAs($moderator)->get(route('admin.console.photo-library.index'))->assertOk();

        $this->upload($moderator)->assertSessionHasNoErrors();
        $photo = LibraryPhoto::firstOrFail();
        $approve = ['decision' => 'approve', 'standards' => $this->standards()];

        $this->actingAs($moderator)->post(route('admin.console.photo-library.review', $photo), $approve)->assertForbidden();
        $this->upload($uploader)->assertSessionHasNoErrors();
        $own = LibraryPhoto::latest('id')->firstOrFail();
        $this->actingAs($uploader)->post(route('admin.console.photo-library.review', $own), $approve)->assertForbidden();

        $partial = $this->standards();
        unset($partial['no_real_venue']);
        $this->actingAs($reviewer)->post(route('admin.console.photo-library.review', $photo), ['decision' => 'approve', 'standards' => $partial])
            ->assertSessionHasErrors('standards.no_real_venue');
        $this->assertSame('pending_review', $photo->fresh()->status);

        $this->actingAs($reviewer)->post(route('admin.console.photo-library.review', $own), ['decision' => 'reject'])->assertSessionHasErrors('notes');

        $this->actingAs($reviewer)->post(route('admin.console.photo-library.review', $photo), $approve)->assertSessionHasNoErrors();
        $photo->refresh();
        $this->assertSame('approved', $photo->status);
        $this->assertSame($reviewer->id, $photo->reviewed_by);
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'library_photo_approved', 'target_id' => (string) $photo->id]);
    }

    public function test_a_photo_is_public_only_once_approved(): void
    {
        $uploader = $this->user('uploader@example.test', 'system_admin');
        $this->upload($uploader)->assertSessionHasNoErrors();
        $photo = LibraryPhoto::firstOrFail();
        app('auth')->forgetGuards();

        $this->get(route('api.media.library-photo', $photo->id))->assertNotFound();
        $this->actingAs($uploader)->get(route('api.media.library-photo', $photo->id))->assertOk();
        app('auth')->forgetGuards();

        $this->assertNull(app(PhotoLibrary::class)->forEvent(Event::withoutGlobalScopes()->findOrFail(self::FISH_FRY)), 'a pending photo is never used');

        $photo = $this->approvedPhoto();
        app('auth')->forgetGuards();
        $this->get(route('api.media.library-photo', $photo->id))->assertOk();
    }

    public function test_an_event_shows_a_library_photo_only_of_its_own_kind_and_never_over_its_flyer(): void
    {
        $fishFry = $this->approvedPhoto('jamaican_fish_fry');
        $this->approvedPhoto('reggae_concert', 'Crowd at an outdoor reggae concert');

        $event = Event::withoutGlobalScopes()->findOrFail(self::FISH_FRY);
        $this->assertSame('jamaican_fish_fry', $event->photo_subject);
        $cover = $event->coverImage();
        $this->assertTrue($cover->illustrative);
        $this->assertSame('Illustrative photo', $cover->label);
        $this->assertStringContainsString('/media/library/' . $fishFry->id, $cover->src);
        $this->assertStringContainsString('not of this event', $cover->alt);

        // A picnic is not shown a concert: an event of a kind with no photo, or with no kind, gets its artwork.
        $picnic = Event::withoutGlobalScopes()->findOrFail('evt-us-sunrise');
        $picnic->photo_subject = 'outdoor_picnic';
        $this->assertFalse($picnic->coverImage()->illustrative);
        $this->assertNull($picnic->coverImage()->label);
        $picnic->photo_subject = null;
        $this->assertNull(app(PhotoLibrary::class)->forEvent($picnic));

        // The title never chooses a photo.
        $named = Event::withoutGlobalScopes()->findOrFail('evt-us-sunrise');
        $named->title = 'Reggae Concert and Jamaican Fish Fry';
        $named->photo_subject = null;
        $this->assertNull(app(PhotoLibrary::class)->forEvent($named));

        // The host's flyer always wins.
        $event->forceFill(['flyer_path' => 'https://example.test/our-flyer.jpg'])->save();
        $this->assertFalse($event->fresh()->coverImage()->illustrative);
    }

    public function test_the_choice_is_stable_per_event_and_spread_across_events(): void
    {
        $this->approvedPhoto('caribbean_food_festival', 'Food stalls at a Caribbean food festival');
        $this->approvedPhoto('caribbean_food_festival', 'Plates of curry goat and rice at a festival');
        $this->approvedPhoto('caribbean_food_festival', 'Festival crowd queuing at food stalls');
        $library = app(PhotoLibrary::class);

        $chosen = [];
        foreach (['evt-boston-bay-jerk-fish', 'evt-us-miami-gardens', 'evt-us-tampa'] as $id) {
            $event = Event::withoutGlobalScopes()->findOrFail($id);
            $first = $library->forEvent($event);
            $this->assertSame($first->id, $library->forEvent($event)->id, 'the same photo on every page');
            $chosen[] = $first->id;
        }
        $this->assertGreaterThan(1, count(array_unique($chosen)), 'events of one kind do not all share a photo');
    }

    public function test_the_api_and_pages_label_a_library_photo(): void
    {
        $this->approvedPhoto();
        app('auth')->forgetGuards(); // looked at signed out, as a visitor

        $json = collect($this->getJson(route('api.events.index'))->assertOk()->json('data'))->firstWhere('id', self::FISH_FRY);
        $cover = $json['coverImage'];
        $this->assertSame('library', $cover['kind']);
        $this->assertStringStartsWith('Illustrative photo', $cover['label']);
        $this->assertNull($json['flyerUrl'] ?? null, 'a library photo is never presented as the flyer');

        $this->get(route('events.show', self::FISH_FRY))->assertOk()->assertSee('Illustrative photo');
        $this->get(route('events.index', ['kind' => 'food']))->assertOk()->assertSee('South Florida Jamaican Fish Fry')
            ->assertDontSee('Reggae and Dancehall Night');
        $this->get('/')->assertOk()->assertSee('Browse by kind of event');
    }

    public function test_retiring_a_photo_removes_it_everywhere_but_keeps_its_record(): void
    {
        $photo = $this->approvedPhoto();
        $admin = User::where('email', 'reviewer@example.test')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.console.photo-library.retire', $photo), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post(route('admin.console.photo-library.retire', $photo), ['reason' => 'Licence ended'])->assertSessionHasNoErrors();
        app()->forgetScopedInstances();
        app('auth')->forgetGuards();

        $this->assertSoftDeleted('library_photos', ['id' => $photo->id]);
        $this->assertNull(app(PhotoLibrary::class)->forEvent(Event::withoutGlobalScopes()->findOrFail(self::FISH_FRY)));
        $this->get(route('api.media.library-photo', $photo->id))->assertNotFound();
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'library_photo_retired', 'target_id' => (string) $photo->id]);
    }
}
