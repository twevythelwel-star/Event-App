@extends('layouts.app')

@section('title', 'EventBite — Events, Food & Culture Across the U.S.')
@section('page-title', 'Discover events')

@section('content')
@php
    $marketValue = fn ($m) => strtoupper($m->state_code) . ':' . strtolower($m->city);
    $sections = [
        ['id' => 'featured', 'title' => 'Featured events', 'note' => 'Coming up soonest in our featured cities.', 'events' => $featured, 'more' => route('events.index')],
        ['id' => 'food-festivals', 'title' => 'Upcoming food festivals', 'note' => 'A range of cuisines, one event per tradition first.', 'events' => $foodFestivals, 'more' => route('events.index', ['category' => 'food'])],
        ['id' => 'music', 'title' => 'Live music & nightlife', 'note' => 'Concerts, DJs and dances across genres.', 'events' => $music, 'more' => route('events.index', ['category' => 'music'])],
        ['id' => 'culture', 'title' => 'Cultural & community celebrations', 'note' => null, 'events' => $culture, 'more' => route('events.index', ['category' => 'culture'])],
        ['id' => 'family', 'title' => 'Family-friendly', 'note' => 'Family categories or family and child tickets.', 'events' => $family, 'more' => route('events.index', ['q' => 'family'])],
        ['id' => 'food-traditions', 'title' => 'Food from many traditions', 'note' => 'Cookouts and food days from different culinary traditions.', 'events' => $foodTraditions, 'more' => route('events.index', ['category' => 'food'])],
        ['id' => 'seasonal', 'title' => 'Seasonal & cultural celebrations', 'note' => 'Celebrations in the next few months.', 'events' => $seasonal, 'more' => null],
    ];
