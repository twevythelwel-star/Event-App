@extends('layouts.app')

@section('title', $page['title'] . ' — EventBite')
@section('page-title', 'Jurisdiction information')

@push('head')
<style>.eb-legal a:not([class]) { color: var(--color-accent); text-decoration: underline; text-underline-offset: 2px; }</style>
@endpush

@section('content')
<div class="eb-stack eb-legal" style="gap: var(--layout-section-gap); max-width: 900px;">
    <x-page-header :title="$page['title']" :description="$page['summary']" />
    <p style="margin: 0;"><a href="{{ route('legal.index') }}">All policies</a></p>
    @include('legal._notice')

    <section class="eb-card" style="padding: 1.25rem 1.5rem;">
        <h2 class="eb-text-section-title" style="margin-top: 0;">How requirements work in the United States</h2>
        <p>Food and alcohol at events are regulated by federal agencies and, in more detail, by each state, and often by counties and cities. Requirements differ between places: one state's rules never apply in another.</p>
        <p>The list below shows the agencies EventBite has recorded for each state, taken from official government directories. Each entry shows whether EventBite has verified it. An entry marked "Verification Required" or "Draft" has not been confirmed, so contact the agency directly.</p>
        <p style="margin: 0;">Hosts: your event's own requirements checklist shows what applies to your event's location and arrangements.</p>
    </section>

    <section aria-labelledby="states-title">
        <h2 id="states-title" class="eb-text-section-title">Choose a state</h2>
        @if($states->isEmpty())
            <x-alert type="info">State information has not been loaded yet.</x-alert>
        @else
            <ul class="eb-grid-3" style="list-style: none; padding: 0; margin: 0; gap: 0.4rem;">
                @foreach($states as $state)<li><a href="{{ route('legal.jurisdiction', $state->code) }}">{{ $state->name }}</a></li>@endforeach
            </ul>
        @endif
    </section>
</div>
@endsection
