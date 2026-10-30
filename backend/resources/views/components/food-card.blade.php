{{--
    Menu item card. Shows the dish, its price in the order currency and stock state;
    the action (usually a quantity-control) goes in the slot.
    Props: item (App\Models\MenuItem), currency ('JMD' | 'USD'),
           layout ('card' | 'row'; 'row' becomes a compact row on phones).
--}}
@props([
    'item',
    'currency' => 'JMD',
    'layout' => 'card',
])

@php
    $price = $currency === 'USD' ? $item->price_usd : $item->price_jmd;
    $soldOut = $item->available_stock !== null && (int) $item->available_stock <= 0;
    $lowStock = !$soldOut && $item->available_stock !== null && (int) $item->available_stock <= 10;
    $image = $item->display_image_url;
@endphp

<article {{ $attributes->merge(['class' => 'eb-food-card' . ($layout === 'row' ? ' eb-food-card-row' : '')]) }} @if($soldOut) aria-disabled="true" style="opacity: 0.6;" @endif>
    @if($image)
        <div class="eb-food-card-media">
            <img src="{{ $image }}" alt="" loading="lazy" decoding="async">
            @if($soldOut)
                <span class="eb-card-chip eb-badge eb-badge-error">Sold out</span>
            @elseif($lowStock)
                <span class="eb-card-chip eb-badge eb-badge-warning">{{ (int) $item->available_stock }} left</span>
            @endif
        </div>
    @endif
    <div class="eb-food-card-body">
        <div style="display: flex; justify-content: space-between; gap: var(--space-3); align-items: baseline;">
            <h3 class="eb-text-card-title" style="margin: 0;">{{ $item->name }}</h3>
            <span class="eb-text-price" style="white-space: nowrap;">
                @if($item->pricing_type === 'customizable')<span class="eb-text-supporting" style="font-size: 0.8rem;">From </span>@endif{{ \App\Support\Money::format($price, $currency) }}
            </span>
        </div>
        @if($item->description)
            <p class="eb-text-supporting eb-clamp-2" style="margin: 0;">{{ $item->description }}</p>
        @endif
        @if(!$image && ($soldOut || $lowStock))
            <span class="eb-badge {{ $soldOut ? 'eb-badge-error' : 'eb-badge-warning' }}" style="align-self: flex-start;">{{ $soldOut ? 'Sold out' : (int) $item->available_stock . ' left' }}</span>
        @endif
        @if(trim((string) $slot) !== '')
            <div style="margin-top: auto; display: flex; justify-content: flex-end;">{{ $slot }}</div>
        @endif
    </div>
</article>
