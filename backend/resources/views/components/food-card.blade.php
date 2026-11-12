{{--
    Menu item card (3D Soft Realism & Dark Mode Elevation).
    Shows the dish with high-quality photography (only when the image corresponds to this actual menu item),
    consistent 4:3 image dimensions, availability indicators, dietary/allergen disclosures, clear tabular pricing,
    and an interactive customization/quantity slot.
    Props: item (App\Models\MenuItem), currency ('USD' | 'JMD'),
           layout ('card' | 'row'; 'row' becomes a horizontal builder card on desktop and compact row on phones).
--}}
@props([
    'item',
    'currency' => 'USD',
    'layout' => 'card',
])

@php
    $price = $currency === 'USD' ? $item->price_usd : $item->price_jmd;
    $soldOut = $item->available_stock !== null && (int) $item->available_stock <= 0;
    $lowStock = !$soldOut && $item->available_stock !== null && (int) $item->available_stock <= 10;
    $inStockCount = !$soldOut && !$lowStock && $item->available_stock !== null ? (int) $item->available_stock : null;
    // The host's photo at the size the card needs (AVIF/WebP), cropped around its focal point; null when there is none.
    $photo = $item->responsiveImage();
    $image = $photo?->src;
    // Each dietary tag with its icon (the text label is always shown too; the icon only helps scanning).
    $dietIcons = [
        'vegetarian' => 'leaf',
        'vegan' => 'leaf',
        'gluten_free' => 'wheat',
        'pescatarian' => 'fish',
        'dairy_free' => 'check',
        'halal' => 'check',
        'kosher' => 'check',
        'nut_free' => 'shield',
    ];
    $rawDietaryTags = (array) ($item->dietary_tags ?? []);
    if ($rawDietaryTags === [] && $item->description) {
        $descLower = mb_strtolower((string) $item->description);
        foreach (['vegan' => 'vegan', 'vegetarian' => 'vegetarian', 'gluten-free' => 'gluten_free', 'gluten free' => 'gluten_free', 'halal' => 'halal'] as $needle => $tagKey) {
            if (str_contains($descLower, $needle)) {
                $rawDietaryTags[] = $tagKey;
            }
        }
        $rawDietaryTags = array_values(array_unique($rawDietaryTags));
    }
    $dietary = collect($rawDietaryTags)->map(fn ($tag) => [
        'label' => \App\Models\MenuItem::DIETARY_TAGS[$tag] ?? ucfirst(str_replace('_', ' ', (string) $tag)),
        'icon' => $dietIcons[$tag] ?? 'check',
    ]);
    $spice = ['mild' => 1, 'medium' => 2, 'hot' => 3, 'extra_hot' => 4][$item->spice_level ?? ''] ?? 0;
    $allergens = collect((array) ($item->allergens ?? []))->map(fn ($a) => \App\Models\MenuItem::ALLERGENS[$a] ?? ucfirst(str_replace('_', ' ', (string) $a)));
    $ingredientsText = is_array($item->ingredients) ? implode(', ', $item->ingredients) : trim((string) ($item->ingredients ?? ''));
    $portions = is_array($item->portion_sizes) && count($item->portion_sizes) > 1 ? $item->portion_sizes : [];
@endphp

