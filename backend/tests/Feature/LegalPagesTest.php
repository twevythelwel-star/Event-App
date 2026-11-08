<?php

namespace Tests\Feature;

use App\Models\Compliance\CompliancePolicyVersion;
use App\Models\User;
use App\Services\Compliance\EventPublicationGates;
use App\Services\Compliance\HostComplianceOverview;
use App\Support\LegalPages;
use Database\Seeders\ComplianceDatabaseSeeder;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The user-facing legal and policy pages: every page public, versioned, marked as draft wording until legal review,
 * and carrying the "not legal advice" statement without limiting EventBite's obligations or vouching for hosts.
 */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class, ComplianceDatabaseSeeder::class]);
    }

    private function url(string $slug): string
    {
        return $slug === 'jurisdictions' ? route('legal.jurisdictions') : route('legal.show', $slug);
    }

    public function test_every_page_is_public_and_carries_the_disclaimers_and_draft_notice(): void
    {
        $this->assertCount(9, LegalPages::PAGES);
        foreach (LegalPages::PAGES as $slug => $page) {
            $html = $this->get($this->url($slug))->assertOk()
                ->assertSee($page['title'])
                ->assertSee('General information, not legal advice')
                ->assertSee(LegalPages::NOT_LEGAL_ADVICE)
                ->assertSee('Nothing on this page limits any legal obligation EventBite has.')
                ->assertSee('EventBite does not guarantee that any host, vendor or event complies with the law')
                ->assertSee('has not yet been reviewed by qualified US legal counsel')
                ->getContent();

            // No wording that tries to remove EventBite's obligations or vouch for a host's compliance.
            foreach (['not liable', 'no liability', 'hold harmless', 'waive', 'indemnif', 'guarantees compliance', 'is fully compliant', 'legally compliant host'] as $phrase) {
                $this->assertStringNotContainsStringIgnoringCase($phrase, strip_tags($html), "{$slug} contains \"{$phrase}\"");
            }
        }
        $this->get(route('legal.index'))->assertOk()->assertSee('Event Host Agreement')->assertSee('Privacy Policy');
        $this->get(route('legal.show', 'no-such-page'))->assertNotFound();
        // Registration links to the full policies it asks people to accept.
        $this->get(route('register'))->assertOk()->assertSee(route('legal.show', 'terms'), false)->assertSee(route('legal.show', 'privacy'), false);
    }

    public function test_the_draft_notice_goes_once_counsel_has_reviewed_the_wording(): void
    {
        config(['compliance.legal.counsel_reviewed' => true]);
        $this->get(route('legal.show', 'privacy'))->assertOk()
            ->assertDontSee('has not yet been reviewed by qualified US legal counsel')
            ->assertSee('General information, not legal advice');
    }

    public function test_pages_show_the_version_in_force_and_keep_earlier_versions_readable(): void
    {
        $this->get(route('legal.show', 'cancellations-refunds'))->assertOk()
            ->assertSee('data-policy-version="2026.11-v1"', false)->assertSee('Each event has its own refund policy');

        $root = User::create(['name' => 'Root', 'email' => 'root@example.test', 'password' => bcrypt('password'), 'status' => 'active', 'is_system_admin' => true]);
        \App\Models\UserRole::create(['user_id' => $root->id, 'role' => 'system_admin', 'is_active' => true, 'assigned_at' => now()]);
        $this->actingAs($root)->post(route('admin.console.compliance-centre.policies.store'), [
            'policy_name' => 'event_cancellation_refund_policy', 'version' => '2026.12-v2', 'title' => 'Event Cancellation and Refund Policy',
            'category' => 'payments_refunds', 'applicable_roles' => ['all'], 'requires_reacknowledgement' => '0',
            'body' => "## Each event has its own refund policy\nRefund requests close 48 hours before the event unless the host says otherwise.",
            'change_summary' => 'Clarified the default refund deadline.',
        ])->assertSessionHasNoErrors();
        $this->actingAs($root)->post(route('admin.console.compliance-centre.policies.publish', CompliancePolicyVersion::where('status', 'draft')->first()), ['confirm' => '1']);
        app()->forgetScopedInstances();
        auth()->logout();

        $this->get(route('legal.show', 'cancellations-refunds'))->assertOk()
            ->assertSee('data-policy-version="2026.12-v2"', false)->assertSee('Refund requests close 48 hours')->assertSee('Earlier versions');
        $this->get(route('legal.show', ['cancellations-refunds', 'version' => '2026.11-v1']))->assertOk()
            ->assertSee('This is an earlier version')->assertSee('Signed-in attendees can request a cancellation');
        $this->get(route('legal.show', ['cancellations-refunds', 'version' => 'nope']))->assertNotFound();
    }

    public function test_the_host_agreement_lists_the_acknowledgements_and_declarations_hosts_confirm(): void
    {
        $this->get(route('legal.show', 'host-agreement'))->assertOk()
            ->assertSee('Host responsibility acknowledgements')
            ->assertSee('Independent Legal &amp; Municipal Compliance', false)
            ->assertSee('Event Compliance declarations (confirmed for each event)')
            ->assertSee('Event Compliance: Alcohol');
    }

    public function test_jurisdiction_pages_show_agencies_and_never_present_unverified_requirements_as_fact(): void
    {
        $this->get(route('legal.jurisdictions'))->assertOk()->assertSee('Florida')->assertSee('one state\'s rules never apply in another', false);
        $this->get(route('legal.jurisdiction', 'fl'))->assertOk()
            ->assertSee('Florida: compliance information')
            ->assertSee('Division of Alcoholic Beverages &amp; Tobacco', false)
            ->assertSee('Verification Required')
            ->assertSee('EventBite has not verified this.');
        $this->get(route('legal.jurisdiction', 'zz'))->assertNotFound();
    }

    public function test_notices_are_linked_everywhere_but_never_block_anyone(): void
    {
        $this->get(route('legal.show', 'attendee-safety'))->assertSee('call 911 first');
        $host = User::where('email', 'host@islandevents.example')->firstOrFail();
        $this->actingAs($host)->get(route('host.compliance'))->assertOk()
            ->assertSee(route('legal.show', 'privacy'), false)->assertDontSee('Host Identity Verification and Privacy Notice</strong>', false);
        // The new notices are not mandatory: no host is blocked from publishing and nothing is outstanding.
        $this->assertSame([], EventPublicationGates::missingHostPolicies($host));
        $titles = array_column(app(HostComplianceOverview::class)->for($host)['policies'], 'title');
        $this->assertNotContains('Event Host Agreement', $titles);
    }
}
