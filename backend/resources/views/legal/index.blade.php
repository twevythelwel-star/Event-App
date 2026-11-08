@extends('layouts.app')

@section('title', 'Policies and legal information — EventBite')
@section('page-title', 'Policies')

@section('content')
<div class="eb-stack eb-legal" style="gap: var(--layout-section-gap); max-width: 900px;">
    <x-page-header title="Policies and legal information" description="The terms, policies and notices that apply when you use EventBite." />
    @include('legal._notice')
    <nav aria-label="Policies" class="eb-grid-2" style="gap: 0.75rem;">
        @foreach($pages as $slug => $page)
            <a class="eb-card" href="{{ $slug === 'jurisdictions' ? route('legal.jurisdictions') : route('legal.show', $slug) }}" style="padding: 1rem 1.25rem; color: inherit; text-decoration: none;">
                <span style="display: block; font-weight: 700; color: var(--color-accent);">{{ $page['title'] }}</span>
                <span class="eb-help-text" style="display: block;">{{ $page['summary'] }}</span>
                <span class="eb-help-text" style="display: block;">For: {{ $page['audience'] }}</span>
            </a>
        @endforeach
    </nav>
</div>
@endsection
