@extends('layouts.app')

@section('title', 'Schedule studio — ' . $event->title)
@section('page-title', 'Schedule studio')

@section('content')
@php
    $tabs = ['programme' => 'Programme', 'people' => 'Line-up', 'stages' => 'Stages & places', 'preview' => 'Attendee preview'];
    $byDay = $activities->groupBy(fn ($a) => $a->starts_at->copy()->setTimezone($tz)->format('Y-m-d'));
@endphp
<div class="eb-stack" style="gap: var(--space-5);">
    <div>
        <p class="eb-text-supporting" style="margin: 0;"><a href="{{ route('events.edit', $event->id) }}">← {{ $event->title }}</a></p>
        <h1 class="eb-text-page-title" style="margin: 0;">Schedule studio</h1>
        <p class="eb-text-supporting" style="margin: 0;">Plan what happens when and where, who is on, and see the schedule as attendees will.
            @if($event->starts_at && $event->ends_at) The event runs {{ $event->starts_at->copy()->setTimezone($tz)->format('M j, g:i A') }} to {{ $event->ends_at->copy()->setTimezone($tz)->format('M j, g:i A T') }}.@endif</p>
    </div>

    @if($errors->any())
        <x-alert type="error" title="Not saved" id="schedule-errors" tabindex="-1">
            <ul style="margin: 4px 0 0; padding-left: 1.1rem;">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
        </x-alert>
        <script>document.addEventListener('DOMContentLoaded', () => document.getElementById('schedule-errors')?.focus());</script>
    @endif

    <nav class="eb-tabs" role="tablist" aria-label="Schedule studio">
        @foreach($tabs as $key => $label)
            <a href="{{ route('events.schedule-studio', ['id' => $event->id, 'tab' => $key]) }}" role="tab" id="tab-{{ $key }}" aria-controls="panel-{{ $key }}"
               aria-selected="{{ $tab === $key ? 'true' : 'false' }}" class="eb-tab {{ $tab === $key ? 'is-active' : '' }}">{{ $label }}</a>
        @endforeach
    </nav>

    {{-- Programme --}}
    <section role="tabpanel" id="panel-programme" aria-labelledby="tab-programme" class="eb-stack" style="gap: var(--space-4);" @if($tab !== 'programme') hidden @endif>
        <details class="eb-panel" style="margin: 0;" @if($activities->isEmpty()) open @endif>
            <summary class="eb-text-card-title">＋ Add an activity</summary>
            @include('events.partials.activity-form', ['activity' => null])
        </details>
        @forelse($byDay as $date => $list)
            <h2 class="eb-text-section-title" style="margin: 0;">{{ \Illuminate\Support\Carbon::parse($date)->format('l, F j') }}</h2>
            <ul class="eb-stack" style="gap: var(--space-3); list-style: none; margin: 0; padding: 0;">
                @foreach($list as $activity)
                    <li class="eb-panel" style="margin: 0;">
                        <details>
                            <summary class="eb-schedule-studio-row">
                                <span class="eb-schedule-time">{{ $activity->starts_at->copy()->setTimezone($tz)->format('g:i A') }}–{{ $activity->ends_at->copy()->setTimezone($tz)->format('g:i A') }}</span>
                                <strong>{{ $activity->title }}</strong>
                                <span class="eb-badge eb-badge-info">{{ $activity->kindLabel() }}</span>
                                @if($activity->stage)<span class="eb-help-text">{{ $activity->stage->name }}</span>@endif
                                @unless($activity->is_public)<span class="eb-badge">Not public</span>@endunless
                            </summary>
                            @include('events.partials.activity-form', ['activity' => $activity])
                            <form method="POST" action="{{ route('events.schedule-studio.activities.destroy', [$event->id, $activity->id]) }}" onsubmit="return confirm('Remove this activity from the schedule?');" style="margin-top: var(--space-3);">
                                @csrf @method('DELETE')
                                <button type="submit" class="eb-btn eb-btn-ghost eb-btn-sm">Remove activity</button>
                            </form>
                        </details>
                    </li>
                @endforeach
            </ul>
        @empty
            <x-empty-state title="No activities yet" description="Add the performances, DJ sets, talks and food service that make up your event." illustration="events" />
        @endforelse
    </section>

    {{-- Line-up --}}
    <section role="tabpanel" id="panel-people" aria-labelledby="tab-people" class="eb-stack" style="gap: var(--space-4);" @if($tab !== 'people') hidden @endif>
        <details class="eb-panel" style="margin: 0;" @if($performers->isEmpty()) open @endif>
            <summary class="eb-text-card-title">＋ Add a DJ, performer or speaker</summary>
            @include('events.partials.performer-form', ['performer' => null])
        </details>
        @foreach($performers as $person)
            <details class="eb-panel" style="margin: 0;">
                <summary class="eb-schedule-studio-row"><strong>{{ $person->name }}</strong><span class="eb-badge eb-badge-info">{{ $person->roleLabel() }}</span>
                    <span class="eb-help-text">{{ $person->activities()->count() }} {{ \Illuminate\Support\Str::plural('activity', $person->activities()->count()) }}</span></summary>
                @include('events.partials.performer-form', ['performer' => $person])
                <form method="POST" action="{{ route('events.schedule-studio.performers.destroy', [$event->id, $person->id]) }}" onsubmit="return confirm('Remove {{ $person->name }} from the line-up and from every activity?');" style="margin-top: var(--space-3);">
                    @csrf @method('DELETE')
                    <button type="submit" class="eb-btn eb-btn-ghost eb-btn-sm">Remove from line-up</button>
                </form>
            </details>
        @endforeach
    </section>

    {{-- Stages --}}
    <section role="tabpanel" id="panel-stages" aria-labelledby="tab-stages" class="eb-stack" style="gap: var(--space-4);" @if($tab !== 'stages') hidden @endif>
        <form method="POST" action="{{ route('events.schedule-studio.stages.store', $event->id) }}" class="eb-panel eb-stack" style="gap: var(--space-3); margin: 0;">
            @csrf
            <h2 class="eb-text-card-title" style="margin: 0;">Add a stage or place</h2>
            <div class="eb-grid-2">
                <x-form-field name="name" id="stage-name-new" label="Name" :required="true" placeholder="e.g. Main stage, Food court, Kids' tent" />
                <x-form-field name="description" id="stage-desc-new" label="Where to find it (optional)" placeholder="e.g. By the north gate" />
            </div>
            <div><button type="submit" class="eb-btn eb-btn-primary">Add place</button></div>
        </form>
        @foreach($stages as $stage)
            <div class="eb-panel" style="margin: 0;">
                <form method="POST" action="{{ route('events.schedule-studio.stages.update', [$event->id, $stage->id]) }}" class="eb-grid-2" style="align-items: end;">
                    @csrf @method('PUT')
                    <x-form-field name="name" :id="'stage-name-' . $stage->id" label="Name" :value="$stage->name" :required="true" />
                    <x-form-field name="description" :id="'stage-desc-' . $stage->id" label="Where to find it" :value="$stage->description" />
                    <div class="eb-cluster">
                        <button type="submit" class="eb-btn eb-btn-secondary eb-btn-sm">Save</button>
                        <button type="submit" form="stage-delete-{{ $stage->id }}" class="eb-btn eb-btn-ghost eb-btn-sm">Remove</button>
                        <span class="eb-help-text">{{ $stage->activities()->count() }} {{ \Illuminate\Support\Str::plural('activity', $stage->activities()->count()) }}</span>
                    </div>
                </form>
                <form id="stage-delete-{{ $stage->id }}" method="POST" action="{{ route('events.schedule-studio.stages.destroy', [$event->id, $stage->id]) }}" onsubmit="return confirm('Remove this place? Its activities stay on the schedule without a set place.');" hidden>
                    @csrf @method('DELETE')
                </form>
            </div>
        @endforeach
    </section>

    {{-- Preview --}}
    <section role="tabpanel" id="panel-preview" aria-labelledby="tab-preview" class="eb-panel" style="margin: 0;" @if($tab !== 'preview') hidden @endif>
        @if($publicSchedule->isEmpty())
            <p class="eb-text-supporting" style="margin: 0;">Nothing is on the public schedule yet. Activities you add appear here as attendees will see them.</p>
        @else
            @include('events.partials.schedule', ['schedule' => $publicSchedule, 'performers' => $performers->load('activities'), 'headingLevel' => 2])
        @endif
    </section>
</div>
@endsection
