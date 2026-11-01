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
])

@php
    $link = $href ?? route('events.show', $event->id);
    $tag = 'h' . max(2, min(4, (int) $headingLevel));
    $availability = \App\Support\EventListing::availability($event);
    $price = \App\Support\EventListing::fromPriceText($event);
    $location = \App\Support\EventListing::location($event);
    $badge = ['success' => 'eb-badge-success', 'danger' => 'eb-badge-error', 'warning' => 'eb-badge-warning'][$availability['tone']] ?? 'eb-badge-info';
@endphp

<article {{ $attributes->merge(['class' => 'eb-event-card']) }}>
    <a href="{{ $link }}" class="eb-card-link" style="display: flex; flex-direction: column; height: 100%;" aria-label="{{ $event->title }}: view event details">
        <div class="eb-event-card-media">
            <img src="{{ $event->display_image_url }}" alt="" loading="lazy" decoding="async">
            <div class="eb-event-card-scrim"></div>
            @if($event->category)
                <span class="eb-card-chip eb-badge eb-badge-info">{{ $event->category }}</span>
            @endif
            <span class="eb-card-chip eb-card-chip-end eb-badge {{ $badge }}">{{ $availability['label'] }}</span>
        </div>
        <div class="eb-event-card-body">
            <{{ $tag }} class="eb-text-card-title eb-clamp-2" style="margin: 0;">{{ $event->title }}</{{ $tag }}>
            <div class="eb-card-meta">
                <span class="eb-card-meta-row"><x-icon name="calendar" size="sm" /><span>{{ $event->date_str }} · {{ $event->time_str }}</span></span>
                @if($location !== '')
                    <span class="eb-card-meta-row"><x-icon name="events" size="sm" /><span class="eb-clamp-2">{{ $location }}</span></span>
                @endif
                @if($event->organizer_name)
                    <span class="eb-card-meta-row eb-text-supporting"><span class="eb-clamp-2">Hosted by {{ $event->organizer_name }}</span></span>
                @endif
            </div>
            <div class="eb-event-card-footer">
                <span class="eb-text-price">{{ $price ?? 'Tickets not on sale' }}</span>
                <span class="eb-text-label" aria-hidden="true">Details →</span>
            </div>
        </div>
    </a>
</article>
