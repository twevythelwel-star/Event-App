<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventComplianceAcknowledgement;
use App\Models\PolicyAcceptance;
use App\Models\User;
use App\Services\Compliance\EventComplianceService;
use App\Services\EventManagementService;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The Event Compliance acknowledgement: given again for every new event, after its jurisdiction, stored as its own
 * record per event, and required again when the jurisdiction, food or alcohol arrangements materially change.
 */
class EventComplianceAcknowledgementTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
        $this->host = User::where('email', 'host@islandevents.example')->firstOrFail();
    }

    private function form(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Harbour Lights Food Night',
            'description' => 'An evening of Caribbean food, live music and dancing by the water.',
            'event_date_iso' => now()->addMonth()->toDateString(), 'start_time' => '18:00', 'end_time' => '23:00',
            'timezone' => 'America/New_York', 'venue_name' => 'Bayfront Park', 'street_address' => '301 Biscayne Blvd',
            'city' => 'Miami', 'state_code' => 'FL', 'zip_code' => '33132', 'country_code' => 'US',
            'organizer_name' => 'Kingston Youth Collective', 'organizer_phone' => '+1 305 555 0142', 'organizer_email' => 'hello@example.test',
            'advance_order_deadline' => now()->addWeeks(3)->format('Y-m-d\TH:i'), 'supports_food_ordering' => '0',
            'serves_alcohol' => '0', 'alcohol_permit_status' => 'no_alcohol',
            'refund_policy_deadline_hours' => 48, 'refund_policy_notes' => 'Refunds up to two days before the event.',
            'ticket_tiers_submitted' => '1',
            'ticket_tiers' => [['name' => 'General Admission', 'price' => '15.00', 'available_stock' => 100, 'max_per_customer' => 6]],
            'creation_key' => (string) Str::uuid(),
        ], $this->eventComplianceFields(), $overrides);
    }

    private function eventCalled(string $title): ?Event
    {
        return Event::withoutGlobalScopes()->where('title', $title)->first();
    }

    private function editForm(Event $event, array $overrides = []): array
    {
        return array_merge([
            'title' => $event->title, 'description' => $event->description,
            'event_date_iso' => $event->event_date_iso, 'start_time' => '18:00', 'end_time' => '23:00',
            'timezone' => $event->timezone, 'venue_name' => $event->venue_name, 'street_address' => $event->street_address,
            'city' => $event->city, 'state_code' => $event->state_code, 'zip_code' => $event->zip_code, 'country_code' => 'US',
            'organizer_name' => $event->organizer_name, 'organizer_phone' => $event->organizer_phone,
            'supports_food_ordering' => $event->supports_food_ordering ? '1' : '0',
            'serves_alcohol' => $event->serves_alcohol ? '1' : '0', 'alcohol_permit_status' => $event->alcohol_permit_status ?? 'no_alcohol',
            'alcohol_age_verification_required' => $event->alcohol_age_verification_required ? '1' : '0',
            'compliance_jurisdiction_county' => $event->compliance_jurisdiction_county,
            'compliance_jurisdiction_municipality' => $event->compliance_jurisdiction_municipality,
        ], $overrides);
    }

    public function test_the_create_form_shows_the_event_compliance_section_with_every_declaration(): void
    {
        $page = $this->actingAs($this->host)->get(route('events.create'))->assertOk();

        $page->assertSee('Event Compliance')->assertSee('Applicable jurisdiction')
            ->assertSee('separate from the policies you accepted when you registered');
        foreach (EventComplianceService::declarations() as $group) {
            $page->assertSee($group['title']);
            foreach ($group['items'] as $field => $statement) {
                $page->assertSee('name="' . $field . '"', false)->assertSee($statement);
            }
        }
        // None is ticked in advance: the host confirms each one.
        $this->assertStringNotContainsString('data-compliance-declaration checked', $page->getContent());
    }

    public function test_an_event_is_not_created_without_every_declaration(): void
    {
        $form = $this->form(['title' => 'Missing One Declaration']);
        unset($form['decl_alcohol_responsible_service']);

        $this->actingAs($this->host)->post(route('events.store'), $form)
            ->assertSessionHasErrors(['compliance' => 'Confirm every Event Compliance declaration. 1 under "Alcohol" is not confirmed.']);
        $this->assertNull($this->eventCalled('Missing One Declaration'));
        $this->assertSame(0, EventComplianceAcknowledgement::count());
    }

    public function test_the_jurisdiction_is_required_before_the_declarations(): void
    {
        $form = $this->form(['title' => 'No Jurisdiction', 'compliance_jurisdiction_county' => '', 'compliance_jurisdiction_municipality' => '']);

        $response = $this->actingAs($this->host)->post(route('events.store'), $form);

        $response->assertSessionHasErrors('compliance');
        $this->assertStringContainsString("applicable jurisdiction", session('errors')->first('compliance'));
        $this->assertNull($this->eventCalled('No Jurisdiction'));
    }

    public function test_each_new_event_gets_its_own_acknowledgement_record(): void
    {
        $this->actingAs($this->host)->post(route('events.store'), $this->form(['title' => 'First Night']))->assertRedirect();
        $this->actingAs($this->host)->post(route('events.store'), $this->form(['title' => 'Second Night']))->assertRedirect();

        $first = $this->eventCalled('First Night');
        $second = $this->eventCalled('Second Night');
        $acks = EventComplianceAcknowledgement::orderBy('accepted_at_utc')->get();

        $this->assertCount(2, $acks);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $acks->pluck('event_id')->all());
        $ack = $acks->firstWhere('event_id', $first->id);
        $this->assertSame($ack->id, $first->compliance_acknowledgement_id);
        $this->assertSame(['event_creation', $this->host->id, 'US', 'FL', 'Miami-Dade County', 'City of Miami', true],
            [$ack->acknowledgement_context, $ack->user_id, $ack->jurisdiction_country, $ack->jurisdiction_state,
                $ack->jurisdiction_county, $ack->jurisdiction_municipality, $ack->all_declarations_accepted]);
        $this->assertCount(5, $ack->food_safety_declarations);
        $this->assertCount(6, $ack->alcohol_declarations);
        $this->assertCount(4, $ack->general_legal_declarations);
        $this->assertTrue(collect($ack->alcohol_declarations)->every(fn ($row) => $row['accepted'] === true && $row['statement'] !== ''));
        $this->assertNotNull($ack->ip_address);
        $this->assertSame('current', app(EventComplianceService::class)->status($first)['state']);

        // Also in the host's policy history, linked to this event, and separate from registration.
        $this->assertSame(3, PolicyAcceptance::where('context', 'event_creation')->where('context_entity_id', $first->id)->count());
    }

    public function test_a_draft_saved_without_the_declarations_cannot_be_published_until_they_are_confirmed(): void
    {
        // An autosaved draft (or an API-created one) has no acknowledgement yet.
        $event = app(EventManagementService::class)->createEvent($this->host, array_diff_key(
            $this->form(['title' => 'Autosaved Draft', 'supports_food_ordering' => false]),
            array_flip([...EventComplianceService::fieldNames(), 'ticket_tiers', 'ticket_tiers_submitted'])
        ) + ['ticket_tiers' => [['id' => 'ga', 'name' => 'General Admission', 'priceUSD' => 15, 'priceJMD' => 2300, 'availableStock' => 100, 'maxPerCustomer' => 6]]]);
        $this->assertSame('missing', app(EventComplianceService::class)->status($event)['state']);

        $issues = collect(app(EventManagementService::class)->publicationReadiness($event, $this->host));
        $this->assertTrue($issues->contains(fn ($i) => $i['section'] === 'event-compliance' && $i['critical']));
        try {
            app(EventManagementService::class)->setStatus($this->host, $event->id, 'published');
            $this->fail('Published without an Event Compliance acknowledgement.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Event Compliance declarations', $e->getMessage());
        }

        $this->actingAs($this->host)->put(route('events.update', $event->id), $this->editForm($event, $this->eventComplianceFields()))
            ->assertSessionHasNoErrors();
        $this->assertSame('current', app(EventComplianceService::class)->status($event->fresh())['state']);
        $this->assertFalse(collect(app(\App\Services\Compliance\EventPublicationGates::class)->check($event->fresh(), $this->host))
            ->contains('code', 'event_ack'));
    }

    public function test_changing_the_alcohol_arrangements_needs_the_declarations_again(): void
    {
        $this->actingAs($this->host)->post(route('events.store'), $this->form(['title' => 'Rum Tasting']))->assertRedirect();
        $event = $this->eventCalled('Rum Tasting');
        $original = $event->compliance_acknowledgement_id;

        // Turning alcohol service on without confirming again is refused, and nothing is saved.
        $this->actingAs($this->host)->put(route('events.update', $event->id), $this->editForm($event, [
            'serves_alcohol' => '1', 'alcohol_age_verification_required' => '1', 'alcohol_permit_status' => 'special_event_permit_obtained',
        ]))->assertSessionHasErrors(['compliance' => "This change affects the event's alcohol-service arrangements. Review the Event Compliance declarations and confirm them again before saving."]);
        $this->assertFalse((bool) $event->fresh()->serves_alcohol);
        $this->assertSame($original, $event->fresh()->compliance_acknowledgement_id);

        // Confirming them again saves the change and replaces the earlier acknowledgement.
        $this->actingAs($this->host)->put(route('events.update', $event->id), $this->editForm($event, [
            'serves_alcohol' => '1', 'alcohol_age_verification_required' => '1', 'alcohol_permit_status' => 'special_event_permit_obtained',
        ] + $this->eventComplianceFields()))->assertSessionHasNoErrors();

        $event->refresh();
        $this->assertTrue((bool) $event->serves_alcohol);
        $old = EventComplianceAcknowledgement::find($original);
        $new = EventComplianceAcknowledgement::find($event->compliance_acknowledgement_id);
        $this->assertNotNull($old->superseded_at_utc);
        $this->assertSame("Replaced after a change to the alcohol-service arrangements.", $old->superseded_reason);
        $this->assertNull($new->superseded_at_utc);
        $this->assertSame(['event_material_change_reacknowledgement', true], [$new->acknowledgement_context, $new->serves_alcohol]);
        $this->assertSame(2, EventComplianceAcknowledgement::where('event_id', $event->id)->count());
    }

    public function test_changing_the_jurisdiction_or_food_service_needs_the_declarations_again(): void
    {
        $this->actingAs($this->host)->post(route('events.store'), $this->form(['title' => 'Moving Night']))->assertRedirect();
        $event = $this->eventCalled('Moving Night');

        $this->actingAs($this->host)->put(route('events.update', $event->id), $this->editForm($event, ['compliance_jurisdiction_county' => 'Broward County']))
            ->assertSessionHasErrors(['compliance' => "This change affects the event's applicable jurisdiction. Review the Event Compliance declarations and confirm them again before saving."]);
        $this->assertSame('Miami-Dade County', $event->fresh()->compliance_jurisdiction_county);

        // Through the management service (the API path) the same rule applies.
        try {
            app(EventManagementService::class)->updateEvent($this->host, $event->id, ['food_permit_status' => 'temporary_food_permit_obtained']);
            $this->fail('Food arrangements changed without a new acknowledgement.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('food-service arrangements', $e->getMessage());
        }

        // A change that does not touch these arrangements needs nothing.
        $this->actingAs($this->host)->put(route('events.update', $event->id), $this->editForm($event, ['title' => 'Moving Night Returns']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Moving Night Returns', $event->fresh()->title);
        $this->assertSame(1, EventComplianceAcknowledgement::where('event_id', $event->id)->count());
    }

    public function test_an_arrangement_changed_elsewhere_blocks_publishing_until_confirmed_again(): void
    {
        $this->actingAs($this->host)->post(route('events.store'), $this->form(['title' => 'Quiet Change']))->assertRedirect();
        $event = $this->eventCalled('Quiet Change');
        // A change made outside the forms (for example by a data fix) still leaves the acknowledgement out of date.
        $event->forceFill(['serves_alcohol' => true, 'alcohol_age_verification_required' => true, 'alcohol_permit_status' => 'special_event_permit_obtained'])->save();

        $this->assertSame('outdated', app(EventComplianceService::class)->status($event)['state']);
        $this->assertTrue(collect(app(EventManagementService::class)->publicationReadiness($event, $this->host))
            ->contains(fn ($i) => $i['section'] === 'event-compliance' && $i['critical']));
        $this->actingAs($this->host)->get(route('events.edit', $event->id))->assertOk()->assertSee('Confirm these again');
    }
}
