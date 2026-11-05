{{--
    One meal package the guest is building (Method A). $package is the package menu item, $entry the saved cart entry
    (or null), $index this card's position in packages[]. Every option carries its price so the summary updates live;
    the server prices it again (MealPackage::price) before the order is placed.
--}}
@php
    $config = (array) ($package->package_config ?? []);
    $selection = (array) ($entry['selection'] ?? []);
    $field = fn (string $key) => "packages[{$index}][{$key}]";
    $name = fn ($id) => $menuById->get($id)?->name ?? 'Item';
    $fmt = fn (int $m) => \App\Support\Money::formatMinor($m, $currency);
    $soldOut = $package->available_stock !== null && (int) $package->available_stock <= 0;
    $cap = $package->max_per_order ? min(20, (int) $package->max_per_order) : 20;
    $max = $package->available_stock === null ? $cap : max(0, min($cap, (int) $package->available_stock));
@endphp
<div data-package-card data-index="{{ $index }}" data-name="{{ $package->name }}" data-base-minor="{{ $package->priceMinorIn($currency) }}">
    <x-food-card :item="$package" :currency="$currency" layout="row">
        <div class="eb-stack" style="gap: var(--space-3); width: 100%;">
            <input type="hidden" name="{{ $field('menu_item_id') }}" value="{{ $package->id }}">

            @if(!empty($config['components']))
                <div>
                    <p class="eb-text-label" style="margin: 0 0 4px;">Included</p>
                    <ul class="eb-package-includes">
                        @foreach($config['components'] as $c => $component)
                            <li>
                                @if(!empty($component['substitutes']))
                                    <label class="sr-only" for="pkg-{{ $index }}-sub-{{ $c }}">Swap {{ $name($component['menu_item_id']) }}</label>
                                    <select id="pkg-{{ $index }}-sub-{{ $c }}" name="packages[{{ $index }}][substitutions][{{ $c }}]" class="eb-select eb-input" data-priced @disabled($soldOut)>
                                        <option value="" data-extra-minor="0">{{ ($component['quantity'] ?? 1) > 1 ? $component['quantity'] . ' × ' : '' }}{{ $name($component['menu_item_id']) }}</option>
                                        @foreach($component['substitutes'] as $sub)
                                            <option value="{{ $sub['menu_item_id'] }}" data-extra-minor="{{ (int) $sub['extra_minor'] }}" @selected(($selection['substitutions'][$c] ?? '') === $sub['menu_item_id'])>
                                                Swap for {{ $name($sub['menu_item_id']) }}{{ $sub['extra_minor'] > 0 ? ' (+' . $fmt((int) $sub['extra_minor']) . ')' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <span>{{ ($component['quantity'] ?? 1) > 1 ? $component['quantity'] . ' × ' : '' }}{{ $name($component['menu_item_id']) }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @foreach($config['choice_groups'] ?? [] as $group)
                @php $picked = (array) ($selection['choices'][$group['id']] ?? []); $single = (int) $group['max'] === 1; @endphp
                <fieldset class="eb-food-options" @unless($single) data-max="{{ (int) $group['max'] }}" @endunless>
                    <legend class="eb-text-label">{{ $group['title'] }}
                        <span class="eb-help-text">({{ $group['min'] > 0 ? ($group['min'] === $group['max'] ? 'choose ' . $group['max'] : 'choose ' . $group['min'] . ' to ' . $group['max']) : 'optional, up to ' . $group['max'] }})</span>
                    </legend>
                    @foreach($group['options'] as $option)
                        <label class="eb-choice-label">
                            <input type="{{ $single ? 'radio' : 'checkbox' }}" name="packages[{{ $index }}][choices][{{ $group['id'] }}][]" value="{{ $option['menu_item_id'] }}"
                                   data-priced data-extra-minor="{{ (int) $option['extra_minor'] }}" @checked(in_array($option['menu_item_id'], $picked, true)) @disabled($soldOut)
                                   @if($single && $group['min'] > 0) required @endif>
                            <span>{{ $name($option['menu_item_id']) }}{{ $option['extra_minor'] > 0 ? ' (+' . $fmt((int) $option['extra_minor']) . ')' : '' }}</span>
                        </label>
                    @endforeach
                </fieldset>
            @endforeach

            @if(!empty($config['add_ons']))
                <fieldset class="eb-food-options">
                    <legend class="eb-text-label">Add extras</legend>
                    @foreach($config['add_ons'] as $addOn)
                        <label class="eb-choice-label" style="justify-content: space-between; width: 100%;">
                            <span>{{ $name($addOn['menu_item_id']) }} (+{{ $fmt((int) $addOn['price_minor']) }} each)</span>
                            <input type="number" class="eb-input" style="width: 5rem;" min="0" max="{{ (int) $addOn['max'] }}" step="1" inputmode="numeric"
                                   name="packages[{{ $index }}][add_ons][{{ $addOn['menu_item_id'] }}]" value="{{ (int) ($selection['add_ons'][$addOn['menu_item_id']] ?? 0) }}"
                                   data-addon data-price-minor="{{ (int) $addOn['price_minor'] }}" aria-label="Extra {{ $name($addOn['menu_item_id']) }}" @disabled($soldOut)>
                        </label>
                    @endforeach
                </fieldset>
            @endif

            @if($package->allow_special_instructions)
                <label class="eb-text-label">Special instructions (optional)
                    <input type="text" class="eb-input" maxlength="300" name="{{ $field('instructions') }}" value="{{ $entry['instructions'] ?? '' }}" style="width: 100%; margin-top: 2px;" @disabled($soldOut)>
                </label>
            @endif

            <div style="display: flex; justify-content: space-between; align-items: center; gap: var(--space-3); flex-wrap: wrap;">
                <span class="eb-text-supporting">Each: <strong data-package-unit>{{ $fmt($package->priceMinorIn($currency)) }}</strong></span>
                <x-quantity-control :name="$field('quantity')" :value="(int) ($entry['quantity'] ?? 0)" :max="$max" :disabled="$soldOut"
                                    :label="'Quantity of ' . $package->name" :id="'pkg-qty-' . $index" data-line-qty />
            </div>
        </div>
    </x-food-card>
</div>
