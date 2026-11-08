<?php

namespace Tests\Feature;

use App\Models\AdminPermission;
use App\Models\Compliance\ComplianceSafetyReport;
use App\Models\Compliance\CompliancePolicyVersion;
use App\Models\Compliance\HostPublishingSuspension;
use App\Models\Event;
use App\Models\EventComplianceEvidence;
use App\Models\HostIdentityVerification;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Compliance\EventComplianceService;
use App\Services\Compliance\EventPublicationGates;
use App\Services\EventManagementService;
use Database\Seeders\ComplianceDatabaseSeeder;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The administrators' compliance management dashboard: role-based access through Gates and Policies, identity
 * documents only for specifically authorized administrators, suspensions, safety reports, policy versions,
 * consent and audit records, and exports.
 */
class AdminComplianceDashboardTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = 'evt-kingston-harbour-fry';

    private User $host;
    private User $root;     // a system administrator
    private User $delegate; // the demo delegated administrator (compliance management, no identity documents)

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class, ComplianceDatabaseSeeder::class]);
        $this->host = User::where('email', 'host@islandevents.example')->firstOrFail();
        $this->root = $this->systemAdmin('root@example.test');
        $this->delegate = User::where('email', 'admin@islandevents.example')->firstOrFail();
        Event::withoutGlobalScopes()->whereKey(self::EVENT)->update(['starts_at' => now()->addMonth()]);
    }

    private function systemAdmin(string $email): User
    {
        $user = User::create(['name' => 'Root ' . $email, 'email' => $email, 'password' => bcrypt('password'), 'status' => 'active', 'is_system_admin' => true]);
        UserRole::create(['user_id' => $user->id, 'role' => 'system_admin', 'is_active' => true, 'assigned_at' => now()]);

        return $user;
    }

    private function readyEvent(): Event
    {
        $event = Event::withoutGlobalScopes()->findOrFail(self::EVENT);
        $event->forceFill(['status' => 'draft', 'is_ordering_open' => false] + $this->eventArrangementFields(true))->save();
        app(EventComplianceService::class)->record($this->host, $event->fresh(), $this->eventComplianceFields(), 'event_editing');

        return $event->fresh();
    }

    private function codes(Event $event): array
    {
        return array_column(app(EventPublicationGates::class)->blocking($event->fresh(), $this->host), 'code');
    }

    public function test_only_compliance_managers_reach_the_dashboard(): void
    {
        $this->actingAs($this->host)->get(route('admin.console.compliance-centre.dashboard'))->assertForbidden();
        $this->actingAs(User::where('email', 'attendee@islandevents.example')->first())->get(route('admin.console.compliance-centre.dashboard'))->assertForbidden();

        // A delegated administrator without "Compliance management" is refused; with it, allowed.
        AdminPermission::where('user_id', $this->delegate->id)->update(['can_manage_compliance' => false]);
        $this->actingAs($this->delegate->fresh())->get(route('admin.console.compliance-centre.dashboard'))->assertForbidden();
        AdminPermission::where('user_id', $this->delegate->id)->update(['can_manage_compliance' => true]);
        $this->actingAs($this->delegate->fresh())->get(route('admin.console.compliance-centre.dashboard'))->assertOk()
            ->assertSee('Compliance Dashboard')->assertSee('Food permits to review')->assertDontSee('>Identity</a>', false);

        $this->actingAs($this->root)->get(route('admin.console.compliance-centre.dashboard', ['state' => 'FL']))->assertOk()
            ->assertSee('Maintain jurisdiction requirements');
        foreach (['declarations', 'expiring', 'safety', 'hosts', 'policies', 'access'] as $page) {
            $this->actingAs($this->root)->get(route('admin.console.compliance-centre.' . $page))->assertOk();
        }
    }

    public function test_identity_documents_need_a_specific_grant_even_for_a_system_administrator(): void
    {
        $verification = HostIdentityVerification::where('user_id', $this->host->id)->firstOrFail();
        $verification->forceFill(['status' => 'pending_review'])->save();

        // Neither role is enough on its own.
        foreach ([$this->root, $this->delegate] as $admin) {
            $this->actingAs($admin)->get(route('admin.console.compliance-centre.identity'))->assertForbidden();
            $this->actingAs($admin)->post(route('admin.console.compliance-centre.identity.document', $verification))->assertForbidden();
            $this->actingAs($admin)->post(route('admin.host-verification.status', $verification->id), ['status' => 'approved'])->assertForbidden();
            $this->actingAs($admin)->getJson(route('api.admin.host-verifications.index'))->assertForbidden();
        }
        $this->actingAs($this->root)->get(route('admin.host-applications'))->assertOk()->assertDontSee('Host Government-Issued ID Verification Desk');

        // A system administrator cannot grant it to themselves; another one grants it to the delegate, with an audit entry.
        $grant = ['grant' => '1', 'reason' => 'Assigned to identity review rota.', 'confirm' => '1'];
        $this->actingAs($this->root)->post(route('admin.console.compliance-centre.access.update', $this->root), $grant)->assertForbidden();
        $this->actingAs($this->delegate)->post(route('admin.console.compliance-centre.access.update', $this->delegate), $grant)->assertForbidden();
        $this->actingAs($this->root)->post(route('admin.console.compliance-centre.access.update', $this->delegate), $grant)->assertRedirect();
        $this->assertTrue((bool) AdminPermission::where('user_id', $this->delegate->id)->value('can_access_identity_documents'));
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'compliance_identity_access_changed', 'target_id' => (string) $this->delegate->id]);

        $this->actingAs($this->delegate->fresh())->get(route('admin.console.compliance-centre.identity'))->assertOk()
            ->assertSee($this->host->email)->assertSee('Request additional documents');
        $this->actingAs($this->delegate->fresh())->post(route('admin.host-verification.status', $verification->id),
            ['status' => 'additional_information_required', 'reason' => 'The photo is blurred; upload a clearer scan.'])->assertRedirect();
        $this->assertSame('additional_information_required', $verification->fresh()->status);

        // Removing the grant removes access at once.
        $this->actingAs($this->root)->post(route('admin.console.compliance-centre.access.update', $this->delegate), ['grant' => '0'] + $grant)->assertRedirect();
        $this->actingAs($this->delegate->fresh())->get(route('admin.console.compliance-centre.identity'))->assertForbidden();
    }

    public function test_suspending_an_event_unpublishes_it_and_blocks_publishing_until_reinstated(): void
    {
        $event = $this->readyEvent();
        $event->forceFill(['status' => 'published', 'is_ordering_open' => true])->save();
        $url = route('admin.console.compliance-centre.events.suspend', $event->id);

        $this->actingAs($this->host)->post($url, ['reason' => str_repeat('x', 30), 'confirm' => '1'])->assertForbidden();
        $this->actingAs($this->root)->post($url, ['reason' => 'too short', 'confirm' => '1'])->assertSessionHasErrors('reason');
        $this->actingAs($this->root)->post($url, ['reason' => 'Food poisoning reports under investigation by the county.', 'confirm' => '1'])->assertRedirect();

        $event = $event->fresh();
        $this->assertSame('unpublished', $event->status);
        $this->assertFalse((bool) $event->is_ordering_open);
        $this->assertContains('event_suspended', $this->codes($event));
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'compliance_event_suspended', 'target_id' => $event->id]);
        try {
            app(EventManagementService::class)->setStatus($this->host, $event->id, 'published');
            $this->fail('A suspended event was published.');
        } catch (\InvalidArgumentException) {
        }
        $this->actingAs($this->host)->get(route('host.compliance'))->assertSee('Suspended by EventBite');

        $this->actingAs($this->root)->post(route('admin.console.compliance-centre.events.reinstate', $event->id), ['reason' => 'County cleared the vendor.'])->assertRedirect();
        $this->assertNotContains('event_suspended', $this->codes($event));
        $this->assertSame('unpublished', $event->fresh()->status);
    }

    public function test_suspending_a_hosts_publishing_privileges(): void
    {
        $event = $this->readyEvent();
        $event->forceFill(['status' => 'published'])->save();
        $url = route('admin.console.compliance-centre.hosts.suspend', $this->host);

        $this->actingAs($this->root)->post($url, ['reason' => 'Repeated unlicensed alcohol sales reported by the state.', 'unpublish' => '1', 'confirm' => '1'])->assertRedirect();
        $this->assertNotNull(HostPublishingSuspension::activeFor($this->host->id));
        $this->assertSame('unpublished', $event->fresh()->status);
        $this->assertContains('host_suspended', $this->codes($event));
        $this->actingAs($this->host)->get(route('host.compliance'))->assertSee('Publishing suspended')->assertSee('Repeated unlicensed alcohol sales');

        // An administrator cannot suspend themselves.
        $this->actingAs($this->root)->post(route('admin.console.compliance-centre.hosts.suspend', $this->root), ['reason' => str_repeat('y', 25), 'confirm' => '1'])->assertForbidden();

        $suspension = HostPublishingSuspension::activeFor($this->host->id);
        $this->actingAs($this->delegate)->post(route('admin.console.compliance-centre.hosts.reinstate', $suspension), ['reason' => 'State confirmed licence now held.'])->assertRedirect();
        $this->assertNull(HostPublishingSuspension::activeFor($this->host->id));
        $this->assertNotContains('host_suspended', $this->codes($event));
    }

    public function test_safety_reports_are_filed_by_users_and_handled_by_compliance_administrators(): void
    {
        Event::withoutGlobalScopes()->whereKey(self::EVENT)->update(['status' => 'published']);
        $attendee = User::where('email', 'attendee@islandevents.example')->firstOrFail();
        $this->actingAs($attendee)->post(route('events.safety-report', self::EVENT), ['category' => 'food_safety', 'description' => 'Several of us were sick after the fried fish on Saturday night.'])
            ->assertRedirect()->assertSessionHas('success');
        $report = ComplianceSafetyReport::firstOrFail();
        $this->assertSame('urgent', $report->priority);

        $this->actingAs($attendee)->post(route('admin.console.compliance-centre.safety.update', $report), ['status' => 'resolved', 'priority' => 'normal'])->assertForbidden();
        $this->actingAs($this->delegate)->get(route('admin.console.compliance-centre.safety'))->assertOk()->assertSee('Several of us were sick');
        $this->actingAs($this->delegate)->post(route('admin.console.compliance-centre.safety.update', $report), ['status' => 'resolved', 'priority' => 'urgent'])
            ->assertSessionHasErrors('resolution');
        $this->actingAs($this->delegate)->post(route('admin.console.compliance-centre.safety.update', $report),
            ['status' => 'resolved', 'priority' => 'urgent', 'resolution' => 'County inspected; vendor removed from future events.'])->assertRedirect();
        $this->assertSame('resolved', $report->fresh()->status);
        $this->assertSame($this->delegate->id, $report->fresh()->resolved_by);
    }

    public function test_publishing_a_new_host_acknowledgement_requires_every_host_to_accept_it(): void
    {
        $event = $this->readyEvent();
        $this->assertSame([], $this->codes($event));

        // A compliance manager drafts it; a draft changes nothing.
        $this->actingAs($this->delegate)->post(route('admin.console.compliance-centre.policies.store'), [
            'category' => 'safety', 'applicable_roles' => ['event_host'], 'acknowledgement_mandatory' => '1', 'requires_reacknowledgement' => '1', 'version' => '2026.12-v1', 'title' => 'Heat Illness Prevention',
            'body' => "I will provide shade and drinking water at outdoor events.\nI will plan for extreme heat.", 'change_summary' => 'New acknowledgement for outdoor summer events.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $draft = CompliancePolicyVersion::where('status', 'draft')->firstOrFail();
        $this->assertSame([], $this->codes($event));

        // Only a system administrator publishes.
        $this->actingAs($this->delegate)->post(route('admin.console.compliance-centre.policies.publish', $draft), ['confirm' => '1'])->assertForbidden();
        $this->actingAs($this->root)->post(route('admin.console.compliance-centre.policies.publish', $draft), ['confirm' => '1'])->assertRedirect();
        app()->forgetScopedInstances();
        $this->assertSame('published', $draft->fresh()->status);
        $this->assertContains('host_policies', $this->codes($event));
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'compliance_policy_published']);

        // The host accepts it from their compliance page and can publish again.
        $this->actingAs($this->host)->get(route('host.compliance'))->assertSee('Heat Illness Prevention');
        $this->actingAs($this->host)->post(route('host.compliance.policies'), ['policies' => [$draft->policy_name]])->assertRedirect();
        app()->forgetScopedInstances();
        $this->assertSame([], $this->codes($event));

        // A new version of a built-in policy retires nothing built in, and the host sees it as an update.
        $this->actingAs($this->delegate)->post(route('admin.console.compliance-centre.policies.store'), [
            'policy_name' => 'host_resp_permits_and_inspections', 'category' => 'host_responsibility', 'applicable_roles' => ['event_host'], 'acknowledgement_mandatory' => '1', 'requires_reacknowledgement' => '1', 'version' => '2026.12-v2',
            'title' => 'Food-Service Permits, Licences & Health Inspections', 'body' => 'I will hold every required permit before serving food, and show it on request.',
            'change_summary' => 'Hosts must show permits on request.',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->root)->post(route('admin.console.compliance-centre.policies.publish', CompliancePolicyVersion::where('version', '2026.12-v2')->first()), ['confirm' => '1']);
        app()->forgetScopedInstances();
        $overview = app(\App\Services\Compliance\HostComplianceOverview::class)->for($this->host);
        $this->assertSame('updated', collect($overview['policies'])->firstWhere('policy_name', 'host_resp_permits_and_inspections')['state']);
        $this->assertContains('host_policies', $this->codes($event));
    }

    public function test_records_and_exports_are_authorized_by_what_they_contain(): void
    {
        $event = $this->readyEvent();
        EventComplianceEvidence::create(['event_id' => $event->id, 'item_key' => 'event:food_permit:state:fl/fl', 'item_title' => 'Food permit',
            'status' => 'submitted', 'reference_number' => '=HYPERLINK("x")', 'submitted_by' => $this->host->id, 'submitted_at' => now()]);
        EventComplianceEvidence::create(['event_id' => $event->id, 'item_key' => 'alcohol_service:alcohol_licensing:state:fl/fl', 'item_title' => 'Alcohol licence',
            'status' => 'submitted', 'reference_number' => 'AL-1', 'submitted_by' => $this->host->id, 'submitted_at' => now()]);

        // Filtering permits and licences by type and jurisdiction.
        $this->actingAs($this->delegate)->get(route('admin.console.compliance.evidence.index', ['kind' => 'food', 'state' => 'FL']))->assertOk()
            ->assertSee('Food permit')->assertDontSee('Alcohol licence</h2>', false);
        $this->actingAs($this->delegate)->get(route('admin.console.compliance.evidence.index', ['kind' => 'alcohol', 'state' => 'TX']))->assertOk()
            ->assertDontSee('Alcohol licence</h2>', false);

        // Consent and audit records need audit access; the demo delegate has none.
        $this->actingAs($this->delegate)->get(route('admin.console.compliance-centre.records'))->assertForbidden();
        $this->actingAs($this->delegate)->get(route('admin.console.compliance-centre.export', 'consent'))->assertForbidden();
        $this->actingAs($this->delegate)->get(route('admin.console.compliance-centre.export', 'identity'))->assertForbidden();
        $this->actingAs($this->root)->get(route('admin.console.compliance-centre.records', ['q' => 'host@islandevents']))->assertOk()->assertSee('host@islandevents.example');

        $csv = $this->actingAs($this->delegate)->get(route('admin.console.compliance-centre.export', ['report' => 'permits', 'state' => 'FL']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Requirement,Reference', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv); // spreadsheet formulas neutralised
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'compliance_report_exported', 'target_id' => 'permits']);
        $this->actingAs($this->root)->get(route('admin.console.compliance-centre.export', 'audit'))->assertOk();
        $this->actingAs($this->root)->get(route('admin.console.compliance-centre.export', 'nope'))->assertNotFound();
    }
}
