{{--
    One meal package the guest is building (Method A). $package is the package menu item, $entry the saved cart entry
    (or null), $index this card's position in packages[]. Every option carries its price so the summary updates live;
    the server prices it again (MealPackage::price) before the order is placed.
--}}
@php
    $config = (array) ($package->package_config ?? []);
    $selection = (array) ($entry['selection'] ?? []);
    $field = fn (string $key) => "packages[{$index}][{$key}]";
    $fmt = fn (int $m) => \App\Support\Money::formatMinor($m, $currency);
    $soldOut = $package->available_stock !== null && (int) $package->available_stock <= 0;
    $cap = $package->max_per_order ? min(20, (int) $package->max_per_order) : 20;
    $max = $package->available_stock === null ? $cap : max(0, min($cap, (int) $package->available_stock));
    $initQty = (int) ($entry['quantity'] ?? 0);
@endphp
<div data-package-card data-index="{{ $index }}" data-name="{{ $package->name }}" data-base-minor="{{ $package->priceMinorIn($currency) }}">
    <x-food-card :item="$package" :currency="$currency" layout="row" :class="$initQty > 0 ? 'is-selected' : ''">
        <div class="eb-stack" style="gap: var(--space-3); width: 100%;">
            <input type="hidden" name="{{ $field('menu_item_id') }}" value="{{ $package->id }}">

            <x-food-item-options
                :item="$package"
                mode="package"
                :index="$index"
                :entry="$entry"
                :menu-by-id="$menuById"
                :currency="$currency"
                :sold-out="$soldOut" />

            <x-food-addons
                :add-ons="$config['add_ons'] ?? []"
                :selection="$selection['add_ons'] ?? []"
                :index="$index"
                :menu-by-id="$menuById"
                :currency="$currency"
                :sold-out="$soldOut" />

            @if($package->allow_special_instructions)
                <div class="eb-customization-block">
                    <label class="eb-text-label" style="width: 100%; display: block;">
                        <span class="eb-customization-title" style="margin-bottom: 4px; display: inline-flex;">
                            <x-icon name="edit" :size="14" />
                            Special instructions <span class="eb-help-text">(optional)</span>
                        </span>
                        <input type="text" class="eb-input" maxlength="300" name="{{ $field('instructions') }}"
                               value="{{ $entry['instructions'] ?? '' }}" placeholder="e.g. Sauce on the side, extra napkins"
                               style="width: 100%; margin-top: 2px;" @disabled($soldOut)>
                    </label>
                </div>
            @endif

            <div class="eb-food-action-bar">
                <div class="eb-food-unit-readout">
                    <span class="eb-text-supporting">Each: <strong class="eb-food-unit-price" data-package-unit>{{ $fmt($package->priceMinorIn($currency)) }}</strong></span>
                    @if($package->max_per_order)
                        <span class="eb-help-text">· Limit {{ $package->max_per_order }} per customer</span>
                    @endif
                </div>
                <x-quantity-control
                    :name="$field('quantity')"
                    :value="$initQty"
                    :max="$max"
                    :disabled="$soldOut"
                    :label="'Quantity of ' . $package->name"
                    :id="'pkg-qty-' . $index"
                    data-line-qty />
            </div>
        </div>
    </x-food-card>
</div>
