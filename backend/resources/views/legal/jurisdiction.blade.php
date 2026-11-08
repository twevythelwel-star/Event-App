@extends('layouts.app')

@section('title', $state->name . ' compliance information — EventBite')
@section('page-title', $state->name)

@push('head')
<style>.eb-legal a:not([class]) { color: var(--color-accent); text-decoration: underline; text-underline-offset: 2px; overflow-wrap: anywhere; }</style>
@endpush

@section('content')
@php
    $tone = fn ($record) => $record->isVerified() ? 'eb-badge-success' : 'eb-badge-warning';
@endphp
<div class="eb-stack eb-legal" style="gap: var(--layout-section-gap); max-width: 900px;">
    <x-page-header :title="$state->name . ': compliance information'" description="Government agencies responsible for food and alcohol, and the requirements EventBite has recorded." />
    <p style="margin: 0;"><a href="{{ route('legal.jurisdictions') }}">All states</a></p>
    @include('legal._notice')

    <section class="eb-card" style="padding: 1.25rem 1.5rem;" aria-labelledby="agencies-title">
        <h2 id="agencies-title" class="eb-text-section-title" style="margin-top: 0;">State agencies</h2>
        @forelse($authorities as $authority)
            <div style="padding: 0.6rem 0; border-top: 1px solid var(--color-border);">
                <div class="eb-cluster" style="justify-content: space-between; gap: 0.5rem;">
                    <strong>{{ $authority->name }}</strong>
                    <span class="eb-badge {{ $tone($authority) }}">{{ $authority->statusLabel() }}</span>
                </div>
                <div class="eb-help-text">{{ \App\Models\Compliance\ComplianceAuthority::AREAS[$authority->area] ?? $authority->area }}</div>
                @if($authority->website_url)<div><a href="{{ $authority->website_url }}" rel="noopener noreferrer" target="_blank">{{ $authority->website_url }}</a></div>@endif
            </div>
        @empty
            <p style="margin: 0;">No agencies recorded yet. Contact the state's health department and alcohol control agency.</p>
        @endforelse
    </section>

    <section class="eb-card" style="padding: 1.25rem 1.5rem;" aria-labelledby="req-title">
        <h2 id="req-title" class="eb-text-section-title" style="margin-top: 0;">Requirements</h2>
        <p class="eb-help-text">Only verified requirements show details. Every other topic needs checking with the agency.</p>
        @foreach($requirements as $requirement)
            <div style="padding: 0.6rem 0; border-top: 1px solid var(--color-border);">
                <div class="eb-cluster" style="justify-content: space-between; gap: 0.5rem;">
                    <strong>{{ $requirement->topicLabel() }}</strong>
                    <span class="eb-badge {{ $tone($requirement) }}">{{ $requirement->statusLabel() }}</span>
                </div>
                @if($requirement->isVerified())
                    @if($requirement->summary)<p style="margin: 0.3rem 0;">{{ $requirement->summary }}</p>@endif
                    @if($requirement->source_url)<div class="eb-help-text">Source: <a href="{{ $requirement->source_url }}" rel="noopener noreferrer" target="_blank">{{ $requirement->source_title ?: $requirement->source_url }}</a>{{ $requirement->last_verified_date ? ', checked ' . $requirement->last_verified_date->format('j M Y') : '' }}</div>@endif
                @else
                    <p class="eb-help-text" style="margin: 0.3rem 0 0;">EventBite has not verified this. Confirm it with {{ $requirement->authority?->name ?? 'the responsible agency' }}.</p>
                @endif
            </div>
        @endforeach
    </section>

    @if($federal && $federal->authorities->isNotEmpty())
        <section class="eb-card" style="padding: 1.25rem 1.5rem;" aria-labelledby="federal-title">
            <h2 id="federal-title" class="eb-text-section-title" style="margin-top: 0;">Federal agencies</h2>
            @foreach($federal->authorities as $authority)
                <div style="padding: 0.4rem 0;"><strong>{{ $authority->name }}</strong>@if($authority->website_url) · <a href="{{ $authority->website_url }}" rel="noopener noreferrer" target="_blank">{{ $authority->website_url }}</a>@endif</div>
            @endforeach
        </section>
    @endif

    <p class="eb-help-text" style="margin: 0;">Counties and cities often have their own requirements. Contact the county health department and city clerk where your event takes place.</p>
</div>
@endsection
