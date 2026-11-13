@extends('layouts.app')

@section('title', 'EventBite — Live Music, Food & Culture')
@section('page-title', 'Event Discovery')

@section('content')
<style>
    /* 3D Soft Realism & Dark Mode Elevation for Event Discovery */
    .discovery-root {
        background-color: var(--color-background);
        color: var(--color-text-primary);
        /* Bleed to the edges of the page padding, whatever it is at this width */
        margin: calc(-1 * var(--layout-page-padding-y)) calc(-1 * var(--layout-page-padding-x));
        padding: 0 0 100px;
        min-height: calc(100vh - 68px);
        font-family: var(--font-sans);
    }

    /* Discovery Top Header */
    .discovery-header {
        background: linear-gradient(180deg, rgba(19, 36, 58, 0.94) 0%, rgba(7, 17, 31, 0.94) 100%);
        border-bottom: 1px solid var(--color-border);
        box-shadow: 0 10px 30px rgba(2, 6, 14, 0.45);
        padding: 24px 32px 20px;
        position: sticky;
        top: 0;
        z-index: 30;
        backdrop-filter: blur(16px);
    }

    .discovery-header-inner {
        max-width: 1240px;
        margin: 0 auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        flex-wrap: wrap;
    }

    .discovery-greeting-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: var(--color-accent-subtle);
        border: 1px solid var(--color-accent-border);
        border-radius: 9999px;
        padding: 4px 12px;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--color-accent);
        margin-bottom: 6px;
    }

    .discovery-title {
        font-family: var(--font-display);
        font-size: clamp(1.45rem, 2.2vw, 1.85rem);
        font-weight: 800;
        color: var(--color-text-primary);
        letter-spacing: -0.025em;
        line-height: 1.2;
        margin: 0 0 4px;
    }

    .discovery-subtitle {
        font-size: 0.9rem;
        color: var(--color-text-secondary);
        line-height: 1.45;
        margin: 0;
    }

    /* Search & Filter Controls (3D Elevated Beveled Panel + Recessed Inputs) */
    .discovery-search-card {
        background-color: var(--color-surface);
        background-image: var(--gradient-surface-3d);
        border: 1px solid var(--color-border-strong);
        border-top-color: rgba(255, 255, 255, 0.14);
        border-radius: 20px;
        padding: 18px 22px;
        box-shadow: var(--shadow-card);
        margin-bottom: 32px;
    }

    .discovery-search-input-wrap {
        position: relative;
        margin-bottom: 14px;
    }

    .discovery-search-input-wrap input {
        width: 100%;
        background-color: var(--color-surface-recessed);
        border: 1px solid var(--color-border-strong);
        border-radius: 9999px;
        padding: 13px 44px 13px 46px;
        color: var(--color-text-primary);
        font-size: 0.92rem;
        outline: none;
        box-sizing: border-box;
        box-shadow: var(--shadow-3d-inset);
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .discovery-search-input-wrap input:focus {
        border-color: var(--color-accent);
        box-shadow: var(--shadow-3d-inset), 0 0 0 3px var(--color-accent-subtle);
    }

    .discovery-search-icon {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--color-accent);
        pointer-events: none;
        font-size: 1.05rem;
    }

    .discovery-clear-search {
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: var(--color-text-secondary);
        cursor: pointer;
        padding: 4px;
        font-size: 0.85rem;
    }

    .discovery-selectors-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    /* Filter groups wrap onto new lines on phones instead of running off the screen */
    .discovery-selectors-bar > div,
    .discovery-selectors-bar > div > div {
        flex-wrap: wrap;
        min-width: 0;
        max-width: 100%;
    }

    .discovery-select {
        max-width: 100%;
        background-color: var(--color-surface-recessed);
        color: var(--color-text-primary);
        border: 1px solid var(--color-border-strong);
        border-radius: 10px;
        padding: 8px 14px;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        outline: none;
        box-shadow: var(--shadow-3d-inset);
        transition: border-color 0.18s ease, box-shadow 0.18s ease;
    }

    .discovery-select:focus {
        border-color: var(--color-accent);
        box-shadow: var(--shadow-3d-inset), 0 0 0 2px var(--color-accent-subtle);
    }

    /* Category Shortcuts (4 prominent tactile 3D tiles) */
    .category-shortcuts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 16px;
        margin-bottom: 38px;
    }

    .category-btn {
        background-color: var(--color-surface);
        background-image: var(--gradient-surface-3d);
        border: 1px solid var(--color-border);
        border-top-color: rgba(255, 255, 255, 0.12);
        border-radius: 18px;
        padding: 16px 12px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        box-shadow: var(--shadow-subtle);
        transition: transform 0.2s var(--ease-out-quart), border-color 0.2s ease, box-shadow 0.2s ease;
        text-decoration: none;
        color: var(--color-text-primary);
        border-bottom: 3px solid transparent;
    }

    .category-btn:hover,
    .category-btn:focus-visible {
        background-color: var(--color-surface-elevated);
        border-color: var(--color-border-strong);
        border-top-color: rgba(199, 255, 69, 0.35);
        transform: translateY(-3px);
        box-shadow: var(--shadow-card);
    }

    .category-btn.active {
        background-color: var(--color-surface-elevated);
        background-image: linear-gradient(180deg, rgba(199, 255, 69, 0.14) 0%, rgba(19, 36, 58, 0.95) 100%);
        border-color: var(--color-accent-border);
        border-bottom-color: var(--color-accent);
        box-shadow: var(--shadow-card);
    }

    .category-icon-circle {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2), 0 4px 12px rgba(2, 6, 14, 0.38);
        transition: transform 0.2s var(--ease-out-quart);
    }

    .category-btn:hover .category-icon-circle {
        transform: scale(1.08);
    }

    .cat-music .category-icon-circle { background: rgba(139, 92, 246, 0.2); border: 1px solid rgba(139, 92, 246, 0.38); }
    .cat-food .category-icon-circle { background: rgba(245, 158, 11, 0.2); border: 1px solid rgba(245, 158, 11, 0.38); }
    .cat-sports .category-icon-circle { background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.38); }
    .cat-culture .category-icon-circle { background: rgba(199, 255, 69, 0.18); border: 1px solid rgba(199, 255, 69, 0.35); }

    /* Featured Event (Large image-led 3D card) */
    .featured-event-card {
        background-color: var(--color-surface);
        background-image: var(--gradient-surface-3d);
        border-radius: 24px;
        border: 1px solid var(--color-border-strong);
        border-top-color: rgba(255, 255, 255, 0.15);
        box-shadow: var(--shadow-elevated);
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        overflow: hidden;
        margin-bottom: 42px;
        transition: transform 0.25s var(--ease-out-quart), border-color 0.25s ease, box-shadow 0.25s ease;
    }

    .featured-event-card:hover {
        transform: translateY(-3px);
        border-top-color: rgba(199, 255, 69, 0.42);
    }

    .featured-event-img-wrap {
        position: relative;
        aspect-ratio: var(--ratio-event, 16 / 10);
        min-height: 280px;
        background-color: var(--color-background-secondary);
        overflow: hidden;
    }

    .featured-event-img-wrap img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        transition: transform 0.45s var(--ease-out-quart);
    }

    .featured-event-card:hover .featured-event-img-wrap img {
        transform: scale(1.03);
    }

    .featured-event-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(7, 17, 31, 0.15) 0%, rgba(7, 17, 31, 0.3) 50%, rgba(7, 17, 31, 0.9) 100%);
        pointer-events: none;
    }

    .featured-badge-pill {
        position: absolute;
        top: 16px;
        left: 16px;
        right: 16px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
        z-index: 2;
    }

    .featured-badge-tag {
        background: rgba(7, 17, 31, 0.85);
        backdrop-filter: blur(8px);
        color: var(--color-text-primary);
        padding: 4px 12px;
        border-radius: 9999px;
        font-size: 0.72rem;
        font-weight: 700;
        border: 1px solid rgba(255, 255, 255, 0.18);
    }

    .featured-event-body {
        padding: 28px 30px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 18px;
    }

    .btn-gradient-cta {
        background-color: var(--color-accent);
        background-image: var(--gradient-accent-3d);
        border: 1px solid rgba(255, 255, 255, 0.28);
        border-radius: 12px;
        padding: 12px 22px;
        font-size: 0.92rem;
        font-weight: 800;
        color: var(--color-accent-text);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-decoration: none;
        box-shadow: var(--shadow-btn-primary);
        transition: transform 0.18s var(--ease-out-quart), box-shadow 0.18s ease, background-color 0.18s ease;
    }

    .btn-gradient-cta:hover {
        background-color: var(--color-accent-hover);
        transform: translateY(-1px);
        box-shadow: var(--shadow-btn-primary-hover);
        color: var(--color-accent-text);
    }

    /* Coming Up (Horizontal Collection with consistent 16:10 proportions & 3D hover) */
    .trending-scroll-container {
        display: flex;
        gap: 18px;
        overflow-x: auto;
        padding-bottom: 14px;
        scroll-snap-type: x mandatory;
        -webkit-overflow-scrolling: touch;
        margin-bottom: 42px;
    }

    .trending-card {
        min-width: 280px;
        max-width: 300px;
        flex-shrink: 0;
        scroll-snap-align: start;
        background-color: var(--color-surface);
        background-image: var(--gradient-surface-3d);
        border-radius: 18px;
        border: 1px solid var(--color-border);
        border-top-color: rgba(255, 255, 255, 0.12);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-shadow: var(--shadow-subtle);
        transition: transform 0.22s var(--ease-out-quart), border-color 0.22s ease, box-shadow 0.22s ease;
    }

    .trending-card:hover,
    .trending-card:focus-within {
        transform: translateY(-4px);
        border-color: var(--color-border-strong);
        border-top-color: rgba(199, 255, 69, 0.4);
        box-shadow: var(--shadow-elevated);
    }

    .trending-card-media {
        position: relative;
        width: 100%;
        aspect-ratio: var(--ratio-event, 16 / 10);
        background-color: var(--color-background-secondary);
        overflow: hidden;
    }

    .trending-card-media img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        transition: transform 0.4s var(--ease-out-quart);
    }

    .trending-card:hover .trending-card-media img,
    .trending-card:focus-within .trending-card-media img {
        transform: scale(1.035);
    }

    /* All Events Grid */
    .discovery-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
        gap: 22px;
        margin-bottom: 32px;
    }

    /* Filter wrapper around each shared event card component; the card brings its own styling. */
    .event-item-card { display: flex; flex-direction: column; }
    .event-item-card > .eb-event-card { flex: 1; }

    @media (max-width: 767px) {
        #event-discovery-page .discovery-header { padding: 10px 16px 16px; position: relative; }
        #event-discovery-page .discovery-header-inner { align-items: flex-start; gap: 10px; }
        #event-discovery-page .discovery-greeting-badge { display: none; }
        #event-discovery-page .discovery-title { font-size: 1.45rem; margin-bottom: 3px; }
        #event-discovery-page .discovery-subtitle { font-size: .78rem; }
        #event-discovery-page .discovery-header-inner > div:last-child { display: none !important; }
        #event-discovery-page .discovery-search-card { padding: 0; margin: 14px 0 22px; border: 0; background: transparent; box-shadow: none; }
        #event-discovery-page .discovery-search-input-wrap { margin: 0; }
        #event-discovery-page .discovery-search-input-wrap input { min-height: 48px; background: var(--color-accent); color: var(--color-accent-text); border: 0; box-shadow: var(--shadow-btn-primary); }
        #event-discovery-page .discovery-search-input-wrap input::placeholder { color: #35451B; }
        #event-discovery-page .discovery-search-icon { color: var(--color-accent-text); }
        #event-discovery-page .discovery-selectors-bar { gap: 8px; }
        #event-discovery-page .discovery-selectors-bar > div:first-child { display: none !important; }
        #event-discovery-page .category-shortcuts-grid { display: flex; gap: 8px; overflow-x: auto; margin: 0 -16px 24px; padding: 2px 16px 8px; scroll-snap-type: x mandatory; }
        #event-discovery-page .category-btn { flex: 0 0 auto; min-width: 0; flex-direction: row; gap: 6px; padding: 8px 12px; border-radius: 999px; }
        #event-discovery-page .category-btn > div:last-child { display: none; }
        #event-discovery-page .category-icon-circle { width: auto; height: auto; background: transparent !important; border: 0 !important; box-shadow: none; font-size: .95rem; }
        #event-discovery-page .featured-event-card { display: block; margin-bottom: 28px; border-radius: 18px; }
        #event-discovery-page .featured-event-img-wrap { min-height: 0; aspect-ratio: 16 / 10; }
        #event-discovery-page .featured-event-img-wrap img { position: absolute; inset: 0; }
        #event-discovery-page .featured-event-overlay { background: linear-gradient(180deg, rgba(7,17,31,.05) 10%, rgba(7,17,31,.18) 40%, rgba(7,17,31,.94) 100%); }
        #event-discovery-page .featured-event-body { padding: 14px; gap: 12px; }
        #event-discovery-page .trending-scroll-container { gap: 12px; margin: 0 -16px 30px; padding: 0 16px 12px; }
        #event-discovery-page .trending-card { min-width: 248px; max-width: 248px; border-radius: 16px; }
        #event-discovery-page .discovery-grid { grid-template-columns: 1fr; gap: 12px; }
        .eb-app-header { display: none !important; }
        main.content-canvas { padding-top: 0 !important; }
        .eb-customer-bottom-nav { height: calc(var(--layout-bottom-nav-height) + env(safe-area-inset-bottom, 0px)); padding-bottom: env(safe-area-inset-bottom, 0px); }
    }
    .eb-explore-section { margin: 0 0 28px; }
    .eb-food-screen { margin: 0 0 28px; }
