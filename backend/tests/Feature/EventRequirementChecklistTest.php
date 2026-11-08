<?php

namespace Tests\Feature;

use App\Models\Compliance\ComplianceEventRule;
use App\Models\Compliance\ComplianceJurisdiction;
use App\Models\Event;
use App\Models\EventComplianceEvidence;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Compliance\EventRequirementChecklist;
use App\Services\Compliance\JurisdictionResolver;
use Database\Seeders\ComplianceDatabaseSeeder;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Jurisdiction-specific event requirements: the jurisdiction found from the event's places, a checklist that
 * follows how food and alcohol are provided, administrator rules and exceptions, and evidence that only an
 * administrator can verify.
 */
class EventRequirementChecklistTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = 'evt-kingston-harbour-fry';

    private User $host;
    private ComplianceJurisdiction $florida;
    private ComplianceJurisdiction $miamiDade;
    private ComplianceJurisdiction $miami;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class, ComplianceDatabaseSeeder::class]);
        $this->host = User::where('email', 'host@islandevents.example')->firstOrFail();
        $this->florida = ComplianceJurisdiction::where('type', 'state')->where('code', 'FL')->firstOrFail();
        $this->miamiDade = ComplianceJurisdiction::create(['parent_id' => $this->florida->id, 'type' => 'county', 'name' => 'Miami-Dade County', 'code' => '12086', 'state_code' => 'FL', 'data_source' => 'census_2020']);
        $this->miami = ComplianceJurisdiction::create(['parent_id' => $this->miamiDade->id, 'type' => 'municipality', 'name' => 'Miami city', 'code' => '1245000', 'state_code' => 'FL', 'data_source' => 'census_2020']);
    }

    private function event(array $arrangements = []): Event
    {
        $event = Event::withoutGlobalScopes()->findOrFail(self::EVENT);
        $event->forceFill(array_merge([
            'state_code' => 'FL', 'city' => 'Miami', 'compliance_jurisdiction_county' => 'Miami-Dade County',
            'compliance_jurisdiction_municipality' => 'City of Miami', 'category' => 'Food',
            'food_service_mode' => 'sold', 'alcohol_service_mode' => 'none', 'uses_licensed_caterer' => false,
        ], $arrangements))->save();

        return $event->fresh();
    }

    private function checklist(Event $event): array
    {
        return app(EventRequirementChecklist::class)->build(EventRequirementChecklist::factsFromEvent($event), $event);
    }

    private function item(array $checklist, string $location, string $title, string $level): array
    {
        $section = collect($checklist['sections'])->firstWhere('location', $location);
        $this->assertNotNull($section, "No {$location} section");

        return collect($section['items'])->first(fn ($i) => $i['title'] === $title && $i['level']['name'] === $level)
            ?? $this->fail("No '{$title}' at {$level} in {$location}");
    }

    private function rule(ComplianceJurisdiction $where, array $values): ComplianceEventRule
    {
        return ComplianceEventRule::create(array_merge(['jurisdiction_id' => $where->id, 'location' => 'event', 'effect' => 'required',
            'source_url' => 'https://www.example.gov/rules', 'is_active' => true], $values));
    }

    private function admin(string $email): User
    {
        $user = User::create(['name' => 'Admin ' . $email, 'email' => $email, 'password' => bcrypt('password'), 'status' => 'active', 'is_system_admin' => true]);
        UserRole::create(['user_id' => $user->id, 'role' => 'system_admin', 'is_active' => true, 'assigned_at' => now()]);

        return $user;
    }

    public function test_the_jurisdiction_is_identified_from_the_location_down_to_the_city(): void
    {
        $resolved = app(JurisdictionResolver::class)->resolve('FL', 'Miami-Dade', 'City of Miami');
        $this->assertSame(['United States', 'Florida', 'Miami-Dade County', 'Miami city'], collect($resolved['chain'])->pluck('name')->all());
        $this->assertSame([], $resolved['missing']);

        // A county that is not in the database is reported, never guessed.
        $elsewhere = app(JurisdictionResolver::class)->resolve('FL', 'Broward County', 'Fort Lauderdale');
        $this->assertSame(['United States', 'Florida'], collect($elsewhere['chain'])->pluck('name')->all());
        $this->assertSame(['county', 'municipality'], array_column($elsewhere['missing'], 'type'));
        $this->assertStringContainsString('Broward County (not in the database yet)', $elsewhere['label']);
    }

    public function test_without_rules_every_item_is_verification_required_and_follows_what_the_event_provides(): void
    {
        $foodOnly = $this->checklist($this->event());
        $this->assertSame(['event', 'food_preparation'], array_column($foodOnly['sections'], 'location'));
        $statuses = collect($foodOnly['sections'])->pluck('items')->flatten(1)->pluck('status')->unique()->values()->all();
        $this->assertSame(['verification_required'], $statuses);
        $this->item($foodOnly, 'event', 'Temporary food event requirements', 'Miami city');
        $this->item($foodOnly, 'food_preparation', 'Food establishment requirements', 'Miami-Dade County');
        $this->assertSame(0, $foodOnly['verified']);

        // BYOB still brings the alcohol requirements; no food removes the food ones.
        $byob = $this->checklist($this->event(['food_service_mode' => 'none', 'alcohol_service_mode' => 'byob']));
        $this->assertSame(['event', 'alcohol_service'], array_column($byob['sections'], 'location'));
        $this->item($byob, 'alcohol_service', 'Minimum legal drinking age', 'Florida');
    }

    public function test_food_prepared_elsewhere_uses_that_places_jurisdiction(): void
    {
        $checklist = $this->checklist($this->event(['food_prepared_elsewhere' => true, 'food_prep_state_code' => 'FL', 'food_prep_county' => 'Broward County', 'food_prep_city' => 'Hollywood']));
        $prep = collect($checklist['sections'])->firstWhere('location', 'food_preparation');
        $this->assertFalse($prep['same_as_event']);
        $this->assertStringContainsString('Broward County (not in the database yet)', $prep['jurisdiction']['label']);
        $this->item($checklist, 'food_preparation', 'Food handler training requirements', 'Broward County');
    }

    public function test_administrator_rules_exceptions_and_local_requirements_decide_required_and_not_required(): void
    {
        // Florida requires a temporary food event permit when food is sold, at the state and every place inside it.
        $this->rule($this->florida, ['topic' => 'temporary_food_event', 'conditions' => ['food_modes' => ['sold', 'both']], 'include_descendants' => true]);
        // Miami-Dade: not required when a licensed caterer is involved (an exception wins).
        $this->rule($this->miamiDade, ['topic' => 'temporary_food_event', 'effect' => 'not_required', 'conditions' => ['caterer' => true]]);
        // The City of Miami adds its own requirement for food events only.
        $this->rule($this->miami, ['topic' => 'local', 'title' => 'Fire marshal tent inspection', 'conditions' => ['event_types' => ['Food']]]);
        // An inactive rule never applies.
        $this->rule($this->florida, ['topic' => 'special_event_permit', 'is_active' => false]);

        $plain = $this->checklist($this->event());
        $this->assertSame('required', $this->item($plain, 'event', 'Temporary food event requirements', 'Florida')['status']);
        $this->assertSame('required', $this->item($plain, 'event', 'Temporary food event requirements', 'Miami-Dade County')['status']);
        $this->assertSame('required', $this->item($plain, 'event', 'Fire marshal tent inspection', 'Miami city')['status']);
        $this->assertSame('verification_required', $this->item($plain, 'event', 'Special event permit requirements', 'Florida')['status']);

        $catered = $this->checklist($this->event(['uses_licensed_caterer' => true]));
        $this->assertSame('not_required', $this->item($catered, 'event', 'Temporary food event requirements', 'Miami-Dade County')['status']);
        $this->assertSame('required', $this->item($catered, 'event', 'Temporary food event requirements', 'Florida')['status']);

        // Complimentary food is not "sold": the Florida rule does not apply.
        $free = $this->checklist($this->event(['food_service_mode' => 'complimentary']));
        $this->assertSame('verification_required', $this->item($free, 'event', 'Temporary food event requirements', 'Florida')['status']);
        // A different event type does not get the city's food-event requirement.
        $music = $this->checklist($this->event(['category' => 'Music']));
        $this->assertNull(collect(collect($music['sections'])->firstWhere('location', 'event')['items'])->firstWhere('title', 'Fire marshal tent inspection'));
    }

    public function test_a_host_submits_evidence_and_only_another_administrator_verifies_it(): void
    {
        Storage::fake('local');
        $this->rule($this->florida, ['topic' => 'food_permit', 'conditions' => ['food_modes' => ['sold']]]);
        $event = $this->event();
        $item = $this->item($this->checklist($event), 'event', 'Food permit requirements', 'Florida');

        $this->actingAs($this->host)->get(route('events.requirements', $event->id))->assertOk()
            ->assertSee('Food permit requirements')->assertSee('Submit permit or licence details');

        // An item that is not on the checklist is refused.
        $this->actingAs($this->host)->post(route('events.requirements.evidence', $event->id), [
            'item_key' => 'event:made_up:state:fl/fl', 'reference_number' => 'X', 'issuing_authority' => 'Y',
        ])->assertSessionHasErrors('item_key');

        $this->actingAs($this->host)->post(route('events.requirements.evidence', $event->id), [
            'item_key' => $item['key'], 'reference_number' => 'TFE-2027-0042', 'issuing_authority' => 'Florida DBPR',
            'expires_on' => now()->addMonths(6)->toDateString(),
            'document' => UploadedFile::fake()->create('permit.pdf', 120, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $evidence = EventComplianceEvidence::firstOrFail();
        Storage::disk('local')->assertExists($evidence->document_path);
        $this->assertSame('submitted', $this->item($this->checklist($event), 'event', 'Food permit requirements', 'Florida')['status']);

        // The document is private: the host can open it, another host cannot.
        $this->actingAs($this->host)->get(route('events.requirements.document', $evidence))->assertOk();
        $otherHost = User::create(['name' => 'Other Host', 'email' => 'other-host@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        UserRole::create(['user_id' => $otherHost->id, 'role' => 'event_host', 'is_active' => true, 'assigned_at' => now()]);
        $this->actingAs($otherHost)->get(route('events.requirements.document', $evidence))->assertNotFound();

        $reviewer = $this->admin('reviewer@example.test');
        $this->actingAs($reviewer)->post(route('admin.console.compliance.evidence.review', [$evidence, 'start']))->assertRedirect();
        $this->assertSame('pending_verification', $this->item($this->checklist($event), 'event', 'Food permit requirements', 'Florida')['status']);
        $this->actingAs($reviewer)->post(route('admin.console.compliance.evidence.review', [$evidence, 'verify']), ['comment' => 'Checked with DBPR.'])->assertRedirect();
        $checklist = $this->checklist($event);
        $this->assertSame('verified', $this->item($checklist, 'event', 'Food permit requirements', 'Florida')['status']);
        $this->assertSame(1, $checklist['verified']);

        // A verified permit past its expiry date is expired.
        $evidence->forceFill(['expires_on' => now()->subDay()])->save();
        $this->assertSame('expired', $this->item($this->checklist($event), 'event', 'Food permit requirements', 'Florida')['status']);
    }

    public function test_rejection_needs_a_reason_and_nobody_reviews_their_own_submission(): void
    {
        $admin = $this->admin('both@example.test');
        $evidence = EventComplianceEvidence::create(['event_id' => self::EVENT, 'item_key' => 'event:food_permit:state:fl/fl', 'item_title' => 'Food permit',
            'status' => 'submitted', 'reference_number' => 'A1', 'issuing_authority' => 'B', 'submitted_by' => $admin->id, 'submitted_at' => now()]);

        $this->actingAs($admin)->post(route('admin.console.compliance.evidence.review', [$evidence, 'verify']))->assertSessionHasErrors('evidence');
        $this->assertSame('submitted', $evidence->fresh()->status);

        $other = $this->admin('other@example.test');
        $this->actingAs($other)->post(route('admin.console.compliance.evidence.review', [$evidence, 'reject']))->assertSessionHasErrors('comment');
        $this->actingAs($other)->post(route('admin.console.compliance.evidence.review', [$evidence, 'reject']), ['comment' => 'The number does not match the county register.'])->assertRedirect();
        $this->assertSame(['rejected', 'The number does not match the county register.'], [$evidence->fresh()->status, $evidence->fresh()->review_comment]);
        $this->actingAs($other)->get(route('admin.console.compliance.evidence.index', ['status' => 'rejected']))->assertOk()->assertSee('A1');
    }

    public function test_the_live_preview_and_the_event_pages_show_the_checklist(): void
    {
        $this->actingAs($this->host)->post(route('events.requirements.preview'), [
            'state_code' => 'FL', 'city' => 'Miami', 'compliance_jurisdiction_county' => 'Miami-Dade', 'compliance_jurisdiction_municipality' => 'City of Miami',
            'food_service_mode' => 'sold', 'alcohol_service_mode' => 'included', 'category' => 'Food',
        ])->assertOk()
            ->assertSee('United States → Florida → Miami-Dade County → Miami city')
            ->assertSee('Alcohol service location')->assertSee('Verification Required');

        $this->event();
        $this->actingAs($this->host)->get(route('events.edit', self::EVENT))->assertOk()
            ->assertSee('Requirements for this event')->assertSee('How food and alcohol are provided')->assertSee('Submit permits and licences for verification');
        $this->actingAs($this->host)->get(route('events.create'))->assertOk()->assertSee('Requirements for this event');
    }

    public function test_administrators_add_rules_only_with_an_official_source(): void
    {
        $admin = $this->admin('rules@example.test');
        $url = route('admin.console.compliance.rules.store', $this->miamiDade);
        $this->actingAs($admin)->post($url, ['topic' => 'special_event_permit', 'effect' => 'required', 'location' => 'event', 'source_url' => 'https://someblog.example.com/x'])
            ->assertSessionHasErrors('source_url');
        $this->actingAs($admin)->post($url, ['topic' => 'local', 'title' => 'Noise permit', 'effect' => 'not_required', 'location' => 'event', 'source_url' => 'https://www.miamidade.gov/x'])
            ->assertSessionHasErrors('effect');

        $this->actingAs($admin)->post($url, ['topic' => 'special_event_permit', 'effect' => 'not_required', 'location' => 'event',
            'alcohol_modes' => ['none'], 'caterer' => 'any', 'source_url' => 'https://www.miamidade.gov/permits'])->assertRedirect()->assertSessionHasNoErrors();
        $rule = ComplianceEventRule::firstOrFail();
        $this->assertSame(['alcohol_modes' => ['none']], $rule->conditions);

        $this->actingAs($admin)->post(route('admin.console.compliance.rules.toggle', $rule))->assertRedirect();
        $this->assertFalse($rule->fresh()->is_active);
        $this->actingAs($admin)->get(route('admin.console.compliance.jurisdictions.show', $this->miamiDade))->assertOk()
            ->assertSee('Event checklist rules')->assertSee('Special event permit requirements · Not required (exception)');
        $this->actingAs($this->host)->post($url, ['topic' => 'food_permit'])->assertForbidden();
    }

    public function test_changing_how_alcohol_is_provided_needs_the_declarations_again(): void
    {
        $event = $this->event();
        app(\App\Services\Compliance\EventComplianceService::class)->record($this->host, $event, $this->eventComplianceFields(), 'event_editing');

        try {
            app(\App\Services\EventManagementService::class)->updateEvent($this->host, $event->id, ['alcohol_service_mode' => 'complimentary']);
            $this->fail('Alcohol arrangements changed without a new acknowledgement.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('alcohol-service arrangements', $e->getMessage());
        }
        // Who provides the food is a food-service change too.
        $this->expectExceptionMessage('food-service arrangements');
        app(\App\Services\EventManagementService::class)->updateEvent($this->host, $event->id, ['uses_licensed_caterer' => true]);
    }
}
