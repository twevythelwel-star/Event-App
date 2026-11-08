<?php

namespace Tests\Feature;

use App\Models\Compliance\CompliancePolicyVersion;
use App\Models\Event;
use App\Models\PolicyAcceptance;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Compliance\EventComplianceService;
use App\Services\Compliance\EventPublicationGates;
use App\Services\Compliance\PolicyCatalog;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Version-controlled policies: every version kept unchanged, material changes acknowledged at the next interaction
 * by the people they apply to, restricted actions held until then, and append-only acknowledgement records.
 */
class PolicyVersioningTest extends TestCase
{
    use RefreshDatabase;

    private User $root;
    private User $host;
    private User $attendee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
        $this->host = User::where('email', 'host@islandevents.example')->firstOrFail();
        $this->attendee = User::where('email', 'attendee@islandevents.example')->firstOrFail();
        $this->root = User::create(['name' => 'Root', 'email' => 'root@example.test', 'password' => bcrypt('password'), 'status' => 'active', 'is_system_admin' => true]);
        UserRole::create(['user_id' => $this->root->id, 'role' => 'system_admin', 'is_active' => true, 'assigned_at' => now()]);
    }

    private function catalog(): PolicyCatalog
    {
        app()->forgetScopedInstances();

        return app(PolicyCatalog::class);
    }

    /** Draft and publish a version through the admin screens. */
    private function publish(array $fields): CompliancePolicyVersion
    {
        $this->actingAs($this->root)->post(route('admin.console.compliance-centre.policies.store'), $fields + [
            'category' => 'terms_of_service', 'applicable_roles' => ['all'], 'acknowledgement_mandatory' => '1', 'requires_reacknowledgement' => '1',
            'body' => 'Updated terms: tickets are personal and may not be resold above face value.', 'change_summary' => 'Resale above face value is no longer allowed.',
        ])->assertSessionHasNoErrors();
        $draft = CompliancePolicyVersion::where('status', 'draft')->latest('id')->firstOrFail();
        $this->actingAs($this->root)->post(route('admin.console.compliance-centre.policies.publish', $draft), ['confirm' => '1'])->assertRedirect();
        // The administrator is a user too: when the policy applies to them, they acknowledge it before going on.
        $this->catalog()->record($this->root, [$draft->policy_name], 'policy_update');

        return $draft->fresh();
    }

    public function test_every_policy_has_its_full_record_and_published_versions_are_never_overwritten(): void
    {
        $terms = CompliancePolicyVersion::where('policy_name', 'platform_terms_and_conditions')->firstOrFail();
        $this->assertSame('published', $terms->status);
        $this->assertSame('built_in', $terms->source);
        $this->assertSame('terms_of_service', $terms->category);
        $this->assertSame(['all'], $terms->roles());
        $this->assertSame([], $terms->jurisdictions());
        $this->assertTrue($terms->acknowledgement_mandatory);
        $this->assertNotNull($terms->effective_at);
        $this->assertStringContainsString("Terms of Service", $terms->body);
        // 3 account policies, 6 host acknowledgements, 3 groups of event declarations, and the 5 legal-page notices.
        $this->assertSame(12 + count(\App\Support\LegalPages::originals()), CompliancePolicyVersion::where('source', 'built_in')->count());

        try {
            $terms->update(['title' => 'Rewritten']);
            $this->fail('A published version was changed.');
        } catch (\LogicException) {
        }
        try {
            $terms->delete();
            $this->fail('A published version was deleted.');
        } catch (\LogicException) {
        }
        $this->assertSame('A. Platform Terms and Conditions', $terms->fresh()->title);

        // Publishing a new version keeps the old one, retired, with its text unchanged.
        $v2 = $this->publish(['policy_name' => 'platform_terms_and_conditions', 'version' => '2026.12-v2', 'title' => 'A. Platform Terms and Conditions']);
        $this->assertSame('retired', $terms->fresh()->status);
        $this->assertSame($terms->body, $terms->fresh()->body);
        $this->assertSame('published', $v2->status);
        $this->assertSame('2026.12-v2', $this->catalog()->current('platform_terms_and_conditions')->version);

        // The same version number cannot be published twice.
        $this->actingAs($this->root)->post(route('admin.console.compliance-centre.policies.store'), [
            'policy_name' => 'platform_terms_and_conditions', 'version' => '2026.12-v2', 'title' => 'Again', 'category' => 'terms_of_service',
            'applicable_roles' => ['all'], 'body' => str_repeat('Text. ', 10), 'change_summary' => 'Trying to overwrite.',
        ])->assertSessionHasErrors('version');
    }

    public function test_acknowledgement_records_are_append_only_and_linked_to_the_exact_version(): void
    {
        $record = PolicyAcceptance::where('user_id', $this->host->id)->where('policy_name', 'host_resp_independent_compliance')->firstOrFail();
        $this->assertSame(CompliancePolicyVersion::where('policy_name', 'host_resp_independent_compliance')->value('id'), $record->policy_version_id);

        try {
            $record->update(['policy_version' => 'tampered']);
            $this->fail('An acknowledgement record was changed.');
        } catch (\LogicException) {
        }
        try {
            $record->delete();
            $this->fail('An acknowledgement record was deleted.');
        } catch (\LogicException) {
        }
    }

    public function test_a_material_change_is_acknowledged_at_the_next_interaction_and_holds_restricted_actions(): void
    {
        // The attendee accepted the original terms.
        PolicyAcceptance::create(['user_id' => $this->attendee->id, 'policy_name' => 'platform_terms_and_conditions', 'policy_version' => '2026.11-v1',
            'acceptance_status' => 'accepted', 'accepted_at_utc' => now()->subMonth(), 'context' => 'account_registration']);
        $this->actingAs($this->attendee)->get(route('attendee.dashboard'))->assertOk();

        $v2 = $this->publish(['policy_name' => 'platform_terms_and_conditions', 'version' => '2026.12-v2', 'title' => 'A. Platform Terms and Conditions']);

        // Users affected are identified (everyone the policy applies to who has not accepted it).
        $affected = $this->catalog()->affectedUsers($v2)->pluck('id')->all();
        $this->assertContains($this->attendee->id, $affected);
        $this->assertContains($this->host->id, $affected);
        $csv = $this->actingAs($this->root)->get(route('admin.console.compliance-centre.policies.affected', $v2))->assertOk()->streamedContent();
        $this->assertStringContainsString('attendee@islandevents.example,platform_terms_and_conditions,2026.12-v2,"Accepted an earlier version"', $csv);

        // Next page view: the acknowledgement page, then back.
        $this->actingAs($this->attendee)->get(route('attendee.dashboard'))->assertRedirect(route('policies.acknowledge'));
        $this->actingAs($this->attendee)->get(route('policies.acknowledge'))->assertOk()
            ->assertSee('A. Platform Terms and Conditions')->assertSee('Resale above face value is no longer allowed.')->assertSee('tickets are personal');

        // Restricted actions wait: web forms are sent to the acknowledgement page; JSON clients get 428.
        $this->actingAs($this->attendee)->post(route('attendee.retrieve-ticket'), ['code' => 'X'])->assertRedirect(route('policies.acknowledge'));
        $this->actingAs($this->attendee)->postJson(route('api.disputes.store'), [])->assertStatus(428)
            ->assertJsonPath('outstanding_policies.0.policy', 'platform_terms_and_conditions');
        // Reading through the API is never blocked, and signing out always works.
        $this->actingAs($this->attendee)->getJson(route('api.disputes.mine'))->assertOk();

        $this->actingAs($this->attendee)->post(route('policies.acknowledge.store'), ['policies' => ['platform_terms_and_conditions']])->assertRedirect();
        $this->assertSame(2, PolicyAcceptance::where('user_id', $this->attendee->id)->where('policy_name', 'platform_terms_and_conditions')->count()); // the earlier record is kept
        $this->assertDatabaseHas('policy_acceptances', ['user_id' => $this->attendee->id, 'policy_version' => '2026.12-v2', 'context' => 'policy_update', 'policy_version_id' => $v2->id]);
        $this->actingAs($this->attendee)->get(route('attendee.dashboard'))->assertOk();
    }

    public function test_a_minor_change_or_a_future_effective_date_asks_nobody_yet(): void
    {
        PolicyAcceptance::create(['user_id' => $this->attendee->id, 'policy_name' => 'platform_terms_and_conditions', 'policy_version' => '2026.11-v1',
            'acceptance_status' => 'accepted', 'accepted_at_utc' => now()->subMonth(), 'context' => 'account_registration']);

        $this->publish(['policy_name' => 'platform_terms_and_conditions', 'version' => '2026.12-v1a', 'title' => 'A. Platform Terms and Conditions',
            'requires_reacknowledgement' => '0']);
        $this->assertSame('2026.12-v1a', $this->catalog()->current('platform_terms_and_conditions')->version);
        $this->assertSame('accepted', $this->catalog()->state($this->attendee, 'platform_terms_and_conditions'));
        $this->actingAs($this->attendee)->get(route('attendee.dashboard'))->assertOk();

        // A material change in force next month: published, listed as upcoming, not yet asked for.
        $this->publish(['policy_name' => 'platform_terms_and_conditions', 'version' => '2027.01-v2', 'title' => 'A. Platform Terms and Conditions',
            'effective_at' => now()->addMonth()->toDateString()]);
        $this->assertSame('2026.12-v1a', $this->catalog()->current('platform_terms_and_conditions')->version);
        $this->assertSame(['2027.01-v2'], $this->catalog()->upcoming()->pluck('version')->all());
        $this->actingAs($this->attendee)->get(route('attendee.dashboard'))->assertOk();

        $this->travel(32)->days();
        $this->assertSame('2027.01-v2', $this->catalog()->current('platform_terms_and_conditions')->version);
        $this->actingAs($this->attendee)->get(route('attendee.dashboard'))->assertRedirect(route('policies.acknowledge'));
    }

    public function test_roles_and_jurisdictions_decide_who_a_policy_applies_to(): void
    {
        $state = (string) Event::withoutGlobalScopes()->whereKey('evt-kingston-harbour-fry')->value('state_code');
        $elsewhere = $state === 'TX' ? 'CA' : 'TX';

        $here = $this->publish(['identifier' => 'local_noise_rules', 'version' => '2026.12-v1', 'title' => 'Local noise rules', 'category' => 'safety',
            'applicable_roles' => ['event_host'], 'applicable_jurisdictions' => [$state]]);
        $there = $this->publish(['identifier' => 'other_state_rules', 'version' => '2026.12-v1', 'title' => 'Other state rules', 'category' => 'safety',
            'applicable_roles' => ['event_host'], 'applicable_jurisdictions' => [$elsewhere]]);

        $outstanding = array_keys($this->catalog()->outstanding($this->host, updatesOnly: true));
        $this->assertContains('local_noise_rules', $outstanding);
        $this->assertNotContains('other_state_rules', $outstanding);
        // A host policy does not apply to an attendee.
        $this->assertSame([], $this->catalog()->outstanding($this->attendee, updatesOnly: true));
        $this->assertSame([$this->host->id], $this->catalog()->affectedUsers($here)->pluck('id')->all());
        $this->assertSame([], $this->catalog()->affectedUsers($there)->pluck('id')->all());

        // An unaccepted host acknowledgement blocks publishing.
        $this->assertContains('Local noise rules', EventPublicationGates::missingHostPolicies($this->host));
        $this->assertNotContains('Other state rules', EventPublicationGates::missingHostPolicies($this->host));
    }

    public function test_a_material_change_to_the_event_declarations_makes_each_events_acknowledgement_out_of_date(): void
    {
        $event = Event::withoutGlobalScopes()->findOrFail('evt-kingston-harbour-fry');
        app(EventComplianceService::class)->record($this->host, $event, $this->eventComplianceFields(), 'event_editing');
        $this->assertSame('current', app(EventComplianceService::class)->status($event->fresh())['state']);
        $this->assertSame(3, PolicyAcceptance::where('context_entity_id', $event->id)->where('context', 'event_editing')->count());

        $this->travel(1)->minutes();
        $this->publish(['policy_name' => 'event_alcohol_service_and_age_verification', 'version' => '2026.12-v2', 'category' => 'event_compliance',
            'title' => 'Event Compliance: Alcohol', 'applicable_roles' => ['event_host'], 'body' => 'I will check government ID for everyone served alcohol, with no exceptions.']);
        app()->forgetScopedInstances();
        $status = app(EventComplianceService::class)->status($event->fresh());
        $this->assertSame('outdated', $status['state']);
        $messages = array_column(app(EventPublicationGates::class)->check($event->fresh(), $this->host), 'message');
        $this->assertContains('EventBite updated the Event Compliance declarations since they were confirmed for this event. Review and confirm them again.', $messages);

        // Confirming again records a new acknowledgement at the new version; the earlier records stay.
        $this->travel(1)->minutes();
        app(EventComplianceService::class)->record($this->host, $event->fresh(), $this->eventComplianceFields(), 'event_material_change_reacknowledgement');
        $this->assertSame('current', app(EventComplianceService::class)->status($event->fresh())['state']);
        $this->assertDatabaseHas('policy_acceptances', ['policy_name' => 'event_alcohol_service_and_age_verification', 'policy_version' => '2026.12-v2',
            'context' => 'event_material_change_reacknowledgement']);
        $this->assertSame(6, PolicyAcceptance::where('context_entity_id', $event->id)->count());
    }
}
