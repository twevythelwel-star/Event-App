@extends('layouts.app')

@section('title', $page['title'] . ' — EventBite')
@section('page-title', $page['title'])

@push('head')
<style>
    .eb-legal a:not([class]) { color: var(--color-accent); text-decoration: underline; text-underline-offset: 2px; }
    .eb-legal-body h2 { font-size: 1.05rem; margin: 1.25rem 0 0.4rem; }
    .eb-legal-body p, .eb-legal-body li { line-height: 1.6; margin: 0 0 0.6rem; }
</style>
@endpush

@section('content')
<div class="eb-stack eb-legal" style="gap: var(--layout-section-gap); max-width: 900px;">
    <x-page-header :title="$page['title']" :description="$page['summary']" />
    <p style="margin: 0;"><a href="{{ route('legal.index') }}">All policies</a></p>

    @include('legal._notice')

    <dl class="eb-grid-3 eb-card" style="padding: 1rem 1.25rem; margin: 0;">
        <div><dt class="eb-help-text">Version</dt><dd style="margin: 0;" data-policy-version="{{ $version->version }}">{{ $version->version }}{{ $isCurrent ? ' (in force)' : '' }}</dd></div>
        <div><dt class="eb-help-text">Effective</dt><dd style="margin: 0;">{{ ($version->effective_at ?? $version->published_at)?->format('j F Y') }}</dd></div>
        <div><dt class="eb-help-text">Published</dt><dd style="margin: 0;">{{ $version->published_at?->format('j F Y') }}</dd></div>
        <div><dt class="eb-help-text">Applies to</dt><dd style="margin: 0;">{{ $page['audience'] }}</dd></div>
        <div><dt class="eb-help-text">Policy identifier</dt><dd style="margin: 0;">{{ $version->policy_name }}</dd></div>
    </dl>

    @unless($isCurrent)
        <x-alert type="info" title="Earlier version">This is an earlier version. <a href="{{ route('legal.show', $slug) }}">Read the version in force</a>.</x-alert>
    @endunless
    @if($upcoming && $isCurrent)
        <x-alert type="info" title="Update coming">A new version ({{ $upcoming->version }}) takes effect on {{ $upcoming->effective_at->format('j F Y') }}. <a href="{{ route('legal.show', [$slug, 'version' => $upcoming->version]) }}">Read it</a>.</x-alert>
    @endif

    <article class="eb-card eb-legal-body" style="padding: 1.25rem 1.5rem;">
        @php $hasHeadings = collect($blocks)->contains('type', 'heading'); @endphp
        @if($hasHeadings)
            @foreach($blocks as $block)
                @if($block['type'] === 'heading')<h2>{{ $block['text'] }}</h2>@else<p>{{ $block['text'] }}</p>@endif
            @endforeach
        @else
            <h2 style="margin-top: 0;">{{ $version->title }}</h2>
            <ul style="padding-left: 1.2rem;">@foreach($blocks as $block)<li>{{ $block['text'] }}</li>@endforeach</ul>
        @endif
    </article>

    @foreach($related as $heading => $items)
        <section class="eb-card eb-legal-body" style="padding: 1.25rem 1.5rem;" aria-label="{{ $heading }}">
            <h2 style="margin-top: 0;">{{ $heading }}</h2>
            @foreach($items as $item)
                <h3 style="font-size: 0.95rem; margin: 0.75rem 0 0.25rem;">{{ $item['title'] }} <span class="eb-help-text">(version {{ $item['version'] }})</span></h3>
                <p style="white-space: pre-line;">{{ $item['text'] }}</p>
            @endforeach
        </section>
    @endforeach

    @if($history->count() > 1)
        <details class="eb-card" style="padding: 1rem 1.25rem;">
            <summary>Earlier versions</summary>
            <ul style="margin: 0.5rem 0 0; padding-left: 1.2rem;">
                @foreach($history as $old)
                    <li><a href="{{ route('legal.show', [$slug, 'version' => $old->version]) }}">Version {{ $old->version }}</a>, effective {{ ($old->effective_at ?? $old->published_at)?->format('j F Y') }}@if($old->change_summary) · {{ $old->change_summary }}@endif</li>
                @endforeach
            </ul>
        </details>
    @endif
</div>
@endsection