</style>

@php
    // ?category=food (the home screen's Food chip) opens the Food & Experience screen: real dish photos from the
    // menus of the events listed, then the food events themselves.
    $foodScreen = request()->query('category') === 'food';
    $foodEvents = $foodScreen
        ? $events->getCollection()->filter(fn ($event) => str_contains(strtolower((string) $event->category), 'food')
            || ($event->supports_food_ordering && $event->menuItems->isNotEmpty()))->values()
        : collect();
    $foodPhotos = $foodEvents->flatMap(fn ($event) => $event->menuItems->map(fn ($item) => ['item' => $item, 'event' => $event]))
        ->map(fn ($row) => $row + ['image' => $row['item']->responsiveImage()])
        ->filter(fn ($row) => $row['image'] !== null)->unique(fn ($row) => $row['image']->src)->take(9)->values();
    $foodLead = $foodEvents->first(fn ($event) => $event->hasFlyer()) ?? $foodEvents->first();
@endphp
<div class="discovery-root" id="event-discovery-page">
    <!-- 1. Discovery Header -->
    <header class="discovery-header">
        <div class="discovery-header-inner">
            <div>
                <div class="discovery-greeting-badge">
                    <span>🎟️</span>
                    <span>EventBite Discovery</span>
                </div>
                <h1 class="discovery-title" id="discovery-header-greeting">
                    {{ $foodScreen ? 'Food & Experience' : 'Explore Events' }}
                </h1>
                <p class="discovery-subtitle">
                    @auth
                        Hello, {{ Auth::user()->name }}. Discover food, music, culture, and community events near you.
                    @else
                        Explore diverse food, music, cultural, and community events across the United States.
                    @endauth
                </p>
            </div>

            <!-- Profile / Account Actions -->
            <div style="display: flex; align-items: center; gap: 12px;">
                @auth
                    <div style="display: flex; align-items: center; gap: 10px; background: #1E293B; border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 9999px; padding: 5px 14px 5px 6px;">
                        <div style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #6366F1 0%, #EC4899 100%); display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; font-size: 0.85rem;">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </div>
                        <div style="text-align: left;">
                            <div style="font-size: 0.82rem; font-weight: 700; color: #fff; line-height: 1.2;">{{ Auth::user()->name }}</div>
                            <div style="font-size: 0.68rem; color: #10B981; font-weight: 600;">● Online</div>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" style="background: transparent; border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 9999px; padding: 7px 16px; font-size: 0.82rem; font-weight: 600; color: #F8FAFC; text-decoration: none;">
                        Sign In
                    </a>
                    <a href="{{ route('register') }}" class="btn-gradient-cta" style="padding: 7px 18px; font-size: 0.82rem; border-radius: 9999px;">
                        Get Started
                    </a>
                @endauth

                <button type="button" onclick="window.location.reload()" title="Refresh Catalog" style="background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; color: #94A3B8; cursor: pointer;">
                    <span class="sr-only">Refresh event catalog</span>
                    🔄
                </button>
            </div>
        </div>
    </header>

    <div style="max-width: 1240px; margin: 0 auto; padding: 24px 24px 40px;" class="discovery-body">
        @if($foodScreen && $foodLead)
            {{-- Food & Experience: a food event's photo, then dishes from the listed events' menus. --}}
            <section class="eb-food-screen" aria-labelledby="food-screen-heading">
                @php
                    // A photograph behind the headline: the event's flyer or library photo, else a dish from the menus.
                    $foodCover = $foodLead->coverImage();
                    if (!$foodLead->hasFlyer() && !$foodCover->illustrative && $foodPhotos->isNotEmpty()) {
                        $foodCover = $foodPhotos->first()['image'];
                    }
                @endphp
                <div class="eb-food-hero">
                    <x-responsive-image :image="$foodCover" alt="" class="eb-food-hero-img" :eager="true" sizes="(min-width: 1240px) 1200px, 100vw" />
                    <x-image-label :image="$foodCover" corner="top-right" />
                    <h2 id="food-screen-heading" class="eb-food-hero-title">Good food. <span>Great company.</span></h2>
                    <p class="eb-food-hero-sub">Savor the flavours. Share the moments.</p>
                </div>
                @if($foodPhotos->isNotEmpty())
                    <ul class="eb-food-grid" aria-label="Dishes on the menus of upcoming food events">
                        @foreach($foodPhotos as $row)
                            <li>
                                <a href="{{ route('events.show', $row['event']->id) }}#pane-food" title="{{ $row['item']->name }} at {{ $row['event']->title }}">
                                    <x-responsive-image :image="$row['image']" :alt="$row['item']->name . ', on the menu at ' . $row['event']->title" sizes="(min-width: 768px) 190px, 31vw" />
                                    <x-image-label :image="$row['image']" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <div class="eb-event-rows" style="margin-top: var(--space-3);">
                    @foreach($foodEvents->take(3) as $foodEvent)
                        <x-event-row :event="$foodEvent" />
                    @endforeach
                </div>
            </section>
        @endif

        <!-- 2. Search Area with Rounded Search Field and Selectors -->
        <div class="discovery-search-card">
            <div class="discovery-search-input-wrap">
                <span class="discovery-search-icon">🔍</span>
                <input
                    type="text"
                    id="events-search-input"
                    aria-label="Search events, cities, towns, food or hosts"
                    placeholder="Search events, cities, towns, food or hosts..."
                    onkeyup="filterEvents()"
                />
            </div>

            <div class="discovery-selectors-bar">
                <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="font-size: 0.85rem;">📍</span>
                        <select id="filter-state" class="discovery-select" aria-label="Filter by state" onchange="filterEvents()">
                            <option value="all">All states & territories</option>
                            @foreach($states as $state)
                                <option value="{{ $state->code }}">{{ $state->name }}</option>
                            @endforeach
                        </select>
                        <select id="filter-city" class="discovery-select" aria-label="Filter by city" onchange="filterEvents()">
                            <option value="all">All cities and towns</option>
                            @foreach($markets as $market)
                                <option value="{{ $market->state_code }}:{{ strtolower($market->city) }}" data-state="{{ $market->state_code }}">{{ $market->city }}, {{ $market->state_code }}</option>
                            @endforeach
                            @foreach($events->whereNotNull('city')->unique(fn ($event) => strtolower($event->city) . ':' . $event->state_code) as $event)
                                @if(!$markets->contains(fn ($market) => strtolower($market->city) === strtolower($event->city) && $market->state_code === $event->state_code))
                                    <option value="{{ $event->state_code }}:{{ strtolower($event->city) }}" data-state="{{ $event->state_code }}">{{ $event->city }}, {{ $event->state_code }}</option>
                                @endif
                            @endforeach
                        </select>
                        <input id="filter-zip" class="discovery-select" inputmode="numeric" maxlength="10" placeholder="ZIP code" oninput="filterEvents()" aria-label="Filter by ZIP code">
                    </div>

                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="font-size: 0.85rem;">📅</span>
                        <select id="filter-date" class="discovery-select" aria-label="Filter by date" onchange="filterEvents()">
                            <option value="all">All Dates</option>
                            <option value="weekend">This Weekend</option>
                        </select>
                        <input id="filter-date-from" class="discovery-select" type="date" aria-label="Events from date" onchange="filterEvents()">
                        <input id="filter-date-to" class="discovery-select" type="date" aria-label="Events through date" onchange="filterEvents()">
                    </div>
                    <input id="filter-price-min" class="discovery-select" type="number" min="0" step="0.01" placeholder="Min price $" aria-label="Minimum event price" oninput="filterEvents()">
                    <input id="filter-price-max" class="discovery-select" type="number" min="0" step="0.01" placeholder="Max price $" aria-label="Maximum event price" oninput="filterEvents()">
                    <label style="font-size:0.8rem;color:#CBD5E1"><input id="filter-available" type="checkbox" onchange="filterEvents()"> Taking orders</label>
                </div>

                <div role="status" aria-live="polite" aria-atomic="true" style="font-size: 0.8rem; color: #94A3B8; font-weight: 600;">
                    Showing <strong style="color: #FFFFFF;" id="events-count">{{ count($events) }}</strong> events
                </div>
            </div>
        </div>

        <!-- 3. Category Shortcuts (4 prominent circular buttons) -->
        <div style="margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="font-size: 1.05rem; font-weight: 800; color: #FFFFFF; margin: 0;">Explore by Category</h2>
            <button type="button" onclick="selectCategory('all')" id="btn-cat-clear" style="background: none; border: none; color: #818CF8; font-size: 0.8rem; font-weight: 600; cursor: pointer;">
                View All
            </button>
        </div>

        <div class="category-shortcuts-grid">
            <!-- Music -->
            <button type="button" class="category-btn cat-music" onclick="selectCategory('music')" id="cat-btn-music">
                <div class="category-icon-circle">🎵</div>
                <div style="font-size: 0.85rem; font-weight: 700; color: #FFFFFF;">Music</div>
                <div style="font-size: 0.68rem; color: #94A3B8;">Concerts, DJs & live sets</div>
            </button>

            <!-- Food -->
            <button type="button" class="category-btn cat-food" onclick="selectCategory('food')" id="cat-btn-food">
                <div class="category-icon-circle">🍲</div>
                <div style="font-size: 0.85rem; font-weight: 700; color: #FFFFFF;">Food</div>
                <div style="font-size: 0.68rem; color: #94A3B8;">Festivals & Cookouts</div>
            </button>

            <!-- Sports -->
            <button type="button" class="category-btn cat-sports" onclick="selectCategory('sports')" id="cat-btn-sports">
                <div class="category-icon-circle">⚽</div>
                <div style="font-size: 0.85rem; font-weight: 700; color: #FFFFFF;">Sports</div>
                <div style="font-size: 0.68rem; color: #94A3B8;">Games & Fitness</div>
            </button>

            <!-- Culture -->
            <button type="button" class="category-btn cat-culture" onclick="selectCategory('culture')" id="cat-btn-culture">
                <div class="category-icon-circle">🎭</div>
                <div style="font-size: 0.85rem; font-weight: 700; color: #FFFFFF;">Culture</div>
                <div style="font-size: 0.68rem; color: #94A3B8;">Heritage & Parades</div>
            </button>
        </div>

        <!-- Explore: photo tiles for each kind of event, each a filtered list of upcoming events -->
        <section class="eb-explore-section" aria-labelledby="explore-tiles-heading">
            <div style="margin-bottom: 12px; display: flex; justify-content: space-between; align-items: baseline; gap: 12px;">
                <h2 id="explore-tiles-heading" style="font-size: 1.1rem; font-weight: 800; color: #FFFFFF; margin: 0;">Browse by kind of event</h2>
                @if(request()->filled('kind'))
                    <a href="{{ route('events.index') }}" style="font-size: 0.8rem; font-weight: 600; color: var(--color-accent);">Show all events</a>
                @endif
            </div>
            <x-explore-tiles :active="request()->query('kind')" heading-level="3" />
        </section>

        <!-- 4. Featured Events (Large Image-Led Card) -->
        @php
            $featuredEvent = $events->first();
        @endphp
        @if($featuredEvent)
        @php
            $featuredCover = $featuredEvent->coverImage();
            $featuredAvailability = \App\Support\EventListing::availability($featuredEvent);
            $featuredPrice = \App\Support\EventListing::fromPriceText($featuredEvent);
            $featuredLocation = \App\Support\EventListing::location($featuredEvent);
            $featuredBadgeTone = ['success' => 'eb-badge-success', 'danger' => 'eb-badge-error', 'warning' => 'eb-badge-warning'][$featuredAvailability['tone']] ?? 'eb-badge-info';
        @endphp
        <div style="margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 1.1rem;" aria-hidden="true">🔥</span>
            <h2 style="font-size: 1.1rem; font-weight: 800; color: #FFFFFF; margin: 0;">Featured Event</h2>
        </div>

        <div class="featured-event-card" id="primary-featured-card">
            <div class="featured-event-img-wrap">
                <x-responsive-image :image="$featuredCover" :style="'object-position:' . e($featuredEvent->flyer_position ?: '50% 50%')" sizes="(min-width: 1024px) 600px, 100vw" />
                <div class="featured-event-overlay"></div>
                <x-image-label :image="$featuredCover" />
                <div class="featured-badge-pill">
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <span class="featured-badge-tag">★ FEATURED</span>
                        @if($featuredEvent->category)
                            <span class="eb-badge eb-badge-info">{{ $featuredEvent->category }}</span>
                        @endif
                    </div>
                    <span class="eb-badge {{ $featuredBadgeTone }}">
                        <span class="eb-badge-dot" aria-hidden="true"></span>
                        <span>{{ $featuredAvailability['label'] }}</span>
                    </span>
                </div>
                <div style="position: absolute; bottom: 16px; left: 18px; right: 18px; z-index: 2;">
                    @if($featuredEvent->tagline)
                        <div style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #CBD5E1;">Highlight</div>
                        <div style="font-size: 0.95rem; font-weight: 700; color: #FFFFFF;">{{ $featuredEvent->tagline }}</div>
                    @endif
                </div>
            </div>

            <div class="featured-event-body">
                <div>
                    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px; font-size: 0.82rem; color: var(--color-accent); font-weight: 700; margin-bottom: 8px;">
                        @if($featuredEvent->date_str)
                            <span>📅 {{ $featuredEvent->date_str }}</span>
                        @endif
                        @if($featuredEvent->date_str && $featuredEvent->time_str)
                            <span aria-hidden="true">&bull;</span>
                        @endif
                        @if($featuredEvent->time_str)
                            <span>⏰ {{ $featuredEvent->time_str }}</span>
                        @endif
                    </div>

                    <h3 style="font-size: 1.45rem; font-weight: 800; color: #FFFFFF; line-height: 1.25; margin: 0 0 10px;">
                        {{ $featuredEvent->title }}
                    </h3>

                    @if($featuredEvent->summary || $featuredEvent->description || $featuredEvent->tagline)
                        <p class="eb-clamp-2" style="font-size: 0.88rem; color: var(--color-text-secondary); line-height: 1.55; margin: 0 0 16px;">
                            {{ $featuredEvent->summary ?: ($featuredEvent->description ?: $featuredEvent->tagline) }}
                        </p>
                    @endif

                    @if($featuredEvent->venue_name || $featuredLocation !== '')
                        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 6px; font-size: 0.84rem; color: var(--color-text-secondary); margin-bottom: 16px;">
                            <span aria-hidden="true">📍</span>
                            @if($featuredEvent->venue_name)
                                <span style="font-weight: 700; color: var(--color-text-primary);">{{ $featuredEvent->venue_name }}</span>
                            @endif
                            @if($featuredLocation !== '')
                                <span>({{ $featuredLocation }})</span>
                            @elseif($featuredEvent->venue_address)
                                <span>({{ $featuredEvent->venue_address }})</span>
                            @endif
                        </div>
                    @endif

                    @if($featuredEvent->organizer_name)
                        <div style="font-size: 0.78rem; color: var(--color-text-muted); margin-bottom: 12px;">
                            Hosted by <strong style="color: var(--color-text-secondary);">{{ $featuredEvent->organizer_name }}</strong>
                        </div>
                    @endif
                </div>

                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 16px; background: var(--color-surface-recessed); border-radius: 14px; margin-bottom: 16px; border: 1px solid var(--color-border-strong); box-shadow: var(--shadow-3d-inset);">
                        <div>
                            <div style="font-size: 0.72rem; color: var(--color-text-secondary); font-weight: 600;">Tickets</div>
                            <div style="font-size: 1.05rem; font-weight: 800; color: var(--color-text-primary); font-variant-numeric: tabular-nums;">
                                {{ $featuredPrice ?? $featuredAvailability['label'] }}
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <span class="eb-badge {{ $featuredBadgeTone }}">
                                <span class="eb-badge-dot" aria-hidden="true"></span>
                                <span>{{ $featuredAvailability['label'] }}</span>
                            </span>
                            @if($featuredEvent->supports_food_ordering)
                                <div style="font-size: 0.72rem; color: var(--color-accent); font-weight: 700; margin-top: 4px;">
                                    🍲 Advance food menu available
                                </div>
                            @endif
                        </div>
                    </div>

                    <a href="{{ route('events.show', $featuredEvent->id) }}" class="btn-gradient-cta" style="width: 100%; box-sizing: border-box;" id="btn-featured-view-cta">
                        <span>View Event &amp; Menu</span>
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                </div>
            </div>
        </div>
        @endif

        <!-- 5. Coming Up (Horizontal Collection) -->
        <div style="margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 1.1rem;" aria-hidden="true">📅</span>
                <h2 style="font-size: 1.1rem; font-weight: 800; color: #FFFFFF; margin: 0;">Coming Up</h2>
            </div>
            <span style="font-size: 0.78rem; color: var(--color-text-secondary);">Next on the calendar</span>
        </div>

        <div class="trending-scroll-container" id="trending-container">
            @foreach($events->take(6) as $idx => $trend)
                @php
                    $trendCover = $trend->coverImage();
                    $trendAvailability = \App\Support\EventListing::availability($trend);
                    $trendPrice = \App\Support\EventListing::fromPriceText($trend);
                    $trendLocation = \App\Support\EventListing::location($trend);
                    $trendBadge = ['success' => 'eb-badge-success', 'danger' => 'eb-badge-error', 'warning' => 'eb-badge-warning'][$trendAvailability['tone']] ?? 'eb-badge-info';
                @endphp
                <article class="trending-card">
                    <a href="{{ route('events.show', $trend->id) }}" class="eb-card-link" style="display: flex; flex-direction: column; height: 100%;" aria-label="{{ $trend->title }}: view event details">
                        <div class="trending-card-media">
                            <x-responsive-image :image="$trendCover" alt="" :style="'object-position:' . e($trend->flyer_position ?: '50% 50%')" sizes="300px" />
                            <div class="eb-event-card-scrim"></div>
                            <x-image-label :image="$trendCover" />
                            @if($trend->category)
                                <span class="eb-card-chip eb-badge eb-badge-info">{{ $trend->category }}</span>
                            @endif
                            <span class="eb-card-chip eb-card-chip-end eb-badge {{ $trendBadge }}">
                                <span class="eb-badge-dot" aria-hidden="true"></span>
                                <span>{{ $trendAvailability['label'] }}</span>
                            </span>
                        </div>
                        <div style="padding: 16px; display: flex; flex-direction: column; flex: 1; justify-content: space-between; gap: 10px;">
                            <div>
                                <div style="font-size: 0.75rem; color: var(--color-accent); font-weight: 700; margin-bottom: 4px;">
                                    📅 {{ $trend->date_str }}
                                </div>
                                <h3 style="font-size: 0.96rem; font-weight: 800; color: #FFFFFF; line-height: 1.3; margin: 0 0 6px;" class="eb-clamp-2">
                                    {{ $trend->title }}
                                </h3>
                                @if($trend->venue_name || $trendLocation !== '')
                                    <div style="font-size: 0.78rem; color: var(--color-text-secondary);" class="eb-clamp-2">
                                        📍 {{ implode(' · ', array_filter([$trend->venue_name, $trendLocation])) }}
                                    </div>
                                @endif
                            </div>

                            <div class="eb-event-card-footer">
                                <span class="eb-text-price">{{ $trendPrice ?? 'Tickets not on sale' }}</span>
                                <span class="eb-event-card-cta" aria-hidden="true">Details <span class="eb-event-card-cta-arrow">→</span></span>
                            </div>
                        </div>
                    </a>
                </article>
            @endforeach
        </div>

        <!-- 6. All Events Grid (Search & Filter Results) -->
        <div style="margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between;">
            <h2 style="font-size: 1.1rem; font-weight: 800; color: #FFFFFF; margin: 0;">All Events</h2>
            <span style="font-size: 0.78rem; color: #818CF8; font-weight: 600;" id="active-category-display">
                Category: All
            </span>
        </div>

        <div class="discovery-grid" id="events-grid">
            @foreach($events as $event)
                @php
                    $eventCategory = strtolower((string) ($event->category ?? 'community'));
                    $catType = str_contains($eventCategory, 'food') ? 'food' : (str_contains($eventCategory, 'music') ? 'music' : (str_contains($eventCategory, 'sport') ? 'sports' : (str_contains($eventCategory, 'cultur') || str_contains($eventCategory, 'heritage') ? 'culture' : 'community')));
                    $fromMinor = \App\Support\EventListing::fromPriceMinor($event);
                    $availability = \App\Support\EventListing::availability($event);
                    $search = \App\Services\Discovery\HomeSections::text($event) . ' ' . strtolower(implode(' ', array_filter([
                        $event->venue_name, $event->venue_address, $event->city, $event->state_code, $event->zip_code, $event->organizer_name,
                        implode(' ', \App\Services\Discovery\HomeSections::traditions($event)),
                    ])));
                @endphp
                <div class="event-item-card" data-search="{{ $search }}" data-category="{{ $catType }}" data-date="{{ $event->date_str ?? '' }}" data-event-date="{{ substr((string) ($event->event_date_iso ?? ''), 0, 10) }}" data-state="{{ $event->state_code ?? '' }}" data-city="{{ strtolower($event->city ?? '') }}" data-zip="{{ $event->zip_code ?? '' }}" data-price="{{ $fromMinor === null ? '' : \App\Support\Money::ofMinor($fromMinor, $event->currency ?: 'USD')->toDecimalString() }}" data-available="{{ in_array($availability['key'], ['on_sale', 'food'], true) ? '1' : '0' }}">
                    <x-event-card :event="$event" />
                </div>
            @endforeach
        </div>
        <nav aria-label="Event result pages" style="margin: 20px 0;">
            {{ $events->links() }}
        </nav>

        <!-- Empty Results State -->
        <div id="no-events-found" style="display: none; background: #162038; border: 1px dashed rgba(255, 255, 255, 0.15); border-radius: 20px; padding: 44px 20px; text-align: center; margin: 20px 0;">
            <img src="{{ \App\Support\VisualAssets::emptyState('events') }}" alt="" class="eb-empty-state-art" width="200" height="138" loading="lazy">
            <h3 style="font-size: 1.2rem; font-weight: 700; color: #FFFFFF; margin-bottom: 6px;">No Events Found</h3>
            <p style="font-size: 0.85rem; color: #94A3B8; margin-bottom: 16px;">Try adjusting your search terms or clearing your location and date filters.</p>
            <button type="button" onclick="clearAllFilters()" class="btn-gradient-cta" style="padding: 8px 20px; font-size: 0.82rem; border-radius: 9999px;">
                Clear All Filters
            </button>
        </div>
    </div>


