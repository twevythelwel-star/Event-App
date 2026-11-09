<?php

namespace Tests\Feature\Compliance;

use App\Models\AdminPermission;
use App\Models\Compliance\ComplianceEventRule;
use App\Models\Compliance\ComplianceJurisdiction;
use App\Models\Compliance\CompliancePolicyVersion;
use App\Models\ComplianceAuditLog;
use App\Models\Event;
use App\Models\EventComplianceEvidence;
use App\Models\HostApplication;
use App\Models\HostIdentityVerification;
use App\Models\PolicyAcceptance;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ComplianceDatabaseSeeder;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * End to end, through the real routes: a person registers, applies to host, has their ID approved, creates an
 * event, is held back by a missing permit, gets it verified and publishes; then a policy changes and an edit
 * changes the event's jurisdiction. Every sensitive document access and administrative decision on the way is
 * in the audit log (acceptance scenario 20).
 */
class ComplianceLifecycleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const POLICIES = ['accept_platform_terms' => '1', 'accept_food_safety_disclaimer' => '1', 'accept_alcohol_policy' => '1'];
    private const HOST_ACKS = ['ack_independent_compliance' => '1', 'ack_food_safety_temperatures' => '1', 'ack_allergen_disclosure' => '1',
        'ack_permits_and_inspections' => '1', 'ack_alcohol_compliance' => '1', 'ack_identity_accountability' => '1'];

    private function admin(string $email, string $role, array $permissions = []): User
    {
        $user = User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => bcrypt('password'), 'status' => 'active', 'is_system_admin' => $role === 'system_admin']);
        UserRole::create(['user_id' => $user->id, 'role' => $role, 'is_active' => true, 'assigned_at' => now()]);
        if ($permissions) {
            AdminPermission::create(['user_id' => $user->id, 'scope' => 'all', 'assigned_organizer_ids' => []] + $permissions);
        }

        return $user->fresh();
    }

    public function test_from_registration_to_publication_with_every_decision_audited(): void
    {
        Storage::fake('local');
        Notification::fake();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class, ComplianceDatabaseSeeder::class]);
        $root = $this->admin('root@example.test', 'system_admin');
        $other = $this->admin('root2@example.test', 'system_admin');
        $reviewer = $this->admin('reviewer@example.test', 'admin', ['can_manage_compliance' => true]);

        // 1. Register, accepting each policy.
        $this->post(route('register'), ['first_name' => 'Maya', 'last_name' => 'Grant', 'email' => 'maya@example.test',
            'password' => 'Secret-Pass-2026', 'password_confirmation' => 'Secret-Pass-2026', 'agree_terms' => '1'] + self::POLICIES)->assertRedirect();
        $maya = User::where('email', 'maya@example.test')->firstOrFail();
        $this->assertSame(3, PolicyAcceptance::where('user_id', $maya->id)->where('context', 'account_registration')->count());

        // 2. Apply to host with the six acknowledgements and a government ID.
        $this->actingAs($maya)->post(route('host.apply.submit'), [
            'organization_name' => 'Grant Family Cookouts', 'country' => 'US', 'contact_phone' => '+13055550111', 'legal_full_name' => 'Maya Grant',
            'government_id_type' => 'passport', 'identity_document' => UploadedFile::fake()->image('passport.jpg', 600, 400),
            'consent_id_verification' => '1', 'enforce_host_compliance' => '1',
        ] + self::HOST_ACKS)->assertSessionHasNoErrors();
        $application = HostApplication::where('user_id', $maya->id)->firstOrFail();
        $verification = HostIdentityVerification::where('user_id', $maya->id)->firstOrFail();
        $this->assertSame(6, PolicyAcceptance::where('user_id', $maya->id)->where('context', 'host_application')->count());

        // 3. Identity access is granted to a reviewer by another system administrator; the reviewer opens and approves the ID.
        $this->actingAs($root)->post(route('admin.console.compliance-centre.access.update', $reviewer), ['grant' => '1', 'reason' => 'Identity review rota.', 'confirm' => '1'])->assertRedirect();
        $reviewer = $reviewer->fresh();
        $link = $this->actingAs($reviewer)->post(route('admin.console.compliance-centre.identity.document', $verification))->assertRedirect()->headers->get('Location');
        $this->actingAs($reviewer)->get($link)->assertOk();
        $this->actingAs($reviewer)->post(route('admin.host-verification.status', $verification->id), ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->actingAs($root)->post(route('admin.host-applications.approve', $application->id))->assertRedirect();
        $maya = $maya->fresh();
        $this->assertTrue($maya->hasRole(\App\Enums\AccountType::EVENT_HOST));

        // 4. Create an event in Miami-Dade that sells food; a verified rule requires a food permit there.
        ComplianceEventRule::create(['jurisdiction_id' => ComplianceJurisdiction::where('type', 'state')->where('code', 'FL')->value('id'),
            'topic' => 'food_permit', 'effect' => 'required', 'location' => 'event', 'conditions' => ['food_modes' => ['sold']],
            'source_url' => 'https://www.myfloridalicense.com/', 'is_active' => true]);
        $form = ['title' => 'Grant Family Cookout', 'date_str' => 'Saturday, December 12, 2026', 'time_str' => '2:00 PM - 8:00 PM', 'timezone' => 'America/New_York',
            'venue_name' => 'Tropical Park', 'street_address' => '7900 SW 40th St', 'city' => 'Miami', 'state_code' => 'FL', 'zip_code' => '33155', 'country_code' => 'US',
            'venue_address' => '7900 SW 40th St, Miami, FL, 33155, United States', 'organizer_name' => 'Grant Family Cookouts', 'organizer_phone' => '+13055550111',
            'advance_order_deadline' => '2026-12-11 18:00', 'supports_food_ordering' => '0'] + $this->eventComplianceFields() + $this->eventArrangementFields(true);
        $this->actingAs($maya)->post(route('events.store'), $form)->assertSessionHasNoErrors();
        $event = Event::withoutGlobalScopes()->where('title', 'Grant Family Cookout')->firstOrFail();
        // The event content the readiness check needs besides compliance (description, date, a ticket type).
        $event->forceFill(['description' => 'A family cookout with jerk chicken, fried fish and live music in the park.', 'event_date_iso' => '2026-12-12',
            'ticket_tiers' => Event::withoutGlobalScopes()->find('evt-kingston-harbour-fry')->ticket_tiers])->save();

        // 5. Publishing is refused until the permit is verified.
        $this->actingAs($maya)->post(route('events.publish', $event->id));
        $this->assertNotSame('published', $event->fresh()->status);
        $blocking = app(\App\Services\Compliance\EventPublicationGates::class)->blocking($event->fresh(), $maya);
        $this->assertSame(['item:'], array_values(array_unique(array_map(fn ($g) => substr($g['code'], 0, 5), $blocking))), json_encode(array_column($blocking, 'message')));
        $item = $blocking[0]['code'];
        $this->actingAs($maya)->post(route('events.requirements.evidence', $event->id), ['item_key' => substr($item, strlen(\App\Services\Compliance\EventPublicationGates::OVERRIDABLE_PREFIX)), 'reference_number' => 'TFE-2026-881',
            'issuing_authority' => 'Florida DBPR Division of Hotels and Restaurants', 'expires_on' => now()->addYear()->toDateString(),
            'document' => UploadedFile::fake()->create('permit.pdf', 40, 'application/pdf')])->assertSessionHasNoErrors();
        $evidence = EventComplianceEvidence::where('event_id', $event->id)->firstOrFail();
        $this->actingAs($reviewer)->get(route('events.requirements.document', $evidence))->assertOk();
        $this->actingAs($reviewer)->post(route('admin.console.compliance.evidence.review', [$evidence, 'verify']), ['comment' => 'Checked on the DBPR licence portal.'])->assertRedirect();
        $this->assertSame([], array_column(app(\App\Services\Compliance\EventPublicationGates::class)->blocking($event->fresh(), $maya), 'message'));
        $this->actingAs($maya)->post(route('events.publish', $event->id));
        $this->assertSame('published', $event->fresh()->status);

        // 6. A material policy change: the host must re-acknowledge before doing anything else.
        $this->actingAs($reviewer)->post(route('admin.console.compliance-centre.policies.store'), ['policy_name' => 'host_resp_permits_and_inspections', 'version' => '2026.12-v2',
            'title' => 'Food-Service Permits, Licences & Health Inspections', 'category' => 'host_responsibility', 'applicable_roles' => ['event_host'],
            'acknowledgement_mandatory' => '1', 'requires_reacknowledgement' => '1', 'body' => 'I will keep every permit at the event and show it on request.',
            'change_summary' => 'Permits must be kept at the event.'])->assertSessionHasNoErrors();
        $draft = CompliancePolicyVersion::where('status', 'draft')->firstOrFail();
        $this->actingAs($other)->post(route('admin.console.compliance-centre.policies.publish', $draft), ['confirm' => '1'])->assertRedirect();
        app()->forgetScopedInstances();
        $this->actingAs($maya)->get(route('host.dashboard'))->assertRedirect(route('policies.acknowledge'));
        $this->actingAs($maya)->post(route('policies.acknowledge.store'), ['policies' => ['host_resp_permits_and_inspections']])->assertRedirect();

        // 7. An administrator suspends the event on safety grounds, and a permits report is exported.
        $this->actingAs($root)->post(route('admin.console.compliance-centre.events.suspend', $event->id), ['reason' => 'Food poisoning reports under county investigation.', 'confirm' => '1'])->assertRedirect();
        $this->assertContains('event_suspended', array_column(app(\App\Services\Compliance\EventPublicationGates::class)->blocking($event->fresh(), $maya), 'code'));
        $this->actingAs($root)->get(route('admin.console.compliance-centre.export', 'permits'))->assertOk();

        // 20. Every sensitive document access and administrative decision above is in the audit log.
        $expected = [
            'compliance_identity_access_changed' => (string) $reviewer->id,
            'host_id_temporary_link_issued' => (string) $verification->id,
            'host_id_document_viewed' => (string) $verification->id,
            'host_id_verification_status_changed' => (string) $verification->id,
            'compliance_evidence_submitted' => (string) $evidence->id,
            'compliance_document_downloaded' => (string) $evidence->id,
            'compliance_evidence_verify' => (string) $evidence->id,
            'compliance_policy_drafted' => (string) $draft->id,
            'compliance_policy_published' => (string) $draft->id,
            'compliance_event_suspended' => (string) $event->id,
            'compliance_report_exported' => 'permits',
            'event_compliance_acknowledged' => (string) $event->id,
            'policy_acknowledged' => 'host_resp_permits_and_inspections',
        ];
        foreach ($expected as $action => $target) {
            $this->assertTrue(ComplianceAuditLog::where('action', $action)->where('target_id', $target)->exists(), "Audit entry missing: {$action}");
        }
        $this->assertTrue(ComplianceAuditLog::where('action', 'host_id_document_viewed')->where('is_pii_access', true)->exists());
        $this->assertSame($reviewer->id, ComplianceAuditLog::where('action', 'compliance_evidence_verify')->value('user_id'));
    }
}
