<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\EventManagementService;
use App\Services\OrderPlacementService;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Save draft, review and publish: incomplete drafts save, publishing runs the full readiness check, nothing is
 * duplicated by a refresh or retry, and a draft is never purchasable.
 */
class EventPublishWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
        $this->host = User::where('email', 'host@islandevents.example')->firstOrFail();
    }

    private function complete(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Harbour Lights Food Night',
            'description' => 'An evening of Caribbean food, live music and dancing by the water.',
            'event_date_iso' => now()->addMonth()->toDateString(), 'start_time' => '18:00', 'end_time' => '23:00',
            'timezone' => 'America/New_York', 'venue_name' => 'Bayfront Park', 'street_address' => '301 Biscayne Blvd',
            'city' => 'Miami', 'state_code' => 'FL', 'zip_code' => '33132', 'country_code' => 'US',
            'organizer_name' => 'Kingston Youth Collective', 'organizer_phone' => '+1 305 555 0142', 'organizer_email' => 'hello@example.test',
            'advance_order_deadline' => now()->addWeeks(3)->format('Y-m-d\TH:i'), 'supports_food_ordering' => '0',
            'refund_policy_deadline_hours' => 48, 'refund_policy_notes' => 'Refunds up to two days before the event.',
            'ticket_tiers_submitted' => '1',
            'ticket_tiers' => [['name' => 'General Admission', 'price' => '15.00', 'available_stock' => 100, 'max_per_customer' => 6]],
            'creation_key' => (string) Str::uuid(),
        ], $overrides);
    }

    private function eventsCalled(string $title)
    {
        return Event::withoutGlobalScopes()->where('title', $title);
    }

    public function test_an_incomplete_draft_saves_and_lists_what_is_missing(): void
    {
        $response = $this->actingAs($this->host)->post(route('events.store'), [
            'title' => 'Half-planned Fish Fry', 'status' => 'draft', 'creation_key' => (string) Str::uuid(),
        ]);

        $event = $this->eventsCalled('Half-planned Fish Fry')->firstOrFail();
        $response->assertRedirect(route('events.edit', $event->id))->assertSessionHas('status');
        $this->assertSame('draft', $event->status);
        $this->assertFalse((bool) $event->is_ordering_open);

        $this->actingAs($this->host)->get(route('events.edit', $event->id))->assertOk()
            ->assertSee('to finish before publishing')->assertSee('Name the venue.')->assertSee('visible only to you');
    }

    public function test_a_refresh_or_retry_of_the_same_form_does_not_create_a_second_event(): void
    {
        $form = $this->complete(['status' => 'draft']);
        $this->actingAs($this->host)->post(route('events.store'), $form)->assertRedirect();
        $again = $this->actingAs($this->host)->post(route('events.store'), $form);

        $this->assertSame(1, $this->eventsCalled('Harbour Lights Food Night')->count());
        $event = $this->eventsCalled('Harbour Lights Food Night')->first();
        $again->assertRedirect(route('events.edit', $event->id))->assertSessionHas('status', fn ($m) => str_contains($m, 'already saved'));

        // The create form's autosave uses the same key, so it never makes a second draft either.
        $autosave = ['title' => 'Autosaved Draft', 'creation_key' => (string) Str::uuid()];
        $first = $this->actingAs($this->host)->postJson(route('events.create.autosave'), $autosave)->assertCreated()->json('event_id');
        $second = $this->actingAs($this->host)->postJson(route('events.create.autosave'), $autosave)->json('event_id');
        $this->assertSame($first, $second);
        $this->assertSame(1, $this->eventsCalled('Autosaved Draft')->count());
    }

    public function test_publishing_an_incomplete_event_keeps_it_a_draft_and_shows_the_review_screen(): void
    {
        $form = $this->complete(['status' => 'published', 'venue_name' => '', 'description' => 'Short']);
        $response = $this->actingAs($this->host)->post(route('events.store'), $form);

        $event = $this->eventsCalled('Harbour Lights Food Night')->firstOrFail();
        $response->assertRedirect(route('events.review', $event->id));
        $this->assertSame('draft', $event->status, 'Saved, but not published.');

        $this->actingAs($this->host)->post(route('events.store'), $form);
        $this->assertSame(1, $this->eventsCalled('Harbour Lights Food Night')->count(), 'Trying again does not duplicate it.');

        $this->actingAs($this->host)->get(route('events.review', $event->id))->assertOk()
            ->assertSee('to finish before publishing')->assertSee('Name the venue.')
            ->assertSee('Write a description')->assertSee('Fix in When and where')
            ->assertSee(route('events.edit', $event->id) . '#studio-location', false)
            ->assertSee('Publish event');

        $this->actingAs($this->host)->post(route('events.publish', $event->id))->assertRedirect(route('events.review', $event->id));
        $this->assertSame('draft', $event->fresh()->status, 'The publish button cannot skip the check.');
    }

    public function test_a_complete_event_publishes_and_lands_on_its_edit_page_with_its_qr_identity(): void
    {
        $response = $this->actingAs($this->host)->post(route('events.store'), $this->complete(['status' => 'published']));

        $event = $this->eventsCalled('Harbour Lights Food Night')->firstOrFail();
        $response->assertRedirect(route('events.edit', $event->id))->assertSessionHas('status', fn ($m) => str_contains($m, 'is published'));
        $this->assertSame(['published', true, 'org-kingston-youth'], [$event->status, (bool) $event->is_ordering_open, $event->organizer_id]);
        $this->assertSame('General Admission', $event->ticket_tiers[0]['name']);
        $this->assertNotNull(\App\Models\EventQrCode::where('event_id', $event->id)->first(), 'The promotional QR identity is created.');
        $this->assertSame([], array_filter(app(EventManagementService::class)->publicationReadiness($event, $this->host), fn ($i) => $i['critical']));
    }

    public function test_food_menu_problems_block_publishing_when_food_ordering_is_on(): void
    {
        $this->actingAs($this->host)->post(route('events.store'), $this->complete(['status' => 'draft', 'supports_food_ordering' => '1']));
        $event = $this->eventsCalled('Harbour Lights Food Night')->firstOrFail();
        $messages = fn () => array_column(array_filter(app(EventManagementService::class)->publicationReadiness($event->fresh(), $this->host), fn ($i) => $i['critical']), 'message');

        $this->assertContains('Food ordering is on but the menu is empty. Add menu items, or turn food ordering off.', $messages());

        $rice = MenuItem::allTenants()->create(['organizer_id' => $event->organizer_id, 'event_id' => $event->id, 'name' => 'Rice', 'category' => 'Sides',
            'pricing_type' => 'fixed', 'price_usd' => '4.00', 'price_jmd' => 0, 'currency' => 'USD', 'is_active' => true]);
        MenuItem::allTenants()->create(['organizer_id' => $event->organizer_id, 'event_id' => $event->id, 'name' => 'Plate', 'category' => 'Meal Packages',
            'item_type' => 'package', 'pricing_type' => 'fixed', 'price_usd' => '12.00', 'price_jmd' => 0, 'currency' => 'USD', 'is_active' => true,
            'package_config' => ['components' => [['menu_item_id' => $rice->id, 'quantity' => 1, 'substitutes' => []]], 'choice_groups' => [], 'add_ons' => []]]);
        $this->assertSame([], $messages());

        $rice->update(['is_active' => false]);
        $this->assertContains("The 'Plate' package includes an item that is hidden or removed, so it cannot be ordered.", $messages());
        $this->expectException(\InvalidArgumentException::class);
        app(EventManagementService::class)->setStatus($this->host, $event->id, 'published');
    }

    public function test_a_draft_is_never_purchasable_even_with_its_link(): void
    {
        $this->actingAs($this->host)->post(route('events.store'), $this->complete(['status' => 'draft']));
        $event = $this->eventsCalled('Harbour Lights Food Night')->firstOrFail();
        $event->forceFill(['is_ordering_open' => true])->save();
        $tierId = $event->ticket_tiers[0]['id'];
        $buyer = User::where('email', 'attendee@islandevents.example')->firstOrFail();

        $result = app(OrderPlacementService::class)->placeOrder($event->fresh(), [
            'customer_name' => 'Ana Guest', 'customer_phone' => '+13055550199', 'method' => 'cash', 'items' => [['menu_item_id' => $tierId, 'quantity' => 1]],
        ], $buyer);
        $this->assertFalse($result['success']);
        $this->assertSame('This event is not on sale.', $result['error']);

        $this->actingAs($buyer)->get(route('events.show', $event->id))->assertNotFound();
        $this->actingAs($buyer)->get(route('checkout.tickets', $event))->assertNotFound();
    }
}
