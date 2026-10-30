{{--
    Event card for listings. The whole card is one link.
    Props: event (App\Models\Event), href (defaults to the event page),
           price (pre-formatted "From …" text from the controller, optional),
           headingLevel (2–4, matches the page outline).
--}}
@props([
    'event',
    'href' => null,
    'price' => null,
    'headingLevel' => 3,
])

@php
    $link = $href ?? route('events.show', $event->id);
    $tag = 'h' . max(2, min(4, (int) $headingLevel));
@endphp

<article {{ $attributes->merge(['class' => 'eb-event-card']) }}>
    <a href="{{ $link }}" class="eb-card-link" style="display: flex; flex-direction: column; height: 100%;">
        <div class="eb-event-card-media">
            <img src="{{ $event->display_image_url }}" alt="" loading="lazy" decoding="async">
            <div class="eb-event-card-scrim"></div>
            @if($event->category)
                <span class="eb-card-chip eb-badge eb-badge-info">{{ $event->category }}</span>
            @endif
            @unless($event->is_ordering_open)
                <span class="eb-card-chip eb-card-chip-end eb-badge eb-badge-warning">Ordering closed</span>
            @endunless
        </div>
        <div class="eb-event-card-body">
            <{{ $tag }} class="eb-text-card-title eb-clamp-2" style="margin: 0;">{{ $event->title }}</{{ $tag }}>
            <div class="eb-card-meta">
                <span class="eb-card-meta-row"><x-icon name="calendar" size="sm" /><span>{{ $event->date_str }} · {{ $event->time_str }}</span></span>
                <span class="eb-card-meta-row"><x-icon name="events" size="sm" /><span class="eb-clamp-2">{{ $event->venue_name }}</span></span>
            </div>
            @if($price)
                <div class="eb-event-card-footer">
                    <span class="eb-text-price">{{ $price }}</span>
                    <span class="eb-text-label" aria-hidden="true">View →</span>
                </div>
            @endif
        </div>
    </a>
</article>
