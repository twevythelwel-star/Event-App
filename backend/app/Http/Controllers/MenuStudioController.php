<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\MenuItem;
use App\Services\EventManagementService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The host's menu studio for one event: Menu Builder (single items), Package Builder (meal packages made from
 * those items) and Attendee Preview (the guest's food page, exactly as checkout renders it).
 *
 * Every change goes through EventManagementService, which scopes the event to organizers the host manages, checks
 * packages against the event's own items, and audits the change.
 */
class MenuStudioController extends Controller
{
    public function __construct(private readonly EventManagementService $events)
    {
    }

    public function show(Request $request, string $id): View
    {
        $event = $this->events->findEvent($request->user(), $id);
        $all = MenuItem::withoutGlobalScopes()->where('event_id', $event->id)->whereNull('deleted_at')
            ->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('name')->get();
        $items = $all->reject(fn (MenuItem $item) => $item->isPackage())->values();
        $currency = $event->currency ?: 'USD';
        $orderable = $all->filter(fn (MenuItem $item) => $item->isAvailableAt());

        return view('events.menu-studio', [
            'event' => $event,
            'items' => $items,
            'packages' => $all->filter(fn (MenuItem $item) => $item->isPackage())->values(),
            'currency' => $currency,
            'traditions' => (array) config('traditions', []),
            'tab' => in_array($request->query('tab'), ['menu', 'packages', 'preview'], true) ? $request->query('tab') : 'menu',
            // The attendee preview renders the checkout builder itself, with an empty cart.
            'preview' => [
                'menu' => $orderable->reject(fn (MenuItem $item) => $item->isPackage())->values(),
                'packages' => $orderable->filter(fn (MenuItem $item) => $item->isPackage())->values(),
                'menuById' => $all->keyBy('id'),
                'windows' => $event->pickupWindows(),
                'cart' => [],
            ],
        ]);
    }

    public function storeItem(Request $request, string $id): RedirectResponse
    {
        $event = $this->events->findEvent($request->user(), $id);
        $data = $this->itemData($request, $event, 'item') + ['id' => (string) \Illuminate\Support\Str::uuid()];

        return $this->save($request, $event, fn () => $this->events->createMenuItem($request->user(), $event->id, $data), 'menu', "'{$data['name']}' added to the menu.", $data['id']);
    }

    public function storePackage(Request $request, string $id): RedirectResponse
    {
        $event = $this->events->findEvent($request->user(), $id);
        $data = $this->itemData($request, $event, 'package') + ['id' => (string) \Illuminate\Support\Str::uuid()];

        return $this->save($request, $event, fn () => $this->events->createMenuItem($request->user(), $event->id, $data), 'packages', "Package '{$data['name']}' created.", $data['id']);
    }

    public function update(Request $request, string $id, string $itemId): RedirectResponse
    {
        $event = $this->events->findEvent($request->user(), $id);
        $item = MenuItem::withoutGlobalScopes()->where('event_id', $event->id)->whereKey($itemId)->firstOrFail();
        $data = $this->itemData($request, $event, $item->isPackage() ? 'package' : 'item');

        return $this->save($request, $event, fn () => $this->events->updateMenuItem($request->user(), $event->id, $item->id, $data),
            $item->isPackage() ? 'packages' : 'menu', "'{$data['name']}' saved.", $item->id);
    }

    public function destroy(Request $request, string $id, string $itemId): RedirectResponse
    {
        $event = $this->events->findEvent($request->user(), $id);
        $item = MenuItem::withoutGlobalScopes()->where('event_id', $event->id)->whereKey($itemId)->firstOrFail();
        try {
            $this->events->deleteMenuItem($request->user(), $event->id, $item->id);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('events.menu-studio', ['id' => $event->id, 'tab' => $item->isPackage() ? 'packages' : 'menu'])
            ->with('status', "'{$item->name}' removed. Past orders keep it as it was.");
    }

    /**
     * The menu's order: a full list of ids (drag and drop) or one item moved up or down (the buttons, which also
     * work without JavaScript). Answers JSON to the script and redirects for a plain form post.
     */
    public function reorder(Request $request, string $id): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $event = $this->events->findEvent($request->user(), $id);
        $data = $request->validate([
            'ids' => ['nullable', 'array', 'max:200'],
            'ids.*' => ['string', 'max:64'],
            'move' => ['nullable', 'string', 'max:64'],
            'direction' => ['nullable', 'in:up,down'],
            'tab' => ['nullable', 'in:menu,packages'],
        ]);
        $current = MenuItem::withoutGlobalScopes()->where('event_id', $event->id)->whereNull('deleted_at')
            ->orderBy('sort_order')->orderBy('name')->pluck('id')->all();
        $ids = $data['ids'] ?? $current;
        if (!empty($data['move']) && ($at = array_search($data['move'], $ids, true)) !== false) {
            $to = ($data['direction'] ?? 'up') === 'up' ? $at - 1 : $at + 1;
            if ($to >= 0 && $to < count($ids)) {
                [$ids[$at], $ids[$to]] = [$ids[$to], $ids[$at]];
            }
        }
        $this->events->reorderMenu($request->user(), $event->id, $ids);

        if ($request->expectsJson()) {
            return response()->json(['saved' => true, 'message' => 'Menu order saved.']);
        }

        return redirect()->route('events.menu-studio', ['id' => $event->id, 'tab' => $data['tab'] ?? 'menu'])->with('status', 'Menu order saved.');
    }

    /** Runs a change, then stores, replaces or removes the photo, and returns to the right tab. */
    private function save(Request $request, Event $event, callable $change, string $tab, string $message, string $itemId): RedirectResponse
    {
        try {
            $change();
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        $done = redirect()->route('events.menu-studio', ['id' => $event->id, 'tab' => $tab]);
        try {
            if ($request->boolean('remove_photo')) {
                $this->events->removeMenuPhoto($request->user(), $event->id, $itemId);
            } elseif ($request->hasFile('photo')) {
                $this->events->uploadMenuPhoto($request->user(), $event->id, $itemId, $request->file('photo'));
            }
        } catch (\InvalidArgumentException $e) {
            // The item itself is saved; only the photo needs another try.
            return $done->with('error', rtrim($message, '.') . ', but the photo was not: ' . $e->getMessage());
        }

        return $done->with('status', $message);
    }

    /**
     * The builder form as menu item fields. Prices are typed in dollars (the event's currency) and stored exactly;
     * availability times are in the event's time zone.
     */
    private function itemData(Request $request, Event $event, string $type): array
    {
        $traditions = array_keys((array) config('traditions', []));
        $v = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'category' => ['required', 'string', 'max:80'],
            'cuisine' => ['nullable', 'string', Rule::in($traditions)],
            'description' => ['nullable', 'string', 'max:1000'],
            'ingredients' => ['nullable', 'string', 'max:1000'],
            'guest_instructions' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0', 'max:10000'],
            'available_stock' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'max_per_order' => ['nullable', 'integer', 'min:1', 'max:100'],
            'prep_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'available_from' => ['nullable', 'date'],
            'available_until' => ['nullable', 'date', 'after:available_from'],
            'dietary_tags' => ['nullable', 'array'],
            'dietary_tags.*' => ['string', Rule::in(array_keys(MenuItem::DIETARY_TAGS))],
            'allergens' => ['nullable', 'array'],
            'allergens.*' => ['string', Rule::in(array_keys(MenuItem::ALLERGENS))],
            'spice_level' => ['nullable', 'in:mild,medium,hot,extra_hot'],
            'allow_special_instructions' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'option_groups' => ['nullable', 'array', 'max:10'],
            'option_groups.*.title' => ['nullable', 'string', 'max:100'],
            'option_groups.*.mode' => ['nullable', 'in:required,optional,multi,multi_required'],
            'option_groups.*.max' => ['nullable', 'integer', 'min:1', 'max:20'],
            'option_groups.*.choices' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'remove_photo' => ['nullable', 'boolean'],
            'image_position' => ['nullable', Rule::in(['50% 50%', '50% 20%', '50% 80%', '20% 50%', '80% 50%'])],
            'image_is_illustrative' => ['nullable', 'boolean'],
            'components' => ['nullable', 'array', 'max:20'],
            'components.*.menu_item_id' => ['nullable', 'string', 'max:64'],
            'components.*.quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
            'components.*.substitutes' => ['nullable', 'array'],
            'choice_groups' => ['nullable', 'array', 'max:10'],
            'choice_groups.*.title' => ['nullable', 'string', 'max:100'],
            'choice_groups.*.min' => ['nullable', 'integer', 'min:0', 'max:20'],
            'choice_groups.*.max' => ['nullable', 'integer', 'min:1', 'max:20'],
            'choice_groups.*.options' => ['nullable', 'array'],
            'add_ons' => ['nullable', 'array'],
        ], [
            'photo.mimes' => 'Upload a JPG, PNG or WebP photo.',
            'photo.max' => 'Photos must be 5 MB or smaller.',
            'available_until.after' => 'The item must become unavailable after it becomes available.',
        ]);

        $currency = strtoupper($event->currency ?: 'USD');
        $tz = $event->timezone ?: config('events.market.default_timezone', 'America/New_York');
        $price = Money::parse((string) $v['price'], $currency);
        $local = fn ($value) => filled($value) ? Carbon::parse($value, $tz)->utc() : null;
        $data = [
            'item_type' => $type,
            'name' => trim($v['name']),
            'category' => trim($v['category']),
            'cuisine' => $v['cuisine'] ?? null,
            'description' => $v['description'] ?? null,
            'ingredients' => $v['ingredients'] ?? null,
            'guest_instructions' => $v['guest_instructions'] ?? null,
            'currency' => $currency,
            'price_usd' => $currency === 'USD' ? $price->toDecimalString() : 0,
            'price_jmd' => $currency === 'JMD' ? $price->toMajorNumber() : 0,
            'price_minor' => $price->minor(),
            'available_stock' => ($v['available_stock'] ?? null) === null ? null : (int) $v['available_stock'],
            'max_per_order' => $v['max_per_order'] ?? null,
            'prep_minutes' => $v['prep_minutes'] ?? null,
            'available_from' => $local($v['available_from'] ?? null),
            'available_until' => $local($v['available_until'] ?? null),
            'dietary_tags' => array_values($v['dietary_tags'] ?? []),
            'allergens' => array_values($v['allergens'] ?? []),
            'spice_level' => $v['spice_level'] ?? null,
            'allow_special_instructions' => $request->boolean('allow_special_instructions'),
            'is_featured' => $request->boolean('is_featured'),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
            'image_position' => $v['image_position'] ?? null,
            'image_is_illustrative' => $request->boolean('image_is_illustrative'),
        ];

        if ($type === 'package') {
            $data['package_config'] = [
                'components' => array_map(fn ($row) => [
                    'menu_item_id' => $row['menu_item_id'] ?? '',
                    'quantity' => $row['quantity'] ?? 1,
                    'substitutes' => $this->picked((array) ($row['substitutes'] ?? []), 'extra'),
                ], array_values((array) ($v['components'] ?? []))),
                'choice_groups' => array_values(array_filter(array_map(fn ($group, $i) => filled($group['title'] ?? null) ? [
                    'id' => 'g' . ($i + 1),
                    'title' => $group['title'],
                    'min' => $group['min'] ?? 1,
                    'max' => $group['max'] ?? 1,
                    'options' => $this->picked((array) ($group['options'] ?? []), 'extra'),
                ] : null, array_values((array) ($v['choice_groups'] ?? [])), array_keys(array_values((array) ($v['choice_groups'] ?? [])))))),
                'add_ons' => array_map(fn ($row) => ['menu_item_id' => $row['menu_item_id'], 'price' => $row['price'] ?? 0, 'max' => $row['max'] ?? 5],
                    $this->picked((array) ($v['add_ons'] ?? []), 'price', ['max'])),
            ];
            $data['pricing_type'] = 'fixed';
        } else {
            $groups = $this->optionGroups((array) ($v['option_groups'] ?? []), $currency);
            $data['custom_option_groups'] = $groups ?: null;
            $data['pricing_type'] = $groups ? 'customizable' : 'fixed';
        }

        return $data;
    }

    /**
     * Checkbox lists of the event's items ([id => [on, extra|price, max]]) as a list of the ticked ones.
     *
     * @return list<array{menu_item_id: string}>
     */
    private function picked(array $rows, string $priceKey, array $extraKeys = []): array
    {
        $out = [];
        foreach ($rows as $itemId => $row) {
            if (!is_array($row) || empty($row['on'])) {
                continue;
            }
            $entry = ['menu_item_id' => (string) $itemId, $priceKey => $row[$priceKey] ?? 0];
            foreach ($extraKeys as $key) {
                $entry[$key] = $row[$key] ?? null;
            }
            $out[] = $entry;
        }

        return $out;
    }

    /**
     * Selections typed one per line ("Label" or "Label, 2.50" for an upgrade price) as option groups.
     * Modes: required (exactly one), optional (none or one), multi (none up to max), multi_required (one up to max).
     */
    private function optionGroups(array $groups, string $currency): array
    {
        $out = [];
        foreach (array_values($groups) as $i => $group) {
            $title = trim((string) ($group['title'] ?? ''));
            $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($group['choices'] ?? '')))));
            if ($title === '' && $lines === []) {
                continue;
            }
            if ($title === '' || $lines === []) {
                throw ValidationException::withMessages(["option_groups.{$i}.title" => 'Give each selection a name and at least one choice.']);
            }
            $choices = [];
            foreach ($lines as $line) {
                [$label, $price] = array_pad(array_map('trim', preg_split('/\s*[,|]\s*(?=\$?\d+(?:\.\d{1,2})?$)/', $line, 2)), 2, '0');
                $extra = Money::parse(ltrim($price, '$'), $currency);
                $choices[] = ['label' => mb_substr($label, 0, 150), 'extraPriceUSD' => $currency === 'USD' ? $extra->toMajorNumber() : 0, 'extraPriceJMD' => $currency === 'JMD' ? $extra->toMajorNumber() : 0];
            }
            $mode = $group['mode'] ?? 'required';
            $max = in_array($mode, ['multi', 'multi_required'], true) ? max(1, min(count($choices), (int) ($group['max'] ?? count($choices)))) : 1;
            $out[] = [
                'id' => 'g' . ($i + 1),
                'title' => mb_substr($title, 0, 100),
                'required' => in_array($mode, ['required', 'multi_required'], true),
                'min' => in_array($mode, ['required', 'multi_required'], true) ? 1 : 0,
                'max' => $max,
                'choices' => $choices,
            ];
        }

        return $out;
    }
}
