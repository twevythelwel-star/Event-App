<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\Compliance\ComplianceJurisdiction;

/**
 * Finds a location's chain in the compliance database: United States → state → county → city or municipality.
 * Names are matched loosely ("City of Miami" finds the Census place "Miami city", "Miami-Dade" finds
 * "Miami-Dade County"). A level the database does not have yet is reported as missing, never guessed.
 */
final class JurisdictionResolver
{
    /**
     * @return array{chain: list<ComplianceJurisdiction>, missing: list<array{type: string, name: string, key: string}>, label: string}
     */
    public function resolve(?string $stateCode, ?string $county, ?string $city): array
    {
        $stateCode = strtoupper(trim((string) $stateCode));
        $county = trim((string) $county);
        $city = trim((string) $city);
        $chain = [];
        $missing = [];

        $country = ComplianceJurisdiction::where('type', 'country')->where('code', 'US')->first();
        if ($country) {
            $chain[] = $country;
        }
        $state = $stateCode !== '' ? ComplianceJurisdiction::where('type', 'state')->where('code', $stateCode)->first() : null;
        if ($stateCode !== '' && !$state) {
            $missing[] = ['type' => 'state', 'name' => $stateCode, 'key' => self::key('state', $stateCode, $stateCode)];
        }
        if ($state) {
            $chain[] = $state;
            $countyNode = null;
            if ($county !== '') {
                $countyNode = $this->findChild($state->id, 'county', $county, $stateCode);
                $countyNode ? $chain[] = $countyNode : $missing[] = ['type' => 'county', 'name' => $county, 'key' => self::key('county', $stateCode, $county)];
            }
            if ($city !== '') {
                $place = ($countyNode ? $this->findChild($countyNode->id, 'municipality', $city, $stateCode) : null)
                    ?? $this->findInState('municipality', $city, $stateCode);
                $place ? $chain[] = $place : $missing[] = ['type' => 'municipality', 'name' => $city, 'key' => self::key('municipality', $stateCode, $city)];
            }
        }

        $names = array_merge(array_map(fn ($node) => $node->name, $chain), array_map(fn ($m) => $m['name'] . ' (not in the database yet)', $missing));

        return ['chain' => $chain, 'missing' => $missing, 'label' => implode(' → ', $names)];
    }

    /** A stable key for a jurisdiction level, unchanged when the place is later imported into the database. */
    public static function key(string $type, string $stateCode, string $name): string
    {
        return $type . ':' . strtolower($stateCode) . '/' . self::normalise($name);
    }

    public static function keyFor(ComplianceJurisdiction $node): string
    {
        return $node->type === 'country' ? 'country:us' : self::key($node->type, (string) $node->state_code, $node->type === 'state' ? (string) $node->code : $node->name);
    }

    /** "City of Miami", "Miami city" and "miami" all become "miami"; "Miami-Dade County" becomes "miami-dade". */
    public static function normalise(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/^(city|town|village|borough|township|municipality) of\s+/u', '', $name);
        $name = preg_replace('/\s+(county|parish|borough|city and borough|census area|municipality|city|town|village|township|cdp|consolidated government|metropolitan government)$/u', '', $name);

        return trim(preg_replace('/[^a-z0-9\-]+/u', ' ', $name));
    }

    private function findChild(int $parentId, string $type, string $name, string $stateCode): ?ComplianceJurisdiction
    {
        return $this->best(ComplianceJurisdiction::where('parent_id', $parentId)->where('type', $type)->get(), $name)
            ?? ($type === 'county' ? $this->findInState('county', $name, $stateCode) : null);
    }

    private function findInState(string $type, string $name, string $stateCode): ?ComplianceJurisdiction
    {
        $first = mb_substr(self::normalise($name), 0, 3);

        return $this->best(ComplianceJurisdiction::where('type', $type)->where('state_code', $stateCode)
            ->where('name', 'like', '%' . addcslashes($first, '%_') . '%')->get(), $name);
    }

    private function best($candidates, string $name): ?ComplianceJurisdiction
    {
        $target = self::normalise($name);

        return $candidates->first(fn ($node) => self::normalise($node->name) === $target);
    }
}
