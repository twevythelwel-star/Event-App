<?php

namespace Tests\Feature;

use App\Events\Compliance\ComplianceActionRecorded;
use App\Models\AdminPermission;
use App\Models\Compliance\CompliancePolicy;
use App\Models\ComplianceAuditLog;
use App\Models\Event;
use App\Models\EventComplianceEvidence;
use App\Models\HostIdentityVerification;
use App\Models\OrganizerUser;
use App\Models\PolicyAcceptance;
use App\Models\User;
use App\Models\UserRole;
use App\Notifications\Compliance\ComplianceDocumentExpiringNotification;
use App\Notifications\Compliance\ComplianceDocumentReviewedNotification;
use App\Notifications\Compliance\HostVerificationReviewedNotification;
use App\Notifications\Compliance\PublishingSuspensionNotification;
use App\Support\AdminAuthorization as Admin;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The compliance implementation's structure: role-based access (Gates and Policies), private documents with
 * authorized, audited downloads, queued notifications, after-commit audit events, transactions, rate limits,
 * foreign keys and soft deletion.
 */
class ComplianceHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = 'evt-kingston-harbour-fry';

    private User $host;
    private User $attendee;
    private User $delegate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
        $this->host = User::where('email', 'host@islandevents.example')->firstOrFail();
        $this->attendee = User::where('email', 'attendee@islandevents.example')->firstOrFail();
        $this->delegate = User::where('email', 'admin@islandevents.example')->firstOrFail();
    }

    private function user(string $email, ?string $role = null, array $permissions = []): User
    {
        $user = User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => bcrypt('password'), 'status' => 'active',
            'is_system_admin' => $role === 'system_admin']);
        if ($role) {
            UserRole::create(['user_id' => $user->id, 'role' => $role, 'is_active' => true, 'assigned_at' => now()]);
        }
        if ($permissions !== []) {
            AdminPermission::create(['user_id' => $user->id, 'scope' => 'all', 'assigned_organizer_ids' => []] + $permissions);
        }

        return $user->fresh();
    }

    private function evidence(array $attributes = []): EventComplianceEvidence
    {
        return EventComplianceEvidence::create($attributes + ['event_id' => self::EVENT, 'item_key' => 'event:food_permit:state:fl/fl', 'item_title' => 'Food permit',
            'status' => 'submitted', 'reference_number' => 'FP-1', 'issuing_authority' => 'Florida DBPR', 'submitted_by' => $this->host->id, 'submitted_at' => now()]);
    }

    public function test_every_compliance_ability_follows_the_role_and_grant_matrix(): void
    {
        $actors = [
            'attendee' => $this->attendee,
            'host' => $this->host,
            'delegate without grants' => $this->user('plain@example.test', 'admin', ['can_manage_compliance' => false]),
            'compliance manager' => $this->delegate,
            'manager with audit access' => $this->user('auditor@example.test', 'admin', ['can_manage_compliance' => true, 'can_access_audit_logs' => true]),
            'identity reviewer' => $this->user('idreview@example.test', 'admin', ['can_access_identity_documents' => true]),
            'system admin' => $this->user('root@example.test', 'system_admin'),
            'system admin with identity access' => $this->user('rootid@example.test', 'system_admin', ['can_access_identity_documents' => true]),
        ];
        $expected = [
            //                                     manage  identity records export publish override
            'attendee' =>                          [false, false, false, false, false, false],
            'host' =>                              [false, false, false, false, false, false],
            'delegate without grants' =>           [false, false, false, false, false, false],
            'compliance manager' =>                [true,  false, false, true,  false, false],
            'manager with audit access' =>         [true,  false, true,  true,  false, false],
            'identity reviewer' =>                 [false, true,  false, false, false, false],
            'system admin' =>                      [true,  false, true,  true,  true,  true],
            'system admin with identity access' => [true,  true,  true,  true,  true,  true],
        ];
        $abilities = [Admin::COMPLIANCE_MANAGE, Admin::IDENTITY_DOCUMENTS, Admin::COMPLIANCE_RECORDS, Admin::COMPLIANCE_EXPORT, Admin::POLICY_PUBLISH, Admin::COMPLIANCE_OVERRIDE];

        foreach ($actors as $who => $user) {
            $actual = array_map(fn ($ability) => Gate::forUser($user)->allows($ability), $abilities);
            $this->assertSame($expected[$who], $actual, "Abilities for {$who}");
        }
        // Nobody grants identity access to themselves, and only system administrators grant it at all.
        $this->assertFalse(Gate::forUser($actors['system admin'])->allows(Admin::IDENTITY_ACCESS_GRANT, $actors['system admin']));
        $this->assertTrue(Gate::forUser($actors['system admin'])->allows(Admin::IDENTITY_ACCESS_GRANT, $actors['compliance manager']));
        $this->assertFalse(Gate::forUser($actors['compliance manager'])->allows(Admin::IDENTITY_ACCESS_GRANT, $actors['identity reviewer']));
    }

    public function test_compliance_documents_are_private_and_downloads_are_authorized_and_audited(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        // Signed out: sent to sign in, never served the file.
        $this->get(route('events.requirements.document', $this->evidence(['document_path' => 'compliance-evidence/x.pdf'])))->assertRedirect();
        $this->actingAs($this->host)->post(route('events.requirements.evidence', self::EVENT), [
            'item_key' => 'unknown-item', 'reference_number' => 'X', 'issuing_authority' => 'Y',
        ])->assertSessionHasErrors('item_key');

        $path = UploadedFile::fake()->create('permit.pdf', 20, 'application/pdf')->storeAs('compliance-evidence/' . self::EVENT, 'permit.pdf', 'local');
        $evidence = $this->evidence(['document_path' => $path, 'document_name' => 'permit.pdf', 'document_mime' => 'application/pdf']);
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);

        $url = route('events.requirements.document', $evidence);
        $otherHost = $this->user('other-host@example.test', 'event_host');
        OrganizerUser::create(['organizer_id' => 'org-hellshire-fishers', 'user_id' => $otherHost->id, 'role' => 'event_owner', 'is_active' => true]);

        $this->actingAs($this->attendee)->get($url)->assertNotFound();
        $this->actingAs($otherHost)->get($url)->assertNotFound();
        $this->actingAs($this->user('plain@example.test', 'admin', ['can_manage_compliance' => false]))->get($url)->assertNotFound();
        $this->actingAs($this->host)->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($this->delegate)->get($url)->assertOk();
        $this->assertSame(2, ComplianceAuditLog::where('action', 'compliance_document_downloaded')->where('target_id', (string) $evidence->id)->count());
    }

    public function test_a_host_withdraws_an_unreviewed_submission_and_the_record_is_kept(): void
    {
        $evidence = $this->evidence();
        $this->actingAs($this->attendee)->delete(route('events.requirements.evidence.withdraw', $evidence))->assertForbidden();
        $this->actingAs($this->host)->delete(route('events.requirements.evidence.withdraw', $evidence))->assertRedirect();

        $this->assertNull(EventComplianceEvidence::find($evidence->id));
        $this->assertNotNull(EventComplianceEvidence::withTrashed()->find($evidence->id)?->deleted_at);
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'compliance_evidence_withdrawn', 'target_id' => (string) $evidence->id]);

        $reviewed = $this->evidence(['status' => 'verified']);
        $this->actingAs($this->host)->delete(route('events.requirements.evidence.withdraw', $reviewed))->assertForbidden();
    }

    public function test_review_decisions_and_suspensions_send_queued_notifications_after_commit(): void
    {
        Notification::fake();
        $this->assertInstanceOf(ShouldQueue::class, new ComplianceDocumentReviewedNotification($this->evidence()));

        $evidence = $this->evidence(['reference_number' => 'FP-2']);
        $this->actingAs($this->delegate)->post(route('admin.console.compliance.evidence.review', [$evidence, 'verify']), ['comment' => 'Checked with DBPR.'])->assertRedirect();
        Notification::assertSentTo($this->host, ComplianceDocumentReviewedNotification::class, fn ($n) => $n->evidence->is($evidence));
        // Exactly one audit entry: the listener is registered once.
        $this->assertSame(1, ComplianceAuditLog::where('action', 'compliance_evidence_verify')->where('target_id', (string) $evidence->id)->count());

        $root = $this->user('root@example.test', 'system_admin');
        $this->actingAs($root)->post(route('admin.console.compliance-centre.hosts.suspend', $this->host),
            ['reason' => 'Unlicensed alcohol sales confirmed by the state.', 'confirm' => '1'])->assertRedirect();
        Notification::assertSentTo($this->host, PublishingSuspensionNotification::class, fn ($n) => $n->suspended && $n->eventTitle === null);

        $reviewer = $this->user('idreview@example.test', 'admin', ['can_access_identity_documents' => true]);
        $verification = HostIdentityVerification::where('user_id', $this->host->id)->firstOrFail();
        $this->actingAs($reviewer)->post(route('admin.host-verification.status', $verification->id),
            ['status' => 'additional_information_required', 'reason' => 'The photo is blurred.'])->assertRedirect();
        Notification::assertSentTo($this->host, HostVerificationReviewedNotification::class);
    }

    public function test_audit_events_are_written_only_when_the_transaction_commits(): void
    {
        try {
            DB::transaction(function () {
                ComplianceActionRecorded::dispatch('compliance_report_exported', 'compliance_reports', 'rolled-back', 'Never committed.', $this->delegate);
                throw new \RuntimeException('Something failed after the event was dispatched.');
            });
        } catch (\RuntimeException) {
        }
        $this->assertDatabaseMissing('security_audit_logs', ['target_id' => 'rolled-back']);

        DB::transaction(fn () => ComplianceActionRecorded::dispatch('compliance_report_exported', 'compliance_reports', 'committed', 'Committed.', $this->delegate));
        $this->assertSame(1, ComplianceAuditLog::where('target_id', 'committed')->count());
        // The compliance audit log is the append-only security log, scoped to compliance actions.
        $this->expectException(\LogicException::class);
        ComplianceAuditLog::where('target_id', 'committed')->first()->update(['details' => 'tampered']);
    }

    public function test_document_expiry_reminders_are_queued_on_the_reminder_days_only(): void
    {
        Notification::fake();
        $this->evidence(['status' => 'verified', 'expires_on' => now()->addDays(7)->toDateString(), 'reference_number' => 'SEVEN']);
        $this->evidence(['status' => 'verified', 'expires_on' => now()->addDays(10)->toDateString(), 'reference_number' => 'TEN']);
        $this->evidence(['status' => 'submitted', 'expires_on' => now()->addDays(30)->toDateString(), 'reference_number' => 'UNVERIFIED']);
        HostIdentityVerification::where('user_id', $this->host->id)->update(['document_expires_at' => now()->addDays(30)->toDateString()]);

        $this->artisan('compliance:notify-expiring')->assertSuccessful();

        Notification::assertSentToTimes($this->host, ComplianceDocumentExpiringNotification::class, 2);
        Notification::assertSentTo($this->host, ComplianceDocumentExpiringNotification::class, fn ($n) => $n->daysLeft === 7 && $n->document === 'Food permit');
        Notification::assertSentTo($this->host, ComplianceDocumentExpiringNotification::class, fn ($n) => $n->daysLeft === 30 && $n->document === 'Your government ID');
    }

    public function test_form_requests_authorize_before_validating_and_validate_every_field(): void
    {
        $root = $this->user('root@example.test', 'system_admin');
        // Not allowed: refused before any validation runs.
        $this->actingAs($this->attendee)->post(route('admin.console.compliance-centre.hosts.suspend', $this->host), [])->assertForbidden();
        $this->actingAs($this->host)->post(route('admin.host-verification.status', HostIdentityVerification::first()->id), [])->assertForbidden();
        // Allowed: every rule applies.
        $this->actingAs($root)->post(route('admin.console.compliance-centre.hosts.suspend', $this->host), ['reason' => 'short'])
            ->assertSessionHasErrors(['reason', 'confirm']);
        $this->actingAs($root)->post(route('admin.console.compliance-centre.hosts.suspend', $this->host),
            ['reason' => str_repeat('a', 25), 'confirm' => '1', 'review_by' => now()->subDay()->toDateString()])->assertSessionHasErrors('review_by');
        $this->actingAs($root)->post(route('admin.console.compliance-centre.policies.store'), ['version' => 'bad version!', 'applicable_roles' => ['nobody']])
            ->assertSessionHasErrors(['version', 'title', 'category', 'body', 'change_summary', 'applicable_roles.0']);
        $this->actingAs($this->attendee)->post(route('events.safety-report', self::EVENT), ['category' => 'gossip', 'description' => 'short'])
            ->assertSessionHasErrors(['category', 'description']);
    }

    public function test_sensitive_verification_endpoints_are_rate_limited_per_person(): void
    {
        $reviewer = $this->user('idreview@example.test', 'admin', ['can_access_identity_documents' => true]);
        $verification = HostIdentityVerification::where('user_id', $this->host->id)->firstOrFail();
        RateLimiter::clear('identity:' . $reviewer->id);

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($reviewer)->post(route('admin.host-verification.status', $verification->id), ['status' => 'not-a-status'])->assertSessionHasErrors('status');
        }
        $this->actingAs($reviewer)->post(route('admin.host-verification.status', $verification->id), ['status' => 'not-a-status'])->assertStatus(429);
        // The limit is per person: another reviewer is unaffected.
        $other = $this->user('idreview2@example.test', 'admin', ['can_access_identity_documents' => true]);
        $this->actingAs($other)->post(route('admin.host-verification.status', $verification->id), ['status' => 'not-a-status'])->assertSessionHasErrors('status');
    }

    public function test_policies_versions_and_acknowledgements_are_related_and_protected_by_foreign_keys(): void
    {
        $policy = CompliancePolicy::where('policy_name', 'host_resp_independent_compliance')->firstOrFail();
        $this->assertSame('host_responsibility', $policy->category);
        $this->assertCount(1, $policy->versions);
        $this->assertTrue($policy->acknowledgements()->where('policy_acceptances.user_id', $this->host->id)->exists());

        // A person with acknowledgement records cannot be hard-deleted from under them.
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('users')->where('id', $this->host->id)->delete();
    }
}
