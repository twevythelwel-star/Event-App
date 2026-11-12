{{--
    Event card for listings. The whole card is one link to the event page.
    Shows: flyer, name, city and state, date and local time, category, starting ticket price, availability,
    and the host. Price and availability come from App\Support\EventListing, so the card never claims more
    than the event's data supports.
    Props: event (App\Models\Event, with menuItems loaded), href (defaults to the event page),
           headingLevel (2–4, matches the page outline).
--}}
@props([
    'event',
    'href' => null,
    'headingLevel' => 3,
    'price' => null,
])

@php
    $link = $href ?? route('events.show', $event->id);
    $tag = 'h' . max(2, min(4, (int) $headingLevel));
    $availability = \App\Support\EventListing::availability($event);
    $priceText = $price ?? \App\Support\EventListing::fromPriceText($event);
    $location = \App\Support\EventListing::location($event);
    $venueLine = implode(' · ', array_filter([$event->venue_name, $location]));
    $badge = ['success' => 'eb-badge-success', 'danger' => 'eb-badge-error', 'warning' => 'eb-badge-warning'][$availability['tone']] ?? 'eb-badge-info';
    $traditions = array_slice($event->traditionLabels(), 0, 2);
    $hasMenu = $event->supports_food_ordering && $event->relationLoaded('menuItems') && $event->menuItems->where('is_active', true)->isNotEmpty();
@endphp

<article {{ $attributes->merge(['class' => 'eb-event-card']) }}>
    <a href="{{ $link }}" class="eb-card-link" style="display: flex; flex-direction: column; height: 100%;" aria-label="{{ $event->title }}: view event details">
        <div class="eb-event-card-media">
            @php $cardCover = $event->coverImage(); @endphp
            <x-responsive-image :image="$cardCover" alt="" :style="'object-position:' . e($event->flyer_position ?: '50% 50%')" sizes="(min-width: 1280px) 400px, (min-width: 768px) 45vw, 100vw" />
            <div class="eb-event-card-scrim"></div>
            <x-image-label :image="$cardCover" />
            @if($event->category)
                <span class="eb-card-chip eb-badge eb-badge-info">{{ $event->category }}</span>
            @endif
            <span class="eb-card-chip eb-card-chip-end eb-badge {{ $badge }}">
                <span class="eb-badge-dot" aria-hidden="true"></span>
                <span>{{ $availability['label'] }}</span>
            </span>
        </div>
        <div class="eb-event-card-body">
            @if($traditions !== [] || $hasMenu)
                <div class="eb-event-card-tags">
                    @foreach($traditions as $tradition)
                        <span class="eb-event-card-tag">{{ $tradition }}</span>
                    @endforeach
                    @if($hasMenu)
                        <span class="eb-event-card-tag eb-event-card-tag-food">Food menu</span>
                    @endif
                </div>
            @endif
            <{{ $tag }} class="eb-text-card-title eb-clamp-2" style="margin: 0;">{{ $event->title }}</{{ $tag }}>
            <div class="eb-card-meta">
                <span class="eb-card-meta-row"><x-icon name="calendar" size="sm" /><span>{{ $event->date_str }}@if($event->time_str) · {{ $event->time_str }}@endif</span></span>
                @if($venueLine !== '')
                    <span class="eb-card-meta-row"><x-icon name="events" size="sm" /><span class="eb-clamp-2">{{ $venueLine }}</span></span>
                @endif
                @if($event->organizer_name)
                    <span class="eb-card-meta-row eb-text-supporting"><span class="eb-clamp-2">Hosted by {{ $event->organizer_name }}</span></span>
                @endif
            </div>
            <div class="eb-event-card-footer">
                <div class="eb-event-card-price-block">
                    <span class="eb-text-price">{{ $priceText ?? 'Tickets not on sale' }}</span>
                </div>
                <span class="eb-event-card-cta" aria-hidden="true">Details <span class="eb-event-card-cta-arrow">→</span></span>
            </div>
        </div>
    </a>
</article>
