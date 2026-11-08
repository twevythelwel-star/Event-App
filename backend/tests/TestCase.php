<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests must never reach real services (Twilio, mail APIs): any outgoing HTTP call
        // that a test has not faked fails loudly instead of sending a real message.
        Http::preventStrayRequests();
    }

    /**
     * A complete Event Compliance acknowledgement for a create or edit request: the applicable jurisdiction and
     * every declaration ticked, as a host does on the event form.
     */
    protected function eventComplianceFields(array $overrides = []): array
    {
        $ticked = array_fill_keys(\App\Services\Compliance\EventComplianceService::fieldNames(), '1');

        return array_merge(['compliance_jurisdiction_county' => 'Miami-Dade County', 'compliance_jurisdiction_municipality' => 'City of Miami'], $ticked, $overrides);
    }

    /**
     * The food and alcohol declarations a publishable event needs (EventPublicationGates): by default an event
     * with no alcohol and, when it sells food, the food permit status and the food safety confirmations.
     */
    protected function eventArrangementFields(bool $sellsFood = false, array $overrides = []): array
    {
        return array_merge([
            'food_service_mode' => $sellsFood ? 'sold' : 'none',
            'alcohol_service_mode' => 'none',
        ], $sellsFood ? [
            'food_permit_status' => 'licensed_caterer_or_venue',
            'allergen_disclosure_confirmed' => '1',
            'temperature_and_sanitation_confirmed' => '1',
        ] : [], $overrides);
    }

    /** Gives a test host what an approved host has: an approved ID verification and the host acknowledgements. */
    protected function approveHostForPublishing(\App\Models\User $host): void
    {
        \App\Models\HostIdentityVerification::firstOrCreate(['user_id' => $host->id], ['status' => 'approved', 'legal_full_name' => $host->name]);
        foreach (\App\Services\LegalComplianceService::hostResponsibilityPolicies() as $spec) {
            \App\Models\PolicyAcceptance::firstOrCreate(['user_id' => $host->id, 'policy_name' => $spec['policy_name'], 'policy_version' => $spec['version']],
                ['acceptance_status' => 'accepted', 'accepted_at_utc' => now(), 'context' => 'host_application']);
        }
    }
}
