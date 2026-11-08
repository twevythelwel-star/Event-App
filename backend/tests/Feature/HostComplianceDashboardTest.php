<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventComplianceEvidence;
use App\Models\HostIdentityVerification;
use App\Models\PolicyAcceptance;
use App\Models\User;
use App\Services\Compliance\EventComplianceService;
use App\Services\Compliance\HostComplianceOverview;
use Database\Seeders\ComplianceDatabaseSeeder;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The host's Compliance and verification area: identity, account standing, licences and permits, expirations,
 * events needing information, event status, outstanding acknowledgements, policy updates and review messages.
 */
class HostComplianceDashboardTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = 'evt-kingston-harbour-fry';

    private User $host;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class, ComplianceDatabaseSeeder::class]);
        $this->host = User::where('email', 'host@islandevents.example')->firstOrFail();
        // Keep the seeded event current, so it is listed.
        Event::withoutGlobalScopes()->whereKey(self::EVENT)->update(['starts_at' => now()->addMonth(), 'status' => 'draft']);
    }

    private function overview(): array
    {
        return app(HostComplianceOverview::class)->for($this->host->fresh());
    }

    private function eventRow(array $overview): array
    {
        return collect($overview['events'])->first(fn ($row) => $row['event']->id === self::EVENT);
    }

    private function makeEventReady(): Event
    {
        $event = Event::withoutGlobalScopes()->findOrFail(self::EVENT);
        $event->forceFill(['is_ordering_open' => false] + $this->eventArrangementFields(true))->save();
        app(EventComplianceService::class)->record($this->host, $event->fresh(), $this->eventComplianceFields(), 'event_editing');

        return $event->fresh();
    }

    public function test_only_event_hosts_have_a_compliance_area(): void
    {
        $this->actingAs(User::where('email', 'attendee@islandevents.example')->firstOrFail())
            ->get(route('host.compliance'))->assertRedirect(route('host.apply'));
        $this->actingAs(User::where('email', 'attendee@islandevents.example')->firstOrFail())
            ->getJson(route('api.host.compliance'))->assertForbidden();
        $this->get(route('host.compliance'))->assertRedirect();
    }

    public function test_the_page_shows_every_area_with_a_status_and_what_to_do(): void
    {
        $response = $this->actingAs($this->host)->get(route('host.compliance'))->assertOk();

        foreach (['Host account compliance', 'Identity verification', 'Outstanding acknowledgements', 'Policy updates requiring acceptance',
            'Events requiring additional information', 'Event compliance status', 'Uploaded licences and permits',
            'Upcoming document expirations', 'Compliance review messages'] as $heading) {
            $response->assertSee($heading);
        }

        // The seeded event has no Event Compliance declarations: it needs the host, and says how to fix it.
        $response->assertSee('Information required')
            ->assertSee('confirm the Event Compliance declarations', false)
            ->assertSee(route('events.edit', self::EVENT) . '#studio-event-compliance', false)
            ->assertSee('data-identity-status="approved"', false);

        $overview = $this->overview();
        $this->assertSame('approved', $overview['identity']['state']);
        $this->assertSame('action', $this->eventRow($overview)['state']);
        $this->assertContains(self::EVENT, array_map(fn ($r) => $r['event']->id, $overview['events_needing_information']));
        $this->assertContains('Event Compliance declarations', array_column($overview['acknowledgements'], 'title'));
        $this->assertSame('attention', $overview['account']['state']);
    }

    public function test_a_ready_event_is_shown_as_ready_and_the_account_in_good_standing(): void
    {
        $this->makeEventReady();
        $overview = $this->overview();

        $this->assertSame('ready', $this->eventRow($overview)['state']);
        $this->assertSame('Ready to publish', $this->eventRow($overview)['label']);
        $this->assertSame([], $overview['events_needing_information']);
        $this->assertSame([], $overview['acknowledgements']);
        $this->assertSame('compliant', $overview['account']['state']);
        $this->actingAs($this->host)->get(route('host.compliance'))->assertSee('data-compliance-account="compliant"', false);
    }

    public function test_identity_problems_restrict_the_account_and_offer_an_upload(): void
    {
        HostIdentityVerification::where('user_id', $this->host->id)->delete();
        $overview = $this->overview();
        $this->assertSame('not_submitted', $overview['identity']['state']);
        $this->assertTrue($overview['identity']['can_upload']);
        $this->assertSame('restricted', $overview['account']['state']);
        $this->actingAs($this->host)->get(route('host.compliance'))->assertSee('Upload ID for review')->assertSee(route('host.verification.store'), false);

        // An approved ID whose document expired: shown as expired, listed under expirations, and it can be renewed.
        HostIdentityVerification::create(['user_id' => $this->host->id, 'status' => 'approved', 'legal_full_name' => $this->host->name,
            'document_expires_at' => now()->subDays(3), 'reviewed_at_utc' => now()->subYear()]);
        $overview = $this->overview();
        $this->assertSame('expired', $overview['identity']['state']);
        $this->assertTrue($overview['identity']['can_upload']);
        $this->assertSame('Expired 3 days ago', $overview['expiring'][0]['label']);

        Storage::fake((string) config('compliance.host_verification.storage_disk', 'local'));
        $this->actingAs($this->host)->post(route('host.verification.store'), [
            'document_type' => 'passport', 'legal_full_name' => $this->host->name, 'document_expires_at' => now()->addYears(5)->toDateString(),
            'identity_document' => UploadedFile::fake()->image('passport.jpg', 600, 400), 'consent_id_verification' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame('pending_review', HostIdentityVerification::where('user_id', $this->host->id)->latest()->first()->status);
        $this->assertSame('under_review', $this->overview()['account']['state']);
    }

    public function test_policy_updates_are_accepted_one_by_one_and_recorded(): void
    {
        $name = 'host_resp_alcohol_licensing_and_age_verification';
        PolicyAcceptance::where('user_id', $this->host->id)->where('policy_name', $name)->update(['policy_version' => '2025.01-v1']);

        $overview = $this->overview();
        $row = collect($overview['policies'])->firstWhere('policy_name', $name);
        $this->assertSame('updated', $row['state']);
        $this->assertSame('2025.01-v1', $row['previous_version']);
        $this->assertSame('restricted', $overview['account']['state']);
        $this->assertContains('Alcohol Licensing, Age Verification & Responsible Service', array_column($overview['acknowledgements'], 'title'));

        $this->actingAs($this->host)->post(route('host.compliance.policies'), [])->assertSessionHasErrors('policies');
        $this->actingAs($this->host)->post(route('host.compliance.policies'), ['policies' => ['made_up_policy']])->assertSessionHasErrors('policies');

        $this->actingAs($this->host)->post(route('host.compliance.policies'), ['policies' => [$name]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('policy_acceptances', ['user_id' => $this->host->id, 'policy_name' => $name,
            'policy_version' => \App\Services\LegalComplianceService::CURRENT_POLICY_VERSION, 'context' => 'policy_update']);
        $this->assertSame('accepted', collect($this->overview()['policies'])->firstWhere('policy_name', $name)['state']);
    }

    public function test_permits_expirations_and_review_messages_are_listed(): void
    {
        $event = $this->makeEventReady();
        EventComplianceEvidence::create(['event_id' => $event->id, 'item_key' => 'event:food_permit:us-fl', 'item_title' => 'Food permit',
            'status' => 'verified', 'reference_number' => 'FP-100', 'issuing_authority' => 'Florida DBPR', 'expires_on' => now()->addDays(10),
            'submitted_by' => $this->host->id, 'submitted_at' => now()->subWeek(), 'reviewed_at' => now()->subDays(2), 'review_comment' => 'Checked with DBPR.']);
        EventComplianceEvidence::create(['event_id' => $event->id, 'item_key' => 'alcohol_service:alcohol_licensing:us-fl', 'item_title' => 'Alcohol licence',
            'status' => 'rejected', 'reference_number' => 'AL-9', 'submitted_by' => $this->host->id, 'submitted_at' => now()->subWeek(),
            'reviewed_at' => now()->subDay(), 'review_comment' => 'The licence is for a different address.']);

        $overview = $this->overview();
        $this->assertSame(['Verified', 'Rejected'], array_column($overview['documents'], 'label'));
        $this->assertSame('Submit a corrected permit or licence.', $overview['documents'][1]['action']);
        $this->assertSame('Expires in 10 days', collect($overview['expiring'])->firstWhere('title', 'Food permit')['label']);
        $this->assertContains('The licence is for a different address.', array_column($overview['messages'], 'body'));

        $this->actingAs($this->host)->get(route('host.compliance'))->assertOk()
            ->assertSee('FP-100')->assertSee('Expires in 10 days')->assertSee('The licence is for a different address.');
    }

    public function test_the_host_dashboard_summarises_compliance_and_links_to_it(): void
    {
        $this->actingAs($this->host)->get(route('host.dashboard'))->assertOk()
            ->assertSee('Compliance and verification')->assertSee('Open compliance')->assertSee(route('host.compliance'), false);

        $json = $this->actingAs($this->host)->getJson(route('api.host.compliance'))->assertOk()->json();
        $this->assertSame('approved', $json['identity']['state']);
        $this->assertSame(self::EVENT, $json['events_needing_information'][0]['id']);
        $this->assertArrayNotHasKey('encrypted_storage_path', $json['identity']['record'] ?? []);
    }
}
