<?php

namespace Tests\Feature;

use App\Models\AdminPermission;
use App\Models\Compliance\ComplianceAuthority;
use App\Models\Compliance\ComplianceJurisdiction;
use App\Models\Compliance\ComplianceRecordReview;
use App\Models\Compliance\ComplianceRequirement;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Compliance\ComplianceReviewWorkflow;
use Database\Seeders\ComplianceDatabaseSeeder;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The US state and local compliance database: the hierarchy, the starting data from official federal sources
 * (never marked verified), the Census import, and the administrator review workflow.
 */
class ComplianceDatabaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class, ComplianceDatabaseSeeder::class]);
    }

    private function systemAdmin(string $email): User
    {
        $user = User::create(['name' => 'Compliance ' . $email, 'email' => $email, 'password' => bcrypt('password'), 'status' => 'active', 'is_system_admin' => true]);
        UserRole::create(['user_id' => $user->id, 'role' => 'system_admin', 'is_active' => true, 'assigned_at' => now()]);

        return $user;
    }

    private function administrator(): User
    {
        $user = User::create(['name' => 'Researcher', 'email' => 'researcher@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        UserRole::create(['user_id' => $user->id, 'role' => 'admin', 'is_active' => true, 'assigned_at' => now()]);
        AdminPermission::create(['user_id' => $user->id, 'scope' => 'assigned_events', 'assigned_organizer_ids' => [],
            'can_manage_events' => false, 'can_moderate_content' => false, 'can_support_orders' => false,
            'can_view_payments' => false, 'can_view_reports' => false, 'can_access_audit_logs' => false, 'can_manage_compliance' => true]);

        return $user->fresh();
    }

    private function state(string $code): ComplianceJurisdiction
    {
        return ComplianceJurisdiction::where('type', 'state')->where('code', $code)->firstOrFail();
    }

    private function ready(ComplianceRequirement $record): ComplianceRequirement
    {
        $record->forceFill(['status' => 'draft', 'summary' => 'Summary taken from the official source.',
            'source_url' => 'https://www.example.gov/rules', 'last_verified_date' => now()->subDay()])->save();

        return $record;
    }

    public function test_the_hierarchy_covers_the_country_every_state_and_dc_with_nothing_presented_as_verified(): void
    {
        $us = ComplianceJurisdiction::where('type', 'country')->where('code', 'US')->firstOrFail();
        $states = ComplianceJurisdiction::where('type', 'state')->get();

        $this->assertCount(51, $states);
        $this->assertTrue($states->every(fn ($s) => $s->parent_id === $us->id));
        $this->assertContains('DC', $states->pluck('code')->all());
        $this->assertNotContains('PR', $states->pluck('code')->all());

        // Every topic exists for every state, and none is filled in or verified.
        $stateRequirements = ComplianceRequirement::whereIn('jurisdiction_id', $states->pluck('id'))->get();
        $this->assertCount(51 * count(ComplianceRequirement::TOPICS), $stateRequirements);
        $this->assertTrue($stateRequirements->every(fn ($r) => $r->status === 'verification_required' && $r->summary === null));
        $this->assertSame(0, ComplianceRequirement::where('status', 'verified')->count() + ComplianceAuthority::where('status', 'verified')->count());
    }

    public function test_federal_records_and_state_agencies_come_from_official_federal_sources(): void
    {
        $us = ComplianceJurisdiction::where('type', 'country')->firstOrFail();
        $this->assertEqualsCanonicalizing(['food', 'agriculture', 'alcohol'], $us->authorities()->pluck('area')->all());
        $age = $us->requirements()->where('topic', 'minimum_drinking_age')->firstOrFail();
        $this->assertSame(['pending_review', '23 U.S.C. 158 (National minimum drinking age)'], [$age->status, $age->legislation_citation]);
        $this->assertStringStartsWith('https://www.govinfo.gov/', $age->source_url);

        // Every state and D.C. has its TTB-listed alcohol authority and its FDA-listed retail food agency, as drafts.
        foreach (ComplianceJurisdiction::where('type', 'state')->get() as $state) {
            $authorities = $state->authorities;
            $this->assertTrue($authorities->contains(fn ($a) => $a->area === 'alcohol' && str_contains($a->source_url, 'ttb.gov')), "{$state->code} alcohol");
            $this->assertTrue($authorities->contains(fn ($a) => in_array($a->area, ['food', 'agriculture'], true) && str_contains($a->source_url, 'fda.gov')), "{$state->code} food");
            $this->assertTrue($authorities->every(fn ($a) => $a->status === 'draft'));
        }
        $texas = $this->state('TX')->authorities->pluck('name');
        $this->assertContains('Texas Alcoholic Beverage Commission', $texas);
        $this->assertContains('Texas Department of State Health Services', $texas);
        // Maryland's FDA entry records non-adoption rather than assuming a Food Code edition.
        $this->assertStringContainsString('has not adopted any version', $this->state('MD')->authorities->firstWhere('name', 'Maryland Department of Health')->role);
    }

    public function test_running_the_seed_again_adds_nothing_and_keeps_administrator_work(): void
    {
        $record = $this->ready($this->state('FL')->requirements()->where('topic', 'temporary_food_event')->firstOrFail());
        $counts = [ComplianceJurisdiction::count(), ComplianceAuthority::count(), ComplianceRequirement::count()];

        $this->seed(ComplianceDatabaseSeeder::class);

        $this->assertSame($counts, [ComplianceJurisdiction::count(), ComplianceAuthority::count(), ComplianceRequirement::count()]);
        $this->assertSame(['draft', 'Summary taken from the official source.'], [$record->fresh()->status, $record->fresh()->summary]);
    }

    public function test_the_census_import_builds_counties_and_municipalities_under_their_state(): void
    {
        $dir = sys_get_temp_dir();
        file_put_contents("{$dir}/counties.txt", "STATE|STATEFP|COUNTYFP|COUNTYNS|COUNTYNAME|CLASSFP|FUNCSTAT\nFL|12|086|00295755|Miami-Dade County|H1|A\nFL|12|011|00295753|Broward County|H1|A\nPR|72|001|01804480|Adjuntas Municipio|H1|A\n");
        file_put_contents("{$dir}/places.txt", "STATE|STATEFP|PLACEFP|PLACENS|PLACENAME|TYPE|CLASSFP|FUNCSTAT|COUNTIES\n"
            . "FL|12|45000|02404247|Miami city|INCORPORATED PLACE|C1|A|Miami-Dade County\n"
            . "FL|12|02681|02402713|Aventura city|INCORPORATED PLACE|C1|A|Miami-Dade County~~~Broward County\n"
            . "FL|12|17935|02402968|Country Club CDP|CENSUS DESIGNATED PLACE|U1|S|Miami-Dade County\n");

        $this->artisan('compliance:import-census', ['--counties' => "{$dir}/counties.txt", '--places' => "{$dir}/places.txt"])->assertSuccessful();

        $florida = $this->state('FL');
        $miamiDade = ComplianceJurisdiction::where('type', 'county')->where('code', '12086')->firstOrFail();
        $this->assertSame([$florida->id, 'Miami-Dade County', 'FL'], [$miamiDade->parent_id, $miamiDade->name, $miamiDade->state_code]);
        $miami = ComplianceJurisdiction::where('type', 'municipality')->where('code', '1245000')->firstOrFail();
        $this->assertSame($miamiDade->id, $miami->parent_id);
        $this->assertSame(['United States', 'Florida', 'Miami-Dade County', 'Miami city'], collect($miami->lineage())->pluck('name')->all());
        $this->assertSame($miamiDade->id, ComplianceJurisdiction::where('code', '1202681')->value('parent_id'));
        $this->assertNull(ComplianceJurisdiction::where('name', 'Country Club CDP')->first());
        $this->assertNull(ComplianceJurisdiction::where('name', 'Adjuntas Municipio')->first());

        // A second run adds nothing, and no requirement is assumed for the new places.
        $this->artisan('compliance:import-census', ['--counties' => "{$dir}/counties.txt", '--places' => "{$dir}/places.txt"])->assertSuccessful();
        $this->assertSame(2, ComplianceJurisdiction::where('type', 'county')->count());
        $this->assertSame(0, $miami->requirements()->count());
    }

    public function test_a_record_is_verified_only_after_an_official_source_and_a_second_administrators_approval(): void
    {
        $researcher = $this->systemAdmin('first@example.test');
        $reviewer = $this->systemAdmin('second@example.test');
        $workflow = app(ComplianceReviewWorkflow::class);
        $record = $this->state('FL')->requirements()->where('topic', 'special_event_permit')->firstOrFail();

        // Not without a summary, source and checked date.
        try {
            $workflow->submit($record, $researcher);
            $this->fail('Submitted an empty record.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('the requirement summary', $e->getMessage());
        }

        // Not from a source that is not an official government site, unless confirmed as the agency's own.
        $workflow->edit($record, ['summary' => 'From the official source.', 'source_url' => 'http://some-blog.example.com/fl-permits', 'last_verified_date' => now()->subDay()->toDateString()], $researcher);
        $this->assertSame('draft', $record->fresh()->status);
        try {
            $workflow->submit($record->fresh(), $researcher);
            $this->fail('Submitted with an unofficial source.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('is not on a .gov, .mil or .us site', $e->getMessage());
        }

        $workflow->edit($record->fresh(), ['source_url' => 'https://www.myfloridalicense.gov/permits'], $researcher);
        $workflow->submit($record->fresh(), $researcher);
        $this->assertSame('pending_review', $record->fresh()->status);

        try {
            $workflow->approve($record->fresh(), $researcher);
            $this->fail('Approved by the submitter.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('second administrator', $e->getMessage());
        }

        $workflow->approve($record->fresh(), $reviewer, 'Checked against the agency site.');
        $record->refresh();
        $this->assertSame(['verified', $reviewer->id], [$record->status, $record->approved_by]);
        $this->assertTrue($record->isVerified());
        $this->assertSame(now()->addMonths(12)->toDateString(), $record->next_review_date->toDateString());

        // Changing what an approved record says sends it back to draft.
        $workflow->edit($record, ['summary' => 'A changed summary.'], $researcher);
        $this->assertSame(['draft', null], [$record->fresh()->status, $record->fresh()->approved_by]);

        $this->assertSame(['created', 'edited', 'edited', 'submitted', 'approved', 'edited'],
            ComplianceRecordReview::where('record_type', 'requirement')->where('record_id', $record->id)->orderBy('id')->pluck('action')->all());
    }

    public function test_records_past_their_review_date_become_review_due_and_changes_need_a_comment(): void
    {
        $workflow = app(ComplianceReviewWorkflow::class);
        $reviewer = $this->systemAdmin('reviewer@example.test');
        $verified = $this->state('GA')->requirements()->where('topic', 'alcohol_licensing')->firstOrFail();
        $verified->forceFill(['status' => 'verified', 'next_review_date' => now()->subDay()])->save();
        $this->assertFalse($verified->fresh()->isVerified());

        $this->artisan('compliance:flag-due-reviews')->assertSuccessful();
        $this->assertSame('review_due', $verified->fresh()->status);

        $pending = $this->ready($this->state('GA')->requirements()->where('topic', 'food_permit')->firstOrFail());
        $pending->forceFill(['status' => 'pending_review'])->save();
        $this->expectException(\InvalidArgumentException::class);
        $workflow->requestChanges($pending, $reviewer, ' ');
    }

    public function test_only_administrators_see_the_database_and_only_system_administrators_approve(): void
    {
        $this->actingAs(User::where('email', 'host@islandevents.example')->firstOrFail())
            ->get(route('admin.console.compliance.index'))->assertForbidden();

        $researcher = $this->administrator();
        $florida = $this->state('FL');
        // Compliance management is a specific grant: without it a delegated administrator is refused.
        AdminPermission::where('user_id', $researcher->id)->update(['can_manage_compliance' => false]);
        $this->actingAs($researcher->fresh())->get(route('admin.console.compliance.index'))->assertForbidden();
        AdminPermission::where('user_id', $researcher->id)->update(['can_manage_compliance' => true]);
        $researcher = $researcher->fresh();
        $this->actingAs($researcher)->get(route('admin.console.compliance.index'))->assertOk()
            ->assertSee('Compliance Database')->assertSee('Florida')->assertSee('Verification Required');
        $this->actingAs($researcher)->get(route('admin.console.compliance.jurisdictions.show', $florida))->assertOk()
            ->assertSee('Division of Alcoholic Beverages &amp; Tobacco', false)
            ->assertSee('Florida Department of Business and Professional Regulations')
            ->assertSee('Special event permit requirements');

        $record = $this->ready($florida->requirements()->where('topic', 'temporary_food_event')->firstOrFail());
        $this->actingAs($researcher)->post(route('admin.console.compliance.records.transition', ['requirement', $record->id, 'submit']))->assertRedirect();
        $this->assertSame('pending_review', $record->fresh()->status);
        $this->actingAs($researcher)->post(route('admin.console.compliance.records.transition', ['requirement', $record->id, 'approve']))->assertForbidden();

        $this->actingAs($this->systemAdmin('approver@example.test'))
            ->post(route('admin.console.compliance.records.transition', ['requirement', $record->id, 'approve']))->assertRedirect();
        $this->assertSame('verified', $record->fresh()->status);
    }

    public function test_an_administrator_adds_an_authority_and_opens_research_on_a_county(): void
    {
        $admin = $this->administrator();
        $county = ComplianceJurisdiction::create(['parent_id' => $this->state('FL')->id, 'type' => 'county', 'name' => 'Miami-Dade County', 'code' => '12086', 'state_code' => 'FL']);

        $this->actingAs($admin)->post(route('admin.console.compliance.jurisdictions.research', $county))->assertRedirect();
        $this->assertSame(count(ComplianceRequirement::TOPICS), $county->requirements()->where('status', 'verification_required')->count());

        $this->actingAs($admin)->post(route('admin.console.compliance.authorities.store', $county), [
            'name' => 'Example County Health Department', 'area' => 'food', 'role' => 'Local food permits',
            'website_url' => 'https://example.gov/health',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $authority = $county->authorities()->firstOrFail();
        $this->assertSame(['draft', 'https://example.gov/health'], [$authority->status, $authority->website_url]);

        // A requirement may only cite an authority of this jurisdiction or one above it.
        $requirement = $county->requirements()->where('topic', 'food_permit')->firstOrFail();
        $elsewhere = $this->state('TX')->authorities()->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.console.compliance.records.update', ['requirement', $requirement->id]), ['authority_id' => $elsewhere->id])
            ->assertSessionHasErrors('authority_id');
    }
}
