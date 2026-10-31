<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventQrCode;
use App\Models\EventWorkerAssignment;
use App\Models\Order;
use App\Models\OrganizerUser;
use App\Models\User;
use App\Models\UserRole;
use App\Services\EventQr\EventQrService;
use App\Services\EventQr\QrArtworkRenderer;
use App\Services\EventQr\QrDesignCatalog;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\DemoOrdersSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Event QR codes: one stable promotional identity per event with an approved, randomly chosen design that is
 * verified by decoding, separate from the per-ticket admission codes.
 */
class EventQrCodeTest extends TestCase
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

    private function otherHost(): User
    {
        $user = User::create(['name' => 'Other Host', 'email' => 'other-host@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        UserRole::create(['user_id' => $user->id, 'role' => 'event_host', 'is_active' => true, 'assigned_at' => now()]);
        OrganizerUser::create(['organizer_id' => 'org-hellshire-fishers', 'user_id' => $user->id, 'role' => 'event_owner', 'is_active' => true]);

        return $user;
    }

    private function createEvent(string $title = 'Atlanta Community Seafood Night'): Event
    {
        $id = $this->actingAs($this->user('host'))->postJson('/api/v1/host/events', [
            'title' => $title,
            'category' => 'Seafood Festival',
            'date_str' => 'Saturday, December 19, 2026',
            'event_date_iso' => '2026-12-19',
            'time_str' => '4:00 PM – 11:00 PM',
            'timezone' => 'America/New_York',
            'venue_name' => 'Atlanta Community Park',
            'street_address' => '100 Park Place',
            'city' => 'Atlanta',
            'state_code' => 'GA',
            'zip_code' => '30303',
            'country_code' => 'US',
            'venue_address' => '100 Park Place, Atlanta, GA, 30303, United States',
            'organizer_name' => 'Atlanta Community Foundation',
            'organizer_phone' => '+1 (404) 555-0188',
            'advance_order_deadline' => '2026-12-18 18:00',
            'supports_food_ordering' => true,
        ])->assertCreated()->json('data.id');

        return Event::withoutGlobalScopes()->findOrFail($id);
    }

    private function qrFor(Event $event): EventQrCode
    {
        return EventQrCode::where('event_id', $event->id)->firstOrFail();
    }

    // 1, 2
    public function test_every_new_event_gets_its_own_ready_qr_identity(): void
    {
        $first = $this->createEvent('First Night');
        $second = $this->createEvent('Second Night');

        $a = $this->qrFor($first);
        $b = $this->qrFor($second);

        $this->assertTrue($a->isReady());
        $this->assertTrue($b->isReady());
        $this->assertMatchesRegularExpression('/^[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{10}$/', $a->public_code);
        $this->assertNotSame($a->public_code, $b->public_code);
        Storage::disk('local')->assertExists($a->asset_path);
        $this->assertSame(1, EventQrCode::where('event_id', $first->id)->count());
    }

    // 3
    public function test_designs_come_only_from_approved_configurations(): void
    {
        foreach (['random', 'geometric', 'circular', 'neon', 'minimal', 'festival', 'editorial', 'random_mix'] as $preset) {
            for ($i = 0; $i < 25; $i++) {
                $design = QrDesignCatalog::generate($preset);
                $this->assertNull(QrDesignCatalog::problemWith($design), "{$preset}: " . json_encode($design));
                $this->assertContains($design['module'], QrDesignCatalog::MODULE_SHAPES);
                $this->assertContains($design['finder'], QrDesignCatalog::FINDER_SHAPES);
                $this->assertContains($design['border'], QrDesignCatalog::BORDERS);
                $this->assertArrayHasKey($design['preset'], QrDesignCatalog::PRESETS);
                $this->assertSame(QrDesignCatalog::VERSION, $design['version']);
            }
        }

        $stored = $this->qrFor($this->createEvent())->design;
        $this->assertNull(QrDesignCatalog::problemWith($stored));
    }

    // 4, 5
    public function test_design_is_stable_across_page_loads_and_event_edits(): void
    {
        $event = $this->createEvent();
        $before = $this->qrFor($event);
        $host = $this->user('host');

        $svg1 = $this->actingAs($host)->get(route('events.qr.download', [$event->id, 'svg']))->assertOk()->getContent();
        $this->actingAs($host)->get(route('events.edit', $event->id))->assertOk()->assertSee('Event QR code');
        $svg2 = $this->actingAs($host)->get(route('events.qr.download', [$event->id, 'svg']))->assertOk()->getContent();
        $this->assertSame($svg1, $svg2);

        $this->actingAs($host)->patchJson('/api/v1/host/events/' . $event->id, [
            'title' => 'Port Royal Sunset Seafood Night (Extended)',
            'description' => 'Now with a later finish.',
        ])->assertOk();

        $after = $this->qrFor($event);
        $this->assertSame($before->public_code, $after->public_code);
        $this->assertSame($before->design, $after->design);
        $this->assertSame($before->design_version, $after->design_version);
        $this->assertSame($before->asset_path, $after->asset_path);
    }

    // 6
    public function test_redesign_changes_the_look_but_keeps_the_identity_and_destination(): void
    {
        $event = $this->createEvent();
        $before = $this->qrFor($event);
        $service = app(EventQrService::class);
        $destination = $service->destinationUrl($before);

        $this->actingAs($this->user('host'))->post(route('events.qr.redesign', $event->id), ['preset' => 'festival'])->assertRedirect();

        $after = $this->qrFor($event);
        $this->assertSame($before->public_code, $after->public_code);
        $this->assertSame($destination, $service->destinationUrl($after));
        $this->assertSame($before->design_version + 1, $after->design_version);
        $this->assertTrue($after->isReady());
        $this->assertContains($after->preset, ['festival', 'minimal']); // minimal only if festival had to fall back
        Storage::disk('local')->assertExists($after->asset_path);
        $this->assertSame(1, EventQrCode::where('event_id', $event->id)->count());
    }

    // 7, 8
    public function test_short_link_opens_published_events_only(): void
    {
        $event = $this->createEvent();
        $code = $this->qrFor($event)->public_code;

        // Draft: the link and the public artwork are not found, and the event page is hidden from the public.
        $this->get("/e/{$code}")->assertNotFound();
        $this->get("/e/{$code}/qr.svg")->assertNotFound();
        $this->actingAs($this->user('attendee'))->get(route('events.show', $event->id))->assertNotFound();
        $this->actingAs($this->user('host'))->get(route('events.show', $event->id))->assertOk();

        Event::withoutGlobalScopes()->whereKey($event->id)->update(['status' => 'published']);
        $this->app['auth']->forgetGuards();

        $this->get("/e/{$code}")->assertRedirect(route('events.show', $event->id));
        $this->get('/e/' . strtolower($code))->assertRedirect(route('events.show', $event->id));
        $this->get("/e/{$code}/qr.svg")->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->get('/e/ZZZZZZZZZZ')->assertNotFound();

        Event::withoutGlobalScopes()->whereKey($event->id)->update(['status' => 'cancelled']);
        $this->get("/e/{$code}")->assertNotFound();
    }

    // 9, 10
    public function test_tickets_have_their_own_codes_and_the_event_qr_cannot_admit(): void
    {
        $event = Event::withoutGlobalScopes()->findOrFail(self::KINGSTON);
        $qr = app(EventQrService::class)->ensureFor($event);
        $destination = app(EventQrService::class)->destinationUrl($qr);

        $tokens = Order::allTenants()->where('event_id', self::KINGSTON)->pluck('secure_ticket_token')->filter()->values();
        $this->assertGreaterThan(1, $tokens->count());
        $this->assertSame($tokens->count(), $tokens->unique()->count());
        $this->assertNotContains($destination, $tokens->all());
        $this->assertNotContains($qr->public_code, $tokens->all());

        $checker = User::create(['name' => 'Gate', 'email' => 'gate@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        EventWorkerAssignment::create([
            'organizer_id' => $event->organizer_id, 'event_id' => $event->id, 'user_id' => $checker->id,
            'worker_role' => 'event_supervisor', 'status' => 'active', 'is_active' => true,
        ]);
        $admittedBefore = Order::allTenants()->where('event_id', self::KINGSTON)->whereNotNull('checked_in_at')->count();

        foreach ([$destination, $qr->public_code] as $scanned) {
            $response = $this->actingAs($checker)->postJson("/worker/events/{$event->id}/scan", ['ticket_code' => $scanned]);
            $this->assertNotTrue($response->json('success'));
        }

        $this->assertSame($admittedBefore, Order::allTenants()->where('event_id', self::KINGSTON)->whereNotNull('checked_in_at')->count());
    }

    // 11
    public function test_only_people_who_manage_the_event_can_change_or_download_its_qr(): void
    {
        $event = $this->createEvent();
        $before = $this->qrFor($event);

        foreach ([$this->user('attendee'), $this->otherHost()] as $outsider) {
            $this->actingAs($outsider)->post(route('events.qr.redesign', $event->id), ['preset' => 'neon'])->assertNotFound();
            $this->actingAs($outsider)->post(route('events.qr.retry', $event->id))->assertNotFound();
            $this->actingAs($outsider)->get(route('events.qr.download', [$event->id, 'png']))->assertNotFound();
            $this->actingAs($outsider)->get(route('events.edit', $event->id))->assertDontSee('Event QR code');
        }
        $this->app['auth']->forgetGuards();
        $this->post(route('events.qr.redesign', $event->id), ['preset' => 'neon'])->assertRedirect(route('login'));

        $this->assertSame($before->design_version, $this->qrFor($event)->design_version);
        $this->actingAs($this->user('host'))->post(route('events.qr.redesign', $event->id), ['preset' => 'not-a-preset'])->assertSessionHasErrors('preset');
    }

    // 12
    public function test_artwork_downloads_in_supported_formats(): void
    {
        $event = $this->createEvent('Fish & Chips <script>alert(1)</script> Night');
        $host = $this->user('host');

        $svg = $this->actingAs($host)->get(route('events.qr.download', [$event->id, 'svg']));
        $svg->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('attachment;', (string) $svg->headers->get('Content-Disposition'));
        $this->assertStringContainsString('<svg', $svg->getContent());
        $this->assertStringNotContainsString('<script', $svg->getContent());

        $png = $this->actingAs($host)->get(route('events.qr.download', [$event->id, 'png']));
        $png->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame("\x89PNG", substr($png->getContent(), 0, 4));
        $this->assertGreaterThanOrEqual(1000, imagesx(imagecreatefromstring($png->getContent())));

        $poster = $this->actingAs($host)->get(route('events.qr.download', [$event->id, 'poster']))->assertOk()->getContent();
        $this->assertStringContainsString('Fish &amp; Chips &lt;script&gt;', $poster);
        $this->assertStringNotContainsString('<script', $poster);

        $inline = $this->actingAs($host)->get(route('events.qr.download', [$event->id, 'svg']) . '?inline=1')->assertOk();
        $this->assertStringStartsWith('inline; filename="', (string) $inline->headers->get('Content-Disposition'));
        $this->actingAs($host)->get("/events/{$event->id}/qr/pdf")->assertNotFound();
    }

    // 13
    public function test_invalid_combinations_fall_back_to_a_safe_design(): void
    {
        $good = QrDesignCatalog::generate('minimal');
        $broken = [
            'low contrast' => ['fg' => '#777777', 'plate' => '#888888'],
            'inverted' => ['fg' => '#FFFFFF', 'plate' => '#07111F'],
            'dots at M' => ['module' => 'dot', 'ecc' => 'M'],
            'logo without H' => ['logo' => 'bite', 'ecc' => 'Q'],
            'unknown shape' => ['module' => 'star'],
            'not a colour' => ['fg' => 'url(javascript:alert(1))'],
        ];

        foreach ($broken as $name => $change) {
            $safe = QrDesignCatalog::safe(array_merge($good, $change));
            $this->assertSame('minimal', $safe['preset'], $name);
            $this->assertSame('#000000', $safe['fg'], $name);
            $this->assertArrayHasKey('fallback_reason', $safe, $name);
            $this->assertNull(QrDesignCatalog::problemWith($safe), $name);
        }

        // A stored design that has become invalid is rendered with the fallback, never as stored.
        $event = $this->createEvent();
        $qr = $this->qrFor($event);
        $qr->update(['design' => array_merge($qr->design, ['fg' => '#FFFFFF', 'plate' => '#000000']), 'status' => 'pending']);
        $qr = app(EventQrService::class)->ensureFor($event);
        $this->assertSame('#000000', $qr->design['fg']);
        $this->assertSame('#FFFFFF', $qr->design['plate']);
    }

    // 14
    public function test_generated_codes_decode_with_a_qr_reader_for_every_preset(): void
    {
        $renderer = new QrArtworkRenderer();
        $service = app(EventQrService::class);
        $url = rtrim((string) config('app.url'), '/') . '/e/ABCDEFGH23';

        foreach (array_keys(QrDesignCatalog::PRESETS) as $preset) {
            if ($preset === 'custom_theme') {
                $design = QrDesignCatalog::generate('custom_theme', ['#0B2A6B', '#FF6B5A', '#F4E3C1']);
                $this->assertSame('custom_theme', $design['preset']);
            } else {
                $design = QrDesignCatalog::generate($preset);
            }
            $this->assertTrue($service->decodes($renderer->png($url, $design, 8), $url), "{$preset} at 8px");
            $this->assertTrue($service->decodes($renderer->png($url, $design, 4), $url), "{$preset} at 4px");
        }

        // Negative control: the reader is not just accepting anything.
        $unreadable = array_merge(QrDesignCatalog::fallback(), ['fg' => '#F2F2F2', 'plate' => '#FFFFFF']);
        $this->assertFalse($service->decodes($renderer->png($url, $unreadable, 8), $url));
        $this->assertFalse($service->decodes($renderer->png($url, QrDesignCatalog::fallback(), 8), $url . 'X'));

        // The artwork stored for a real event decodes to its own destination.
        $qr = $this->qrFor($this->createEvent());
        $this->assertTrue($service->decodes($service->png($qr, 8), $service->destinationUrl($qr)));
    }

    // 15
    public function test_failed_generation_can_be_retried_without_a_second_identity(): void
    {
        $failing = new class extends QrArtworkRenderer {
            public bool $fail = true;

            public function svg(string $data, array $design, int $pixelsPerModule = 10): string
            {
                if ($this->fail) {
                    throw new \RuntimeException('simulated renderer outage');
                }

                return parent::svg($data, $design, $pixelsPerModule);
            }
        };
        $this->app->instance(QrArtworkRenderer::class, $failing);
        $this->app->forgetInstance(EventQrService::class);

        $event = $this->createEvent();
        $this->assertNotNull(Event::withoutGlobalScopes()->find($event->id), 'the event is kept when artwork fails');
        $qr = $this->qrFor($event);
        $this->assertSame(EventQrCode::STATUS_FAILED, $qr->status);
        $this->assertStringContainsString('simulated renderer outage', (string) $qr->failure_reason);
        $this->actingAs($this->user('host'))->get(route('events.edit', $event->id))->assertOk()->assertSee('Try generating again');

        $failing->fail = false;
        $this->actingAs($this->user('host'))->post(route('events.qr.retry', $event->id))->assertRedirect();

        $retried = $this->qrFor($event);
        $this->assertTrue($retried->isReady());
        $this->assertSame($qr->public_code, $retried->public_code);
        $this->assertSame(1, EventQrCode::where('event_id', $event->id)->count());
        $this->assertGreaterThanOrEqual(2, $retried->attempts); // the failed render(s), then the successful retry
    }
}
