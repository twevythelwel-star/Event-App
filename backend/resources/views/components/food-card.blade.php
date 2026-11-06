{{--
    Menu item card. Shows the dish, its price in the order currency and stock state;
    the action (usually a quantity-control) goes in the slot.
    Props: item (App\Models\MenuItem), currency ('USD' | 'JMD'),
           layout ('card' | 'row'; 'row' becomes a compact row on phones).
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
    // The host's photo at the size the card needs (AVIF/WebP), cropped around its focal point; null when there is none.
    $photo = $item->responsiveImage();
    $image = $photo?->src;
    // Each dietary tag with its icon (the text label is always shown too; the icon only helps scanning).
    $dietIcons = ['vegetarian' => 'leaf', 'vegan' => 'leaf', 'gluten_free' => 'wheat', 'pescatarian' => 'fish'];
    $dietary = collect((array) ($item->dietary_tags ?? []))->map(fn ($tag) => [
        'label' => \App\Models\MenuItem::DIETARY_TAGS[$tag] ?? ucfirst(str_replace('_', ' ', (string) $tag)),
        'icon' => $dietIcons[$tag] ?? 'check',
    ]);
    $spice = ['mild' => 1, 'medium' => 2, 'hot' => 3, 'extra_hot' => 4][$item->spice_level ?? ''] ?? 0;
    $allergens = collect((array) ($item->allergens ?? []))->map(fn ($a) => \App\Models\MenuItem::ALLERGENS[$a] ?? ucfirst(str_replace('_', ' ', (string) $a)));
@endphp

<article {{ $attributes->merge(['class' => 'eb-food-card' . ($layout === 'row' ? ' eb-food-card-row' : '')]) }} @if($soldOut) aria-disabled="true" style="opacity: 0.6;" @endif>
    @if($image)
        {{-- A soft shimmer shows while the photo loads; a photo that fails to load leaves the plain placeholder. --}}
        <div class="eb-food-card-media eb-skeleton">
            <x-responsive-image :image="$photo" sizes="(min-width: 641px) 220px, 100vw"
                onload="this.closest('.eb-food-card-media').classList.remove('eb-skeleton')"
                onerror="const m = this.closest('.eb-food-card-media'); m.classList.remove('eb-skeleton'); m.classList.add('eb-food-card-placeholder'); this.remove();" />
            @if($item->photoIsIllustrative())
                <span class="eb-food-card-illustrative">Illustrative photo</span>
            @endif
            @if($soldOut)
                <span class="eb-card-chip eb-badge eb-badge-error">Sold out</span>
            @elseif($lowStock)
                <span class="eb-card-chip eb-badge eb-badge-warning">{{ (int) $item->available_stock }} left</span>
            @endif
        </div>
    @else
        {{-- No usable photo: a clearly labelled placeholder for this kind of food (e.g. "Seafood · No photo yet"),
             never a stock photo of some other dish. --}}
        @php $fallback = \App\Support\ImageSubjects::menuFallback($item); @endphp
        <div class="eb-food-card-media eb-food-card-placeholder" data-fallback="{{ $fallback['icon'] }}">
            <x-icon :name="$fallback['icon']" size="lg" />
            <span><strong>{{ $fallback['label'] }}</strong> · No photo yet</span>
        </div>
    @endif
    <div class="eb-food-card-body">
        <div style="display: flex; justify-content: space-between; gap: var(--space-3); align-items: baseline;">
            <h3 class="eb-text-card-title" style="margin: 0;">{{ $item->name }}</h3>
            <span class="eb-text-price" style="white-space: nowrap;">
                @if($item->pricing_type === 'customizable' || $item->item_type === 'package')<span class="eb-text-supporting" style="font-size: 0.8rem;">From </span>@endif{{ \App\Support\Money::format($price, $currency) }}
            </span>
        </div>
        @if($item->is_featured || $item->item_type === 'package' || $dietary->isNotEmpty() || $spice > 0)
            <div class="eb-food-card-tags">
                @if($item->is_featured)<span class="eb-badge eb-badge-info">Featured</span>@endif
                @if($item->item_type === 'package')<span class="eb-badge eb-badge-purple">Meal package</span>@endif
                @foreach($dietary as $tag)<span class="eb-badge eb-badge-success eb-badge-icon"><x-icon :name="$tag['icon']" size="sm" />{{ $tag['label'] }}</span>@endforeach
                @if($spice > 0)<span class="eb-badge eb-badge-warning eb-badge-icon" title="Spice level">@for($i = 0; $i < min($spice, 3); $i++)<x-icon name="flame" size="sm" />@endfor{{ ['', 'Mild', 'Medium', 'Hot', 'Extra hot'][$spice] }}</span>@endif
            </div>
        @endif
        @if($item->description)
            <p class="eb-text-supporting eb-clamp-2" style="margin: 0;">{{ $item->description }}</p>
        @endif
        @if($allergens->isNotEmpty())
            <p class="eb-help-text" style="margin: 0;"><strong>Contains:</strong> {{ $allergens->implode(', ') }}</p>
        @endif
        @if($item->guest_instructions)
            <p class="eb-help-text" style="margin: 0;">{{ $item->guest_instructions }}</p>
        @endif
        @if(!$image && ($soldOut || $lowStock))
            <span class="eb-badge {{ $soldOut ? 'eb-badge-error' : 'eb-badge-warning' }}" style="align-self: flex-start;">{{ $soldOut ? 'Sold out' : (int) $item->available_stock . ' left' }}</span>
        @endif
        @if(trim((string) $slot) !== '')
            <div style="margin-top: auto; display: flex; justify-content: flex-end;">{{ $slot }}</div>
        @endif
    </div>
</article>
