<?php

namespace Tests\Unit\Compliance;

use App\Models\Compliance\ComplianceEventRule;
use App\Services\Compliance\EventRequirementChecklist;
use App\Services\Compliance\JurisdictionResolver;
use PHPUnit\Framework\TestCase;

/**
 * Pure logic behind jurisdiction-specific requirements: what an event's form says it does (facts), which rules
 * match those facts, and how place names are normalised. No database, no framework.
 */
class RequirementRulesTest extends TestCase
{
    private function facts(array $in = []): array
    {
        return EventRequirementChecklist::facts($in + ['state_code' => 'FL', 'compliance_jurisdiction_county' => 'Miami-Dade County', 'city' => 'Miami']);
    }

    private function rule(array $conditions): ComplianceEventRule
    {
        return new ComplianceEventRule(['conditions' => $conditions]);
    }

    public function test_food_and_alcohol_modes_come_from_the_declarations_or_the_legacy_switches(): void
    {
        $this->assertSame('none', $this->facts()['food_mode']);
        $this->assertSame('none', $this->facts()['alcohol_mode']);
        $this->assertSame('sold', $this->facts(['supports_food_ordering' => '1'])['food_mode']);
        $this->assertSame('complimentary', $this->facts(['food_service_mode' => 'complimentary', 'supports_food_ordering' => '1'])['food_mode']);
        $this->assertSame('byob', $this->facts(['alcohol_service_mode' => 'byob'])['alcohol_mode']);
        // An unknown mode is never trusted: it falls back to what the switches say.
        $this->assertSame('none', $this->facts(['alcohol_service_mode' => 'free-for-all'])['alcohol_mode']);
    }

    public function test_preparation_and_service_locations_default_to_the_event_location(): void
    {
        $facts = $this->facts();
        $this->assertSame(['FL', 'Miami-Dade County', 'Miami'], $facts['locations']['event']);
        $this->assertSame($facts['locations']['event'], $facts['locations']['food_preparation']);

        $elsewhere = $this->facts(['food_prepared_elsewhere' => '1', 'food_prep_county' => 'Broward County', 'food_prep_city' => 'Fort Lauderdale']);
        $this->assertSame(['FL', 'Broward County', 'Fort Lauderdale'], $elsewhere['locations']['food_preparation']);
        $this->assertSame(['FL', 'Miami-Dade County', 'Miami'], $elsewhere['locations']['alcohol_service']);
    }

    public function test_rules_match_only_the_events_they_describe(): void
    {
        $foodSold = $this->rule(['food_modes' => ['sold', 'both']]);
        $this->assertTrue($foodSold->matches($this->facts(['food_service_mode' => 'sold'])));
        $this->assertFalse($foodSold->matches($this->facts(['food_service_mode' => 'none'])));
        $this->assertFalse($foodSold->matches($this->facts(['food_service_mode' => 'complimentary'])));

        $alcoholSold = $this->rule(['alcohol_modes' => ['sold'], 'alcohol_vendor' => false]);
        $this->assertTrue($alcoholSold->matches($this->facts(['alcohol_service_mode' => 'sold'])));
        // A licensed vendor serving it takes the event out of this rule.
        $this->assertFalse($alcoholSold->matches($this->facts(['alcohol_service_mode' => 'sold', 'uses_licensed_alcohol_vendor' => '1'])));
        $this->assertFalse($alcoholSold->matches($this->facts(['alcohol_service_mode' => 'none'])));

        // No conditions: the rule applies to every event.
        $this->assertTrue($this->rule([])->matches($this->facts()));
    }

    public function test_place_names_are_normalised_so_records_and_forms_agree(): void
    {
        $this->assertSame('miami', JurisdictionResolver::normalise('City of Miami'));
        $this->assertSame('miami', JurisdictionResolver::normalise('Miami city'));
        $this->assertSame('miami-dade', JurisdictionResolver::normalise('Miami-Dade County'));
        $this->assertSame('county:fl/miami-dade', JurisdictionResolver::key('county', 'FL', 'Miami-Dade County'));
        $this->assertNotSame(JurisdictionResolver::key('county', 'FL', 'Orange County'), JurisdictionResolver::key('county', 'CA', 'Orange County'));
    }
}
