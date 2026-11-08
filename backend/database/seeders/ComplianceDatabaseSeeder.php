<?php

namespace Database\Seeders;

use App\Models\Compliance\ComplianceAuthority;
use App\Models\Compliance\ComplianceJurisdiction;
use App\Models\Compliance\ComplianceRecordReview;
use App\Models\Compliance\ComplianceRequirement;
use Illuminate\Database\Seeder;

/**
 * The starting point of the compliance database (see docs/eventbite-compliance-database.md):
 *
 * - the United States, the 50 states and the District of Columbia;
 * - the federal authorities (FDA, USDA FSIS, TTB) and the two federal facts checked against official text;
 * - every state's alcohol authorities as listed by TTB, and its retail food agencies as listed in FDA's Food
 *   Code adoption report, both as drafts an administrator must confirm;
 * - a "Verification Required" requirement record for every topic in every state.
 *
 * Nothing here is marked verified: only an administrator can approve a record. Running it again only adds what
 * is missing and never overwrites a record an administrator has worked on. Counties and municipalities come from
 * the Census Bureau files (php artisan compliance:import-census).
 */
class ComplianceDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $dir = __DIR__ . '/data/compliance';
        $read = fn (string $file) => json_decode(file_get_contents("{$dir}/{$file}"), true, flags: JSON_THROW_ON_ERROR);

        $us = ComplianceJurisdiction::firstOrCreate(['type' => 'country', 'code' => 'US'], ['name' => 'United States', 'data_source' => 'seed']);

        $states = [];
        foreach (json_decode(file_get_contents(__DIR__ . '/data/us_locations.json'), true, flags: JSON_THROW_ON_ERROR)['states'] as [$code, $name]) {
            if (in_array($code, ['AS', 'GU', 'MP', 'PR', 'VI'], true)) {
                continue; // territories are outside this database for now
            }
            $states[$code] = ComplianceJurisdiction::firstOrCreate(['type' => 'state', 'code' => $code],
                ['parent_id' => $us->id, 'name' => $name, 'state_code' => $code, 'data_source' => 'seed']);
        }

        $this->seedFederal($us, $read('federal.json'));

        $food = $read('state_food_agencies_fda.json');
        foreach ($food['agencies'] as [$code, $agency, $edition, $oversight, $year]) {
            $adopted = str_starts_with($edition, 'None') ? 'has not adopted any version of the FDA Food Code' : "adopted the {$edition} FDA Food Code" . ($year ? ", effective {$year}" : '');
            $this->authority($states[$code], [
                'area' => str_contains($agency, 'Agriculture') ? 'agriculture' : 'food',
                'name' => $agency,
                'role' => "State retail food oversight: {$oversight}. FDA reports this agency {$adopted}.",
            ], [
                'status' => 'draft',
                'source_url' => $food['source_url'],
                'source_title' => $food['source_title'],
                'research_notes' => "Name, oversight and Food Code adoption from FDA's 2025 adoption report (data as of {$food['data_as_of']}), read {$food['retrieved']}. Add the agency's official website and the state's food code regulation, then submit for review.",
            ]);
        }

        $alcohol = $read('state_alcohol_authorities_ttb.json');
        foreach ($alcohol['authorities'] as [$code, $agency, $url]) {
            $this->authority($states[$code], [
                'area' => 'alcohol',
                'name' => $agency,
                'role' => 'Listed by TTB as an alcohol beverage authority for this state. Its licensing role still needs confirming.',
            ], [
                'website_url' => $url,
                'status' => 'draft',
                'source_url' => $alcohol['source_url'],
                'source_title' => $alcohol['source_title'],
                'research_notes' => "Copied from TTB's directory on {$alcohol['retrieved']}. Confirm the name, the website and the agency's role on the agency's own site: the directory can lag behind renamed agencies and moved websites, and some entries are county-level or tax agencies." . ($url === null ? ' TTB lists no website for this agency.' : ''),
            ]);
        }

        // Every topic for every state starts as "Verification Required"; nothing is assumed from another state.
        $foodByState = collect($food['agencies'])->groupBy(0);
        foreach ($states as $code => $state) {
            foreach (array_keys(ComplianceRequirement::TOPICS) as $topic) {
                $notes = match ($topic) {
                    'minimum_drinking_age' => 'Federal law conditions highway funds on a minimum age of 21 (23 U.S.C. 158), but the state sets its own age and exceptions. Confirm this state\'s statute.',
                    'food_establishment' => 'FDA reports: ' . $foodByState->get($code, collect())->map(fn ($row) => "{$row[1]} ({$row[3]}): FDA Food Code {$row[2]}" . ($row[4] ? ", effective {$row[4]}" : ''))->implode('; ') . '. Confirm the state\'s adopted rule and its citation.',
                    default => null,
                };
                $this->requirement($state, $topic, ['research_notes' => $notes]);
            }
        }
    }

    private function seedFederal(ComplianceJurisdiction $us, array $data): void
    {
        $byKey = [];
        foreach ($data['authorities'] as $row) {
            $byKey[$row['key']] = $this->authority($us, ['area' => $row['area'], 'name' => $row['name'], 'role' => $row['role']], [
                'website_url' => $row['website_url'],
                'legislation_url' => $row['legislation_url'],
                'legislation_title' => $row['legislation_title'],
                'status' => 'pending_review',
                'source_url' => $row['source_url'],
                'source_title' => $row['source_title'],
                'research_notes' => "Federal agency and links set during initial research on {$data['retrieved']}. Needs administrator approval.",
            ]);
        }
        foreach ($data['requirements'] as $row) {
            $this->requirement($us, $row['topic'], [
                'authority_id' => $row['authority'] ? $byKey[$row['authority']]->id : null,
                ...collect($row)->only(['status', 'value', 'summary', 'legislation_citation', 'source_url', 'source_title', 'research_notes'])->all(),
            ]);
        }
        foreach (array_keys(ComplianceRequirement::TOPICS) as $topic) {
            $this->requirement($us, $topic, []);
        }
    }

    private function authority(ComplianceJurisdiction $where, array $identity, array $values): ComplianceAuthority
    {
        $authority = ComplianceAuthority::firstOrCreate(['jurisdiction_id' => $where->id, 'name' => $identity['name']], $identity + $values);
        $this->history($authority, 'authority');

        return $authority;
    }

    private function requirement(ComplianceJurisdiction $where, string $topic, array $values): ComplianceRequirement
    {
        $requirement = ComplianceRequirement::firstOrCreate(['jurisdiction_id' => $where->id, 'topic' => $topic], $values + ['status' => 'verification_required']);
        $this->history($requirement, 'requirement');

        return $requirement;
    }

    private function history($record, string $type): void
    {
        if ($record->wasRecentlyCreated) {
            ComplianceRecordReview::create([
                'record_type' => $type, 'record_id' => $record->id, 'action' => 'created', 'to_status' => $record->status,
                'comment' => 'Created by the initial research seed.', 'snapshot' => $record->only($record->getFillable()),
            ]);
        }
    }
}
