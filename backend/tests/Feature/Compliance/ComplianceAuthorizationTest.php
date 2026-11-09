<?php

namespace Tests\Feature\Compliance;

use App\Models\AdminPermission;
use App\Models\Compliance\CompliancePolicyVersion;
use App\Models\Compliance\CompliancePublicationOverride;
use App\Models\Event;
use App\Models\HostApplication;
use App\Models\HostIdentityVerification;
use App\Models\OrganizerUser;
use App\Models\PolicyAcceptance;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Compliance\EventComplianceService;
use App\Services\HostIdentityVerificationService;
use Database\Seeders\ComplianceDatabaseSeeder;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Authorization scenarios (numbers refer to the acceptance list): 2 own verification, 12–13 identity documents,
 * 14 who approves verification, 15 overrides, 16 historical acknowledgements for auditors.
 */
class ComplianceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $host;
    private User $attendee;
    private User $delegate;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class, ComplianceDatabaseSeeder::class]);
        $this->host = User::where('email', 'host@islandevents.example')->firstOrFail();
        $this->attendee = User::where('email', 'attendee@islandevents.example')->firstOrFail();
        $this->delegate = User::where('email', 'admin@islandevents.example')->firstOrFail();
    }

    private function user(string $email, ?string $role, array $permissions = []): User
    {
        $user = User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => bcrypt('password'), 'status' => 'active', 'is_system_admin' => $role === 'system_admin']);
        if ($role) {
            UserRole::create(['user_id' => $user->id, 'role' => $role, 'is_active' => true, 'assigned_at' => now()]);
        }
        if ($permissions) {
            AdminPermission::create(['user_id' => $user->id, 'scope' => 'all', 'assigned_organizer_ids' => []] + $permissions);
        }

        return $user->fresh();
    }

    /** A second host with an uploaded (stored, encrypted) government ID awaiting review. */
    private function hostWithDocument(): array
    {
        $other = $this->user('second-host@example.test', 'event_host');
        OrganizerUser::create(['organizer_id' => 'org-hellshire-fishers', 'user_id' => $other->id, 'role' => 'event_owner', 'is_active' => true]);
        $verification = HostIdentityVerificationService::submitOrReplaceDocument($other, UploadedFile::fake()->image('passport.jpg', 600, 400),
            ['document_type' => 'passport', 'legal_full_name' => 'Second Host', 'consent_id_verification' => '1']);

        return [$other, $verification];
    }

    private function upload(User $who)
    {
        return $this->actingAs($who)->post(route('host.verification.store'), ['document_type' => 'passport', 'legal_full_name' => $who->name,
            'identity_document' => UploadedFile::fake()->image('id.jpg', 600, 400), 'consent_id_verification' => '1']);
    }

    public function test_02_an_ordinary_attendee_cannot_upload_or_approve_their_own_host_verification(): void
    {
        $this->upload($this->attendee)->assertForbidden();
        $this->actingAs($this->attendee)->postJson(route('api.host-verifications.store'), [])->assertForbidden();
        $this->assertFalse(HostIdentityVerification::where('user_id', $this->attendee->id)->exists());

        // Once they apply to host they may upload, but never decide on it.
        HostApplication::create(['user_id' => $this->attendee->id, 'organization_name' => 'Attendee Events', 'contact_phone' => '+13055550000', 'status' => 'pending']);
        $this->upload($this->attendee)->assertSessionHasNoErrors();
        $verification = HostIdentityVerification::where('user_id', $this->attendee->id)->firstOrFail();
        $this->actingAs($this->attendee)->post(route('admin.host-verification.status', $verification->id), ['status' => 'approved'])->assertForbidden();

        // Even an administrator with identity access cannot approve their own ID.
        AdminPermission::create(['user_id' => $this->attendee->id, 'scope' => 'all', 'assigned_organizer_ids' => [], 'can_access_identity_documents' => true]);
        UserRole::create(['user_id' => $this->attendee->id, 'role' => 'admin', 'is_active' => true, 'assigned_at' => now()]);
        $this->actingAs($this->attendee->fresh())->post(route('admin.host-verification.status', $verification->id), ['status' => 'approved'])->assertSessionHasErrors();
        $this->assertSame('pending_review', $verification->fresh()->status);
    }

    public function test_12_a_host_cannot_view_another_hosts_identity_documents(): void
    {
        [, $verification] = $this->hostWithDocument();
        $reviewer = $this->user('idreview@example.test', 'admin', ['can_access_identity_documents' => true]);
        $signedForReviewer = URL::temporarySignedRoute('admin.host-verification.document', now()->addMinutes(5), ['verification' => $verification->id, 'admin' => $reviewer->id]);

        $this->assertFalse($this->host->can('viewDocument', $verification));
        $this->actingAs($this->host)->postJson(route('api.admin.host-verifications.signed-link', $verification->id))->assertForbidden();
        $this->actingAs($this->host)->get($signedForReviewer)->assertForbidden(); // a leaked link is useless to anyone else
        $this->actingAs($this->host)->post(route('admin.console.compliance-centre.identity.document', $verification))->assertForbidden();
        $this->actingAs($this->host)->getJson(route('api.admin.host-verifications.index'))->assertForbidden();
        // The authorized reviewer can open it.
        $this->actingAs($reviewer)->get($signedForReviewer)->assertOk();
    }

    public function test_13_an_attendee_cannot_access_host_identity_documents(): void
    {
        [, $verification] = $this->hostWithDocument();
        $reviewer = $this->user('idreview@example.test', 'admin', ['can_access_identity_documents' => true]);
        $signed = URL::temporarySignedRoute('admin.host-verification.document', now()->addMinutes(5), ['verification' => $verification->id, 'admin' => $reviewer->id]);

        $this->get($signed)->assertRedirect(); // signed out: sent to sign in
        $this->assertFalse($this->attendee->can('viewDocument', $verification));
        $this->actingAs($this->attendee)->get($signed)->assertForbidden();
        $this->actingAs($this->attendee)->postJson(route('api.admin.host-verifications.signed-link', $verification->id))->assertForbidden();
        $this->actingAs($this->attendee)->get(route('admin.console.compliance-centre.identity'))->assertForbidden();
    }

    public function test_14_unauthorized_administrators_cannot_approve_verification(): void
    {
        [, $verification] = $this->hostWithDocument();
        $root = $this->user('root@example.test', 'system_admin');
        foreach ([$this->delegate, $root, $this->host] as $who) {
            $this->actingAs($who)->post(route('admin.host-verification.status', $verification->id), ['status' => 'approved'])->assertForbidden();
            $this->actingAs($who)->postJson(route('api.admin.host-verifications.status', $verification->id), ['status' => 'approved'])->assertForbidden();
        }
        $this->assertSame('pending_review', $verification->fresh()->status);

        $reviewer = $this->user('idreview@example.test', 'admin', ['can_access_identity_documents' => true]);
        $this->actingAs($reviewer)->post(route('admin.host-verification.status', $verification->id), ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $verification->fresh()->status);
        $this->assertSame($reviewer->id, $verification->fresh()->reviewed_by);
    }

    public function test_approving_a_host_application_never_verifies_identity(): void
    {
        $applicant = $this->user('applicant@example.test', null);
        $application = HostApplication::create(['user_id' => $applicant->id, 'organization_name' => 'Applicant Events', 'contact_phone' => '+13055550000', 'status' => 'pending']);
        $root = $this->user('root@example.test', 'system_admin');
        \App\Services\HostApplicationService::approveApplication($root, $application->id);

        $this->assertTrue($applicant->fresh()->hasRole(\App\Enums\AccountType::EVENT_HOST), 'they become a host');
        $this->assertFalse(\App\Services\Compliance\EventPublicationGates::identityVerified($applicant->fresh()), 'but are not identity-verified');
        $this->assertFalse(HostIdentityVerificationService::evaluateHostPublishingEligibility($applicant->fresh())['allowed']);

        // An uploaded ID awaiting review is not approved by the application decision either.
        [$other, $verification] = $this->hostWithDocument();
        $otherApplication = HostApplication::create(['user_id' => $other->id, 'organization_name' => 'Other Events', 'contact_phone' => '+13055550001', 'status' => 'pending']);
        \App\Services\HostApplicationService::approveApplication($root, $otherApplication->id);
        $this->assertSame('pending_review', $verification->fresh()->status);
    }

    public function test_a_requirement_rule_is_in_force_only_after_a_second_system_administrator_approves_it(): void
    {
        $florida = \App\Models\Compliance\ComplianceJurisdiction::where('type', 'state')->where('code', 'FL')->firstOrFail();
        $author = $this->user('author@example.test', 'system_admin');
        $this->actingAs($author)->post(route('admin.console.compliance.rules.store', $florida), ['topic' => 'food_permit', 'effect' => 'required',
            'location' => 'event', 'source_url' => 'https://www.myfloridalicense.com/dbpr/hr/', 'source_confirmed_official' => '1'])->assertSessionHasNoErrors();
        $rule = \App\Models\Compliance\ComplianceEventRule::latest('id')->firstOrFail();
        $this->assertFalse($rule->is_active);
        $this->assertTrue($rule->awaitingApproval());

        // Not shown to hosts as required while it awaits approval.
        $facts = \App\Services\Compliance\EventRequirementChecklist::facts(['state_code' => 'FL', 'food_service_mode' => 'sold']);
        $this->assertNotContains('required', collect(app(\App\Services\Compliance\EventRequirementChecklist::class)->build($facts)['sections'][0]['items'])->pluck('base')->all());

        // Its author cannot approve it, nor can a compliance manager, nor turn it on without approval.
        $this->actingAs($author)->post(route('admin.console.compliance.rules.approve', $rule))->assertSessionHasErrors('rule');
        $this->actingAs($this->delegate)->post(route('admin.console.compliance.rules.approve', $rule))->assertForbidden();
        $this->actingAs($this->delegate)->post(route('admin.console.compliance.rules.toggle', $rule))->assertSessionHasErrors('rule');
        $this->assertFalse($rule->fresh()->is_active);

        $approver = $this->user('approver@example.test', 'system_admin');
        $this->actingAs($approver)->post(route('admin.console.compliance.rules.approve', $rule))->assertSessionHasNoErrors();
        $this->assertTrue($rule->fresh()->is_active);
        $this->assertSame($approver->id, $rule->fresh()->approved_by);
        $this->assertContains('required', collect(app(\App\Services\Compliance\EventRequirementChecklist::class)->build($facts)['sections'][0]['items'])->pluck('base')->all());
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'compliance_rule_approved', 'user_id' => $approver->id, 'target_id' => (string) $rule->id]);
    }

    public function test_15_compliance_overrides_require_permission_and_a_recorded_reason(): void
    {
        \App\Models\Compliance\ComplianceEventRule::create(['jurisdiction_id' => \App\Models\Compliance\ComplianceJurisdiction::where('code', 'FL')->value('id'),
            'topic' => 'food_permit', 'effect' => 'required', 'location' => 'event', 'source_url' => 'https://www.example.gov/x', 'is_active' => true]);
        $event = Event::withoutGlobalScopes()->findOrFail('evt-kingston-harbour-fry');
        $event->forceFill(['state_code' => 'FL', 'status' => 'draft'] + $this->eventArrangementFields(true))->save();
        app(EventComplianceService::class)->record($this->host, $event->fresh(), $this->eventComplianceFields(), 'event_editing');
        $code = collect(app(\App\Services\Compliance\EventPublicationGates::class)->blocking($event->fresh(), $this->host))->pluck('code')->first();
        $url = route('admin.console.compliance.overrides.store', $event->id);
        $valid = ['gate_code' => $code, 'reason' => 'Permit confirmed by phone with the county; certificate to follow.', 'review_by' => now()->addDays(10)->toDateString(), 'confirm' => '1'];

        $this->actingAs($this->host)->post($url, $valid)->assertForbidden();
        $this->actingAs($this->delegate)->post($url, $valid)->assertForbidden();
        $root = $this->user('root@example.test', 'system_admin');
        $this->actingAs($root)->post($url, ['reason' => ''] + $valid)->assertSessionHasErrors('reason');
        $this->assertSame(0, CompliancePublicationOverride::count());

        $this->actingAs($root)->post($url, $valid)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('compliance_publication_overrides', ['event_id' => $event->id, 'granted_by' => $root->id, 'reason' => $valid['reason']]);
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'compliance_override_granted', 'user_id' => $root->id, 'target_id' => $event->id]);
    }

    public function test_16_historical_acknowledgements_stay_available_to_authorized_auditors(): void
    {
        // The host accepted version 1, then version 2 of a host acknowledgement.
        CompliancePolicyVersion::create(['policy_name' => 'host_resp_permits_and_inspections', 'policy_group' => 'host', 'version' => '2026.12-v2',
            'title' => 'Food-Service Permits, Licences & Health Inspections', 'body' => 'I will hold every required permit and show it on request.',
            'change_summary' => 'Permits must be shown on request.', 'category' => 'host_responsibility', 'applicable_roles' => ['event_host'],
            'status' => 'published', 'source' => 'admin', 'published_at' => now(), 'effective_at' => now(), 'requires_reacknowledgement' => true, 'acknowledgement_mandatory' => true]);
        app()->forgetScopedInstances();
        $this->actingAs($this->host)->post(route('policies.acknowledge.store'), ['policies' => ['host_resp_permits_and_inspections']]);
        $this->assertSame(2, PolicyAcceptance::where('user_id', $this->host->id)->where('policy_name', 'host_resp_permits_and_inspections')->count());

        $auditor = $this->user('auditor@example.test', 'admin', ['can_manage_compliance' => true, 'can_access_audit_logs' => true]);
        $records = route('admin.console.compliance-centre.records', ['q' => 'host@islandevents.example', 'policy' => 'host_resp_permits_and_inspections']);
        $this->actingAs($auditor)->get($records)->assertOk()->assertSee('2026.11-v1')->assertSee('2026.12-v2');
        $csv = $this->actingAs($auditor)->get(route('admin.console.compliance-centre.export', 'consent'))->assertOk()->streamedContent();
        $this->assertStringContainsString('host_resp_permits_and_inspections,2026.11-v1', $csv);
        $this->assertStringContainsString('host_resp_permits_and_inspections,2026.12-v2', $csv);

        // Not for those without audit access, and never for hosts or attendees.
        foreach ([$this->delegate, $this->host, $this->attendee] as $who) {
            $this->actingAs($who)->get($records)->assertForbidden();
            $this->actingAs($who)->get(route('admin.console.compliance-centre.export', 'consent'))->assertForbidden();
        }
    }
}
