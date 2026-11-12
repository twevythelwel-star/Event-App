@extends('layouts.app')

@section('title', $title . ' — EventBite')
@section('page-title', $title)

@section('content')
<div class="eb-dashboard-shell" style="max-width: 640px; margin: 0 auto;">
    <section class="eb-card eb-panel" aria-labelledby="qr-status-title" style="text-align: center; padding: 2.5rem 1.5rem;">
        <span class="eb-badge eb-badge-warning" style="margin-bottom: 1rem;">{{ $badge }}</span>
        <h1 id="qr-status-title" class="eb-text-section-title" style="margin: 0 0 0.5rem;">{{ $title }}</h1>
        @if($eventTitle)
            <p class="eb-text-card-title" style="margin: 0 0 0.75rem;">{{ $eventTitle }}</p>
        @endif
        <p class="eb-text-supporting" style="margin: 0 0 1.5rem;">{{ $message }}</p>
        <div class="eb-cluster" style="justify-content: center; gap: var(--space-2);">
            <x-button variant="primary" icon="events" :href="route('events.index')">Browse events</x-button>
            <x-button variant="secondary" :href="route('home')">EventBite home</x-button>
        </div>
        <p class="eb-help-text" style="margin-top: 1.25rem;">
            Scanned a promotional event code? It opens event details only — tickets are admitted with your personal ticket QR.
        </p>
    </section>
</div>
@endsection
