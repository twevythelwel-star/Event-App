<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventQrCode;
use App\Models\OrganizerUser;
use App\Models\User;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventPromotionalQrTest extends TestCase
{
    use RefreshDatabase;

    private const KINGSTON = 'evt-kingston-harbour-fry';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
    }

    private function user(string $who): User
    {
        return User::where('email', "{$who}@islandevents.example")->firstOrFail();
    }

    public function test_event_owner_can_generate_promotional_qr(): void
    {
        $host = $this->user('host');

        $response = $this->actingAs($host)
            ->get(route('events.qr.download', [self::KINGSTON, 'svg']))
            ->assertOk();

        $response->assertHeader('X-EventBite-QR-Type', 'promotional');
    }

    public function test_promotional_qr_resolves_to_published_event(): void
    {
        $event = Event::find(self::KINGSTON);
        $event->status = 'published';
        $event->save();

        $qr = EventQrCode::create([
            'event_id' => $event->id,
            'public_code' => 'ABCD123456',
            'status' => EventQrCode::STATUS_READY,
        ]);

        $response = $this->get(route('event-qr.resolve', $qr->public_code))
            ->assertRedirect(route('events.show', $event->id));
    }

    public function test_promotional_qr_does_not_resolve_draft_event(): void
    {
        $event = Event::find(self::KINGSTON);
        $event->status = 'draft';
        $event->save();

        $qr = EventQrCode::create([
            'event_id' => $event->id,
            'public_code' => 'ABCD123456',
            'status' => EventQrCode::STATUS_READY,
        ]);

        $this->get(route('event-qr.resolve', $qr->public_code))
            ->assertNotFound();
    }

    public function test_promotional_qr_does_not_resolve_unpublished_event(): void
    {
        $event = Event::find(self::KINGSTON);
        $event->status = 'unpublished';
        $event->save();

        $qr = EventQrCode::create([
            'event_id' => $event->id,
            'public_code' => 'ABCD123456',
            'status' => EventQrCode::STATUS_READY,
        ]);

        $this->get(route('event-qr.resolve', $qr->public_code))
            ->assertNotFound();
    }

    public function test_promotional_qr_public_artwork_only_for_published(): void
    {
        $event = Event::find(self::KINGSTON);
        $event->status = 'draft';
        $event->save();

        $qr = EventQrCode::create([
            'event_id' => $event->id,
            'public_code' => 'ABCD123456',
            'status' => EventQrCode::STATUS_READY,
        ]);

        $this->get(route('event-qr.public', $qr->public_code))
            ->assertNotFound();
    }

    public function test_promotional_qr_public_artwork_for_published_event(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $event = Event::find(self::KINGSTON);
        $event->status = 'published';
        $event->save();

        // A ready QR code is one with its artwork stored, made the way the app makes it.
        $qr = app(\App\Services\EventQr\EventQrService::class)->ensureFor($event);

        $response = $this->get(route('event-qr.public', $qr->public_code))
            ->assertOk();

        $response->assertHeader('X-EventBite-QR-Type', 'promotional');
    }

    public function test_host_can_preview_qr_for_draft_event(): void
    {
        $host = $this->user('host');
        $event = Event::find(self::KINGSTON);
        $event->status = 'draft';
        $event->save();

        $response = $this->actingAs($host)
            ->get(route('events.qr.preview', self::KINGSTON))
            ->assertOk();

        $response->assertHeader('X-EventBite-QR-Type', 'promotional');
        $response->assertHeader('X-Event-Publication-Status', 'draft');
    }

    public function test_host_can_preview_qr_for_published_event(): void
    {
        $host = $this->user('host');
        $event = Event::find(self::KINGSTON);
        $event->status = 'published';
        $event->save();

        $response = $this->actingAs($host)
            ->get(route('events.qr.preview', self::KINGSTON))
            ->assertOk();

        $response->assertHeader('X-EventBite-QR-Type', 'promotional');
        $response->assertHeader('X-Event-Publication-Status', 'published');
    }

    public function test_non_owner_cannot_preview_qr(): void
    {
        $other = User::create([
            'name' => 'Other User',
            'email' => 'other@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $this->actingAs($other)
            ->get(route('events.qr.preview', self::KINGSTON))
            ->assertNotFound();
    }

    public function test_guest_cannot_preview_qr(): void
    {
        $this->get(route('events.qr.preview', self::KINGSTON))
            ->assertRedirectToRoute('login');
    }

    public function test_promotional_qr_filename_includes_draft_status(): void
    {
        $host = $this->user('host');
        $event = Event::find(self::KINGSTON);
        $event->status = 'draft';
        $event->save();

        $response = $this->actingAs($host)
            ->get(route('events.qr.download', [self::KINGSTON, 'svg']))
            ->assertOk();

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('-draft-qr.svg', $disposition);
    }

    public function test_promotional_qr_filename_for_published_event(): void
    {
        $host = $this->user('host');
        $event = Event::find(self::KINGSTON);
        $event->status = 'published';
        $event->save();

        $response = $this->actingAs($host)
            ->get(route('events.qr.download', [self::KINGSTON, 'svg']))
            ->assertOk();

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringNotContainsString('-draft', $disposition);
    }

    public function test_multiple_formats_available(): void
    {
        $host = $this->user('host');

        $this->actingAs($host)
            ->get(route('events.qr.download', [self::KINGSTON, 'svg']))
            ->assertOk();

        $this->actingAs($host)
            ->get(route('events.qr.download', [self::KINGSTON, 'png']))
            ->assertOk();

        $this->actingAs($host)
            ->get(route('events.qr.download', [self::KINGSTON, 'poster']))
            ->assertOk();
    }

    public function test_invalid_format_returns_404(): void
    {
        $host = $this->user('host');

        $this->actingAs($host)
            ->get(route('events.qr.download', [self::KINGSTON, 'jpg']))
            ->assertNotFound();
    }

    public function test_promotional_qr_is_unique_per_event(): void
    {
        $event = Event::find(self::KINGSTON);

        $qr1 = EventQrCode::create([
            'event_id' => $event->id,
            'public_code' => 'ABCD123456',
            'status' => EventQrCode::STATUS_READY,
        ]);

        // Attempting to create a second QR for the same event should fail
        $this->expectException(\Illuminate\Database\QueryException::class);

        EventQrCode::create([
            'event_id' => $event->id,
            'public_code' => 'EFGH789012',
            'status' => EventQrCode::STATUS_READY,
        ]);
    }

    public function test_public_code_is_unique(): void
    {
        $event1 = Event::find(self::KINGSTON);
        $event2 = Event::withoutGlobalScopes()->where('id', '!=', $event1->id)->first();

        EventQrCode::create([
            'event_id' => $event1->id,
            'public_code' => 'ABCD123456',
            'status' => EventQrCode::STATUS_READY,
        ]);

        // Attempting to create a QR with the same public code should fail
        $this->expectException(\Illuminate\Database\QueryException::class);

        EventQrCode::create([
            'event_id' => $event2->id,
            'public_code' => 'ABCD123456',
            'status' => EventQrCode::STATUS_READY,
        ]);
    }
}
