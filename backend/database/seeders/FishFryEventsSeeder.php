<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\HostPaymentPolicy;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Organizer;
use App\Models\TargetMarket;
use App\Services\AuditLogService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FishFryEventsSeeder extends Seeder
{
    /**
     * Seed the sample EventBite catalogue: 20 fictional demonstration events, one per target market,
     * with ticket tiers and demo menus. All content is labelled as sample content in the event description.
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

            $existing = Event::withoutGlobalScopes()->find($eventId);
            $orders = $existing ? Order::withoutGlobalScopes()->where('event_id', $eventId)->get(['currency']) : collect();
            $previousTitle = $existing?->title;

            // On an event that already has orders, a tier that is sold again keeps its remaining stock (it
            // reflects real reservations); everything else about the tier comes from the new definition, so
            // no stale price key survives. Tiers that are not in the new catalogue simply go off sale; the
            // orders that bought them keep their own snapshot of name and price.
            if ($orders->isNotEmpty()) {
                $event['ticket_tiers'] = $this->mergeTiers((array) ($existing->ticket_tiers ?? []), $event['ticket_tiers'] ?? []);
            }

            Event::updateOrCreate(['id' => $eventId], $event);

            // Old menu items are deactivated, never deleted, so historical order lines keep their references.
            $retired = MenuItem::withoutGlobalScopes()->where('event_id', $eventId)->whereNotIn('id', $menuIds)
                ->where('is_active', true)->pluck('name')->all();
            MenuItem::withoutGlobalScopes()
                ->where('event_id', $eventId)
                ->whereNotIn('id', $menuIds)
                ->update(['is_active' => false]);

            // Orders already placed are never rewritten: they keep their currency, prices and item names.
            // The event is flagged in the audit log so an administrator can check what customers were promised.
            if ($orders->isNotEmpty()) {
                $currencies = $orders->pluck('currency')->unique()->implode(', ');
                $note = "Sample event '{$eventId}' was updated from '{$previousTitle}' to '{$event['title']}' ({$event['currency']}) "
                    . "while it has {$orders->count()} existing order(s) in {$currencies}. Those orders were left unchanged."
                    . ($retired !== [] ? ' Menu items taken off sale: ' . implode(', ', $retired) . '.' : '')
                    . ' Review the customer-facing details before relying on this event.';
                AuditLogService::log(
                    action: 'event_review_required',
                    targetEntity: 'events',
                    targetId: $eventId,
                    details: $note,
                    organizerId: $organizerId,
                    eventId: $eventId,
                );
                Log::warning($note);
            }

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

        // East Orange demo: cash at pickup plus manually verified bank and Zelle transfers (placeholder details
        // only). The organiser verifies each transfer before the order is marked paid.
        HostPaymentPolicy::updateOrCreate(['organizer_id' => 'org-runaway-events'], [
            'enable_cash' => true,
            'bank_transfer_instructions' => 'DEMO ONLY: no real account. Configure a verified U.S. ACH recipient before accepting transfers.',
            'zelle_instructions' => 'DEMO ONLY: no real Zelle recipient. Send the exact amount shown with your order number in the memo, then enter your Zelle confirmation number on your ticket page.',
            'require_transfer_reference' => true,
            'require_sender_name' => true,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $current
     * @param array<int, array<string, mixed>> $replacement
     * @return array<int, array<string, mixed>>
     */
    private function mergeTiers(array $current, array $replacement): array
    {
        $remaining = [];
        foreach ($current as $tier) {
            foreach (['availableStock', 'available_stock', 'quantity_available', 'quantityAvailable'] as $key) {
                if (isset($tier['id'], $tier[$key])) {
                    $remaining[$tier['id']] = (int) $tier[$key];
                    break;
                }
            }
        }

        return array_map(function (array $tier) use ($remaining) {
            if (array_key_exists($tier['id'], $remaining)) {
                $tier['availableStock'] = $remaining[$tier['id']];
            }

            return $tier;
        }, $replacement);
    }
}