<article {{ $attributes->merge(['class' => 'eb-food-card' . ($layout === 'row' ? ' eb-food-card-row' : '') . ($soldOut ? ' is-sold-out' : '')]) }} @if($soldOut) aria-disabled="true" style="opacity: 0.6;" @endif>
    @if($image)
        {{-- A soft shimmer shows while the photo loads; a photo that fails to load leaves the plain placeholder. --}}
        <div class="eb-food-card-media eb-skeleton">
            <x-responsive-image
                :image="$photo"
                sizes="(min-width: 641px) 260px, 100vw"
                :style="'object-position:' . e($photo->objectPosition())"
                onload="this.closest('.eb-food-card-media').classList.remove('eb-skeleton')"
                onerror="const m = this.closest('.eb-food-card-media'); m.classList.remove('eb-skeleton'); m.classList.add('eb-food-card-placeholder'); this.remove();"
            />
            @if($item->category)
                <span class="eb-card-chip eb-badge eb-badge-info">{{ $item->category }}</span>
            @endif
            @if($soldOut)
                <span class="eb-card-chip eb-card-chip-end eb-badge eb-badge-error">
                    <span class="eb-badge-dot" aria-hidden="true"></span>
                    <span>Sold out</span>
                </span>
            @elseif($lowStock)
                <span class="eb-card-chip eb-card-chip-end eb-badge eb-badge-warning">
                    <span class="eb-badge-dot" aria-hidden="true"></span>
                    <span>{{ (int) $item->available_stock }} left</span>
                </span>
            @else
                <span class="eb-card-chip eb-card-chip-end eb-badge eb-badge-success">
                    <span class="eb-badge-dot" aria-hidden="true"></span>
                    <span>{{ $inStockCount !== null ? $inStockCount . ' available' : 'Available' }}</span>
                </span>
            @endif
            @if($item->photoIsIllustrative())
                <span class="eb-food-card-illustrative">Illustrative photo</span>
            @endif
        </div>
    @else
        {{-- No usable photo: a clearly labelled placeholder for this kind of food (e.g. "Seafood · No photo yet"),
             never a stock photo of some other dish. --}}
        @php $fallback = \App\Support\ImageSubjects::menuFallback($item); @endphp
        <div class="eb-food-card-media eb-food-card-placeholder" data-fallback="{{ $fallback['icon'] }}">
            <div class="eb-food-placeholder-emblem" aria-hidden="true">
                <x-icon :name="$fallback['icon']" size="lg" />
            </div>
            <span><strong>{{ $fallback['label'] }}</strong> · No photo yet</span>
        </div>
    @endif

    <div class="eb-food-card-body">
        <div class="eb-food-card-header">
            <div style="min-width: 0; flex: 1;">
                <h3 class="eb-text-card-title eb-food-card-title" style="margin: 0;">{{ $item->name }}</h3>
                @if(!$image && $item->category)
                    <span class="eb-food-card-category-inline">{{ $item->category }}{{ $item->cuisine ? ' · ' . $item->cuisine : '' }}</span>
                @elseif($item->cuisine)
                    <span class="eb-food-card-category-inline">{{ $item->cuisine }}</span>
                @endif
            </div>
            <div class="eb-food-price-pill">
                @if($item->pricing_type === 'customizable' || $item->item_type === 'package')<span class="eb-food-price-prefix">From </span>@endif<span class="eb-text-price">{{ \App\Support\Money::format($price, $currency) }}</span>
            </div>
        </div>

        {{-- Availability & Dietary / Spice / Feature Tags --}}
        <div class="eb-food-card-tags">
            @if(!$image)
                @if($soldOut)
                    <span class="eb-badge eb-badge-error">
                        <span class="eb-badge-dot" aria-hidden="true"></span>
                        <span>Sold out</span>
                    </span>
                @elseif($lowStock)
                    <span class="eb-badge eb-badge-warning">
                        <span class="eb-badge-dot" aria-hidden="true"></span>
                        <span>{{ (int) $item->available_stock }} left</span>
                    </span>
                @else
                    <span class="eb-badge eb-badge-success">
                        <span class="eb-badge-dot" aria-hidden="true"></span>
                        <span>{{ $inStockCount !== null ? $inStockCount . ' available' : 'Available' }}</span>
                    </span>
                @endif
            @endif
            @if($item->is_featured)<span class="eb-badge eb-badge-info">Featured</span>@endif
            @if($item->item_type === 'package')<span class="eb-badge eb-badge-purple">Meal package</span>@endif
            @if($item->pricing_type === 'customizable')<span class="eb-badge eb-badge-accent">Customizable</span>@endif
            @foreach($dietary as $tag)
                <span class="eb-badge eb-badge-success eb-badge-icon"><x-icon :name="$tag['icon']" size="sm" />{{ $tag['label'] }}</span>
            @endforeach
            @if($spice > 0)
                <span class="eb-badge eb-badge-warning eb-badge-icon" title="Spice level">
                    @for($i = 0; $i < min($spice, 3); $i++)<x-icon name="flame" size="sm" />@endfor
                    {{ ['', 'Mild', 'Medium', 'Hot', 'Extra hot'][$spice] }}
                </span>
            @endif
            @if($item->prep_minutes)
                <span class="eb-badge eb-badge-info eb-badge-icon"><x-icon name="clock" size="sm" />~{{ (int) $item->prep_minutes }}m prep</span>
            @endif
        </div>

        @if($item->description)
            <p class="eb-text-supporting eb-clamp-2" style="margin: 0; line-height: 1.5;">{{ $item->description }}</p>
        @endif

        @if($portions !== [])
            <div class="eb-food-portions-bar" aria-label="Portion sizes">
                @foreach($portions as $portion)
                    <span class="eb-food-portion-chip">
                        <span>{{ $portion['label'] ?? $portion['name'] ?? 'Portion' }}</span>
                        @if(isset($portion['price_minor']))
                            <strong>{{ \App\Support\Money::formatMinor((int) $portion['price_minor'], $currency) }}</strong>
                        @endif
                    </span>
                @endforeach
            </div>
        @endif

        @if($ingredientsText !== '' || $allergens->isNotEmpty() || $item->guest_instructions)
            <div class="eb-food-dietary-well">
                @if($ingredientsText !== '')
                    <p class="eb-help-text" style="margin: 0;"><strong>Ingredients:</strong> {{ $ingredientsText }}</p>
                @endif
                @if($allergens->isNotEmpty())
                    <p class="eb-help-text eb-food-allergen-line" style="margin: 0;">
                        <x-icon name="alert" size="sm" />
                        <span><strong>Contains:</strong> {{ $allergens->implode(', ') }}</span>
                    </p>
                @endif
                @if($item->guest_instructions)
                    <p class="eb-help-text" style="margin: 0;">{{ $item->guest_instructions }}</p>
                @endif
            </div>
        @endif

        @if(trim((string) $slot) !== '')
            <div class="eb-food-card-actions">{{ $slot }}</div>
        @endif
    </div>
</article>
