@extends('layouts.app')

@section('title', 'Requirements for ' . $event->title . ' — EventBite')
@section('page-title', 'Event requirements')

@section('content')
<div class="eb-stack" style="gap: var(--layout-section-gap);">
    <header class="eb-page-header">
        <div>
            <h1 class="eb-text-page-title" style="margin:0">Requirements for {{ $event->title }}</h1>
            <p class="eb-text-supporting" style="margin: var(--space-1) 0 0;">
                Worked out from where the event happens, where food is prepared and alcohol served, and how they are provided.
                <a href="{{ route('events.edit', $event->id) }}#studio-event-compliance">Change these details</a>
            </p>
        </div>
    </header>

    @if(session('status'))<x-alert type="success">{{ session('status') }}</x-alert>@endif
    @if($errors->any())<x-alert type="error" title="Not submitted">{{ $errors->first() }}</x-alert>@endif

    <x-alert type="info" title="What the statuses mean">
        <strong>Required</strong> and <strong>Not Required</strong> come from rules EventBite administrators set from official sources.
        <strong>Verification Required</strong> means EventBite has not confirmed whether it applies here.
        Your submissions show as <strong>Submitted</strong> until an administrator reviews them (<strong>Pending Verification</strong>),
        then <strong>Verified</strong> or <strong>Rejected</strong>. A verified permit past its expiry date shows as <strong>Expired</strong>.
    </x-alert>

    <section class="eb-panel card" style="margin:0">
        @include('events.partials.requirements-checklist', ['checklist' => $checklist, 'event' => $event, 'interactive' => true])
    </section>
</div>
@endsection
