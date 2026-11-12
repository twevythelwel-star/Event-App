{{--
    Reusable food item options & customization controls (3D Soft Realism & Dark Mode Elevation).
    Supports:
      - mode="item" (default): renders $item->custom_option_groups, custom_amount input, and special instructions.
      - mode="package": renders Meal Package included components (with swap selectors) and choice_groups.
--}}
@props([
    'item',
    'currency' => 'USD',
    'saved' => [],
    'entry' => null,
    'soldOut' => false,
    'mode' => 'item',
    'index' => 0,
    'menuById' => null,
])

@php
    $minor = fn ($major) => \App\Support\Money::parse((string) ($major ?? 0), $currency)->minor();
    $fmt = fn (int $m) => \App\Support\Money::formatMinor($m, $currency);
@endphp

@if($mode === 'package')
    @php
        $config = (array) ($item->package_config ?? []);
        $selection = (array) ($entry['selection'] ?? $saved['selection'] ?? []);
        $menuLookup = $menuById ?? collect();
        $name = fn ($id) => $menuLookup->get($id)?->name ?? 'Item';
        $thumb = fn ($id) => $menuLookup->get($id)?->responsiveImage();
        $fallbackFor = fn ($id) => $menuLookup->get($id)
            ? \App\Support\ImageSubjects::menuFallback($menuLookup->get($id))
            : ['icon' => 'utensils', 'label' => 'Food'];
    @endphp

    @if(!empty($config['components']))
        <div class="eb-customization-block">
            <div class="eb-customization-head">
                <span class="eb-text-label">Included in package</span>
                <span class="eb-customization-rule">Base plates &amp; swaps</span>
            </div>
            <ul class="eb-package-includes">
                @foreach($config['components'] as $c => $component)
                    @php
                        $compThumb = $thumb($component['menu_item_id']);
                        $compFallback = $fallbackFor($component['menu_item_id']);
                    @endphp
                    <li class="eb-package-include-item">
                        @if(!empty($component['substitutes']))
                            <div class="eb-package-swap-row">
                                <div class="eb-package-swap-label">
                                    @if($compThumb)
                                        <x-responsive-image :image="$compThumb" alt="" sizes="40px" class="eb-option-thumb" width="40" height="40" />
                                    @else
                                        <span class="eb-option-thumb eb-option-thumb-icon" aria-hidden="true">
                                            <x-icon :name="$compFallback['icon']" size="sm" />
                                        </span>
                                    @endif
                                    <span class="eb-text-supporting" style="font-size: 0.78rem; font-weight: 600;">Base item or upgrade</span>
                                </div>
                                <label class="sr-only" for="pkg-{{ $index }}-sub-{{ $c }}">Swap {{ $name($component['menu_item_id']) }}</label>
                                <select id="pkg-{{ $index }}-sub-{{ $c }}" name="packages[{{ $index }}][substitutions][{{ $c }}]" class="eb-select eb-input" data-priced @disabled($soldOut)>
                                    <option value="" data-extra-minor="0">{{ ($component['quantity'] ?? 1) > 1 ? $component['quantity'] . ' × ' : '' }}{{ $name($component['menu_item_id']) }} (Included)</option>
                                    @foreach($component['substitutes'] as $sub)
                                        <option value="{{ $sub['menu_item_id'] }}" data-extra-minor="{{ (int) $sub['extra_minor'] }}" @selected(($selection['substitutions'][$c] ?? '') === $sub['menu_item_id'])>
                                            Swap for {{ $name($sub['menu_item_id']) }}{{ $sub['extra_minor'] > 0 ? ' (+' . $fmt((int) $sub['extra_minor']) . ')' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @else
                            <div class="eb-package-fixed-chip">
                                @if($compThumb)
                                    <x-responsive-image :image="$compThumb" alt="" sizes="40px" class="eb-option-thumb" width="40" height="40" />
                                @else
                                    <span class="eb-option-thumb eb-option-thumb-icon" aria-hidden="true">
                                        <x-icon :name="$compFallback['icon']" size="sm" />
                                    </span>
                                @endif
                                <span class="eb-package-fixed-name">{{ ($component['quantity'] ?? 1) > 1 ? $component['quantity'] . ' × ' : '' }}{{ $name($component['menu_item_id']) }}</span>
                                <span class="eb-option-price-tag is-included">Included</span>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @foreach($config['choice_groups'] ?? [] as $group)
        @php
            $picked = (array) ($selection['choices'][$group['id']] ?? []);
            $single = (int) $group['max'] === 1;
            $ruleText = $group['min'] > 0
                ? ($group['min'] === $group['max'] ? 'Choose ' . $group['max'] : 'Choose ' . $group['min'] . ' to ' . $group['max'])
                : 'Optional · Up to ' . $group['max'];
        @endphp
        <fieldset class="eb-food-options eb-customization-block" @unless($single) data-max="{{ (int) $group['max'] }}" @endunless>
            <legend class="eb-customization-head">
                <span class="eb-text-label">{{ $group['title'] }}</span>
                <span class="eb-customization-rule {{ ($group['min'] ?? 0) > 0 ? 'is-required' : '' }}">{{ $ruleText }}</span>
            </legend>
            <div class="eb-option-tiles-grid">
                @foreach($group['options'] as $option)
                    @php
                        $optThumb = $thumb($option['menu_item_id']);
                        $optFallback = $fallbackFor($option['menu_item_id']);
                        $optExtra = (int) ($option['extra_minor'] ?? 0);
                    @endphp
                    <label class="eb-choice-label eb-option-tile">
                        <input type="{{ $single ? 'radio' : 'checkbox' }}"
                               name="packages[{{ $index }}][choices][{{ $group['id'] }}][]"
                               value="{{ $option['menu_item_id'] }}"
                               data-priced
                               data-extra-minor="{{ $optExtra }}"
                               data-label="{{ $name($option['menu_item_id']) }}"
                               @checked(in_array($option['menu_item_id'], $picked, true))
                               @disabled($soldOut)
                               @if($single && ($group['min'] ?? 0) > 0) required @endif>
                        @if($optThumb)
                            <x-responsive-image :image="$optThumb" alt="" sizes="40px" class="eb-option-thumb" width="40" height="40" />
                        @else
                            <span class="eb-option-thumb eb-option-thumb-icon" aria-hidden="true">
                                <x-icon :name="$optFallback['icon']" size="sm" />
                            </span>
                        @endif
                        <span class="eb-option-tile-name">{{ $name($option['menu_item_id']) }}</span>
                        <span class="eb-option-price-tag {{ $optExtra > 0 ? 'is-extra' : 'is-included' }}">
                            {{ $optExtra > 0 ? '+' . $fmt($optExtra) : 'Included' }}
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    @endforeach
@else
    @php
        $groups = $item->pricing_type === 'customizable' && is_array($item->custom_option_groups) ? $item->custom_option_groups : [];
    @endphp

    @foreach($groups as $group)
        @php
            $groupId = $group['id'] ?? ('g' . $loop->index);
            $groupMax = max(1, (int) ($group['max'] ?? 1));
            $required = (bool) ($group['required'] ?? true);
            $savedChoice = old("items.{$item->id}.choices.{$groupId}", $saved['choices'][$groupId] ?? ($required ? 0 : ''));
            $selectId = 'opt-' . $item->id . '-' . $groupId;
        @endphp

        @if($groupMax > 1)
            <fieldset class="eb-food-options eb-customization-block" data-max="{{ $groupMax }}">
                <legend class="eb-customization-head">
                    <span class="eb-text-label">{{ $group['title'] ?? 'Options' }}</span>
                    <span class="eb-customization-rule {{ $required ? 'is-required' : '' }}">
                        {{ $required ? 'Choose 1 to ' . $groupMax : 'Optional · Up to ' . $groupMax }}
                    </span>
                </legend>
                <div class="eb-option-tiles-grid">
                    @foreach($group['choices'] ?? [] as $choiceIndex => $choice)
                        @php $extra = $minor($currency === 'USD' ? ($choice['extraPriceUSD'] ?? 0) : ($choice['extraPriceJMD'] ?? 0)); @endphp
                        <label class="eb-choice-label eb-option-tile">
                            <input type="checkbox"
                                   name="items[{{ $item->id }}][choices][{{ $groupId }}][]"
                                   value="{{ $choiceIndex }}"
                                   data-extra-minor="{{ $extra }}"
                                   data-label="{{ $choice['label'] ?? '' }}"
                                   @checked(in_array($choiceIndex, array_map('intval', (array) $savedChoice), true) && $savedChoice !== '')
                                   @disabled($soldOut)>
                            <span class="eb-option-tile-name">{{ $choice['label'] ?? 'Choice' }}</span>
                            <span class="eb-option-price-tag {{ $extra > 0 ? 'is-extra' : 'is-included' }}">
                                {{ $extra > 0 ? '(+' . $fmt($extra) . ')' : 'Included' }}
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @else
            <div class="eb-customization-block" data-single-option-group>
                <div class="eb-customization-head">
                    <label class="eb-text-label" for="{{ $selectId }}">{{ $group['title'] ?? 'Option' }}</label>
                    <span class="eb-customization-rule {{ $required ? 'is-required' : '' }}">
                        {{ $required ? 'Required · Pick 1' : 'Optional' }}
                    </span>
                </div>

                {{-- Interactive 3D Pill Selector synced with the accessible <select> control --}}
                <div class="eb-option-pills" role="group" aria-label="{{ $group['title'] ?? 'Option' }} choices">
                    @unless($required)
                        <button type="button"
                                class="eb-option-pill {{ (string) $savedChoice === '' ? 'is-active' : '' }}"
                                data-option-pill
                                data-target-select="{{ $selectId }}"
                                data-option-value=""
                                @disabled($soldOut)>
                            <span>None</span>
                        </button>
                    @endunless
                    @foreach($group['choices'] ?? [] as $choiceIndex => $choice)
                        @php
                            $extra = $minor($currency === 'USD' ? ($choice['extraPriceUSD'] ?? 0) : ($choice['extraPriceJMD'] ?? 0));
                            $isSelected = (string) $savedChoice !== '' && (int) $savedChoice === $choiceIndex;
                        @endphp
                        <button type="button"
                                class="eb-option-pill {{ $isSelected ? 'is-active' : '' }}"
                                data-option-pill
                                data-target-select="{{ $selectId }}"
                                data-option-value="{{ $choiceIndex }}"
                                @disabled($soldOut)>
                            <span>{{ $choice['label'] ?? 'Choice' }}</span>
                            <span class="eb-option-price-tag {{ $extra > 0 ? 'is-extra' : 'is-included' }}">
                                {{ $extra > 0 ? '+' . $fmt($extra) : 'Included' }}
                            </span>
                        </button>
                    @endforeach
                </div>

                <select id="{{ $selectId }}" name="items[{{ $item->id }}][choices][{{ $groupId }}]" class="eb-select eb-input eb-option-select-synced" @disabled($soldOut)>
                    @unless($required)<option value="" data-extra-minor="0">None</option>@endunless
                    @foreach($group['choices'] ?? [] as $choiceIndex => $choice)
                        @php $extra = $minor($currency === 'USD' ? ($choice['extraPriceUSD'] ?? 0) : ($choice['extraPriceJMD'] ?? 0)); @endphp
                        <option value="{{ $choiceIndex }}" data-extra-minor="{{ $extra }}" data-label="{{ $choice['label'] ?? '' }}" @selected((string) $savedChoice !== '' && (int) $savedChoice === $choiceIndex)>
                            {{ $choice['label'] ?? 'Choice' }}{{ $extra > 0 ? ' (+' . $fmt($extra) . ')' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif
    @endforeach

    @if($item->pricing_type === 'custom_amount')
        <div class="eb-customization-block">
            <label class="eb-text-label" style="width: 100%;">
                Your amount ({{ $currency }})
                <input type="number" name="items[{{ $item->id }}][amount]" min="0" step="0.01" inputmode="decimal" class="eb-input" data-custom-amount
                       value="{{ old("items.{$item->id}.amount", $saved['amount'] ?? '') }}" style="width: 100%; margin-top: 4px;" @disabled($soldOut)>
            </label>
        </div>
    @endif

    @if($item->allow_special_instructions)
        <div class="eb-customization-block eb-instructions-block">
            <label class="eb-text-label" style="width: 100%;">
                <span>Special instructions <span class="eb-help-text">(optional — e.g. sauce on the side, allergy note)</span></span>
                <input type="text" name="items[{{ $item->id }}][instructions]" maxlength="300" class="eb-input"
                       placeholder="Add preparation or dietary notes for the kitchen…"
                       value="{{ old("items.{$item->id}.instructions", $saved['instructions'] ?? '') }}" style="width: 100%; margin-top: 4px;" @disabled($soldOut)>
            </label>
        </div>
    @endif
@endif

