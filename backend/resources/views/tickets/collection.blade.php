@extends('layouts.app')

@section('title', 'My Tickets — EventBite')
@section('page-title', 'My Tickets')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/ticket-collection.css') }}">
    <script src="{{ asset('js/ticket-collection.js') }}" defer></script>
@endpush

@section('content')
<div class="eb-stack eb-ticket-collection" style="gap: var(--space-5);">
    <header class="eb-section-header">
        <div>
            <h1 class="eb-text-page-title" style="margin: 0;">My Tickets</h1>
            <p class="eb-text-supporting" style="margin: 0;">Every ticket you hold, from the next event to the ones you keep as souvenirs. Open one to see the full ticket.</p>
        </div>
        <x-button variant="ghost" icon="events" :href="route('events.index')">Find events</x-button>
    </header>

    <nav class="eb-tabs eb-collection-tabs" role="tablist" aria-label="Ticket collection">
        @foreach($tabMeta as $key => $meta)
            <a href="{{ route('tickets.collection', ['tab' => $key]) }}" role="tab" id="tab-{{ $key }}" aria-controls="panel-{{ $key }}"
               aria-selected="{{ $current === $key ? 'true' : 'false' }}" class="eb-tab {{ $current === $key ? 'is-active' : '' }}"
               data-collection-tab="{{ $key }}">
                {{ $meta['label'] }} <span class="eb-collection-count">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </nav>

    @foreach($tabMeta as $key => $meta)
        <section role="tabpanel" id="panel-{{ $key }}" aria-labelledby="tab-{{ $key }}" class="eb-stack" style="gap: var(--space-3);"
                 data-collection-panel="{{ $key }}" @if($current !== $key) hidden @endif>
            <p class="eb-help-text" style="margin: 0;">{{ $meta['hint'] }}</p>
            @if($tabs[$key]->isNotEmpty())
                <div class="eb-collectible-grid">
                    @foreach($tabs[$key] as $entry)
                        @if($key === 'transferred')
                            @if($entry->order)<x-ticket-collectible :order="$entry->order" :transfer="$entry" />@endif
                        @else
                            <x-ticket-collectible :order="$entry" />
                        @endif
                    @endforeach
                </div>
            @else
                <x-empty-state icon="tickets" illustration="tickets"
                    :title="match ($key) {
                        'upcoming' => 'No upcoming tickets',
                        'active' => 'No tickets ready for entry',
                        'past' => 'No keepsakes yet',
                        'transferred' => 'No transferred tickets',
                        default => 'Nothing here',
                    }"
                    :description="match ($key) {
                        'upcoming', 'active' => 'Tickets you buy appear here, ready to open at the gate.',
                        'past' => 'After an event, its ticket stays here as a keepsake.',
                        default => 'Nothing to show, which is good news.',
                    }">
                    @if(in_array($key, ['upcoming', 'active'], true))
                        <x-button variant="primary" icon="events" :href="route('events.index')">Explore events</x-button>
                    @endif
                </x-empty-state>
            @endif
        </section>
    @endforeach
</div>
@endsection
