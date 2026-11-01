<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\HostApplication;
use App\Models\HostOperationalRule;
use App\Models\HostPaymentPolicy;
use App\Models\User;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\DemoOrdersSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Database\Seeders\SystemAdminSeeder;
use Database\Seeders\UsLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Pages and actions corrected by the profile audit: every page uses the shared design system, shows only
 * real data, and every form on it saves.
 */
class ProfilePagesAuditTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = 'evt-kingston-harbour-fry';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([UsLocationSeeder::class, FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function host(): User
    {
        return $this->user('host@islandevents.example');
    }

    /** The event form, filled from the event as it is. */
    private function eventForm(array $overrides = []): array
    {
        $event = Event::allTenants()->findOrFail(self::EVENT);

        return array_merge([
            'title' => $event->title, 'date_str' => $event->date_str, 'time_str' => $event->time_str,
            'event_date_iso' => substr((string) $event->event_date_iso, 0, 10), 'timezone' => $event->timezone,
            'venue_name' => $event->venue_name, 'street_address' => $event->street_address, 'city' => $event->city,
            'state_code' => $event->state_code, 'zip_code' => $event->zip_code, 'country_code' => 'US',
            'organizer_name' => $event->organizer_name, 'organizer_phone' => $event->organizer_phone,
            'advance_order_deadline' => '2027-01-10 18:00',
        ], $overrides);
    }

    public function test_no_page_depends_on_tailwind_classes_the_app_never_loads(): void
    {
        $host = $this->host();
        $pages = [
            [$host, '/events/create'],
            [$host, '/events/' . self::EVENT . '/edit'],
            [$host, '/host/events/' . self::EVENT . '/staff'],
            [$host, '/host/dashboard'],
            [$host, '/settings'],
            [$this->user('attendee@islandevents.example'), '/host/apply'],
            [$this->user('admin@islandevents.example'), '/admin/host-applications'],
        ];
        foreach ($pages as [$user, $url]) {
            $html = $this->actingAs($user)->get($url)->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('/class="[^"]*\b(text-slate-\d+|bg-white|rounded-xl|space-y-\d)\b/', $html, $url);
            $this->assertStringContainsString('eb-page-header', $html, "{$url} uses the standard page header");
        }
        // No view in the app uses them either.
        foreach (glob(resource_path('views/{,*/,*/*/}*.blade.php'), GLOB_BRACE) as $view) {
            $this->assertDoesNotMatchRegularExpression('/class="[^"]*\b(text-slate-\d+|bg-slate-\d+)\b/', file_get_contents($view), $view);
        }
    }

    public function test_host_dashboard_shows_real_data_and_working_actions(): void
    {
        $this->seed(DemoOrdersSeeder::class);
        $html = $this->actingAs($this->host())->get('/host/dashboard')->assertOk()
            ->assertSee('South Florida Jamaican Fish Fry')
            ->assertSee('Payments to review')
            ->assertDontSee('Sister Beverly')
            ->assertDontSee('Audited Ledger')
            ->assertDontSee('Today (Peak)')
            ->assertDontSee('Main Grounds')
            ->getContent();

        // Every in-page link from the sidebar and the summary cards has a section to land on.
        foreach (['events', 'review', 'orders', 'finances', 'forecast', 'workers'] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $html);
        }
        // The payment decisions use the operations API, which the host is allowed to call.
        $order = \App\Models\Order::allTenants()->where('event_id', self::EVENT)->whereIn('status', ['payment_submitted', 'under_verification'])->first();
        $this->assertNotNull($order, 'the demo orders include a confirmation to review');
        $this->assertStringContainsString('data-order="' . $order->id . '"', $html);
        $this->actingAs($this->host())->postJson('/api/v1/operations/orders/' . $order->id . '/payment', ['status' => 'paid'])->assertOk();
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_settings_forms_save_and_come_back_to_the_page(): void
    {
        $attendee = $this->user('attendee@islandevents.example');

        $this->actingAs($attendee)->post('/settings/profile', ['name' => 'Dana Demo', 'email' => 'attendee@islandevents.example', 'phone' => '+1 305 555 0142', 'preferred_display_currency' => 'USD'])
            ->assertRedirect(route('settings.show') . '#profile')->assertSessionHas('status');
        $this->assertSame('Dana Demo', $attendee->fresh()->name);

        $this->actingAs($attendee)->post('/settings/password', ['current_password' => 'not-my-password', 'new_password' => 'a-much-better-one', 'new_password_confirmation' => 'a-much-better-one'])
            ->assertRedirect(route('settings.show') . '#security')->assertSessionHasErrors('current_password');
        $this->actingAs($attendee)->post('/settings/password', ['current_password' => DemoAccountsSeeder::DEMO_PASSWORD, 'new_password' => 'short', 'new_password_confirmation' => 'short'])
            ->assertSessionHasErrors('new_password');

        // This route pointed at a method that did not exist.
        $this->actingAs($attendee)->post('/settings/currency', ['currency' => 'USD'])->assertRedirect()->assertSessionHas('status');
        $this->actingAs($attendee)->postJson('/settings/currency', ['currency' => 'USD'])->assertOk()->assertJsonPath('success', true);

        // The page shows no invented infrastructure values.
        $this->actingAs($attendee)->get('/settings')->assertOk()->assertDontSee('island-events-flyers-prod')->assertDontSee('prod_••••');
    }

    public function test_a_host_saves_payment_instructions_refund_policy_and_food_buffer(): void
    {
        $this->actingAs($this->host())->post('/settings/host', [
            'default_timezone' => 'America/Chicago',
            'default_order_deadline_hours' => 12,
            'food_prep_buffer_percent' => 15,
            'enable_cash' => '0',
            'zelle_instructions' => 'Send to pay@demo.example with your order number.',
            'require_transfer_reference' => '1',
            'require_sender_name' => '0',
            'refund_policy_deadline_hours' => 48,
            'refund_policy_notes' => 'Full refunds until 48 hours before the event.',
        ])->assertRedirect(route('settings.show') . '#host')->assertSessionHas('status');

        $policy = HostPaymentPolicy::where('organizer_id', 'org-kingston-youth')->firstOrFail();
        $this->assertFalse($policy->enable_cash);
        $this->assertSame('Send to pay@demo.example with your order number.', $policy->zelle_instructions);
        $this->assertSame(48, (int) $policy->refund_policy_deadline_hours);
        $this->assertSame(15, (int) HostOperationalRule::where('organizer_id', 'org-kingston-youth')->value('food_prep_buffer_percent'));

        // The refund policy is what the public event page shows.
        $this->get('/events/' . self::EVENT)->assertSee('Full refunds until 48 hours before the event.');

        // Out of range and wrong role are refused.
        $this->actingAs($this->host())->post('/settings/host', ['food_prep_buffer_percent' => 90])->assertSessionHasErrors('food_prep_buffer_percent');
        $this->actingAs($this->user('attendee@islandevents.example'))->post('/settings/host', ['enable_cash' => '1'])->assertForbidden();
    }

    public function test_hosts_manage_ticket_types_from_the_event_form(): void
    {
        $tiers = [
            ['name' => 'General admission', 'price' => '12.50', 'available_stock' => 150, 'max_per_customer' => 6, 'description' => 'Entry', 'sale_start' => '', 'sale_end' => ''],
            ['name' => 'Early bird', 'price' => '9', 'available_stock' => 40, 'max_per_customer' => 2, 'sale_start' => '2026-11-01T09:00', 'sale_end' => '2026-11-20T23:00'],
        ];
        $this->actingAs($this->host())
            ->put('/events/' . self::EVENT, $this->eventForm(['ticket_tiers_submitted' => '1', 'ticket_tiers' => $tiers]))
            ->assertRedirect(route('events.edit', self::EVENT))->assertSessionHasNoErrors();

        $stored = Event::allTenants()->findOrFail(self::EVENT)->ticket_tiers;
        $this->assertCount(2, $stored);
        $this->assertSame('General admission', $stored[0]['name']);
        $this->assertSame(1250, \App\Services\Checkout\TicketTierCatalog::priceMinorFor($stored[0], 'USD'));
        $this->assertSame(150, $stored[0]['availableStock']);
        $this->assertStringStartsWith('2026-11-01T09:00:00', $stored[1]['saleStartIso']);

        // Editing a row keeps its id; the edit page shows the saved values.
        $id = $stored[0]['id'];
        $this->actingAs($this->host())->put('/events/' . self::EVENT, $this->eventForm(['ticket_tiers_submitted' => '1', 'ticket_tiers' => [
            ['id' => $id, 'name' => 'General admission', 'price' => '15', 'available_stock' => 140, 'max_per_customer' => 6],
        ]]))->assertSessionHasNoErrors();
        $this->assertSame($id, Event::allTenants()->findOrFail(self::EVENT)->ticket_tiers[0]['id']);
        $this->actingAs($this->host())->get('/events/' . self::EVENT . '/edit')->assertOk()->assertSee('value="140"', false);

        // A limit per customer above the tickets available is rejected and nothing changes.
        $this->actingAs($this->host())->put('/events/' . self::EVENT, $this->eventForm(['ticket_tiers_submitted' => '1', 'ticket_tiers' => [
            ['name' => 'VIP', 'price' => '50', 'available_stock' => 3, 'max_per_customer' => 5],
        ]]))->assertSessionHasErrors('ticket_tiers.0.max_per_customer');
        $this->assertSame('General admission', Event::allTenants()->findOrFail(self::EVENT)->ticket_tiers[0]['name']);
    }

    public function test_event_form_rejects_a_flyer_that_is_not_an_image(): void
    {
        $file = UploadedFile::fake()->create('flyer.php', 10, 'application/x-php');
        $this->actingAs($this->host())->put('/events/' . self::EVENT, $this->eventForm(['flyer' => $file]))
            ->assertSessionHasErrors('flyer');

        $this->actingAs($this->host())->post('/events', $this->eventForm(['title' => 'New Night', 'flyer' => $file]))
            ->assertSessionHasErrors('flyer');
        $this->assertFalse(Event::allTenants()->where('title', 'New Night')->exists());
    }

    public function test_new_events_keep_their_date_so_they_are_listed_as_upcoming(): void
    {
        $this->actingAs($this->host())->post('/events', $this->eventForm(['title' => 'Winter Supper Club', 'event_date_iso' => '2027-01-16', 'status' => 'published']))
            ->assertRedirect();
        $event = Event::allTenants()->where('title', 'Winter Supper Club')->firstOrFail();
        $this->assertSame('2027-01-16', substr((string) $event->event_date_iso, 0, 10));

        $this->actingAs($this->host())->post('/events', $this->eventForm(['title' => 'Bad Date', 'event_date_iso' => '16/01/2027']))
            ->assertSessionHasErrors('event_date_iso');
    }

    public function test_host_application_links_only_real_web_addresses(): void
    {
        $attendee = $this->user('attendee@islandevents.example');
        HostApplication::create(['user_id' => $attendee->id, 'organization_name' => 'Sneaky Org', 'country' => 'US', 'contact_phone' => '+13055550100',
            'website_or_social' => 'javascript:alert(1)', 'status' => 'pending']);

        $this->actingAs($this->user('admin@islandevents.example'))->get('/admin/host-applications')->assertOk()
            ->assertSee('Sneaky Org')
            ->assertDontSee('href="javascript:alert(1)"', false);
    }

    public function test_delegated_admins_are_not_offered_records_they_cannot_open(): void
    {
        $this->seed(SystemAdminSeeder::class);
        $root = User::where('email', config('events.system_admin.email'))->firstOrFail();
        $admin = $this->user('admin@islandevents.example');

        $this->actingAs($admin)->get('/admin/console/users/' . $root->id)->assertForbidden();
        $this->actingAs($admin)->get('/admin/console/users')->assertOk()
            ->assertDontSee('href="' . route('admin.console.users.show', $root) . '"', false);
    }
}
