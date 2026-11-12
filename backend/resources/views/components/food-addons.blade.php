{{--
    Reusable add-on and side selection visuals for meal packages and customizable dishes.
    Props:
      addOns     array of ['menu_item_id' => string, 'price_minor' => int, 'max' => int]
      selection  array of saved add_on quantities keyed by menu_item_id
      index      package card index (default 0)
      menuById   Collection of MenuItem keyed by id
      currency   ISO currency code
      soldOut    bool
      title      fieldset legend label (default "Add extras & sides")
--}}
@props([
    'addOns' => [],
    'selection' => [],
    'index' => 0,
    'menuById' => collect(),
    'currency' => null,
    'soldOut' => false,
    'title' => 'Add extras & sides',
])

@php
    $currency = $currency ?: \App\Support\Currency::defaultCode();
    $fmt = fn (int $m) => \App\Support\Money::formatMinor($m, $currency);
    $menuLookup = $menuById instanceof \Illuminate\Support\Collection ? $menuById : collect($menuById);
    $name = fn ($id) => $menuLookup->get($id)?->name ?? 'Item';
    $thumb = fn ($id) => $menuLookup->get($id)?->responsiveImage();
    $fallbackFor = fn ($id) => \App\Support\ImageSubjects::menuFallback($menuLookup->get($id) ?? new \App\Models\MenuItem(['category' => 'side']));
@endphp

@if(!empty($addOns))
    <fieldset class="eb-food-options eb-customization-block eb-addons-block" {{ $attributes }}>
        <legend class="eb-customization-legend">
            <span class="eb-customization-title">
                <x-icon name="plus" :size="14" />
                {{ $title }}
            </span>
            <span class="eb-customization-badge">Optional add-ons</span>
        </legend>

        <div class="eb-addons-grid">
            @foreach($addOns as $addOn)
                @php
                    $addOnId = $addOn['menu_item_id'];
                    $addOnItem = $menuLookup->get($addOnId);
                    $addOnThumb = $thumb($addOnId);
                    $addOnFallback = $fallbackFor($addOnId);
                    $addOnQty = (int) ($selection[$addOnId] ?? 0);
                    $addOnMax = max(1, (int) ($addOn['max'] ?? 10));
                    $addOnPrice = (int) ($addOn['price_minor'] ?? 0);
                    $dietaryIcons = [
                        'vegetarian' => 'leaf',
                        'vegan' => 'leaf',
                        'gluten_free' => 'wheat',
                        'pescatarian' => 'fish',
                        'dairy_free' => 'check',
                        'halal' => 'check',
                        'kosher' => 'check',
                        'nut_free' => 'shield',
                    ];
                    $itemTags = is_array($addOnItem?->dietary_tags) ? array_slice($addOnItem->dietary_tags, 0, 2) : [];
                @endphp
                <div class="eb-addon-card {{ $addOnQty > 0 ? 'is-selected' : '' }}" data-addon-card>
                    <div class="eb-addon-media">
                        @if($addOnThumb)
                            <x-responsive-image :image="$addOnThumb" alt="" sizes="52px" class="eb-addon-thumb" width="52" height="52" />
                        @else
                            <span class="eb-addon-thumb-icon" aria-hidden="true">
                                <x-icon :name="$addOnFallback['icon']" :size="20" />
                            </span>
                        @endif
                    </div>

                    <div class="eb-addon-info">
                        <span class="eb-addon-name">{{ $name($addOnId) }}</span>
                        <div class="eb-addon-meta">
                            <span class="eb-addon-price">(+{{ $fmt($addOnPrice) }} each)</span>
                            @foreach($itemTags as $tag)
                                @php
                                    $tagKey = str_replace('-', '_', \Illuminate\Support\Str::slug($tag));
                                    $tagIcon = $dietaryIcons[$tagKey] ?? 'check';
                                    $tagLabel = \App\Models\MenuItem::DIETARY_TAGS[$tagKey] ?? ucfirst(str_replace('_', ' ', (string) $tag));
                                @endphp
                                <span class="eb-addon-dietary-pill">
                                    <x-icon :name="$tagIcon" :size="11" />
                                    {{ $tagLabel }}
                                </span>
                            @endforeach
                        </div>
                    </div>

                    <div class="eb-addon-stepper" data-addon-stepper>
                        <button type="button" class="eb-qty-btn eb-addon-step-btn" data-addon-step="down"
                                aria-label="Decrease Extra {{ $name($addOnId) }}" @disabled($soldOut || $addOnQty <= 0)>−</button>
                        <input type="number"
                               class="eb-input eb-addon-input"
                               min="0"
                               max="{{ $addOnMax }}"
                               step="1"
                               inputmode="numeric"
                               name="packages[{{ $index }}][add_ons][{{ $addOnId }}]"
                               value="{{ $addOnQty }}"
                               data-addon
                               data-addon-label="{{ $name($addOnId) }}"
                               data-price-minor="{{ $addOnPrice }}"
                               aria-label="Extra {{ $name($addOnId) }}"
                               @disabled($soldOut)>
                        <button type="button" class="eb-qty-btn eb-addon-step-btn" data-addon-step="up"
                                aria-label="Increase Extra {{ $name($addOnId) }}" @disabled($soldOut || $addOnQty >= $addOnMax)>+</button>
                    </div>
                </div>
            @endforeach
        </div>
    </fieldset>
@endif

