<?php

namespace Tests\Feature;

use App\Models\AttendeeTicket;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organizer;
use App\Models\OrganizerUser;
use App\Models\SystemPlatformSetting;
use App\Models\User;
use App\Models\UserRole;
use App\Services\EventQr\QrArtworkRenderer;
use App\Services\EventQr\QrDesignCatalog;
use App\Services\EventQr\TicketQrDesignSystem;
use App\Services\EventQr\TicketQrService;
use App\Services\OrderPlacementService;
use App\Services\Tickets\AttendeeTicketService;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The event-themed ticket QR design system: every approved preset is readable (contrast, quiet zone, error
 * correction, finder patterns, resolution and a real decode), unreadable choices are refused or fall back, hosts
 * choose a preset for their event, and administrators control it globally.
 */
class TicketQrDesignSystemTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizer;
    private Event $event;
    private User $host;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organizer = Organizer::create(['name' => 'Design Org', 'slug' => 'design-org']);
        $this->event = Event::allTenants()->create([
            'organizer_id' => $this->organizer->id, 'slug' => 'jamaica-fest', 'title' => 'Jamaica Heritage Festival',
            'date_str' => 'Saturday, November 28, 2026', 'time_str' => '2:00 PM - 10:00 PM', 'currency' => 'JMD',
            'venue_name' => 'Hope Gardens', 'venue_address' => 'Kingston', 'organizer_name' => 'Design Org',
            'organizer_phone' => '+18765550000', 'advance_order_deadline' => now()->addWeek(), 'status' => 'published',
            'is_ordering_open' => true, 'category' => 'Caribbean Culture',
            'ticket_tiers' => [['id' => 'tier-ga', 'name' => 'General Admission', 'price_jmd' => 2000, 'quantity_available' => 50, 'max_per_order' => 4]],
        ]);
        $this->host = User::create(['name' => 'Design Host', 'email' => 'design-host@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        UserRole::create(['user_id' => $this->host->id, 'role' => 'event_host', 'is_active' => true, 'assigned_at' => now()]);
        OrganizerUser::create(['organizer_id' => $this->organizer->id, 'user_id' => $this->host->id, 'role' => 'event_owner', 'is_active' => true]);
    }

    private function designs(): TicketQrDesignSystem
    {
        return app(TicketQrDesignSystem::class);
    }

    private function admin(): User
    {
        $admin = User::create(['name' => 'Root Admin', 'email' => 'qr-root@example.test', 'password' => bcrypt('password'), 'status' => 'active', 'is_system_admin' => true]);
        UserRole::create(['user_id' => $admin->id, 'role' => 'system_admin', 'is_active' => true, 'assigned_at' => now()]);

        return $admin;
    }

    private static function credential(): string
    {
        return 'tkt_' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function decode(string $png): string
    {
        return (string) (new QRCode(new QROptions(['readerGrayscale' => true])))->readFromBlob($png);
    }

    // ---- 2. Readability of every preset ---------------------------------------------------------------------

    public function test_every_preset_and_centre_mark_passes_the_readability_check_with_a_real_decode(): void
    {
        $renderer = app(QrArtworkRenderer::class);
        foreach (TicketQrDesignSystem::PRESETS as $key => $spec) {
            foreach ($spec['logo'] as $logo) {
                $design = $this->designs()->resolve($this->event, ['preset' => $key, 'logo' => $logo]);
                $label = "{$key} with centre mark {$logo}";

                $this->assertArrayNotHasKey('fallback_reason', $design, "{$label} should not need the fallback");
                $this->assertSame($key, $design['preset']);
                $this->assertNull(QrDesignCatalog::problemWith($design), $label);
                $this->assertGreaterThanOrEqual(7.0, QrDesignCatalog::contrast($design['fg'], $design['plate']), "{$label}: module contrast");
                if ($design['finder_accent']) {
                    $this->assertGreaterThanOrEqual(7.0, QrDesignCatalog::contrast($design['finder_accent'], $design['plate']), "{$label}: finder contrast");
                }
                $this->assertContains($design['ecc'], ['Q', 'H'], "{$label}: error correction");
                if ($logo !== 'none') {
                    $this->assertSame('H', $design['ecc'], "{$label}: a centre mark needs H");
                }

                $credential = self::credential();
                $report = $this->designs()->readability($design, $credential);
                $failed = array_keys(array_filter($report['checks'], fn ($c) => !$c['pass']));
                $this->assertTrue($report['passes'], "{$label} failed: " . implode(', ', $failed));
                foreach (['polarity', 'contrast', 'quiet_zone', 'error_correction', 'finder_patterns', 'centre_mark', 'resolution', 'decode'] as $check) {
                    $this->assertArrayHasKey($check, $report['checks']);
                }

                // Independently: a real QR decoder reads the exact credential at a small phone-like size.
                $this->assertSame($credential, $this->decode($renderer->png($credential, $design, 4)), "{$label}: decode");
            }
        }
    }

    public function test_the_quiet_zone_is_plate_coloured_and_finder_patterns_are_intact(): void
    {
        $renderer = app(QrArtworkRenderer::class);
        $credential = self::credential();
        foreach (array_keys(TicketQrDesignSystem::PRESETS) as $key) {
            $design = $this->designs()->resolve($this->event, ['preset' => $key]);
            $px = 4;
            $img = imagecreatefromstring($renderer->png($credential, $design, $px));
            $plate = QrDesignCatalog::rgb($design['plate']);
            $matrix = $renderer->matrix($credential, $design);
            $frame = 3;
            // Sample every module of the quiet-zone ring: all plate colour, never decoration.
            $start = $frame;
            $end = $frame + $matrix['size'] + 2 * QrArtworkRenderer::QUIET_ZONE - 1;
            for ($m = $start + 1; $m < $end; $m++) {
                foreach ([[$m, $start + 1], [$m, $end - 1], [$start + 1, $m], [$end - 1, $m]] as [$mx, $my]) {
                    $rgb = imagecolorat($img, (int) (($mx + 0.5) * $px), (int) (($my + 0.5) * $px));
                    $this->assertSame($plate, [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF], "{$key}: quiet zone at {$mx},{$my}");
                }
            }
            // Finder centres are dark, their inner ring is plate.
            $origin = $frame + QrArtworkRenderer::QUIET_ZONE;
            foreach ([[0, 0], [$matrix['size'] - 7, 0], [0, $matrix['size'] - 7]] as [$fx, $fy]) {
                $centre = imagecolorat($img, (int) (($origin + $fx + 3.5) * $px), (int) (($origin + $fy + 3.5) * $px));
                $ring = imagecolorat($img, (int) (($origin + $fx + 1.5) * $px), (int) (($origin + $fy + 3.5) * $px));
                $this->assertNotSame($plate, [($centre >> 16) & 0xFF, ($centre >> 8) & 0xFF, $centre & 0xFF], "{$key}: finder centre");
                $this->assertSame($plate, [($ring >> 16) & 0xFF, ($ring >> 8) & 0xFF, $ring & 0xFF], "{$key}: finder ring");
            }
            imagedestroy($img);
        }
    }

    public function test_the_event_theme_picks_the_matching_preset_automatically(): void
    {
        $this->assertSame('caribbean_festival', TicketQrDesignSystem::presetForTheme($this->event));
        $this->assertSame('caribbean_festival', $this->designs()->design($this->event)['preset']);

        $wedding = new Event(['title' => 'Our Wedding', 'photo_subject' => 'wedding']);
        $this->assertSame('wedding', TicketQrDesignSystem::presetForTheme($wedding));
        $corporate = new Event(['title' => 'Summit', 'category' => 'Corporate conference']);
        $this->assertSame('corporate', TicketQrDesignSystem::presetForTheme($corporate));
    }

    public function test_unreadable_custom_colours_are_refused_and_never_rendered(): void
    {
        $choice = ['preset' => 'cultural_celebration', 'primary' => '#FFD000', 'background' => '#FFFFFF'];
        $this->assertStringContainsString('hard to scan', (string) $this->designs()->choiceProblem($this->event, $choice));
        $design = $this->designs()->resolve($this->event, $choice);
        $this->assertSame('minimal', $design['preset'], 'falls back to the conventional design');
        $this->assertSame('#000000', $design['fg']);
        $this->assertNotEmpty($design['fallback_reason']);

        // Inverted (light on dark) is refused even with high contrast.
        $inverted = ['preset' => 'cultural_celebration', 'primary' => '#FFFFFF', 'secondary' => '#FFFFFF', 'background' => '#000000'];
        $this->assertNotNull($this->designs()->choiceProblem($this->event, $inverted));

        // A good palette is accepted and used as given.
        $good = ['preset' => 'cultural_celebration', 'primary' => '#1B2A10', 'secondary' => '#4A1030', 'background' => '#FFF8EC', 'frame' => '#E0A33A'];
        $this->assertNull($this->designs()->choiceProblem($this->event, $good));
        $design = $this->designs()->resolve($this->event, $good);
        $this->assertSame(['#1B2A10', '#4A1030', '#FFF8EC', '#E0A33A'], [$design['fg'], $design['finder_accent'], $design['plate'], $design['frame']]);
        $this->assertTrue($this->designs()->readability($design, self::credential())['passes']);

        // Custom colours are only for the Cultural Celebration preset.
        $this->assertNotNull($this->designs()->choiceProblem($this->event, ['preset' => 'corporate', 'primary' => '#000000']));
    }

    // ---- Host choice ----------------------------------------------------------------------------------------

    public function test_host_picks_a_preset_and_new_and_existing_tickets_use_it(): void
    {
        $this->actingAs($this->host)->get(route('events.ticketing', ['id' => $this->event->id, 'tab' => 'qr']))->assertOk()
            ->assertSee('Music Festival')->assertSee('Caribbean Festival')->assertSee('Readability check passed')
            ->assertSee('data-qr-preset="wedding"', false);

        $buyer = User::create(['name' => 'Buyer', 'email' => 'design-buyer@example.test', 'phone' => '+18765550123', 'password' => bcrypt('password'), 'status' => 'active']);
        $order = app(OrderPlacementService::class)->placeOrder($this->event, [
            'customer_name' => 'Buyer', 'customer_phone' => '+1 876 555 0123', 'method' => 'bank',
            'items' => [['menu_item_id' => 'tier-ga', 'quantity' => 2]],
        ], $buyer)['order'];
        $order->forceFill(['status' => 'paid', 'amount_paid_minor' => (int) $order->total_minor])->save();
        app(TicketQrService::class)->prepare($order);
        $this->assertSame(['caribbean_festival'], AttendeeTicket::pluck('qr_design')->pluck('preset')->unique()->values()->all());
        $hashes = AttendeeTicket::orderBy('sequence')->pluck('token_hash')->all();

        $this->actingAs($this->host)->post(route('events.ticketing.qr-design', $this->event->id), [
            'preset' => 'music_festival', 'module' => 'dot', 'finder' => 'circle', 'logo' => 'monogram', 'monogram' => 'jhf',
        ])->assertRedirect(route('events.ticketing', ['id' => $this->event->id, 'tab' => 'qr']))->assertSessionHas('status');

        $saved = $this->event->fresh()->ticket_qr_design;
        $this->assertSame('music_festival', $saved['preset']);
        $design = $this->designs()->design($this->event->fresh());
        $this->assertSame(['music_festival', 'dot', 'circle', 'monogram', 'JHF', 'H'], [$design['preset'], $design['module'], $design['finder'], $design['logo'], $design['monogram'], $design['ecc']]);

        // Existing tickets take the new look on their next view; their credentials are unchanged.
        $this->actingAs($buyer)->get(route('tickets.show', $order))->assertOk();
        $this->assertSame(['music_festival'], AttendeeTicket::pluck('qr_design')->pluck('preset')->unique()->values()->all());
        $this->assertSame($hashes, AttendeeTicket::orderBy('sequence')->pluck('token_hash')->all());
    }

    public function test_host_choices_outside_the_approved_options_are_refused(): void
    {
        $url = route('events.ticketing.qr-design', $this->event->id);
        $this->actingAs($this->host)->post($url, ['preset' => 'neon-chaos'])->assertSessionHasErrors('preset');
        $this->actingAs($this->host)->post($url, ['preset' => 'corporate', 'module' => 'dot'])->assertSessionHasErrors('ticketing');
        $this->actingAs($this->host)->post($url, ['preset' => 'cultural_celebration', 'primary' => '#EEEEEE', 'background' => '#FFFFFF'])
            ->assertSessionHasErrors('ticketing');
        $this->assertNull($this->event->fresh()->ticket_qr_design);

        // Another organizer's host cannot touch this event.
        $stranger = User::create(['name' => 'Stranger', 'email' => 'stranger@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        UserRole::create(['user_id' => $stranger->id, 'role' => 'event_host', 'is_active' => true, 'assigned_at' => now()]);
        $this->actingAs($stranger)->post($url, ['preset' => 'wedding'])->assertNotFound();
        $this->assertNull($this->event->fresh()->ticket_qr_design);
    }

    // ---- Administrator controls -----------------------------------------------------------------------------

    public function test_admin_controls_limit_presets_logos_and_contrast(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.console.qr-codes.index'))->assertOk()
            ->assertSee('Attendee ticket QR codes')->assertSee('Caribbean Festival');

        $this->actingAs($admin)->post(route('admin.console.qr-codes.ticket-controls'), [
            'enabled' => '1', 'default_preset' => 'corporate', 'allowed_presets' => ['corporate', 'wedding'],
            'allow_custom_palette' => '0', 'allow_center_logo' => '0', 'min_contrast' => '10',
        ])->assertRedirect(route('admin.console.qr-codes.index'));

        $controls = $this->designs()->controls();
        $this->assertSame(['corporate', 'wedding'], $controls['allowed_presets']);
        $this->assertFalse($controls['allow_center_logo']);
        $this->assertSame(10.0, $controls['min_contrast']);

        // The event's automatic choice (Caribbean) is no longer allowed: the admin default applies.
        $this->assertSame('corporate', $this->designs()->design($this->event)['preset']);
        // Hosts cannot pick a preset outside the list, nor a centre mark.
        $this->actingAs($this->host)->post(route('events.ticketing.qr-design', $this->event->id), ['preset' => 'music_festival'])
            ->assertSessionHasErrors('ticketing');
        $this->actingAs($this->host)->post(route('events.ticketing.qr-design', $this->event->id), ['preset' => 'wedding', 'logo' => 'monogram'])
            ->assertSessionHasErrors('ticketing');
        $this->assertSame('none', $this->designs()->resolve($this->event, ['preset' => 'wedding', 'logo' => 'monogram'])['logo']);
        // The raised 10:1 floor applies to every preset: Corporate meets it; Wedding's taupe corner markers
        // (about 8.7:1) do not, so Wedding tickets use the standard code until the floor is lowered again.
        $this->assertSame(TicketQrDesignSystem::PRESETS['corporate']['primary'], $this->designs()->resolve($this->event, ['preset' => 'corporate'])['fg']);
        $wedding = $this->designs()->resolve($this->event, ['preset' => 'wedding']);
        $this->assertSame('minimal', $wedding['preset']);
        $this->assertStringContainsString('10.0:1', $wedding['fallback_reason']);

        // The contrast floor can be raised but never lowered below 7:1.
        $this->actingAs($admin)->post(route('admin.console.qr-codes.ticket-controls'), [
            'enabled' => '1', 'default_preset' => 'auto', 'allowed_presets' => ['corporate'], 'min_contrast' => '4.5',
        ])->assertSessionHasErrors('min_contrast');
        $this->assertSame(10.0, $this->designs()->controls()['min_contrast']);
    }

    public function test_admin_can_switch_themed_codes_off_and_tickets_fall_back_to_the_standard_code(): void
    {
        SystemPlatformSetting::firstOrCreate([])->forceFill(['ticket_qr_controls' => ['enabled' => false]])->save();

        $design = $this->designs()->design($this->event);
        $this->assertSame('minimal', $design['preset']);
        $this->assertSame(['#000000', '#FFFFFF', 'Q'], [$design['fg'], $design['plate'], $design['ecc']]);
        $this->assertTrue($this->designs()->readability($design, self::credential())['passes']);

        $this->actingAs($this->host)->get(route('events.ticketing', ['id' => $this->event->id, 'tab' => 'qr']))->assertOk()
            ->assertSee('Themed QR codes are off');
    }

    public function test_only_administrators_change_global_controls(): void
    {
        $this->actingAs($this->host)->post(route('admin.console.qr-codes.ticket-controls'), [
            'enabled' => '0', 'default_preset' => 'auto', 'allowed_presets' => ['corporate'], 'min_contrast' => '7',
        ])->assertForbidden();
        $this->assertTrue($this->designs()->controls()['enabled']);
    }

    public function test_promotional_event_qr_keeps_its_original_random_ingredients(): void
    {
        for ($i = 0; $i < 40; $i++) {
            $design = QrDesignCatalog::generate('random_mix');
            $this->assertContains($design['logo'], ['none', 'bite']);
            $this->assertContains($design['finder'], ['square', 'rounded', 'circle']);
        }
    }

    public function test_credentials_are_derived_from_the_app_key_and_cannot_be_forged_from_the_database(): void
    {
        $buyer = User::create(['name' => 'Buyer', 'email' => 'forge-buyer@example.test', 'phone' => '+18765550123', 'password' => bcrypt('password'), 'status' => 'active']);
        $order = app(OrderPlacementService::class)->placeOrder($this->event, [
            'customer_name' => 'Buyer', 'customer_phone' => '+1 876 555 0123', 'method' => 'bank',
            'items' => [['menu_item_id' => 'tier-ga', 'quantity' => 1]],
        ], $buyer)['order'];
        $ticket = AttendeeTicket::where('order_id', $order->id)->sole();
        $service = app(AttendeeTicketService::class);
        $real = $service->credential($ticket);

        config(['app.key' => 'base64:' . base64_encode(random_bytes(32))]);
        $this->assertNotSame($real, $service->credential($ticket), 'the same row under another key gives another credential');
        $this->assertNotSame(hash('sha256', $service->credential($ticket)), $ticket->token_hash);
    }
}