@endphp
<style>
    .eb-home { display: flex; flex-direction: column; gap: var(--space-8, 2.5rem); max-width: 1240px; margin: 0 auto; }
    .eb-home-hero { padding: var(--space-6, 2rem); border-radius: var(--radius-lg, 16px); background: var(--color-surface, #10243A); border: 1px solid var(--color-border, #26384D); }
    .eb-home-hero h1 { margin: 0 0 var(--space-2, .5rem); }
    .eb-home-search { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) auto; gap: var(--space-3, .75rem); margin-top: var(--space-5, 1.5rem); align-items: end; }
    .eb-home-near { display: flex; flex-wrap: wrap; gap: var(--space-3, .75rem); align-items: end; margin-top: var(--space-4, 1rem); }
    .eb-home-chips { display: flex; flex-wrap: wrap; gap: var(--space-2, .5rem); margin: 0; padding: 0; list-style: none; }
    .eb-home-chips a { display: inline-flex; gap: 6px; align-items: center; padding: 6px 14px; border-radius: 9999px; border: 1px solid var(--color-border, #26384D); color: var(--color-text-primary, #F8FAFC); text-decoration: none; font-size: .875rem; font-weight: 600; }
    .eb-home-chips a:hover, .eb-home-chips a:focus-visible { border-color: var(--color-accent, #C7FF45); }
    .eb-home-section-head { display: flex; justify-content: space-between; align-items: baseline; gap: var(--space-3, .75rem); margin-bottom: var(--space-3, .75rem); }
    .eb-home-markets { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: var(--space-3, .75rem); margin: 0; padding: 0; list-style: none; }
    .eb-home-markets a { display: block; padding: var(--space-3, .75rem) var(--space-4, 1rem); border-radius: var(--radius-md, 12px); background: var(--color-surface, #10243A); border: 1px solid var(--color-border, #26384D); color: var(--color-text-primary, #F8FAFC); text-decoration: none; }
    .eb-home-steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 170px), 1fr)); gap: var(--space-3, .75rem); margin: 0; padding: 0; list-style: none; counter-reset: step; }
    .eb-home-steps li { padding: var(--space-4, 1rem); border-radius: var(--radius-md, 12px); background: var(--color-background-secondary, #0B192B); border: 1px solid var(--color-border, #26384D); }
    .eb-home-steps li::before { counter-increment: step; content: counter(step); display: inline-grid; place-items: center; width: 28px; height: 28px; margin-bottom: var(--space-2, .5rem); border-radius: 50%; background: var(--color-accent-subtle); color: var(--color-accent, #C7FF45); font-weight: 800; font-size: .85rem; }
    .eb-home-steps strong { display: block; color: var(--color-text-primary, #F8FAFC); }
    @media (max-width: 720px) { .eb-home-search { grid-template-columns: 1fr; } }
</style>

<div class="eb-home">
    <section class="eb-home-hero" aria-labelledby="home-heading">
        <h1 id="home-heading" class="eb-text-display">Events, food and culture across the U.S.</h1>
        <p class="eb-text-supporting" style="margin: 0; max-width: 60ch;">
            Find food festivals, cookouts, live music and community celebrations from many traditions. Buy tickets and order food for pickup in one place.
        </p>

        <form method="GET" action="{{ route('events.index') }}" class="eb-home-search" role="search" aria-label="Search events">
            <div class="eb-form-group" style="margin: 0;">
                <label for="home-q" class="eb-label">Search events, cities and towns</label>
                <input id="home-q" name="q" type="search" class="eb-input" placeholder="e.g. tamales, jollof, Atlanta, family day" autocomplete="off">
            </div>
            <div class="eb-form-group" style="margin: 0;">
                <label for="home-city" class="eb-label">Location</label>
                <select id="home-city" name="city" class="eb-input">
                    <option value="">United States (all)</option>
                    @foreach($allMarkets as $m)
                        <option value="{{ $marketValue($m) }}" @selected($market && $market->id === $m->id)>{{ $m->city }}, {{ $m->state_code }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="eb-btn eb-btn-primary">Search</button>
        </form>

        <form method="GET" action="{{ route('home') }}" class="eb-home-near" id="home-near-form">
            <div class="eb-form-group" style="margin: 0;">
                <label for="home-market" class="eb-label">Show events near</label>
                <select id="home-market" name="market" class="eb-input" onchange="this.form.submit()">
                    <option value="">Anywhere in the United States</option>
                    @foreach($allMarkets as $m)
                        <option value="{{ $m->id }}" @selected($market && $market->id === $m->id)>{{ $m->city }}, {{ $m->state_code }}</option>
                    @endforeach
                </select>
            </div>
            <noscript><button type="submit" class="eb-btn eb-btn-secondary">Show</button></noscript>
        </form>
    </section>

    {{-- What EventBite does, in the order people do it --}}
    <section aria-labelledby="how-heading">
        <h2 id="how-heading" class="sr-only">How EventBite works</h2>
        <ol class="eb-home-steps">
            <li><strong>Discover</strong><span class="eb-text-supporting">Events from many cultures and communities.</span></li>
            <li><strong>Find events near you</strong><span class="eb-text-supporting">Search by city, town or tradition.</span></li>
            <li><strong>Buy tickets</strong><span class="eb-text-supporting">Prices, fees and taxes shown before you pay.</span></li>
            <li><strong>Order food</strong><span class="eb-text-supporting">Pre-order from the event's menu for pickup.</span></li>
            <li><strong>Pay your way</strong><span class="eb-text-supporting">Bank transfer, Zelle, Cash App or cash, wherever the host accepts it.</span></li>
            <li><strong>Get your ticket</strong><span class="eb-text-supporting">Your QR ticket appears once the payment is confirmed, and is checked at the door.</span></li>
        </ol>
    </section>

    @if($traditions !== [])
        <section aria-labelledby="traditions-heading">
            <div class="eb-home-section-head">
                <h2 id="traditions-heading" class="eb-text-section-title" style="margin: 0;">Browse by tradition</h2>
            </div>
            <ul class="eb-home-chips">
                @foreach($traditions as $label => $count)
                    <li><a href="{{ route('events.index', ['q' => $label]) }}">{{ $label }} <span class="eb-text-supporting">{{ $count }}</span></a></li>
                @endforeach
            </ul>
        </section>
    @endif

    @if($market)
        <section aria-labelledby="near-heading">
            <div class="eb-home-section-head">
                <h2 id="near-heading" class="eb-text-section-title" style="margin: 0;">Near {{ $market->city }}, {{ $market->state_code }}</h2>
                <a class="eb-text-label" href="{{ route('events.index', ['city' => $marketValue($market)]) }}">See all in {{ $market->city }} →</a>
            </div>
            @if($near->isEmpty())
                <x-empty-state title="No upcoming events in {{ $market->state_code }} yet" description="Try another city, or browse every event." />
            @else
                <div class="eb-grid-4">
                    @foreach($near as $event)
                        <x-event-card :event="$event" />
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    @foreach($sections as $section)
        @continue($section['events']->isEmpty())
        <section aria-labelledby="{{ $section['id'] }}-heading">
            <div class="eb-home-section-head">
                <div>
                    <h2 id="{{ $section['id'] }}-heading" class="eb-text-section-title" style="margin: 0;">{{ $section['title'] }}</h2>
                    @if($section['note'])<p class="eb-text-supporting" style="margin: 2px 0 0;">{{ $section['note'] }}</p>@endif
                </div>
                @if($section['more'])<a class="eb-text-label" href="{{ $section['more'] }}">See all →</a>@endif
            </div>
            <div class="eb-grid-4">
                @foreach($section['events'] as $event)
                    <x-event-card :event="$event" />
                @endforeach
            </div>
        </section>
    @endforeach

    @if($markets->isNotEmpty())
        <section aria-labelledby="markets-heading">
            <div class="eb-home-section-head">
                <h2 id="markets-heading" class="eb-text-section-title" style="margin: 0;">Popular cities</h2>
            </div>
            <ul class="eb-home-markets">
                @foreach($markets as $m)
                    <li>
                        <a href="{{ route('events.index', ['city' => $marketValue($m)]) }}">
                            <strong>{{ $m->city }}, {{ $m->state_code }}</strong><br>
                            <span class="eb-text-supporting">{{ $m->upcoming_count }} upcoming {{ Str::plural('event', $m->upcoming_count) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if($featured->isEmpty())
        <x-empty-state title="No upcoming events yet" description="Check back soon, or create an account to host your own." />
    @endif

    <section class="eb-card" style="display: flex; flex-wrap: wrap; gap: var(--space-3); align-items: center; justify-content: space-between;">
        <p class="eb-text-supporting" style="margin: 0;">Keep your tickets and food orders in one place.</p>
        <div style="display: flex; gap: var(--space-2);">
            <a href="{{ route('register') }}" class="eb-btn eb-btn-primary">Create account</a>
            <a href="{{ route('login') }}" class="eb-btn eb-btn-secondary">Sign in</a>
        </div>
    </section>
</div>
@endsection
