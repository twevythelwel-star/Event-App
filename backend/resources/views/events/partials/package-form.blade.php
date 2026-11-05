{{--
    Package Builder form. A package is made from the event's own single items ($items): included items (each may
    allow swaps), "choose N" selections and paid extras. Prices are in dollars; included items add nothing to the
    package price unless a swap or choice has an upgrade price.
--}}
@php
    $config = (array) ($package?->package_config ?? []);
    $tz = $event->timezone ?: config('events.market.default_timezone', 'America/New_York');
    $local = fn ($instant) => $instant ? \Illuminate\Support\Carbon::parse($instant)->setTimezone($tz)->format('Y-m-d\TH:i') : '';
    $major = fn ($minor) => \App\Support\Money::ofMinor((int) $minor, $currency)->toDecimalString();
    $id = fn (string $key) => "{$formId}-{$key}";
    $components = $config['components'] ?? [];
    $groups = $config['choice_groups'] ?? [];
    $addOns = collect($config['add_ons'] ?? [])->keyBy('menu_item_id');
@endphp
<form method="POST" enctype="multipart/form-data" class="eb-stack" style="gap: var(--space-3); margin-top: var(--space-3);" data-package-form data-currency="{{ $currency }}"
      action="{{ $package ? route('events.menu-studio.update', [$event->id, $package->id]) : route('events.menu-studio.packages.store', $event->id) }}">
    @csrf
    @if($package) @method('PUT') @endif
    <input type="hidden" name="item_type_form" value="{{ $formId }}">
    <div class="eb-grid-3">
        <x-form-field name="name" :id="$id('name')" label="Package name" :value="$package?->name" :required="true" placeholder="Jamaican Sunday Dinner" />
        <x-form-field name="price" :id="$id('price')" type="number" label="Package price ($)" :value="$package ? $major($package->priceMinorIn($currency)) : ''" :min="0" :step="0.01" :required="true" />
        <div class="eb-form-group">
            <label class="eb-label" for="{{ $id('category') }}">Menu section</label>
            <select class="eb-select eb-input" id="{{ $id('category') }}" name="category">
                @foreach(collect(['Meal Packages'])->merge(\App\Models\MenuItem::CATEGORIES)->merge($package ? [$package->category] : [])->unique() as $category)
                    <option @selected(($package?->category ?? 'Meal Packages') === $category)>{{ $category }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <x-form-field name="description" :id="$id('description')" type="textarea" :rows="2" label="Description" :value="$package?->description" placeholder="A full plate of Sunday favourites" />
    <p class="eb-help-text" data-package-value role="status" aria-live="polite"></p>

    <fieldset class="eb-fieldset" data-repeat>
        <legend class="eb-text-label">Included items</legend>
        <div class="eb-stack" data-repeat-rows style="gap: var(--space-2);">
            @foreach($components as $c => $component)
                @include('events.partials.package-component-row', ['n' => $c, 'component' => $component])
            @endforeach
        </div>
        <template>@include('events.partials.package-component-row', ['n' => '__N__', 'component' => ['menu_item_id' => '', 'quantity' => 1, 'substitutes' => []]])</template>
        <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" data-repeat-add style="align-self: flex-start;">＋ Include an item</button>
    </fieldset>

    <fieldset class="eb-fieldset" data-repeat>
        <legend class="eb-text-label">Choices guests make</legend>
        <p class="eb-help-text" style="margin-top: 0;">For example "Choice of two main dishes": tick the items guests choose from and set how many they pick.</p>
        <div class="eb-stack" data-repeat-rows style="gap: var(--space-2);">
            @foreach($groups as $g => $group)
                @include('events.partials.package-choice-row', ['n' => $g, 'group' => $group])
            @endforeach
        </div>
        <template>@include('events.partials.package-choice-row', ['n' => '__N__', 'group' => ['title' => '', 'min' => 1, 'max' => 1, 'options' => []]])</template>
        <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" data-repeat-add style="align-self: flex-start;">＋ Add a choice</button>
    </fieldset>

    <fieldset class="eb-fieldset">
        <legend class="eb-text-label">Optional extras</legend>
        <p class="eb-help-text" style="margin-top: 0;">Paid additions, like extra plantain. Each extra is charged per portion on top of the package price.</p>
        <div class="eb-stack" style="gap: var(--space-1);">
            @foreach($items as $menuItem)
                @php $addOn = $addOns->get($menuItem->id); @endphp
                <div class="eb-package-option">
                    <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="add_ons[{{ $menuItem->id }}][on]" value="1" @checked($addOn)> <span>{{ $menuItem->name }}</span></label>
                    <label class="eb-help-text">Price $ <input class="eb-input eb-input-inline" type="number" min="0" step="0.01" name="add_ons[{{ $menuItem->id }}][price]" value="{{ $addOn ? $major($addOn['price_minor']) : $major($menuItem->priceMinorIn($currency)) }}"></label>
                    <label class="eb-help-text">Most <input class="eb-input eb-input-inline" type="number" min="1" max="20" name="add_ons[{{ $menuItem->id }}][max]" value="{{ $addOn['max'] ?? 3 }}"></label>
                </div>
            @endforeach
        </div>
    </fieldset>

    <div class="eb-grid-4">
        <x-form-field name="available_stock" :id="$id('stock')" type="number" label="Packages available" :value="$package?->available_stock" :min="0" help="Empty for no limit; item stock is also checked." />
        <x-form-field name="max_per_order" :id="$id('max')" type="number" label="Limit per customer" :value="$package?->max_per_order" :min="1" />
        <x-form-field name="available_from" :id="$id('from')" type="datetime-local" label="Available from" :value="$local($package?->available_from)" />
        <x-form-field name="available_until" :id="$id('until')" type="datetime-local" label="Available until" :value="$local($package?->available_until)" />
    </div>
    <div class="eb-cluster" style="gap: var(--space-4); flex-wrap: wrap;">
        <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="allow_special_instructions" value="1" @checked($package?->allow_special_instructions)> <span>Guests may add special instructions</span></label>
        <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="is_featured" value="1" @checked($package?->is_featured)> <span>Feature at the top of the menu</span></label>
        <input type="hidden" name="is_active" value="0">
        <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="is_active" value="1" @checked($package ? $package->is_active : true)> <span>On the menu</span></label>
    </div>

    @include('events.partials.menu-photo-field', ['subject' => $package])

    <div><button type="submit" class="eb-btn eb-btn-primary">{{ $package ? 'Save package' : 'Create package' }}</button></div>
</form>
