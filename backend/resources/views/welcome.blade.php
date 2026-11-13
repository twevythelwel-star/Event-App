@extends('layouts.app')

@section('title', 'EventBite — Events, Food & Culture Across the U.S.')
@section('page-title', 'Discover events')

@section('content')
@php
    $marketValue = fn ($m) => strtoupper($m->state_code) . ':' . strtolower($m->city);
    $sections = [
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
    .eb-home-near { display: flex; flex-wrap: wrap; gap: var(--space-3, .75rem); align-items: end; margin: 0; }
    .eb-home-section-head { display: flex; justify-content: space-between; align-items: baseline; gap: var(--space-3, .75rem); margin-bottom: var(--space-3, .75rem); }
    .eb-home-markets { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: var(--space-3, .75rem); margin: 0; padding: 0; list-style: none; }
    .eb-home-markets a { display: block; padding: var(--space-3, .75rem) var(--space-4, 1rem); border-radius: var(--radius-md, 12px); background: var(--color-surface, #10243A); border: 1px solid var(--color-border, #26384D); color: var(--color-text-primary, #F8FAFC); text-decoration: none; }
    .eb-home-steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 170px), 1fr)); gap: var(--space-3, .75rem); margin: 0; padding: 0; list-style: none; counter-reset: step; }
    .eb-home-steps li { padding: var(--space-4, 1rem); border-radius: var(--radius-md, 12px); background: var(--color-background-secondary, #0B192B); border: 1px solid var(--color-border, #26384D); }
    .eb-home-steps li::before { counter-increment: step; content: counter(step); display: inline-grid; place-items: center; width: 28px; height: 28px; margin-bottom: var(--space-2, .5rem); border-radius: 50%; background: var(--color-accent-subtle); color: var(--color-accent, #C7FF45); font-weight: 800; font-size: .85rem; }
    .eb-home-steps strong { display: block; color: var(--color-text-primary, #F8FAFC); }
</style>

<div class="eb-home">
    @php
        // Photography leads the hero: events with the host's own flyer first, then events shown by an illustrative
        // photo of their kind (labelled), then events with only their generated cover; soonest first within each.
        $heroArt = $featured->sortByDesc(fn ($event) => $event->hasFlyer() ? 2 : ($event->coverImage()->illustrative ? 1 : 0))->take(3)->values();
        // Event collections by kind of event, each with a photograph from the library when one is approved.
        $library = app(\App\Services\PhotoLibrary::class);
        $collections = [];
        foreach ((array) config('photo_library.groups') as $groupKey => $groupLabel) {
            $subjectKeys = array_keys(array_filter(\App\Services\PhotoLibrary::subjects(), fn ($s) => $s['group'] === $groupKey));
            $count = \App\Models\Event::allTenants()->where('status', 'published')->where('visibility', 'public')->whereIn('photo_subject', $subjectKeys)
                ->where(fn ($q) => $q->whereNull('event_date_iso')->orWhereDate('event_date_iso', '>=', now()->toDateString()))->count();
            $photo = collect($subjectKeys)->map(fn ($key) => $library->forSubject($key)->first())->filter()->first();
            if ($count > 0) {
                $collections[] = ['key' => $groupKey, 'label' => $groupLabel, 'count' => $count,
                    'image' => $photo ? \App\Support\ResponsiveImage::forLibraryPhoto($photo) : null];
            }
        }
        // A real photograph for each tradition: the flyer of an upcoming event that celebrates it.
        $traditionPhotos = [];
        foreach (collect($sections)->pluck('events')->prepend($featured)->map(fn ($events) => collect($events))->flatten(1)->unique('id') as $listed) {
            if (!$listed->hasFlyer() || !method_exists($listed, 'traditionLabels')) {
                continue;
            }
            foreach ($listed->traditionLabels() as $label) {
                $traditionPhotos[$label] ??= $listed;
            }
        }
    @endphp
    @php
        $heroLead = $heroArt->first();
        // The hero needs a photograph behind the headline. A generated flyer is text, so when the lead event has no
        // photo, a photo of a dish on a featured event's menu stands in (labelled when it is illustrative).
        $heroCover = $heroLead?->coverImage();
        $heroAlt = $heroLead ? 'Featured: ' . $heroLead->title . ($heroCover->label ? ' (illustrative photo)' : '') : null;
        if ($heroLead && !$heroLead->hasFlyer() && !$heroCover->illustrative) {
            $heroDish = $featured->flatMap(fn ($e) => $e->menuItems->where('is_active', true)->map(fn ($item) => [$e, $item, $item->responsiveImage()]))
                ->first(fn ($row) => $row[2] !== null);
            if ($heroDish) {
                [$heroLead, $heroItem, $heroCover] = $heroDish;
                $heroAlt = 'Featured: ' . $heroItem->name . ' at ' . $heroLead->title . ($heroCover->illustrative ? ' (illustrative photo)' : '');
            } else {
                $heroCover = null;
            }
        }
    @endphp
    @if($heroCover)
        @push('head')
            <x-preload-image :image="$heroCover" sizes="(min-width: 1024px) 1240px, 100vw" />
        @endpush
    @endif
    {{-- Phone-first photo hero: a featured event's own photograph fills the panel behind the headline and search. --}}
    <section class="eb-home-hero eb-hero-cinematic eb-home-photo-hero" aria-labelledby="home-heading">
        @if($heroCover)
            <div class="eb-hero-art">
                <x-responsive-image :image="$heroCover" :alt="$heroAlt" :eager="true" sizes="(min-width: 1024px) 1240px, 100vw" />
                <x-image-label :image="$heroCover" corner="top-right" />
            </div>
        @endif
        <div class="eb-hero-backdrop" aria-hidden="true"></div>
        <div class="eb-home-photo-hero-content">
            <h1 id="home-heading" class="eb-home-photo-hero-title">Discover. Enjoy. <span>Make Memories.</span></h1>
            <p class="eb-home-photo-hero-kicker">Events <span aria-hidden="true">•</span> Food <span aria-hidden="true">•</span> Culture <span aria-hidden="true">•</span> More</p>
            <p class="eb-home-photo-hero-lede">Events, food and culture across the U.S. Find food festivals, cookouts, live music and community celebrations, then buy tickets and order food in one place.</p>

            <form method="GET" action="{{ route('events.index') }}" class="eb-home-search" role="search" aria-label="Search events">
                <div class="eb-search-pill">
                    <x-icon name="search" size="md" />
                    <label for="home-q" class="sr-only">Search events, cities and towns</label>
                    <input id="home-q" name="q" type="search" placeholder="Search events..." autocomplete="off">
                    <button type="submit" class="eb-search-pill-go"><x-icon name="arrow-right" size="sm" /><span class="sr-only">Search</span></button>
                </div>
                <div class="eb-home-search-where">
                    <label for="home-city" class="eb-home-search-where-label"><x-icon name="map-pin" size="sm" /><span>Location</span></label>
                    <select id="home-city" name="city" class="eb-input">
                        <option value="">United States (all)</option>
                        @foreach($allMarkets as $m)
                            <option value="{{ $marketValue($m) }}" @selected($market && $market->id === $m->id)>{{ $m->city }}, {{ $m->state_code }}</option>
                        @endforeach
                    </select>
                </div>
            </form>

            <ul class="eb-home-chips" aria-label="Browse by category">
                <li><a href="{{ route('events.index', ['category' => 'music']) }}"><x-icon name="music" size="sm" /><span>Music</span></a></li>
                <li><a href="{{ route('events.index', ['category' => 'food']) }}"><x-icon name="utensils" size="sm" /><span>Food</span></a></li>
                <li><a href="{{ route('events.index', ['category' => 'culture']) }}"><x-icon name="users" size="sm" /><span>Culture</span></a></li>
                <li><a href="{{ route('events.index', ['q' => 'family']) }}"><x-icon name="heart" size="sm" /><span>Family</span></a></li>
            </ul>

            @if($heroLead)
                <a href="{{ route('events.show', $heroLead->id) }}" class="eb-home-photo-hero-featured">
                    <span class="eb-badge eb-badge-accent">Featured</span>
                    <span class="eb-clamp-1">{{ $heroLead->title }}</span>
                    <x-icon name="arrow-right" size="sm" />
                </a>
            @endif
        </div>
    </section>

    {{-- Upcoming events as compact rows: photo, category tag, title, date, venue and an arrow. --}}
    @if($featured->isNotEmpty())
        <section aria-labelledby="upcoming-heading">
            <div class="eb-home-section-head">
                <div>
                    <h2 id="upcoming-heading" class="eb-text-section-title" style="margin: 0;">Featured events</h2>
                    <p class="eb-text-supporting" style="margin: 2px 0 0;">Upcoming events in our featured cities, soonest first.</p>
                </div>
                <a class="eb-text-label" href="{{ route('events.index') }}">See all →</a>
            </div>
            <div class="eb-event-rows">
                @foreach($featured as $event)
                    <x-event-row :event="$event" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- What EventBite does, in the order people do it --}}
    <section aria-labelledby="how-heading">
        <h2 id="how-heading" class="sr-only">How EventBite works</h2>
        <ol class="eb-home-steps">
            <li><span class="eb-step-icon"><x-icon name="globe" /></span><strong>Discover</strong><span class="eb-text-supporting">Events from many cultures and communities.</span></li>
            <li><span class="eb-step-icon"><x-icon name="map-pin" /></span><strong>Find events near you</strong><span class="eb-text-supporting">Search by city, town or tradition.</span></li>
            <li><span class="eb-step-icon"><x-icon name="ticket" /></span><strong>Buy tickets</strong><span class="eb-text-supporting">Prices, fees and taxes shown before you pay.</span></li>
            <li><span class="eb-step-icon"><x-icon name="utensils" /></span><strong>Order food</strong><span class="eb-text-supporting">Pre-order from the event's menu for pickup.</span></li>
            <li><span class="eb-step-icon"><x-icon name="cash" /></span><strong>Pay your way</strong><span class="eb-text-supporting">Bank transfer, Zelle, Cash App or cash, wherever the host accepts it.</span></li>
            <li><span class="eb-step-icon"><x-icon name="qr-frame" /></span><strong>Get your ticket</strong><span class="eb-text-supporting">Your QR ticket appears once the payment is confirmed, and is checked at the door.</span></li>
        </ol>
    </section>

    @if($traditions !== [])
        <section aria-labelledby="traditions-heading">
            <div class="eb-home-section-head">
                <h2 id="traditions-heading" class="eb-text-section-title" style="margin: 0;">Browse by tradition</h2>
            </div>
            <ul class="eb-tradition-cards">
                @foreach($traditions as $label => $count)
                    @php $photoEvent = $traditionPhotos[$label] ?? null; $cuisineArt = $photoEvent ? null : \App\Support\VisualAssets::cuisineForTradition($label); @endphp
                    <li>
                        <a href="{{ route('events.index', ['q' => $label]) }}" class="eb-tradition-card{{ ($cuisineArt || $photoEvent) ? ' has-art' : '' }}" style="--tint: {{ ['#C7FF45', '#C4B5FD', '#7DD3FC', '#FBBF24', '#4ADE80', '#FCA5A5', '#F472B6'][crc32($label) % 7] }}; --turn: {{ crc32($label) % 360 }}deg;">
                            {{-- Illustrated cuisine artwork where one fits the tradition; decorative, the name says what it is. --}}
                            <span class="eb-tradition-art" aria-hidden="true">@if($photoEvent)<x-responsive-image :image="$photoEvent->coverImage()" alt="" sizes="240px" />@elseif($cuisineArt)<img src="{{ $cuisineArt['url'] }}" alt="" width="800" height="600" loading="lazy" decoding="async">@endif</span>
                            <span class="eb-tradition-name">{{ $label }}</span>
                            <span class="eb-tradition-count">{{ $count }} {{ \Illuminate\Support\Str::plural('event', $count) }}</span>
                            @if($photoEvent)<span class="eb-tradition-credit">Photo: {{ $photoEvent->title }}</span>@endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if($collections !== [])
        <section aria-labelledby="collections-heading">
            <div class="eb-home-section-head">
                <h2 id="collections-heading" class="eb-text-section-title" style="margin: 0;">Browse by kind of event</h2>
            </div>
            <ul class="eb-tradition-cards">
                @foreach($collections as $collection)
                    <li>
                        <a href="{{ route('events.index', ['kind' => $collection['key']]) }}" class="eb-tradition-card{{ $collection['image'] ? ' has-art' : '' }}" style="--tint: {{ ['#C7FF45', '#C4B5FD', '#7DD3FC', '#FBBF24'][crc32($collection['key']) % 4] }}; --turn: {{ crc32($collection['key']) % 360 }}deg; position: relative;">
                            {{-- An illustrative library photo of this kind of event, labelled; the name says what the collection is. --}}
                            <span class="eb-tradition-art" aria-hidden="true">@if($collection['image'])<x-responsive-image :image="$collection['image']" alt="" sizes="240px" />@endif</span>
                            <span class="eb-tradition-name">{{ $collection['label'] }}</span>
                            <span class="eb-tradition-count">{{ $collection['count'] }} {{ \Illuminate\Support\Str::plural('event', $collection['count']) }}</span>
                            @if($collection['image'])<span class="eb-tradition-credit">Illustrative photo</span>@endif
                        </a>
                    </li>
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
                <x-empty-state illustration="events" title="No upcoming events in {{ $market->state_code }} yet" description="Try another city, or browse every event." />
            @else
                <div class="eb-grid-4 eb-home-rail">
                    @foreach($near as $event)
                        <x-event-card :event="$event" />
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    @foreach($sections as $section)
        @continue($section['events']->isEmpty())
        @php $sectionArt = \App\Support\VisualAssets::heroFor(['featured' => 'festival', 'food-festivals' => 'food', 'music' => 'music', 'culture' => 'culture', 'family' => 'family', 'food-traditions' => 'food', 'seasonal' => 'party'][$section['id']] ?? null); @endphp
        <section aria-labelledby="{{ $section['id'] }}-heading">
            <div class="eb-home-section-head">
                <div class="eb-section-titled">
                    <img src="{{ $sectionArt['url'] }}" alt="" class="eb-section-art" width="56" height="56" loading="lazy">
                    <div>
                    <h2 id="{{ $section['id'] }}-heading" class="eb-text-section-title" style="margin: 0;">{{ $section['title'] }}</h2>
                    @if($section['note'])<p class="eb-text-supporting" style="margin: 2px 0 0;">{{ $section['note'] }}</p>@endif
                    </div>
                </div>
                @if($section['more'])<a class="eb-text-label" href="{{ $section['more'] }}">See all →</a>@endif
            </div>
            <div class="eb-grid-4 eb-home-rail">
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
            </div>
            <ul class="eb-home-markets">
                @foreach($markets as $m)
                    <li>
                        <a href="{{ route('events.index', ['city' => $marketValue($m)]) }}" class="eb-city-card">
                            <span class="eb-city-pin" aria-hidden="true"><x-icon name="map-pin" /></span>
                            <span><strong>{{ $m->city }}, {{ $m->state_code }}</strong><br>
                            <span class="eb-text-supporting">{{ $m->upcoming_count }} upcoming {{ Str::plural('event', $m->upcoming_count) }}</span></span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if($featured->isEmpty())
        <x-empty-state illustration="events" title="No upcoming events yet" description="Check back soon, or create an account to host your own." />
    @endif

    @php $ticketArt = \App\Support\VisualAssets::illustration('ticket'); @endphp
    <section class="eb-card eb-home-cta" style="display: flex; flex-wrap: wrap; gap: var(--space-3); align-items: center; justify-content: space-between;">
        <img src="{{ $ticketArt['url'] }}" alt="" class="eb-home-cta-art" width="150" height="112" loading="lazy">
        <p class="eb-text-supporting" style="margin: 0; flex: 1 1 220px;">Keep your tickets and food orders in one place.</p>
        <div style="display: flex; gap: var(--space-2);">
            <a href="{{ route('register') }}" class="eb-btn eb-btn-primary">Create account</a>
            <a href="{{ route('login') }}" class="eb-btn eb-btn-secondary">Sign in</a>
        </div>
    </section>
</div>
@endsection