</div>

<script>
    let activeCategory = 'all';

    function selectCategory(category) {
        activeCategory = (activeCategory === category && category !== 'all') ? 'all' : category;
        
        // Update category button active classes
        document.querySelectorAll('.category-btn').forEach(btn => btn.classList.remove('active'));
        if (activeCategory !== 'all') {
            const activeBtn = document.getElementById('cat-btn-' + activeCategory);
            if (activeBtn) activeBtn.classList.add('active');
        }
        
        document.getElementById('active-category-display').innerText = 'Category: ' + (activeCategory.charAt(0).toUpperCase() + activeCategory.slice(1));
        filterEvents();
    }

    function filterEvents() {
        const q = (document.getElementById('events-search-input').value || '').toLowerCase().trim();
        const dateFilter = document.getElementById('filter-date').value;

        const cards = document.querySelectorAll('.event-item-card');
        let visibleCount = 0;

        cards.forEach(card => {
            const searchText = card.getAttribute('data-search') || '';
            const cat = card.getAttribute('data-category') || '';
            const dateStr = card.getAttribute('data-date') || '';
            const eventDateText = card.getAttribute('data-event-date') || '';
            const eventDate = eventDateText
                ? new Date(`${eventDateText}T00:00:00`)
                : new Date(dateStr);
            const state = card.getAttribute('data-state') || '';
            const city = card.getAttribute('data-city') || '';
            const zip = card.getAttribute('data-zip') || '';
            const priceText = card.getAttribute('data-price') || '';
            const price = priceText === '' ? null : parseFloat(priceText);
            const available = card.getAttribute('data-available') === '1';
            const stateFilter = document.getElementById('filter-state').value;
            const cityFilter = document.getElementById('filter-city').value;
            const zipFilter = (document.getElementById('filter-zip').value || '').trim();

            let matchQuery = true;
            if (q) {
                matchQuery = q.split(/\s+/).every(word => searchText.includes(word) || cat.includes(word));
            }

            let matchLocation = true;
            if (stateFilter !== 'all') matchLocation = state.toUpperCase() === stateFilter;
            if (cityFilter !== 'all') {
                const [cityState, cityName] = cityFilter.split(':', 2);
                matchLocation = matchLocation && state.toUpperCase() === cityState && city.toLowerCase() === cityName;
            }
            if (zipFilter) matchLocation = matchLocation && zip.startsWith(zipFilter);

            const minPrice = document.getElementById('filter-price-min').value;
            const maxPrice = document.getElementById('filter-price-max').value;
            const availableOnly = document.getElementById('filter-available').checked;
            // Events with no ticket on sale have no starting price, so a price filter leaves them out.
            const matchPrice = (!minPrice && !maxPrice) || (price !== null && (!minPrice || price >= parseFloat(minPrice)) && (!maxPrice || price <= parseFloat(maxPrice)));
            const matchAvailability = !availableOnly || available;

            let matchDate = true;
            const dateFrom = document.getElementById('filter-date-from').value;
            const dateTo = document.getElementById('filter-date-to').value;
            if (dateFilter === 'weekend') {
                const today = new Date();
                const saturday = new Date(today.getFullYear(), today.getMonth(), today.getDate() + ((6 - today.getDay() + 7) % 7));
                const sunday = new Date(saturday.getFullYear(), saturday.getMonth(), saturday.getDate() + 1);
                matchDate = !Number.isNaN(eventDate.getTime()) && eventDate >= saturday && eventDate <= sunday;
            }
            if (dateFrom) matchDate = matchDate && !Number.isNaN(eventDate.getTime()) && eventDate >= new Date(`${dateFrom}T00:00:00`);
            if (dateTo) matchDate = matchDate && !Number.isNaN(eventDate.getTime()) && eventDate <= new Date(`${dateTo}T23:59:59`);

            let matchCategory = true;
            if (activeCategory !== 'all') {
                matchCategory = (cat === activeCategory);
            }

            if (matchQuery && matchLocation && matchDate && matchCategory && matchPrice && matchAvailability) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        document.getElementById('events-count').innerText = visibleCount;
        const emptyState = document.getElementById('no-events-found');
        if (emptyState) {
            emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    }

    function clearAllFilters() {
        document.getElementById('events-search-input').value = '';
        document.getElementById('filter-state').value = 'all';
        document.getElementById('filter-city').value = 'all';
        document.getElementById('filter-zip').value = '';
        document.getElementById('filter-price-min').value = '';
        document.getElementById('filter-price-max').value = '';
        document.getElementById('filter-available').checked = false;
        document.getElementById('filter-date').value = 'all';
        document.getElementById('filter-date-from').value = '';
        document.getElementById('filter-date-to').value = '';
        selectCategory('all');
    }

    // Links from the homepage arrive with ?q=, ?city=STATE:city and ?category= already chosen.
    document.addEventListener('DOMContentLoaded', () => {
        const params = new URLSearchParams(window.location.search);
        if (params.get('q')) document.getElementById('events-search-input').value = params.get('q');
        const city = (params.get('city') || '').toLowerCase();
        const cityOption = [...document.getElementById('filter-city').options].find(o => o.value.toLowerCase() === city);
        if (cityOption) {
            document.getElementById('filter-city').value = cityOption.value;
            document.getElementById('filter-state').value = cityOption.value.split(':')[0];
        }
        const category = params.get('category');
        if (category && document.getElementById('cat-btn-' + category)) {
            selectCategory(category);
        } else if (params.toString() !== '') {
            filterEvents();
        }
    });

    function focusSearch() {
        const el = document.getElementById('events-search-input');
        if (el) {
            el.focus();
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
</script>
@endsection
