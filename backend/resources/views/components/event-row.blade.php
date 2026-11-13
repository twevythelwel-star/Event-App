{{--
    Compact event row for phone lists (the home screen's "Upcoming events"): the event's photo on the left, its
    category as a coloured tag, the title, date and time, the venue, and an arrow. The whole row is one link.
    Everything shown comes from the event record; nothing is invented when a field is empty.
    Props: event (App\Models\Event), headingLevel (2–4, matches the page outline), eager (first row above the fold).
--}}
@props([
    'event',
    'headingLevel' => 3,
    'eager' => false,
])

@php
    $tag = 'h' . max(2, min(4, (int) $headingLevel));
    $category = trim((string) $event->category);
    $categoryKey = strtolower($category);
    // The tag's colour follows the kind of event; the words are always the host's own category.
    $tone = match (true) {
        str_contains($categoryKey, 'music') || str_contains($categoryKey, 'concert') || str_contains($categoryKey, 'night') || str_contains($categoryKey, 'party') => 'purple',
        str_contains($categoryKey, 'food') || str_contains($categoryKey, 'cookout') || str_contains($categoryKey, 'market') => 'lime',
        str_contains($categoryKey, 'dinner') || str_contains($categoryKey, 'gala') || str_contains($categoryKey, 'wedding') || str_contains($categoryKey, 'private') => 'sky',
        default => 'amber',
    };
    $venue = $event->venue_name ?: \App\Support\EventListing::location($event);
    $cover = $event->coverImage();
    // Short date and start time for a phone row ("Sat, Nov 21 · 12:00 PM"); the full range is on the event page.
    try {
        $when = $event->event_date_iso ? \Carbon\Carbon::parse(substr((string) $event->event_date_iso, 0, 10))->format('D, M j') : null;
        $start = $event->start_time ? \Carbon\Carbon::createFromFormat('H:i', substr((string) $event->start_time, 0, 5))->format('g:i A') : null;
    } catch (\Throwable) {
        $when = $start = null;
    }
    $whenText = $when ? $when . ($start ? ' · ' . $start : '') : trim(($event->date_str ?? '') . ($event->time_str ? ' · ' . $event->time_str : ''));
@endphp

<article {{ $attributes->merge(['class' => 'eb-event-row']) }}>
    <a href="{{ route('events.show', $event->id) }}" class="eb-event-row-link">
        <span class="eb-event-row-media">
            <x-responsive-image :image="$cover" alt="" :eager="$eager" sizes="(min-width: 768px) 180px, 38vw" />
        </span>
        <span class="eb-event-row-body">
            @if($category !== '')
                <span class="eb-event-row-tag eb-event-row-tag-{{ $tone }}">{{ $category }}</span>
            @endif
            <{{ $tag }} class="eb-event-row-title eb-clamp-2">{{ $event->title }}</{{ $tag }}>
            @if($whenText !== '')
                <span class="eb-event-row-meta"><x-icon name="calendar" size="sm" /><span>{{ $whenText }}</span></span>
            @endif
            @if($venue)
                <span class="eb-event-row-meta"><x-icon name="map-pin" size="sm" /><span class="eb-clamp-1">{{ $venue }}</span></span>
            @endif
        </span>
        <span class="eb-event-row-arrow" aria-hidden="true"><x-icon name="arrow-right" size="sm" /></span>
    </a>
</article>
