{{-- Compact event header shown on every ordering step. --}}
<header class="eb-checkout-event">
    <img src="{{ $event->display_image_url }}" alt="" width="64" height="64">
    <div style="min-width: 0;">
        <a href="{{ route('events.show', $event->id) }}" class="eb-text-label" style="display: inline-block; padding: 10px 0;">← Back to event</a>
        <h1 class="eb-text-page-title eb-clamp-2" style="margin: 2px 0 0;">{{ $event->title }}</h1>
        <p class="eb-text-supporting" style="margin: 2px 0 0;">{{ $event->date_str }} · {{ $event->venue_name }}</p>
    </div>
</header>
