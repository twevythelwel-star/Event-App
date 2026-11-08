<?php

namespace Tests\Unit\Compliance;

use App\Http\Controllers\Admin\ComplianceExportController;
use App\Models\Compliance\CompliancePolicyVersion;
use App\Models\EventComplianceEvidence;
use App\Models\HostIdentityVerification;
use App\Support\LegalPages;
use Tests\TestCase;

/**
 * Unit rules for documents and policies: expiry, approval validity, who a policy version applies to, the legal
 * page text, and CSV safety. Uses the framework for dates and config, but no database.
 */
class DocumentAndPolicyRulesTest extends TestCase
{
    public function test_a_verified_document_past_its_expiry_counts_as_expired(): void
    {
        $evidence = new EventComplianceEvidence(['status' => 'verified', 'expires_on' => now()->subDay()->toDateString()]);
        $this->assertTrue($evidence->isExpired());
        $this->assertSame('expired', $evidence->checklistStatus());

        $today = new EventComplianceEvidence(['status' => 'verified', 'expires_on' => now()->toDateString()]);
        $this->assertFalse($today->isExpired(), 'valid until the end of its expiry day');
        $this->assertSame('verified', $today->checklistStatus());

        // Expiry never turns an unreviewed or rejected submission into anything else.
        $this->assertSame('rejected', (new EventComplianceEvidence(['status' => 'rejected', 'expires_on' => now()->subYear()->toDateString()]))->checklistStatus());
    }

    public function test_an_approved_id_stops_counting_once_its_document_expires(): void
    {
        $valid = new HostIdentityVerification(['status' => 'approved', 'document_expires_at' => now()->addYear()->toDateString()]);
        $this->assertTrue($valid->isApprovedAndValid());
        $expired = new HostIdentityVerification(['status' => 'approved', 'document_expires_at' => now()->subDay()->toDateString()]);
        $this->assertFalse($expired->isApprovedAndValid());
        $this->assertFalse((new HostIdentityVerification(['status' => 'pending_review']))->isApprovedAndValid());
        $this->assertTrue((new HostIdentityVerification(['status' => 'approved']))->isApprovedAndValid(), 'no expiry date given');
    }

    public function test_policy_versions_know_who_they_apply_to_and_when_they_are_in_force(): void
    {
        $hostOnly = new CompliancePolicyVersion(['policy_group' => 'host', 'applicable_roles' => null, 'applicable_jurisdictions' => ['fl', 'ga']]);
        $this->assertSame(['event_host'], $hostOnly->roles());
        $this->assertSame(['FL', 'GA'], $hostOnly->jurisdictions());
        $everyone = new CompliancePolicyVersion(['policy_group' => 'account', 'applicable_roles' => ['all']]);
        $this->assertSame([], $everyone->jurisdictions());

        $published = new CompliancePolicyVersion(['status' => 'published', 'effective_at' => now()->subDay()]);
        $this->assertTrue($published->isInForce());
        $this->assertFalse((new CompliancePolicyVersion(['status' => 'published', 'effective_at' => now()->addDay()]))->isInForce());
        $this->assertFalse((new CompliancePolicyVersion(['status' => 'draft']))->isInForce());
    }

    public function test_legal_text_is_parsed_into_headings_and_paragraphs_and_never_limits_obligations(): void
    {
        $blocks = LegalPages::blocks("## Heading\nFirst paragraph.\n\n  Second paragraph.  ");
        $this->assertSame([['type' => 'heading', 'text' => 'Heading'], ['type' => 'paragraph', 'text' => 'First paragraph.'], ['type' => 'paragraph', 'text' => 'Second paragraph.']], $blocks);
        $this->assertStringContainsString('not legal advice', LegalPages::NOT_LEGAL_ADVICE);
        $this->assertStringContainsString('does not guarantee', LegalPages::NO_GUARANTEE);
        foreach (LegalPages::originals() as $name => $policy) {
            foreach (['not liable', 'no liability', 'hold harmless', 'indemnif', 'waive'] as $phrase) {
                $this->assertStringNotContainsStringIgnoringCase($phrase, $policy['body'], "{$name} contains \"{$phrase}\"");
            }
        }
    }

    public function test_csv_exports_neutralise_spreadsheet_formulas(): void
    {
        foreach (['=SUM(A1)', '+1', '-2', '@cmd', "\tx"] as $value) {
            $this->assertSame("'" . $value, ComplianceExportController::safe($value));
        }
        $this->assertSame('FP-100', ComplianceExportController::safe('FP-100'));
        $this->assertSame('', ComplianceExportController::safe(null));
    }
}
