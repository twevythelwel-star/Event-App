<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Organizer;
use App\Models\OrganizerUser;
use App\Models\User;
use App\Models\UserRole;
use App\Services\OrderPlacementService;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Database\Seeders\SystemAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The event studio requirements not covered elsewhere: who may create and change events, the authorization gaps
 * closed in the security review, ticket types (several, free, stock), the ticket designer showing the saved design,
 * the ticket QR credential, validation messages, and packages rejecting another event's items.
 *
 * Covered in other files: drafts, publishing, readiness and duplicate submissions (EventPublishWorkflowTest);
 * schedule, food, packages, mix-and-match, add-ons, stock and availability (FoodMenuAndScheduleTest); the cultural
 * catalog (CulturalCatalogApiTest); sales windows (CheckoutCorrectnessTest).
 */
class EventStudioRequirementsTest extends TestCase
{
    use RefreshDatabase;

    private const KINGSTON = 'evt-kingston-harbour-fry';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class, SystemAdminSeeder::class]);
    }

    private function user(string $who): User
    {
        return User::where('email', "{$who}@islandevents.example")->firstOrFail();
    }

    private function otherHost(): User
    {
        $organizer = Organizer::firstOrCreate(['slug' => 'other-kitchen'], ['name' => 'Other Kitchen']);
        $host = User::create(['name' => 'Other Host', 'email' => 'other-host@example.test', 'password' => bcrypt('x'), 'status' => 'active']);
        UserRole::create(['user_id' => $host->id, 'role' => 'event_host', 'is_active' => true, 'assigned_at' => now()]);
        OrganizerUser::create(['organizer_id' => $organizer->id, 'user_id' => $host->id, 'role' => 'event_owner', 'is_active' => true]);

        return $host;
    }

    private function draftForm(array $overrides = []): array
    {
        return array_merge(['title' => 'Studio Requirements Night', 'status' => 'draft', 'creation_key' => (string) Str::uuid()], $overrides);
    }

    // ---- Access ---------------------------------------------------------------------------------------------------

    public function test_hosts_and_authorized_system_admins_create_events_and_attendees_are_sent_to_apply(): void
    {
        $this->actingAs($this->user('host'))->post(route('events.store'), $this->draftForm())->assertRedirect();
        $this->assertSame('org-kingston-youth', Event::withoutGlobalScopes()->where('title', 'Studio Requirements Night')->value('organizer_id'));

        $admin = User::where('email', config('events.system_admin.email'))->firstOrFail();
        $this->actingAs($admin)->get(route('events.create'))->assertOk()->assertSee('Choose the organizer this event belongs to');
        $this->actingAs($admin)->post(route('events.store'), $this->draftForm(['title' => 'Admin Made Night']))->assertForbidden();
        $this->actingAs($admin)->post(route('events.store'), $this->draftForm(['title' => 'Admin Made Night', 'organizer_id' => 'org-kingston-youth']))->assertRedirect();
        $this->assertSame('org-kingston-youth', Event::withoutGlobalScopes()->where('title', 'Admin Made Night')->value('organizer_id'));

        $attendee = $this->user('attendee');
        $this->actingAs($attendee)->get(route('events.create'))->assertRedirect(route('host.apply'));
        $this->actingAs($attendee)->post(route('events.store'), $this->draftForm(['title' => 'Sneaky Night']))->assertRedirect(route('host.apply'));
        $this->assertFalse(Event::withoutGlobalScopes()->where('title', 'Sneaky Night')->exists());
    }

    public function test_a_host_cannot_choose_another_organizer_for_a_new_event(): void
    {
        $other = $this->otherHost();
        $this->actingAs($other)->post(route('events.store'), $this->draftForm(['organizer_id' => 'org-kingston-youth']))->assertRedirect();

        $this->assertSame('other-kitchen', Organizer::find(Event::withoutGlobalScopes()->where('title', 'Studio Requirements Night')->value('organizer_id'))->slug);
    }

    public function test_hosts_from_another_organizer_cannot_touch_the_event_its_menu_or_its_publishing(): void
    {
        $other = $this->otherHost();
        $item = MenuItem::allTenants()->where('event_id', self::KINGSTON)->firstOrFail();
        $before = Event::withoutGlobalScopes()->find(self::KINGSTON)->only(['title', 'status', 'is_ordering_open']);

        foreach ([
            ['get', route('events.edit', self::KINGSTON)],
            ['put', route('events.update', self::KINGSTON)],
            ['get', route('events.review', self::KINGSTON)],
            ['post', route('events.publish', self::KINGSTON)],
            ['post', route('events.toggle-ordering', self::KINGSTON)],
            ['get', route('events.menu-studio', self::KINGSTON)],
            ['post', route('events.menu-studio.items.store', self::KINGSTON)],
            ['put', route('events.menu-studio.update', [self::KINGSTON, $item->id])],
            ['delete', route('events.menu-studio.destroy', [self::KINGSTON, $item->id])],
            ['post', route('events.menu-studio.order', self::KINGSTON)],
        ] as [$method, $url]) {
            $this->actingAs($other)->{$method}($url, ['title' => 'Hijacked', 'name' => 'Hijacked', 'category' => 'Sides', 'price' => 1])->assertNotFound("{$method} {$url}");
        }

        $this->assertSame($before, Event::withoutGlobalScopes()->find(self::KINGSTON)->only(['title', 'status', 'is_ordering_open']));
        $this->assertNotNull($item->fresh());
    }

    // ---- Security review fixes --------------------------------------------------------------------------------------

    public function test_saving_the_form_cannot_publish_or_open_ordering_past_the_readiness_check(): void
    {
        $host = $this->user('host');
        $this->actingAs($host)->post(route('events.store'), $this->draftForm());
        $event = Event::withoutGlobalScopes()->where('title', 'Studio Requirements Night')->firstOrFail();

        // A crafted save asking to publish and open ordering: the details save, the draft stays a draft.
        $this->actingAs($host)->put(route('events.update', $event->id), ['title' => 'Studio Requirements Night', 'status' => 'published', 'is_ordering_open' => '1'])
            ->assertSessionHasErrors('venue_name');
        $this->assertSame(['draft', false], [$event->fresh()->status, (bool) $event->fresh()->is_ordering_open]);

        $this->actingAs($host)->put(route('events.update', $event->id), ['title' => 'Studio Requirements Night', 'tagline' => 'Changed',
            'venue_name' => 'Bayfront Park', 'organizer_name' => 'Kingston Youth', 'organizer_phone' => '+1 305 555 0142',
            'advance_order_deadline' => now()->addWeek()->format('Y-m-d\TH:i'), 'status' => 'published', 'is_ordering_open' => '1'])
            ->assertRedirect(route('events.review', $event->id));
        $fresh = $event->fresh();
        $this->assertSame(['draft', false, 'Changed'], [$fresh->status, (bool) $fresh->is_ordering_open, $fresh->tagline], 'The details save; publishing waits for the readiness check.');

        // An incomplete draft can still be edited and saved without an address.
        $this->actingAs($host)->put(route('events.update', $event->id), ['title' => 'Studio Requirements Night', 'tagline' => 'Still a draft'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Still a draft', $event->fresh()->tagline);

        $this->actingAs($host)->post(route('events.toggle-ordering', $event->id))->assertSessionHas('error', 'Publish the event before opening it for orders.');
        $this->assertFalse((bool) $event->fresh()->is_ordering_open);
    }

    public function test_validation_messages_name_the_problem(): void
    {
        $host = $this->user('host');
        $this->actingAs($host)->post(route('events.store'), ['status' => 'draft', 'creation_key' => (string) Str::uuid()])->assertSessionHasErrors('title');
        $this->actingAs($host)->put(route('events.update', self::KINGSTON), ['title' => 'South Florida Jamaican Fish Fry', 'venue_name' => ''])
            ->assertSessionHasErrors(['venue_name', 'organizer_name', 'organizer_phone']);
        $this->actingAs($host)->post(route('events.menu-studio.items.store', self::KINGSTON), ['name' => 'X', 'category' => 'Sides', 'price' => '-2'])
            ->assertSessionHasErrors(['name', 'price']);
        $this->actingAs($host)->followingRedirects()->from(route('events.menu-studio', self::KINGSTON))
            ->post(route('events.menu-studio.items.store', self::KINGSTON), ['name' => 'X', 'category' => 'Sides', 'price' => '-2', 'item_type_form' => 'new-item'])
            ->assertSee('Not saved: please check the form')->assertSee('role="alert"', false);
    }

    // ---- Packages ---------------------------------------------------------------------------------------------------

    public function test_a_package_cannot_include_another_events_items(): void
    {
        $foreign = MenuItem::allTenants()->where('event_id', '!=', self::KINGSTON)->firstOrFail();
        $own = MenuItem::allTenants()->where('event_id', self::KINGSTON)->where('item_type', 'item')->firstOrFail();

        $this->actingAs($this->user('host'))->post(route('events.menu-studio.packages.store', self::KINGSTON), [
            'name' => 'Borrowed Plate', 'category' => 'Meal Packages', 'price' => '10.00',
            'components' => [['menu_item_id' => $own->id, 'quantity' => 1], ['menu_item_id' => $foreign->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('package_config.components.1.menu_item_id');
        $this->assertFalse(MenuItem::allTenants()->where('name', 'Borrowed Plate')->exists());
    }

    // ---- Tickets ----------------------------------------------------------------------------------------------------

    public function test_several_ticket_types_including_free_ones_sell_within_their_stock(): void
    {
        $event = Event::withoutGlobalScopes()->findOrFail(self::KINGSTON);
        $event->update(['ticket_tiers' => [
            ['id' => 'tier-free', 'name' => 'Community Pass', 'priceUSD' => 0, 'availableStock' => 2, 'maxPerCustomer' => 2],
            ['id' => 'tier-vip', 'name' => 'VIP', 'priceUSD' => 40, 'availableStock' => 5, 'maxPerCustomer' => 4],
        ]]);
        $buyer = $this->user('attendee');
        $place = fn (array $items) => app(OrderPlacementService::class)->placeOrder($event->fresh(), [
            'customer_name' => 'Ana Guest', 'customer_phone' => '+13055550199', 'method' => 'cash', 'items' => $items,
        ], $buyer);

        $mixed = $place([['menu_item_id' => 'tier-free', 'quantity' => 1], ['menu_item_id' => 'tier-vip', 'quantity' => 2]]);
        $this->assertTrue($mixed['success'], $mixed['error'] ?? '');
        $this->assertSame(8000, (int) $mixed['order']->total_minor, 'The free pass adds nothing; two VIP tickets are $80.');

        $free = $place([['menu_item_id' => 'tier-free', 'quantity' => 1]]);
        $this->assertSame(0, (int) $free['order']->total_minor);

        $soldOut = $place([['menu_item_id' => 'tier-free', 'quantity' => 1]]);
        $this->assertFalse($soldOut['success']);
        $this->assertStringContainsString('Insufficient ticket inventory', $soldOut['error']);
        $this->assertSame(0, (int) collect($event->fresh()->ticket_tiers)->firstWhere('id', 'tier-free')['availableStock']);
    }

    public function test_the_ticket_designer_shows_the_saved_design_and_the_qr_credential_is_issued_only_when_paid(): void
    {
        $event = Event::withoutGlobalScopes()->findOrFail(self::KINGSTON);
        $tiers = $event->ticket_tiers;
        $tiers[0] = array_merge($tiers[0], ['designStyle' => 'concert_pass', 'accentColor' => 'coral', 'backgroundStyle' => 'glow']);
        $event->update(['ticket_tiers' => $tiers]);

        $this->actingAs($this->user('host'))->get(route('events.edit', self::KINGSTON))->assertOk()
            ->assertSee('data-design="concert_pass" data-accent="coral" data-bg="glow"', false);

        $buyer = $this->user('attendee');
        $orders = collect([1, 2])->map(fn () => app(OrderPlacementService::class)->placeOrder($event->fresh(), [
            'customer_name' => 'Ana Guest', 'customer_phone' => '+13055550199', 'method' => 'cash', 'items' => [['menu_item_id' => $tiers[0]['id'], 'quantity' => 1]],
        ], $buyer)['order']);
        foreach ($orders as $order) {
            $this->assertMatchesRegularExpression('/^tok_[A-Za-z0-9]{32}$/', $order->secure_ticket_token);
        }
        $this->assertNotSame($orders[0]->secure_ticket_token, $orders[1]->secure_ticket_token);

        $order = $orders[0];
        $this->actingAs($buyer)->get(route('tickets.show', $order))->assertOk()
            ->assertSee('Your ticket QR code appears here once your payment is confirmed.')->assertDontSee('Print ticket');
        $order->update(['status' => 'paid', 'amount_paid_minor' => $order->total_minor]);
        $this->actingAs($buyer)->get(route('tickets.show', $order))->assertOk()->assertSee('QR code for ticket ' . $order->ticket_code)->assertSee('Print ticket');
        $this->actingAs($this->otherHost())->get(route('tickets.show', $order))->assertNotFound();
    }
}
