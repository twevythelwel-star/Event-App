<?php

declare(strict_types=1);

namespace App\Services\Menu;

use App\Models\Event;
use App\Models\MenuItem;
use App\Services\OrderPlacementException;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * A host's meal package (a menu item with item_type "package"), built from the event's own menu items.
 *
 * package_config:
 * - components: items included in every package, each with a quantity and optional substitutes
 *   [{menu_item_id, quantity, substitutes: [{menu_item_id, extra_minor}]}]
 * - choice_groups: "choose N" selections [{id, title, min, max, options: [{menu_item_id, extra_minor}]}]
 * - add_ons: optional paid extras [{menu_item_id, price_minor, max}]
 *
 * The package's own price covers everything included. Included items and the chosen options add nothing unless
 * the host set an upgrade price, so an item is never charged twice. Every part is a real menu item, so each can be
 * prepared and handed over on its own and its stock is reserved.
 */
final class MealPackage
{
    /**
     * What one package costs with the guest's selection, and the parts it is made of.
     *
     * @param array{substitutions?: array<int|string, string>, choices?: array<string, list<string>|string>, add_ons?: array<string, int|string>} $selection
     * @param Collection<string, MenuItem> $menuById the event's menu items, keyed by id
     * @return array{unit_minor: int, base_minor: int, extras_minor: int, summary: list<string>, parts: list<array{item: MenuItem, quantity: int, label: string, role: string}>}
     */
    public static function price(MenuItem $package, array $selection, string $currency, Collection $menuById, ?\DateTimeInterface $now = null): array
    {
        $config = is_array($package->package_config) ? $package->package_config : [];
        // Read the way checkout reads every item's price, in the order's currency.
        $base = Money::ofMinor($package->priceMinorIn($currency), $currency);
        $extras = 0;
        $summary = [];
        $parts = [];
        $item = function (string $id) use ($menuById, $package, $now): MenuItem {
            $found = $menuById->get($id);
            if (!$found || $found->item_type === 'package' || !$found->isAvailableAt($now)) {
                $name = $found?->name ?? 'An item';
                throw new OrderPlacementException("{$name} in '{$package->name}' is not available right now.");
            }

            return $found;
        };

        $substitutions = (array) ($selection['substitutions'] ?? []);
        foreach (array_values((array) ($config['components'] ?? [])) as $index => $component) {
            $quantity = max(1, (int) ($component['quantity'] ?? 1));
            $chosenId = (string) ($substitutions[$index] ?? $substitutions[(string) $index] ?? '');
            $original = (string) ($component['menu_item_id'] ?? '');
            if ($chosenId !== '' && $chosenId !== $original) {
                $swap = collect((array) ($component['substitutes'] ?? []))->firstWhere('menu_item_id', $chosenId);
                if (!$swap) {
                    throw new OrderPlacementException("That substitution isn't offered for '{$package->name}'.");
                }
                $part = $item($chosenId);
                $extras += max(0, (int) ($swap['extra_minor'] ?? 0));
                $summary[] = self::qty($quantity) . $part->name . ' (instead of ' . ($menuById->get($original)?->name ?? 'the original') . ')';
                $parts[] = ['item' => $part, 'quantity' => $quantity, 'label' => $part->name, 'role' => 'substitute'];
                continue;
            }
            $part = $item($original);
            $summary[] = self::qty($quantity) . $part->name;
            $parts[] = ['item' => $part, 'quantity' => $quantity, 'label' => $part->name, 'role' => 'included'];
        }

        $choices = (array) ($selection['choices'] ?? []);
        foreach ((array) ($config['choice_groups'] ?? []) as $group) {
            $groupId = (string) ($group['id'] ?? '');
            $title = (string) ($group['title'] ?? 'Choice');
            $picked = array_values(array_unique(array_filter(array_map('strval', (array) ($choices[$groupId] ?? [])), fn ($v) => $v !== '')));
            $min = max(0, (int) ($group['min'] ?? 1));
            $max = max(1, (int) ($group['max'] ?? 1));
            if (count($picked) < $min) {
                throw new OrderPlacementException("Choose " . ($min === 1 ? 'one' : $min) . " for {$title} in '{$package->name}'.");
            }
            if (count($picked) > $max) {
                throw new OrderPlacementException("Choose at most {$max} for {$title} in '{$package->name}'.");
            }
            foreach ($picked as $id) {
                $option = collect((array) ($group['options'] ?? []))->firstWhere('menu_item_id', $id);
                if (!$option) {
                    throw new OrderPlacementException("That choice isn't offered for {$title} in '{$package->name}'.");
                }
                $part = $item($id);
                $extras += max(0, (int) ($option['extra_minor'] ?? 0));
                $summary[] = "{$title}: {$part->name}";
                $parts[] = ['item' => $part, 'quantity' => 1, 'label' => $part->name, 'role' => 'choice'];
            }
        }

        $addOns = (array) ($selection['add_ons'] ?? []);
        foreach ((array) ($config['add_ons'] ?? []) as $addOn) {
            $id = (string) ($addOn['menu_item_id'] ?? '');
            $quantity = (int) ($addOns[$id] ?? 0);
            if ($quantity <= 0) {
                continue;
            }
            $max = max(1, (int) ($addOn['max'] ?? 5));
            if ($quantity > $max) {
                throw new OrderPlacementException("At most {$max} extra " . ($menuById->get($id)?->name ?? 'portions') . " per '{$package->name}'.");
            }
            $part = $item($id);
            $extras += max(0, (int) ($addOn['price_minor'] ?? 0)) * $quantity;
            $summary[] = 'Extra: ' . self::qty($quantity) . $part->name;
            $parts[] = ['item' => $part, 'quantity' => $quantity, 'label' => 'Extra ' . $part->name, 'role' => 'add_on'];
        }
        foreach (array_keys($addOns) as $id) {
            if ((int) $addOns[$id] > 0 && !collect((array) ($config['add_ons'] ?? []))->contains('menu_item_id', (string) $id)) {
                throw new OrderPlacementException("That extra isn't offered with '{$package->name}'.");
            }
        }

        if ($parts === []) {
            throw new OrderPlacementException("'{$package->name}' has nothing in it yet.");
        }

        return [
            'unit_minor' => $base->minor() + $extras,
            'base_minor' => $base->minor(),
            'extras_minor' => $extras,
            'summary' => $summary,
            'parts' => $parts,
        ];
    }

