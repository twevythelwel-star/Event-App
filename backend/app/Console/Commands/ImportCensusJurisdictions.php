<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Compliance\ComplianceJurisdiction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Adds counties and cities/municipalities to the compliance database from the Census Bureau's official 2020
 * FIPS code files (pipe-delimited, with a header row):
 *
 *   counties: STATE|STATEFP|COUNTYFP|COUNTYNS|COUNTYNAME|CLASSFP|FUNCSTAT
 *   places:   STATE|STATEFP|PLACEFP|PLACENS|PLACENAME|TYPE|CLASSFP|FUNCSTAT|COUNTIES
 *
 * Only incorporated places become municipalities (census-designated places are statistical areas, not
 * governments). A municipality's parent is the first county the file lists for it. Rows for states not in the
 * database (territories) are skipped. Running it again adds only what is missing.
 */
class ImportCensusJurisdictions extends Command
{
    protected $signature = 'compliance:import-census
        {--counties= : Path to national_county2020.txt}
        {--places= : Path to national_place2020.txt}
        {--download : Fetch both files from the Census Bureau (URLs in config/compliance.php)}';

    protected $description = 'Add counties and municipalities to the compliance database from the Census Bureau FIPS code files';

    public function handle(): int
    {
        $states = ComplianceJurisdiction::where('type', 'state')->get()->keyBy('state_code');
        if ($states->isEmpty()) {
            $this->error('Seed the states first: php artisan db:seed --class=ComplianceDatabaseSeeder');

            return self::FAILURE;
        }

        $counties = $this->rows('counties', 'census_county_file_url');
        $places = $this->rows('places', 'census_place_file_url');
        if ($counties === null && $places === null) {
            $this->error('Give --counties and/or --places file paths, or --download.');

            return self::FAILURE;
        }

        if ($counties !== null) {
            $added = 0;
            DB::transaction(function () use ($counties, $states, &$added) {
                foreach ($counties as $row) {
                    $state = $states->get($row['STATE'] ?? '');
                    if (!$state || empty($row['COUNTYNAME'])) {
                        continue;
                    }
                    $county = ComplianceJurisdiction::firstOrCreate(
                        ['type' => 'county', 'code' => $row['STATEFP'] . $row['COUNTYFP']],
                        ['parent_id' => $state->id, 'name' => $row['COUNTYNAME'], 'state_code' => $state->state_code,
                            'census_class' => $row['CLASSFP'] ?? null, 'data_source' => 'census_2020']
                    );
                    $added += $county->wasRecentlyCreated ? 1 : 0;
                }
            });
            $this->info("Counties added: {$added} (of " . count($counties) . ' rows).');
        }

        if ($places !== null) {
            $countiesByName = ComplianceJurisdiction::where('type', 'county')->get()
                ->keyBy(fn ($c) => $c->state_code . '|' . mb_strtolower($c->name));
            $added = 0;
            $withoutCounty = 0;
            DB::transaction(function () use ($places, $states, $countiesByName, &$added, &$withoutCounty) {
                foreach ($places as $row) {
                    $state = $states->get($row['STATE'] ?? '');
                    if (!$state || ($row['TYPE'] ?? '') !== 'INCORPORATED PLACE' || empty($row['PLACENAME'])) {
                        continue;
                    }
                    $firstCounty = trim(preg_split('/~~~|,/', (string) ($row['COUNTIES'] ?? ''))[0] ?? '');
                    $parent = $countiesByName->get($state->state_code . '|' . mb_strtolower($firstCounty));
                    $withoutCounty += $parent ? 0 : 1;
                    $place = ComplianceJurisdiction::firstOrCreate(
                        ['type' => 'municipality', 'code' => $row['STATEFP'] . $row['PLACEFP']],
                        ['parent_id' => ($parent ?? $state)->id, 'name' => $row['PLACENAME'], 'state_code' => $state->state_code,
                            'census_class' => $row['CLASSFP'] ?? null, 'data_source' => 'census_2020']
                    );
                    $added += $place->wasRecentlyCreated ? 1 : 0;
                }
            });
            $this->info("Municipalities added: {$added}." . ($withoutCounty ? " {$withoutCounty} were placed directly under their state because their county was not found." : ''));
        }

        return self::SUCCESS;
    }

    /** @return list<array<string, string>>|null */
    private function rows(string $option, string $urlKey): ?array
    {
        if ($this->option('download')) {
            $url = (string) config("compliance.database.{$urlKey}");
            $this->line("Downloading {$url}");
            $text = Http::timeout(120)->get($url)->throw()->body();
        } elseif ($path = $this->option($option)) {
            if (!is_readable($path)) {
                throw new \RuntimeException("Cannot read {$path}");
            }
            $text = file_get_contents($path);
        } else {
            return null;
        }

        $lines = preg_split('/\r\n|\n|\r/', trim(mb_convert_encoding($text, 'UTF-8', 'UTF-8, ISO-8859-1')));
        $header = explode('|', array_shift($lines));
        $rows = [];
        foreach ($lines as $line) {
            $cells = explode('|', $line);
            if (count($cells) === count($header)) {
                $rows[] = array_combine($header, $cells);
            }
        }

        return $rows;
    }
}
