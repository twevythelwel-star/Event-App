@extends('layouts.app')

@section('title', 'Review ' . $event->title . ' — EventBite')
@section('page-title', 'Review and publish')

@section('content')
@php
    $link = fn (string $section) => match (true) {
        $section === 'menu' && \Illuminate\Support\Facades\Route::has('events.menu-studio') => route('events.menu-studio', $event->id),
        // Permits and local requirements are submitted on the Requirements page; identity and host acknowledgements on the host application.
        $section === 'requirements' => route('events.requirements', $event->id),
        $section === 'identity' => route('host.compliance') . '#identity',
        default => route('events.edit', $event->id) . '#studio-' . $section,
    };
    $bySection = collect($issues)->groupBy('section');
    $ready = $blocking === [];
    $tz = $event->timezone ?: config('events.market.default_timezone', 'America/New_York');
@endphp
<div class="eb-stack" style="gap: var(--layout-section-gap);">
    <x-page-header :title="'Review: ' . $event->title" :badge="ucfirst($event->status)"
                   description="Everything is checked before the event goes public. Fix anything marked as needed; recommendations can wait."
                   :breadcrumbs="['Host dashboard' => route('host.dashboard'), $event->title => route('events.edit', $event->id), 'Review' => null]" />

    <section class="eb-panel eb-review-status {{ $ready ? 'is-ready' : 'is-blocked' }}" aria-live="polite">
        <div>
            <h2 class="eb-text-section-title" style="margin: 0;">
                @if($event->status === 'published') This event is published.
                @elseif($ready) Ready to publish.
                @else {{ count($blocking) }} {{ \Illuminate\Support\Str::plural('thing', count($blocking)) }} to finish before publishing.
                @endif
            </h2>
            <p class="eb-text-supporting" style="margin: 4px 0 0;">
                @if($event->status === 'published') Guests can find it and buy tickets.
                @else Until it is published, the event page is visible only to you and nothing can be bought, even with its link.
                @endif
            </p>
        </div>
        @if($event->status !== 'published')
            <form method="POST" action="{{ route('events.publish', $event->id) }}">
                @csrf
                <button type="submit" class="eb-btn eb-btn-primary" @disabled(!$ready) @unless($ready) aria-describedby="publish-blocked" @endunless>Publish event</button>
            </form>
        @endif
    </section>
    @unless($ready)<p id="publish-blocked" class="eb-help-text" style="margin-top: calc(-1 * var(--space-4));">Publishing is available once the items marked as needed are done.</p>@endunless

    <div class="eb-review-grid">
        <section class="eb-panel" aria-labelledby="checks-heading" style="margin: 0;">
            <h2 id="checks-heading" class="eb-text-card-title" style="margin: 0 0 var(--space-3);">Checklist</h2>
            <ul class="eb-review-checks">
                @foreach($sections as $key => $label)
                    @php $found = $bySection->get($key, collect()); $needed = $found->where('critical', true); @endphp
                    <li class="eb-review-check {{ $needed->isNotEmpty() ? 'is-needed' : ($found->isNotEmpty() ? 'is-suggested' : 'is-done') }}">
                        <span class="eb-review-mark" aria-hidden="true">{{ $needed->isNotEmpty() ? '!' : ($found->isNotEmpty() ? '•' : '✓') }}</span>
                        <div style="flex: 1; min-width: 0;">
                            <div style="display: flex; justify-content: space-between; gap: var(--space-2); flex-wrap: wrap;">
                                <strong>{{ $label }}</strong>
                                <span class="eb-help-text">{{ $needed->isNotEmpty() ? 'Needed' : ($found->isNotEmpty() ? 'Recommended' : 'Done') }}</span>
                            </div>
                            @foreach($found as $issue)
                                <p class="eb-review-issue">
                                    <span class="sr-only">{{ $issue['critical'] ? 'Needed:' : 'Recommended:' }}</span>{{ $issue['message'] }}
                                    @if($key !== 'publishing')<a href="{{ $link($key) }}">Fix in {{ $label }}</a>@endif
                                </p>
                            @endforeach
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="eb-panel" aria-labelledby="summary-heading" style="margin: 0;">
            <h2 id="summary-heading" class="eb-text-card-title" style="margin: 0 0 var(--space-3);">What guests will see</h2>
            <x-responsive-image :image="$event->coverImage()" alt="" class="eb-review-cover" sizes="(min-width: 1024px) 420px, 100vw" />
            <dl class="eb-review-facts">
                <div><dt>When</dt><dd>{{ $event->starts_at ? $event->starts_at->copy()->setTimezone($tz)->format('l, F j, Y · g:i A T') : ($event->date_str ?: 'Not set') }}</dd></div>
                <div><dt>Where</dt><dd>{{ $event->venue_name ?: 'Not set' }}{{ $event->venue_address ? ' · ' . $event->venue_address : '' }}</dd></div>
                <div><dt>Host</dt><dd>{{ $event->organizer_name ?: 'Not set' }}{{ $event->organizer_phone ? ' · ' . $event->organizer_phone : '' }}</dd></div>
                <div><dt>Tickets</dt><dd>
                    @forelse($tiers as $tier){{ $tier['name'] }} · {{ \App\Support\Money::formatMinor((int) $tier['price_minor'], $tier['currency']) }}{{ $tier['available'] !== null ? ' · ' . $tier['available'] . ' available' : '' }}@if(!$loop->last)<br>@endif
                    @empty None yet @endforelse
                </dd></div>
                <div><dt>Food</dt><dd>{{ $event->supports_food_ordering === false ? 'Not offered' : $itemCount . ' ' . \Illuminate\Support\Str::plural('item', $itemCount) . ', ' . $packageCount . ' ' . \Illuminate\Support\Str::plural('package', $packageCount) }}</dd></div>
                <div><dt>Compliance</dt><dd>
                    Food safety &amp; allergen disclosure: {{ ($event->temperature_and_sanitation_confirmed && $event->allergen_disclosure_confirmed) ? 'Confirmed' : 'Pending confirmation' }}
                    · Alcohol service: {{ $event->serves_alcohol ? ('Yes (' . ($event->alcohol_age_verification_required ? 'ID age verification required' : 'Age check needed') . ')') : 'No alcohol served' }}
                </dd></div>
            </dl>
            <div class="eb-cluster" style="gap: var(--space-2); flex-wrap: wrap;">
                <a class="eb-btn eb-btn-secondary eb-btn-sm" href="{{ route('events.edit', $event->id) }}">Back to editing</a>
                @if(\Illuminate\Support\Facades\Route::has('events.preview'))<a class="eb-btn eb-btn-ghost eb-btn-sm" href="{{ route('events.preview', $event->id) }}">Preview the event page</a>@endif
            </div>
        </section>
    </div>
</div>
@endsection
