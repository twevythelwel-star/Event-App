@extends('layouts.app')

@section('title', 'Saved events — EventBite')
@section('page-title', 'Saved events')

@section('content')
<div class="eb-dashboard-shell eb-stack" style="gap: var(--layout-section-gap);">
    <x-page-header
        title="Saved events"
        badge="Your bookmarks"
        description="Events you bookmarked while browsing. Save or remove them from any event page.">
        <x-slot:actions>
            <x-button variant="primary" icon="search" :href="route('events.index')">Discover events</x-button>
        </x-slot:actions>
    </x-page-header>

    <section aria-label="Saved events">
        @if($events->isEmpty())
            <x-empty-state
                icon="heart"
                title="No saved events yet"
                description="Open any event and choose Save event to keep it here for later.">
                <x-slot:actions>
                    <x-button variant="primary" :href="route('events.index')">Browse events</x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <div class="eb-grid-4">
                @foreach($events as $event)
                    <div class="eb-stack" style="gap: 0.5rem;">
                        <x-event-card :event="$event" />
                        <form method="POST" action="{{ route('events.save.toggle', $event->id) }}">
                            @csrf
                            <button type="submit" class="eb-btn eb-btn-ghost eb-btn-sm" style="width: 100%;">
                                <x-icon name="heart" size="sm" />
                                <span>Remove from saved</span>
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection
