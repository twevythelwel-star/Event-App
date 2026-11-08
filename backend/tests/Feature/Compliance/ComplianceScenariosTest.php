<?php

namespace Tests\Feature\Compliance;

use App\Models\Compliance\ComplianceEventRule;
use App\Models\Compliance\ComplianceJurisdiction;
use App\Models\Compliance\CompliancePolicyVersion;
use App\Models\Event;
use App\Models\EventComplianceAcknowledgement;
use App\Models\EventComplianceEvidence;
use App\Models\HostIdentityVerification;
use App\Models\PolicyAcceptance;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Compliance\EventComplianceService;
use App\Services\Compliance\EventPublicationGates;
use App\Services\Compliance\EventRequirementChecklist;
use App\Services\Compliance\JurisdictionResolver;
use App\Services\EventManagementService;
use Database\Seeders\ComplianceDatabaseSeeder;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The compliance acceptance scenarios, one test each (numbers refer to the acceptance list):
 *  1 registration, 3–10 publishing and requirements, 11 policy re-acknowledgement, 17–19 jurisdictions and edits.
 * Authorization scenarios (2, 12–16) are in ComplianceAuthorizationTest; 20 and the end-to-end flow are in
 * ComplianceLifecycleIntegrationTest.
 */
class ComplianceScenariosTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = 'evt-kingston-harbour-fry';

    private User $host;
    private ComplianceJurisdiction $florida;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class, ComplianceDatabaseSeeder::class]);
        $this->host = User::where('email', 'host@islandevents.example')->firstOrFail();
        $this->florida = ComplianceJurisdiction::where('type', 'state')->where('code', 'FL')->firstOrFail();
        Event::withoutGlobalScopes()->whereKey(self::EVENT)->update(['state_code' => 'FL']);
    }

    /** The seeded event as a draft with its food and alcohol answers and its own acknowledgement. */
    private function readyEvent(array $arrangements = []): Event
    {
        $event = Event::withoutGlobalScopes()->findOrFail(self::EVENT);
        $event->forceFill(['status' => 'draft', 'is_ordering_open' => false] + $arrangements + $this->eventArrangementFields(true))->save();
        app(EventComplianceService::class)->record($this->host, $event->fresh(), $this->eventComplianceFields(), 'event_editing');

        return $event->fresh();
    }

    private function publish(Event $event): bool
    {
        try {
            app(EventManagementService::class)->setStatus($this->host, $event->id, 'published');

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    private function blocking(Event $event): array
    {
        return array_column(app(EventPublicationGates::class)->blocking($event->fresh(), $this->host), 'message');
    }

    private function rule(string $topic, string $location, array $conditions = [], ?ComplianceJurisdiction $where = null): ComplianceEventRule
    {
        return ComplianceEventRule::create(['jurisdiction_id' => ($where ?? $this->florida)->id, 'topic' => $topic, 'effect' => 'required',
            'location' => $location, 'conditions' => $conditions, 'source_url' => 'https://www.example.gov/rule', 'is_active' => true]);
    }

    private function eventForm(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Harbour Lights Fish Fry', 'date_str' => 'Saturday, December 5, 2026', 'time_str' => '5:00 PM - 11:00 PM',
            'timezone' => 'America/New_York', 'venue_name' => 'Bayfront Park', 'street_address' => '301 Biscayne Blvd', 'city' => 'Miami',
            'state_code' => 'FL', 'zip_code' => '33132', 'country_code' => 'US', 'venue_address' => '301 Biscayne Blvd, Miami, FL, 33132, United States',
            'organizer_name' => 'Kingston Youth Foundation', 'organizer_phone' => '+13055550100', 'advance_order_deadline' => '2026-12-04 18:00',
        ], $this->eventComplianceFields(), $overrides);
    }

    // 1 -----------------------------------------------------------------------------------------------------

    public function test_01_a_user_cannot_register_without_each_mandatory_policy_acknowledgement(): void
    {
        $person = ['first_name' => 'Ana', 'last_name' => 'Ruiz', 'email' => 'ana@example.test', 'password' => 'Secret-Pass-2026', 'password_confirmation' => 'Secret-Pass-2026', 'agree_terms' => '1'];
        $policies = ['accept_platform_terms' => '1', 'accept_food_safety_disclaimer' => '1', 'accept_alcohol_policy' => '1'];

        // Ticking only the general box is not enough, and neither is leaving any one policy out.
        $this->post(route('register'), $person)->assertSessionHasErrors(['accept_platform_terms', 'accept_food_safety_disclaimer', 'accept_alcohol_policy']);
        foreach (array_keys($policies) as $missing) {
            $this->post(route('register'), $person + array_diff_key($policies, [$missing => 1]))->assertSessionHasErrors($missing);
        }
        $this->postJson(route('api.register'), $person)->assertStatus(422);
        $this->assertDatabaseMissing('users', ['email' => 'ana@example.test']);
        $this->assertSame(0, PolicyAcceptance::whereIn('user_id', User::where('email', 'ana@example.test')->pluck('id'))->count());

        // With every policy ticked, the person is registered and each acceptance is recorded separately.
        $this->post(route('register'), $person + $policies)->assertRedirect();
        $user = User::where('email', 'ana@example.test')->firstOrFail();
        $this->assertSame(['alcohol_policy', 'food_safety_disclaimer', 'platform_terms_and_conditions'],
            PolicyAcceptance::where('user_id', $user->id)->where('context', 'account_registration')->orderBy('policy_name')->pluck('policy_name')->all());
    }

    // 3–5 ---------------------------------------------------------------------------------------------------

    public function test_03_an_unverified_host_cannot_publish_an_event(): void
    {
        $event = $this->readyEvent();
        HostIdentityVerification::where('user_id', $this->host->id)->update(['status' => 'pending_review']);
        $this->assertFalse($this->publish($event));
        $this->actingAs($this->host)->post(route('events.publish', $event->id));
        $this->assertSame('draft', $event->fresh()->status);
        $this->assertStringContainsString('has not passed government ID verification', implode(' ', $this->blocking($event)));
    }

    public function test_04_an_approved_host_can_create_an_event_and_others_cannot(): void
    {
        $this->actingAs($this->host)->post(route('events.store'), $this->eventForm(['title' => 'Approved Host Night']))->assertRedirect();
        $this->assertTrue(Event::withoutGlobalScopes()->where('title', 'Approved Host Night')->exists());

        $attendee = User::where('email', 'attendee@islandevents.example')->firstOrFail();
        $this->actingAs($attendee)->post(route('events.store'), $this->eventForm(['title' => 'Attendee Night']));
        $this->assertFalse(Event::withoutGlobalScopes()->where('title', 'Attendee Night')->exists());
    }

    public function test_05_a_host_acknowledges_the_event_policies_for_each_new_event(): void
    {
        $this->actingAs($this->host)->post(route('events.store'), $this->eventForm(['title' => 'First Night']))->assertRedirect();
        $first = Event::withoutGlobalScopes()->where('title', 'First Night')->firstOrFail();
        $this->assertSame(1, EventComplianceAcknowledgement::where('event_id', $first->id)->count());
        $this->assertSame(3, PolicyAcceptance::where('context_entity_id', $first->id)->where('context', 'event_creation')->count());

        // The first event's acknowledgement does not carry over: the next event needs its own.
        $withoutDeclarations = array_diff_key($this->eventForm(['title' => 'Second Night']), array_flip(EventComplianceService::fieldNames()));
        $this->actingAs($this->host)->post(route('events.store'), $withoutDeclarations)->assertSessionHasErrors();
        $this->assertFalse(Event::withoutGlobalScopes()->where('title', 'Second Night')->exists());
    }

    // 6–10 --------------------------------------------------------------------------------------------------

    public function test_06_a_host_cannot_publish_with_a_missing_required_food_permit(): void
    {
        $this->rule('food_permit', 'event', ['food_modes' => ['sold', 'both']]);
        $event = $this->readyEvent();
        $this->assertFalse($this->publish($event));
        $this->assertStringContainsString('Submit the permit or licence for Food permit requirements', implode(' ', $this->blocking($event)));
    }

    public function test_07_an_event_without_food_is_not_blocked_by_food_requirements(): void
    {
        $this->rule('food_permit', 'event');
        $this->rule('food_handler_training', 'food_preparation');
        $event = $this->readyEvent(['food_service_mode' => 'none', 'supports_food_ordering' => false, 'food_permit_status' => null,
            'allergen_disclosure_confirmed' => false, 'temperature_and_sanitation_confirmed' => false]);
        $this->assertSame([], $this->blocking($event));
        $this->assertTrue($this->publish($event));
    }

    public function test_08_an_event_without_alcohol_is_not_blocked_by_alcohol_requirements(): void
    {
        $this->rule('alcohol_licensing', 'alcohol_service');
        $this->rule('special_event_permit', 'alcohol_service');
        $event = $this->readyEvent();
        $this->assertSame('none', $event->alcohol_service_mode);
        $this->assertSame([], $this->blocking($event));
        $this->assertTrue($this->publish($event));
    }

    public function test_09_an_alcohol_event_cannot_publish_until_its_mandatory_licence_is_verified(): void
    {
        $this->rule('alcohol_licensing', 'alcohol_service', ['alcohol_modes' => ['sold']]);
        $event = $this->readyEvent(['alcohol_service_mode' => 'sold', 'serves_alcohol' => true, 'alcohol_age_verification_required' => true,
            'alcohol_permit_status' => 'special_event_permit_obtained']);
        $this->assertFalse($this->publish($event));

        $key = substr(collect(app(EventPublicationGates::class)->blocking($event, $this->host))->pluck('code')->first(), strlen(EventPublicationGates::OVERRIDABLE_PREFIX));
        $licence = EventComplianceEvidence::create(['event_id' => $event->id, 'item_key' => $key, 'item_title' => 'Alcohol licence', 'status' => 'submitted',
            'reference_number' => 'BEV-1', 'issuing_authority' => 'Florida ABT', 'submitted_by' => $this->host->id, 'submitted_at' => now()]);
        $this->assertFalse($this->publish($event), 'submitted but not verified');
        $licence->forceFill(['status' => 'pending_verification'])->save();
        $this->assertFalse($this->publish($event), 'verification in progress');
        $licence->forceFill(['status' => 'rejected', 'review_comment' => 'Wrong premises'])->save();
        $this->assertFalse($this->publish($event), 'rejected');
        $licence->forceFill(['status' => 'verified', 'expires_on' => now()->addYear()])->save();
        $this->assertTrue($this->publish($event));
    }

    public function test_10_expired_documents_bring_the_restrictions_back(): void
    {
        $this->rule('food_permit', 'event', ['food_modes' => ['sold', 'both']]);
        $event = $this->readyEvent();
        $key = substr(collect(app(EventPublicationGates::class)->blocking($event, $this->host))->pluck('code')->first(), strlen(EventPublicationGates::OVERRIDABLE_PREFIX));
        EventComplianceEvidence::create(['event_id' => $event->id, 'item_key' => $key, 'item_title' => 'Food permit', 'status' => 'verified',
            'expires_on' => now()->subDay(), 'reference_number' => 'FP-1', 'issuing_authority' => 'DBPR', 'submitted_by' => $this->host->id, 'submitted_at' => now()->subYear()]);
        $this->assertStringContainsString('has expired. Submit the renewed permit or licence.', implode(' ', $this->blocking($event)));
        $this->assertFalse($this->publish($event));

        // An expired government ID blocks the host's events too.
        EventComplianceEvidence::where('event_id', $event->id)->update(['expires_on' => now()->addYear()]);
        $this->assertTrue($this->publish($event->fresh()));
        $event->forceFill(['status' => 'draft'])->save();
        HostIdentityVerification::where('user_id', $this->host->id)->update(['document_expires_at' => now()->subDay()]);
        $this->assertFalse($this->publish($event->fresh()));
        $this->assertStringContainsString('has not passed government ID verification', implode(' ', $this->blocking($event)));
    }

    // 11 ----------------------------------------------------------------------------------------------------

    public function test_11_a_material_policy_change_requires_re_acknowledgement_before_restricted_actions(): void
    {
        $event = $this->readyEvent();
        $this->assertTrue($this->publish($event));
        $event->forceFill(['status' => 'draft'])->save();

        CompliancePolicyVersion::create(['policy_name' => 'host_resp_permits_and_inspections', 'policy_group' => 'host', 'version' => '2026.12-v2',
            'title' => 'Food-Service Permits, Licences & Health Inspections', 'body' => 'I will hold every required permit and show it on request.',
            'change_summary' => 'Permits must be shown on request.', 'category' => 'host_responsibility', 'applicable_roles' => ['event_host'],
            'applicable_jurisdictions' => [], 'acknowledgement_mandatory' => true, 'requires_reacknowledgement' => true,
            'status' => 'published', 'source' => 'admin', 'published_at' => now(), 'effective_at' => now()]);
        app()->forgetScopedInstances();

        // Restricted until acknowledged: the next page asks for it, publishing is refused.
        $this->actingAs($this->host)->get(route('host.dashboard'))->assertRedirect(route('policies.acknowledge'));
        $this->assertFalse($this->publish($event->fresh()));
        $this->actingAs($this->host)->get(route('policies.acknowledge'))->assertSee('Permits must be shown on request.');

        $this->actingAs($this->host)->post(route('policies.acknowledge.store'), ['policies' => ['host_resp_permits_and_inspections']])->assertRedirect();
        app()->forgetScopedInstances();
        $this->assertTrue($this->publish($event->fresh()));
        // The earlier acknowledgement is kept beside the new one.
        $this->assertSame(['2026.11-v1', '2026.12-v2'], PolicyAcceptance::where('user_id', $this->host->id)
            ->where('policy_name', 'host_resp_permits_and_inspections')->orderBy('id')->pluck('policy_version')->all());
    }

    // 17–19 -------------------------------------------------------------------------------------------------

    public function test_17_the_events_jurisdiction_selects_the_applicable_requirements(): void
    {
        $miamiDade = ComplianceJurisdiction::create(['parent_id' => $this->florida->id, 'type' => 'county', 'name' => 'Miami-Dade County', 'code' => '12086', 'state_code' => 'FL']);
        $texas = ComplianceJurisdiction::where('type', 'state')->where('code', 'TX')->firstOrFail();
        $this->rule('temporary_food_event', 'event', [], $miamiDade);
        $this->rule('food_permit', 'event', [], $texas);

        $facts = EventRequirementChecklist::facts(['state_code' => 'FL', 'compliance_jurisdiction_county' => 'Miami-Dade County', 'city' => 'Miami', 'food_service_mode' => 'sold']);
        $checklist = app(EventRequirementChecklist::class)->build($facts);
        $event = collect($checklist['sections'])->firstWhere('location', 'event');

        $this->assertSame(['United States', 'Florida', 'Miami-Dade County'], array_map(fn ($n) => $n->name, $event['jurisdiction']['chain']));
        $required = collect($event['items'])->where('base', 'required');
        $this->assertSame(['Temporary food event requirements'], $required->pluck('title')->unique()->values()->all());
        $this->assertSame('Miami-Dade County', $required->first()['level']['name']);
        $this->assertNotContains('Texas', collect($event['items'])->pluck('level.name')->all());
    }

    public function test_18_missing_or_unverified_jurisdiction_data_is_identified(): void
    {
        $resolved = app(JurisdictionResolver::class)->resolve('FL', 'Nowhere County', 'Atlantis');
        $this->assertSame(['Nowhere County', 'Atlantis'], array_column($resolved['missing'], 'name'));
        $this->assertStringContainsString('Nowhere County (not in the database yet)', $resolved['label']);

        $checklist = app(EventRequirementChecklist::class)->build(EventRequirementChecklist::facts(
            ['state_code' => 'FL', 'compliance_jurisdiction_county' => 'Nowhere County', 'city' => 'Atlantis', 'food_service_mode' => 'sold']));
        $items = collect($checklist['sections'][0]['items']);
        $this->assertTrue($items->where('level.name', 'Nowhere County')->isNotEmpty());
        $this->assertSame(['verification_required'], $items->pluck('status')->unique()->values()->all(), 'nothing is presented as known');

        $this->get(route('legal.jurisdiction', 'FL'))->assertOk()->assertSee('Verification Required')->assertSee('EventBite has not verified this.');
        $this->actingAs($this->host)->post(route('events.requirements.preview'), ['state_code' => 'FL', 'compliance_jurisdiction_county' => 'Nowhere County',
            'city' => 'Atlantis', 'food_service_mode' => 'sold'])->assertOk()->assertSee('not in the database yet');
    }

    public function test_19_editing_compliance_details_re_evaluates_the_event(): void
    {
        $this->rule('food_permit', 'event', ['food_modes' => ['sold', 'both']]);
        $event = $this->readyEvent();
        $firstAck = app(EventComplianceService::class)->activeAcknowledgement($event);

        // A material change without new declarations is refused and nothing is saved.
        $change = array_diff_key($this->eventForm(['title' => $event->title, 'compliance_jurisdiction_county' => 'Broward County', 'compliance_jurisdiction_municipality' => 'Fort Lauderdale'])
            + $this->eventArrangementFields(true), array_flip(EventComplianceService::fieldNames()));
        $this->actingAs($this->host)->put(route('events.update', $event->id), $change)->assertSessionHasErrors();
        $this->assertSame('Miami-Dade County', $event->fresh()->compliance_jurisdiction_county);

        // Confirming the declarations again saves it: a new acknowledgement, the old one superseded.
        $this->actingAs($this->host)->put(route('events.update', $event->id), $change + $this->eventComplianceFields(['compliance_jurisdiction_county' => 'Broward County',
            'compliance_jurisdiction_municipality' => 'Fort Lauderdale']))->assertSessionHasNoErrors();
        $this->assertSame('Broward County', $event->fresh()->compliance_jurisdiction_county);
        $this->assertNotNull($firstAck->fresh()->superseded_at_utc);
        $this->assertSame('current', app(EventComplianceService::class)->status($event->fresh())['state']);

        // Dropping food removes the food requirements from the checklist and the publication gates.
        $this->assertNotEmpty(collect($this->blocking($event))->filter(fn ($m) => str_contains($m, 'Food permit')));
        $noFood = $this->eventForm(['title' => $event->title, 'compliance_jurisdiction_county' => 'Broward County', 'compliance_jurisdiction_municipality' => 'Fort Lauderdale',
            'food_service_mode' => 'none', 'alcohol_service_mode' => 'none', 'supports_food_ordering' => '0']);
        $this->actingAs($this->host)->put(route('events.update', $event->id), $noFood)->assertSessionHasNoErrors();
        $this->assertSame([], collect($this->blocking($event))->filter(fn ($m) => str_contains($m, 'Food permit'))->all());
    }
}
