{{-- Compact event header shown on every ordering step. --}}
<header class="eb-checkout-event">
    <x-responsive-image :image="$event->coverImage()" alt="" sizes="64px" width="64" height="64" :eager="true" />
    <div style="min-width: 0;">
        <a href="{{ route('events.show', $event->id) }}" class="eb-text-label" style="display: inline-block; padding: 10px 0;">← Back to event</a>
        <h1 class="eb-text-page-title eb-clamp-2" style="margin: 2px 0 0;">{{ $event->title }}</h1>
        <p class="eb-text-supporting" style="margin: 2px 0 0;">{{ $event->date_str }} · {{ $event->venue_name }}</p>
    </div>
</header>
