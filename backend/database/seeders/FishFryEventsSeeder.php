<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Organizer;
use App\Models\TargetMarket;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FishFryEventsSeeder extends Seeder
{
    /**
     * Seed a diverse, U.S.-based EventBite launch catalogue and its demo menus.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $this->call(UsLocationSeeder::class);

        $catalog = json_decode(
            file_get_contents(__DIR__ . '/data/us_cultural_events.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        foreach ($catalog as $record) {
            $event = $record['event'];
            $eventId = $event['id'];
            $organizerId = $event['organizer_id'];
            $menu = $record['menu'];
            $menuIds = array_column($menu, 'id');

            Organizer::updateOrCreate(['id' => $organizerId], [
                'name' => $event['organizer_name'],
                'slug' => Str::after($organizerId, 'org-'),
                'status' => 'active',
                'default_currency' => 'USD',
                'business_location' => 'US',
            ]);

            $event['advance_order_deadline'] = now()->addDays((int) $event['deadline_days']);
            unset($event['deadline_days']);
            $event['is_ordering_open'] = true;
            $event['status'] = 'published';
            $event['packaging_fee_jmd'] = 0;
            $event['is_flyer_public'] = true;
            $event['target_market_id'] = TargetMarket::where('state_code', $event['state_code'] ?? '')
                ->whereRaw('LOWER(city) = ?', [strtolower($event['city'] ?? '')])
                ->value('id');

            Event::updateOrCreate(['id' => $eventId], $event);

            MenuItem::withoutGlobalScopes()
                ->where('event_id', $eventId)
                ->whereNotIn('id', $menuIds)
                ->update(['is_active' => false]);

            foreach ($menu as $position => $item) {
                $menuItem = MenuItem::withTrashed()->updateOrCreate(['id' => $item['id']], [
                    'organizer_id' => $organizerId,
                    'event_id' => $eventId,
                    'name' => $item['name'],
                    'category' => $item['category'],
                    'description' => $item['description'] ?? null,
                    'pricing_type' => $item['pricingType'],
                    'price_usd' => $item['priceUSD'],
                    'price_jmd' => 0,
                    'image_url' => $item['imageUrl'] ?? null,
                    'custom_option_groups' => $item['customOptionGroups'] ?? null,
                    'custom_amount_config' => $item['customAmountConfig'] ?? null,
                    'allow_special_instructions' => $item['allowSpecialInstructions'] ?? true,
                    'available_stock' => $item['availableStock'] ?? null,
                    'sort_order' => $position,
                    'is_active' => true,
                ]);

                if ($menuItem->trashed()) {
                    $menuItem->restore();
                }
            }
        }
    }
}
