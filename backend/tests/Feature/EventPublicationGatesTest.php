<?php

namespace Tests\Feature;

use App\Models\Compliance\ComplianceEventRule;
use App\Models\Compliance\ComplianceJurisdiction;
use App\Models\Compliance\CompliancePublicationOverride;
use App\Models\Event;
use App\Models\EventComplianceEvidence;
use App\Models\HostIdentityVerification;
use App\Models\PolicyAcceptance;
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
 * Event publication gates: identity, host acknowledgements, the event's acknowledgement, food and alcohol
 * declarations and mandatory jurisdiction requirements, with audited, expiring overrides that only a compliance
 * administrator can grant, and only for requirement items.
 */
class EventPublicationGatesTest extends TestCase
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
    }

    /** The seeded event, made ready: food sold, no alcohol, its own acknowledgement. */
    private function readyEvent(array $arrangements = []): Event
    {
        $event = Event::withoutGlobalScopes()->findOrFail(self::EVENT);
        $event->forceFill(['status' => 'draft', 'is_ordering_open' => false] + $arrangements + $this->eventArrangementFields(true))->save();
        app(EventComplianceService::class)->record($this->host, $event->fresh(), $this->eventComplianceFields(), 'event_editing');

        return $event->fresh();
    }

    private function codes(Event $event, ?User $actor = null): array
    {
        return array_column(app(EventPublicationGates::class)->blocking($event, $actor ?? $this->host), 'code');
    }

    private function systemAdmin(string $email): User
    {
        $user = User::create(['name' => 'Compliance ' . $email, 'email' => $email, 'password' => bcrypt('password'), 'status' => 'active', 'is_system_admin' => true]);
        UserRole::create(['user_id' => $user->id, 'role' => 'system_admin', 'is_active' => true, 'assigned_at' => now()]);

        return $user;
    }

    private function foodPermitRule(): ComplianceEventRule
    {
        return ComplianceEventRule::create(['jurisdiction_id' => $this->florida->id, 'topic' => 'food_permit', 'effect' => 'required', 'location' => 'event',
            'conditions' => ['food_modes' => ['sold', 'both']], 'source_url' => 'https://www.example.gov/food', 'is_active' => true]);
    }

    public function test_a_ready_event_publishes(): void
    {
        $event = $this->readyEvent();
        $this->assertSame([], $this->codes($event));
        $this->assertSame('published', app(EventManagementService::class)->setStatus($this->host, $event->id, 'published')->status);
    }

    public function test_an_unverified_host_or_missing_host_acknowledgements_block_publication_even_for_an_administrator(): void
    {
        $event = $this->readyEvent();
        HostIdentityVerification::where('user_id', $this->host->id)->update(['status' => 'pending_verification']);
        PolicyAcceptance::where('user_id', $this->host->id)->where('policy_name', 'host_resp_alcohol_licensing_and_age_verification')->delete();

        $this->assertSame(['identity', 'host_policies'], $this->codes($event));
        // An administrator publishing the host's event is checked against the host, not against themselves.
        $admin = $this->systemAdmin('publisher@example.test');
        $this->assertSame(['identity', 'host_policies'], $this->codes($event, $admin));
        $this->expectException(\InvalidArgumentException::class);
        app(EventManagementService::class)->setStatus($admin, $event->id, 'published');
    }

    public function test_a_host_with_no_verification_record_at_all_cannot_publish(): void
    {
        $event = $this->readyEvent();
        HostIdentityVerification::where('user_id', $this->host->id)->delete();
        $this->assertContains('identity', $this->codes($event));
    }

    public function test_incomplete_or_contradictory_food_and_alcohol_declarations_block_publication(): void
    {
        $unanswered = $this->readyEvent();
        $unanswered->forceFill(['food_service_mode' => null, 'alcohol_service_mode' => null])->save();
        // Both questions unanswered (what the event does is unchanged, so its acknowledgement still covers it).
        $this->assertSame(['arrangements', 'arrangements'], $this->codes($unanswered->fresh()));

        $contradiction = $this->readyEvent(['food_service_mode' => 'none']);
        $messages = array_column(app(EventPublicationGates::class)->blocking($contradiction, $this->host), 'message');
        $this->assertContains('Food ordering is on, but "Food at this event" says no food. Correct one of them.', $messages);

        // BYOB needs the age checks but no licence; selling alcohol needs a licence status unless a licensed vendor serves it.
        $byob = $this->readyEvent(['alcohol_service_mode' => 'byob', 'alcohol_age_verification_required' => false]);
        $this->assertSame(['Alcohol is provided: confirm the legal drinking age and government ID checks.'], array_column(app(EventPublicationGates::class)->blocking($byob, $this->host), 'message'));
        $sold = $this->readyEvent(['alcohol_service_mode' => 'sold', 'alcohol_age_verification_required' => true, 'alcohol_permit_status' => 'no_alcohol']);
        $this->assertSame(['arrangements'], $this->codes($sold));
        $vendor = $this->readyEvent(['alcohol_service_mode' => 'sold', 'alcohol_age_verification_required' => true, 'alcohol_permit_status' => 'no_alcohol', 'uses_licensed_alcohol_vendor' => true]);
        $this->assertSame([], $this->codes($vendor));
    }

    public function test_required_permits_block_until_verified_and_unrelated_alcohol_requirements_never_block_a_food_only_event(): void
    {
        $this->foodPermitRule();
        ComplianceEventRule::create(['jurisdiction_id' => $this->florida->id, 'topic' => 'alcohol_licensing', 'effect' => 'required', 'location' => 'alcohol_service',
            'source_url' => 'https://www.example.gov/alcohol', 'is_active' => true]);
        $event = $this->readyEvent();

        // Food but no alcohol: only the food permit gate; the alcohol licence rule does not apply.
        $gates = app(EventPublicationGates::class)->blocking($event, $this->host);
        $this->assertCount(1, $gates);
        $this->assertStringStartsWith('Submit the permit or licence for Food permit requirements (Florida', $gates[0]['message']);
        $key = substr($gates[0]['code'], strlen(EventPublicationGates::OVERRIDABLE_PREFIX));

        $evidence = EventComplianceEvidence::create(['event_id' => $event->id, 'item_key' => $key, 'item_title' => 'Food permit', 'status' => 'submitted',
            'reference_number' => 'FP-1', 'issuing_authority' => 'Florida DBPR', 'submitted_by' => $this->host->id, 'submitted_at' => now()]);
        $this->assertStringContainsString('waiting for an EventBite administrator to verify it', app(EventPublicationGates::class)->blocking($event, $this->host)[0]['message']);
        $evidence->forceFill(['status' => 'rejected', 'review_comment' => 'Wrong county'])->save();
        $this->assertStringContainsString('was rejected: Wrong county', app(EventPublicationGates::class)->blocking($event, $this->host)[0]['message']);
        $evidence->forceFill(['status' => 'verified', 'expires_on' => now()->subDay()])->save();
        $this->assertStringContainsString('has expired', app(EventPublicationGates::class)->blocking($event, $this->host)[0]['message']);
        $evidence->forceFill(['expires_on' => now()->addYear()])->save();
        $this->assertSame([], $this->codes($event));

        // Adding alcohol brings the alcohol licence requirement in.
        $withAlcohol = $this->readyEvent(['alcohol_service_mode' => 'sold', 'alcohol_age_verification_required' => true, 'alcohol_permit_status' => 'special_event_permit_obtained']);
        $this->assertCount(1, app(EventPublicationGates::class)->blocking($withAlcohol, $this->host));
        $this->assertStringContainsString('Alcohol licensing requirements', app(EventPublicationGates::class)->blocking($withAlcohol, $this->host)[0]['message']);
    }

    public function test_only_a_compliance_administrator_overrides_a_requirement_with_a_reason_an_expiry_and_an_audit_entry(): void
    {
        $this->foodPermitRule();
        $event = $this->readyEvent();
        $code = $this->codes($event)[0];
        $url = route('admin.console.compliance.overrides.store', $event->id);
        $valid = ['gate_code' => $code, 'reason' => 'Permit confirmed by phone with the county office; certificate to follow.', 'review_by' => now()->addDays(14)->toDateString(), 'confirm' => '1'];

        // An event host, even the event's own, cannot reach it; nor can a delegated administrator.
        $this->actingAs($this->host)->post($url, $valid)->assertForbidden();
        $this->actingAs(User::where('email', 'admin@islandevents.example')->firstOrFail())->post($url, $valid)->assertForbidden();

        $admin = $this->systemAdmin('override@example.test');
        $this->actingAs($admin)->post($url, ['reason' => 'too short'] + $valid)->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post($url, ['review_by' => now()->addDays(120)->toDateString()] + $valid)->assertSessionHasErrors('review_by');
        $this->actingAs($admin)->post($url, ['confirm' => null] + $valid)->assertSessionHasErrors('confirm');
        $this->actingAs($admin)->post($url, ['gate_code' => 'identity'] + $valid)->assertSessionHasErrors('gate_code');

        $this->actingAs($admin)->post($url, $valid)->assertRedirect()->assertSessionHasNoErrors();
        $override = CompliancePublicationOverride::firstOrFail();
        $this->assertSame([], $this->codes($event));
        $this->assertDatabaseHas('security_audit_logs', ['user_id' => $admin->id, 'action' => 'compliance_override_granted', 'target_id' => $event->id]);
        $this->assertTrue(collect(app(EventManagementService::class)->publicationReadiness($event, $this->host))
            ->contains(fn ($i) => !$i['critical'] && str_starts_with($i['message'], 'Overridden by ' . $admin->name)));

        // It lapses on its review date, and a revoked override blocks again at once.
        $override->forceFill(['review_by' => now()->subDay()])->save();
        $this->assertSame([$code], $this->codes($event));
        $override->forceFill(['review_by' => now()->addDays(5)])->save();
        $this->actingAs($admin)->post(route('admin.console.compliance.overrides.revoke', $override), ['revoke_reason' => 'Certificate never arrived.'])->assertRedirect();
        $this->assertSame([$code], $this->codes($event));
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'compliance_override_revoked', 'target_id' => $event->id]);
    }

    public function test_identity_and_acknowledgement_gates_cannot_be_overridden_and_moderation_cannot_skip_the_gates(): void
    {
        $event = $this->readyEvent();
        HostIdentityVerification::where('user_id', $this->host->id)->delete();
        $gate = collect(app(EventPublicationGates::class)->check($event))->firstWhere('code', 'identity');
        $this->assertFalse($gate['overridable']);

        $admin = $this->systemAdmin('moderator@example.test');
        $this->actingAs($admin)->get(route('admin.console.events.show', $event->id))->assertOk()
            ->assertSee('Compliance publication gates')->assertSee('Blocking · cannot be overridden');

        $event->forceFill(['status' => 'unpublished'])->save();
        $this->actingAs($admin)->post(route('admin.console.events.moderate', $event->id), ['action' => 'republish', 'reason' => 'Host asked to relist', 'confirm' => '1'])
            ->assertSessionHas('error', fn ($m) => str_starts_with($m, 'Republishing is blocked by the compliance gates'));
        $this->assertSame('unpublished', $event->fresh()->status);
    }
}
