<?php

namespace Database\Seeders;

use App\Models\TargetMarket;
use App\Models\UsState;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UsLocationSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = json_decode(
            file_get_contents(__DIR__ . '/data/us_locations.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        foreach ($catalog['states'] as [$code, $name, $timezone]) {
            UsState::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'default_timezone' => $timezone,
                    'is_state_or_dc' => !in_array($code, ['AS', 'GU', 'MP', 'PR', 'VI'], true),
                    'is_enabled' => true,
                ],
            );
        }

        foreach ($catalog['markets'] as $market) {
            $slug = Str::slug($market['city'] . '-' . $market['state']);
            $targetMarket = TargetMarket::where('slug', $slug)->first()
                ?? TargetMarket::where('metadata->seed_key', $slug)->first();
            $defaults = [
                    'state_code' => $market['state'],
                    'city' => $market['city'],
                    'default_timezone' => $market['timezone'],
                    'zip_codes' => $market['zip_codes'],
                    'metadata' => ['designation' => 'recommended_target_market', 'seed_key' => $slug],
                    'discovery_priority' => $market['priority'],
                    'is_featured' => $market['featured'],
                    'is_enabled' => true,
                ];

            if ($targetMarket) {
                if (!isset($targetMarket->metadata['seed_key'])) {
                    $targetMarket->metadata = array_merge($targetMarket->metadata ?? [], ['seed_key' => $slug]);
                    $targetMarket->save();
                }
            } else {
                TargetMarket::create(array_merge(['slug' => $slug], $defaults));
            }
        }
    }
}