    /**
     * A host's package definition, checked and cleaned: every part must be an active single item on the same
     * event, quantities and prices whole and non-negative, at least one included item or choice.
     *
     * @param array $config raw builder input: components, choice_groups, add_ons (prices in major units)
     * @return array the stored package_config (prices in minor units)
     */
    public static function normalizeConfig(array $config, Event $event): array
    {
        $currency = (string) ($event->currency ?: \App\Support\Currency::defaultCode());
        $items = MenuItem::withoutGlobalScopes()->where('event_id', $event->id)->where('item_type', 'item')->whereNull('deleted_at')
            ->pluck('name', 'id');
        $check = function (?string $id, string $field) use ($items): string {
            $id = (string) $id;
            if ($id === '' || !$items->has($id)) {
                throw ValidationException::withMessages([$field => 'Choose an item from this event\'s menu.']);
            }

            return $id;
        };
        $minor = fn ($value) => ($value === null || $value === '') ? 0 : Money::parse((string) $value, $currency)->minor();

        $components = [];
        foreach (array_values((array) ($config['components'] ?? [])) as $i => $component) {
            if (($component['menu_item_id'] ?? '') === '') {
                continue;
            }
            $substitutes = [];
            foreach (array_values((array) ($component['substitutes'] ?? [])) as $j => $sub) {
                if (($sub['menu_item_id'] ?? '') === '') {
                    continue;
                }
                $substitutes[] = ['menu_item_id' => $check($sub['menu_item_id'], "package_config.components.{$i}.substitutes.{$j}.menu_item_id"), 'extra_minor' => max(0, $minor($sub['extra'] ?? $sub['extra_price'] ?? 0))];
            }
            $components[] = [
                'menu_item_id' => $check($component['menu_item_id'], "package_config.components.{$i}.menu_item_id"),
                'quantity' => max(1, min(20, (int) ($component['quantity'] ?? 1))),
                'substitutes' => $substitutes,
            ];
        }

        $groups = [];
        foreach (array_values((array) ($config['choice_groups'] ?? [])) as $i => $group) {
            $options = [];
            foreach (array_values((array) ($group['options'] ?? [])) as $j => $option) {
                if (($option['menu_item_id'] ?? '') === '') {
                    continue;
                }
                $options[] = ['menu_item_id' => $check($option['menu_item_id'], "package_config.choice_groups.{$i}.options.{$j}.menu_item_id"), 'extra_minor' => max(0, $minor($option['extra'] ?? $option['extra_price'] ?? 0))];
            }
            if ($options === []) {
                continue;
            }
            $max = max(1, min(count($options), (int) ($group['max'] ?? 1)));
            $groups[] = [
                'id' => (string) ($group['id'] ?? '') ?: 'g' . ($i + 1),
                'title' => trim((string) ($group['title'] ?? '')) ?: 'Choose',
                'min' => max(0, min($max, (int) ($group['min'] ?? 1))),
                'max' => $max,
                'options' => $options,
            ];
        }

        $addOns = [];
        foreach (array_values((array) ($config['add_ons'] ?? [])) as $i => $addOn) {
            if (($addOn['menu_item_id'] ?? '') === '') {
                continue;
            }
            $addOns[] = [
                'menu_item_id' => $check($addOn['menu_item_id'], "package_config.add_ons.{$i}.menu_item_id"),
                'price_minor' => max(0, $minor($addOn['price'] ?? 0)),
                'max' => max(1, min(20, (int) ($addOn['max'] ?? 5))),
            ];
        }

        if ($components === [] && $groups === []) {
            throw ValidationException::withMessages(['package_config' => 'Add at least one included item or one choice to the package.']);
        }

        return ['components' => $components, 'choice_groups' => $groups, 'add_ons' => $addOns];
    }

    private static function qty(int $quantity): string
    {
        return $quantity > 1 ? "{$quantity} × " : '';
    }
}
