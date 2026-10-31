@extends('layouts.app')

@section('title', 'EventBite — Live Music, Food & Culture')
@section('page-title', 'Event Discovery')

@section('content')
<style>
    /* Midnight Navy Theme Overrides for Discovery Experience */
    .discovery-root {
        background-color: #0B132B;
        color: #F8FAFC;
        margin: -1.75rem -2rem;
        padding: 0 0 100px;
        min-height: calc(100vh - 68px);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    /* Discovery Top Header */
    .discovery-header {
        background: linear-gradient(180deg, #101B37 0%, #0B132B 100%);
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        padding: 24px 32px 20px;
        position: sticky;
        top: 0;
        z-index: 30;
        backdrop-filter: blur(14px);
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
        background: rgba(129, 140, 248, 0.15);
        border: 1px solid rgba(129, 140, 248, 0.3);
        border-radius: 9999px;
        padding: 4px 12px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #818CF8;
        margin-bottom: 6px;
    }

    .discovery-title {
        font-size: 1.65rem;
        font-weight: 800;
        color: #FFFFFF;
        letter-spacing: -0.02em;
        line-height: 1.25;
        margin: 0 0 4px;
    }

    .discovery-subtitle {
        font-size: 0.88rem;
        color: #94A3B8;
        line-height: 1.45;
        margin: 0;
    }

    /* Search & Filter Controls */
    .discovery-search-card {
        background: #162038;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 16px 20px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        margin-bottom: 32px;
    }

    .discovery-search-input-wrap {
        position: relative;
        margin-bottom: 14px;
    }

    .discovery-search-input-wrap input {
        width: 100%;
        background-color: #0F172A;
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 9999px;
        padding: 13px 44px 13px 46px;
        color: #FFFFFF;
        font-size: 0.92rem;
        outline: none;
        box-sizing: border-box;
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .discovery-search-input-wrap input:focus {
        border-color: #818CF8;
        box-shadow: 0 0 0 3px rgba(129, 140, 248, 0.25);
    }

    .discovery-search-icon {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: #818CF8;
        pointer-events: none;
        font-size: 1.1rem;
    }

    .discovery-clear-search {
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #94A3B8;
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

    .discovery-select {
        background-color: #0F172A;
        color: #F8FAFC;
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 10px;
        padding: 8px 14px;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        outline: none;
    }

    /* Category Shortcuts (4 prominent circular buttons) */
    .category-shortcuts-grid {
        display: grid;
        grid-templateColumns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 16px;
        margin-bottom: 38px;
    }

    .category-btn {
        background: #162038;
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 18px;
        padding: 16px 12px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        text-decoration: none;
        color: #E2E8F0;
        border-bottom: 3px solid transparent;
    }

    .category-btn:hover {
        background: #1E293B;
        transform: translateY(-2px);
    }

    .category-btn.active {
        background: rgba(99, 102, 241, 0.25);
        border-color: #818CF8;
        border-bottom-color: #818CF8;
        box-shadow: 0 8px 24px rgba(99, 102, 241, 0.3);
    }

    .category-icon-circle {
        width: 58px;
        height: 58px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justifyContent: center;
        font-size: 1.55rem;
        transition: transform 0.2s;
    }

    .category-btn:hover .category-icon-circle {
        transform: scale(1.08);
    }

    .cat-music .category-icon-circle { background: rgba(99, 102, 241, 0.2); border: 1px solid rgba(99, 102, 241, 0.35); }
    .cat-food .category-icon-circle { background: rgba(245, 158, 11, 0.2); border: 1px solid rgba(245, 158, 11, 0.35); }
    .cat-sports .category-icon-circle { background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.35); }
    .cat-culture .category-icon-circle { background: rgba(236, 72, 153, 0.2); border: 1px solid rgba(236, 72, 153, 0.35); }

    /* Featured Event (Large image-led card) */
    .featured-event-card {
        background: #162038;
        border-radius: 24px;
        border: 1px solid rgba(255, 255, 255, 0.12);
        box-shadow: 0 16px 36px rgba(0, 0, 0, 0.35);
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        overflow: hidden;
        margin-bottom: 42px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .featured-event-img-wrap {
        position: relative;
        min-height: 280px;
        max-height: 400px;
    }

    .featured-event-img-wrap img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .featured-event-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(15,23,42,0.15) 0%, rgba(15,23,42,0.85) 100%);
    }

    .featured-badge-pill {
        position: absolute;
        top: 18px;
        left: 18px;
        display: flex;
        gap: 8px;
    }

    .featured-badge-tag {
        background: rgba(15, 23, 42, 0.85);
        backdrop-filter: blur(8px);
        color: #FFFFFF;
        padding: 4px 12px;
        border-radius: 9999px;
        font-size: 0.72rem;
        font-weight: 700;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .featured-event-body {
        padding: 30px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .btn-gradient-cta {
        background: linear-gradient(135deg, #7c3aed 0%, #ec4899 50%, #f97316 100%);
        border: none;
        border-radius: 12px;
        padding: 12px 22px;
        font-size: 0.92rem;
        font-weight: 800;
        color: #FFFFFF;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-decoration: none;
        box-shadow: 0 4px 14px rgba(236, 72, 153, 0.35);
        transition: opacity 0.18s, transform 0.18s;
    }

    .btn-gradient-cta:hover {
        opacity: 0.95;
        transform: translateY(-1px);
        color: #FFFFFF;
    }

    /* Trending Near You (Horizontal Scroll) */
    .trending-scroll-container {
        display: flex;
        gap: 16px;
        overflow-x: auto;
        padding-bottom: 14px;
        scroll-snap-type: x mandatory;
        -webkit-overflow-scrolling: touch;
        margin-bottom: 42px;
    }

    .trending-card {
        min-width: 260px;
        max-width: 280px;
        flex-shrink: 0;
        scroll-snap-align: start;
        background-color: #162038;
        border-radius: 18px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-shadow: 0 6px 18px rgba(0,0,0,0.25);
        transition: transform 0.2s, border-color 0.2s;
    }

    .trending-card:hover {
        transform: translateY(-3px);
        border-color: rgba(129, 140, 248, 0.4);
    }

    /* All Events Grid */
    .discovery-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
        gap: 22px;
        margin-bottom: 32px;
    }

    .event-item-card {
        background-color: #162038;
        border-radius: 20px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-shadow: 0 4px 16px rgba(0,0,0,0.25);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .event-item-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 24px rgba(0,0,0,0.35);
        border-color: rgba(129, 140, 248, 0.3);
    }

    /* Fixed Bottom Navigation (5 items) */
    .discovery-bottom-nav {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background-color: #0B132B;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        padding: 8px 16px;
        display: flex;
        justify-content: space-around;
        align-items: center;
        z-index: 50;
        backdrop-filter: blur(16px);
        box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.45);
    }

    .bottom-nav-link {
        background: none;
        border: none;
        color: #94A3B8;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 3px;
        cursor: pointer;
        padding: 6px 14px;
        border-radius: 12px;
        text-decoration: none;
        font-size: 0.74rem;
        font-weight: 500;
        transition: all 0.15s;
    }

    .bottom-nav-link.active, .bottom-nav-link:hover {
        color: #818CF8;
    }

    .bottom-nav-link.active .nav-dot {
        width: 4px;
        height: 4px;
        border-radius: 50%;
        background-color: #818CF8;
    }
</style>

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
                    @auth
                        Hello, {{ Auth::user()->name }} 👋
                    @else
                        Discover Events Across the U.S.
                    @endauth
                </h1>
                <p class="discovery-subtitle">
                    @auth
                        Discover food, music, culture, and community events near you.
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
                    🔄
                </button>
            </div>
        </div>
    </header>

    <div style="max-width: 1240px; margin: 0 auto; padding: 24px 24px 40px;">
        <!-- 2. Search Area with Rounded Search Field and Selectors -->
        <div class="discovery-search-card">
            <div class="discovery-search-input-wrap">
                <span class="discovery-search-icon">🔍</span>
                <input
                    type="text"
                    id="events-search-input"
                    placeholder="Search events, artists, performers, venues, organizers, categories..."
                    onkeyup="filterEvents()"
                />
            </div>

            <div class="discovery-selectors-bar">
                <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="font-size: 0.85rem;">📍</span>
                        <select id="filter-state" class="discovery-select" onchange="filterEvents()">
                            <option value="all">All states & territories</option>
                            @foreach($states as $state)
                                <option value="{{ $state->code }}">{{ $state->name }}</option>
                            @endforeach
                        </select>
                        <select id="filter-city" class="discovery-select" onchange="filterEvents()">
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
                        <select id="filter-date" class="discovery-select" onchange="filterEvents()">
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

                <div style="font-size: 0.8rem; color: #94A3B8; font-weight: 600;">
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
                <div style="font-size: 0.68rem; color: #94A3B8;">Live Reggae & Sets</div>
            </button>

            <!-- Food -->
            <button type="button" class="category-btn cat-food" onclick="selectCategory('food')" id="cat-btn-food">
                <div class="category-icon-circle">🍲</div>
                <div style="font-size: 0.85rem; font-weight: 700; color: #FFFFFF;">Food</div>
                <div style="font-size: 0.68rem; color: #94A3B8;">Fish Fries & Feasts</div>
            </button>

            <!-- Sports -->
            <button type="button" class="category-btn cat-sports" onclick="selectCategory('sports')" id="cat-btn-sports">
                <div class="category-icon-circle">⚽</div>
                <div style="font-size: 0.85rem; font-weight: 700; color: #FFFFFF;">Sports</div>
                <div style="font-size: 0.68rem; color: #94A3B8;">Regattas & Cricket</div>
            </button>

            <!-- Culture -->
            <button type="button" class="category-btn cat-culture" onclick="selectCategory('culture')" id="cat-btn-culture">
                <div class="category-icon-circle">🎭</div>
                <div style="font-size: 0.85rem; font-weight: 700; color: #FFFFFF;">Culture</div>
                <div style="font-size: 0.68rem; color: #94A3B8;">Heritage & Parades</div>
            </button>
        </div>

        <!-- 4. Featured Events (Large Image-Led Card) -->
        @php
            $featuredEvent = $events->first();
        @endphp
        @if($featuredEvent)
        <div style="margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 1.1rem;">🔥</span>
            <h2 style="font-size: 1.1rem; font-weight: 800; color: #FFFFFF; margin: 0;">Featured Event</h2>
        </div>

        <div class="featured-event-card" id="primary-featured-card">
            <div class="featured-event-img-wrap">
                <img src="{{ !empty($featuredEvent->flyer_path) ? $featuredEvent->flyer_path : '/images/island_music_reggae_concert.jpg' }}" alt="{{ $featuredEvent->title }}">
                <div class="featured-event-overlay"></div>
                <div class="featured-badge-pill">
                    <span class="featured-badge-tag">★ FEATURED</span>
                    <span class="featured-badge-tag" style="background: rgba(99, 102, 241, 0.85);">🎵 Music & Culinary</span>
                </div>
                <div style="position: absolute; bottom: 16px; left: 18px; right: 18px;">
                    <div style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #CBD5E1;">Performer Highlight</div>
                    <div style="font-size: 0.95rem; font-weight: 700; color: #FFFFFF;">🎤 Roots Reggae All-Stars & Chronixx Tribute</div>
                </div>
            </div>

            <div class="featured-event-body">
                <div>
                    <div style="display: flex; align-items: center; gap: 8px; font-size: 0.8rem; color: #818CF8; font-weight: 600; margin-bottom: 8px;">
                        <span>📅 {{ $featuredEvent->date_str ?? 'Sunday, Nov 22, 2026' }}</span>
                        <span>&bull;</span>
                        <span>⏰ {{ $featuredEvent->time_str ?? '12:00 PM – 8:00 PM' }}</span>
                    </div>

                    <h3 style="font-size: 1.35rem; font-weight: 800; color: #FFFFFF; line-height: 1.3; margin-bottom: 10px;">
                        {{ $featuredEvent->title }}
                    </h3>

                    <p style="font-size: 0.85rem; color: #94A3B8; line-height: 1.55; margin-bottom: 16px;">
                        {{ $featuredEvent->description ?? $featuredEvent->tagline }}
                    </p>

                    <div style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; color: #CBD5E1; margin-bottom: 18px;">
                        <span style="color: #EC4899;">📍</span>
                        <span style="font-weight: 600;">{{ $featuredEvent->venue_name ?? 'Victoria Pier' }}</span>
                        <span style="color: #64748B;">({{ $featuredEvent->venue_address ?? 'United States' }})</span>
                    </div>
                </div>

                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: rgba(15, 23, 42, 0.6); border-radius: 12px; margin-bottom: 16px; border: 1px solid rgba(255, 255, 255, 0.08);">
                        <div style="font-size: 0.75rem; color: #10B981; font-weight: 700;">
                            ✓ Genuine Verified Kitchen & Menu
                        </div>
                        <div style="font-size: 0.85rem; font-weight: 800; color: #FFFFFF;">
                            Digital QR Pass Included
                        </div>
                    </div>

                    <a href="{{ route('events.show', $featuredEvent->id) }}" class="btn-gradient-cta" style="width: 100%; box-sizing: border-box;" id="btn-featured-view-cta">
                        <span>View Event & Menu</span>
                        <span>&rarr;</span>
                    </a>
                </div>
            </div>
        </div>
        @endif

        <!-- 5. Trending Near You (Horizontal Collection) -->
        <div style="margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 1.1rem;">📈</span>
                <h2 style="font-size: 1.1rem; font-weight: 800; color: #FFFFFF; margin: 0;">Trending Near You</h2>
            </div>
            <span style="font-size: 0.78rem; color: #94A3B8;">Genuine active events</span>
        </div>

        <div class="trending-scroll-container" id="trending-container">
            @foreach($events->take(6) as $idx => $trend)
                @php
                    $trendImg = !empty($trend->flyer_path) ? $trend->flyer_path : (($idx % 3 === 0) ? '/images/island_festival_sunset.jpg' : (($idx % 3 === 1) ? '/images/island_sports_championship.jpg' : '/images/island_culture_carnival.jpg'));
                @endphp
                <div class="trending-card">
                    <div style="height: 130px; position: relative;">
                        <img src="{{ $trendImg }}" alt="{{ $trend->title }}" style="width: 100%; height: 100%; object-fit: cover;">
                        <span style="position: absolute; top: 8px; left: 8px; background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(6px); color: #fff; padding: 2px 8px; border-radius: 9999px; font-size: 0.65rem; font-weight: 700;">
                            TRENDING
                        </span>
                    </div>
                    <div style="padding: 14px; display: flex; flex-direction: column; flex: 1; justify-content: space-between;">
                        <div>
                            <div style="font-size: 0.72rem; color: #818CF8; font-weight: 600; margin-bottom: 3px;">
                                📅 {{ explode(',', $trend->date_str ?? '')[0] ?: $trend->date_str }}
                            </div>
                            <h4 style="font-size: 0.88rem; font-weight: 700; color: #FFFFFF; line-height: 1.3; margin: 0 0 6px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                {{ $trend->title }}
                            </h4>
                            <div style="font-size: 0.72rem; color: #94A3B8; margin-bottom: 8px;">
                                📍 {{ $trend->venue_name ?? $trend->venue }}
                            </div>
                        </div>

                        <div>
                            <a href="{{ route('events.show', $trend->id) }}" style="display: block; text-align: center; background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 8px; padding: 7px; color: #fff; font-size: 0.75rem; font-weight: 700; text-decoration: none;">
                                View Event
                            </a>
                        </div>
                    </div>
                </div>
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
            @foreach($events as $idx => $event)
                @php
                    $gridImg = !empty($event->flyer_path) ? $event->flyer_path : (($idx % 4 === 0) ? '/images/island_music_reggae_concert.jpg' : (($idx % 4 === 1) ? '/images/escovitch_fish.jpg' : (($idx % 4 === 2) ? '/images/island_sports_championship.jpg' : '/images/island_culture_carnival.jpg')));
                    $eventCategory = strtolower((string) ($event->category ?? 'community'));
                    $catType = str_contains($eventCategory, 'food') ? 'food' : (str_contains($eventCategory, 'music') ? 'music' : (str_contains($eventCategory, 'sport') ? 'sports' : (str_contains($eventCategory, 'culture') || str_contains($eventCategory, 'heritage') ? 'culture' : 'community')));
                    $priceValues = collect($event->ticket_tiers ?? [])->pluck('priceUSD')
                        ->merge($event->menuItems->where('is_active', true)->pluck('price_usd'))
                        ->filter(fn ($price) => is_numeric($price));
                    $lowestPrice = $priceValues->isEmpty() ? 0 : $priceValues->min();
                @endphp
                <div class="event-item-card" data-title="{{ strtolower($event->title) }}" data-desc="{{ strtolower($event->description ?? '') }}" data-venue="{{ strtolower($event->venue_name ?? $event->venue ?? '') }}" data-address="{{ strtolower($event->venue_address ?? '') }}" data-category="{{ $catType }}" data-date="{{ $event->date_str ?? '' }}" data-event-date="{{ substr((string) ($event->event_date_iso ?? ''), 0, 10) }}" data-state="{{ $event->state_code ?? '' }}" data-city="{{ strtolower($event->city ?? '') }}" data-zip="{{ $event->zip_code ?? '' }}" data-price="{{ $lowestPrice }}" data-available="{{ $event->isOrderingAllowed() ? '1' : '0' }}">
                    <div style="height: 170px; position: relative;">
                        <img src="{{ $gridImg }}" alt="{{ $event->title }}" style="width: 100%; height: 100%; object-fit: cover;">
                        <div style="position: absolute; inset: 0; background: linear-gradient(180deg, transparent 40%, rgba(15, 23, 42, 0.85) 100%);"></div>
                        <span style="position: absolute; top: 10px; left: 10px; background: rgba(15, 23, 42, 0.85); color: #fff; padding: 3px 10px; border-radius: 9999px; font-size: 0.68rem; font-weight: 700; text-transform: uppercase;">
                            {{ $catType }}
                        </span>
                    </div>

                    <div style="padding: 18px; display: flex; flex-direction: column; flex: 1; justify-content: space-between;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 6px; font-size: 0.74rem; color: #818CF8; font-weight: 600; margin-bottom: 6px;">
                                <span>📅 {{ $event->date_str ?? 'Date TBA' }}</span>
                                <span>&bull;</span>
                                <span>⏰ {{ $event->time_str ?? '12:00 PM' }}</span>
                            </div>

                            <h3 style="font-size: 1.05rem; font-weight: 700; color: #FFFFFF; line-height: 1.35; margin-bottom: 6px;">
                                {{ $event->title }}
                            </h3>

                            <p style="font-size: 0.8rem; color: #94A3B8; line-height: 1.5; margin-bottom: 12px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                {{ $event->description ?? $event->tagline ?? 'Authentic Caribbean entertainment & food.' }}
                            </p>

                            <div style="display: flex; align-items: center; gap: 5px; color: #CBD5E1; font-size: 0.75rem; margin-bottom: 14px;">
                                <span style="color: #EC4899;">📍</span>
                                <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 600;">
                                    {{ $event->venue_name ?? $event->venue ?? 'United States' }}
                                </span>
                            </div>
                        </div>

                        <div>
                            <a href="{{ route('events.show', $event->id) }}" class="btn-gradient-cta" style="width: 100%; box-sizing: border-box; padding: 9px; font-size: 0.82rem;">
                                View Event & Menu &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Empty Results State -->
        <div id="no-events-found" style="display: none; background: #162038; border: 1px dashed rgba(255, 255, 255, 0.15); border-radius: 20px; padding: 44px 20px; text-align: center; margin: 20px 0;">
            <div style="font-size: 2.5rem; margin-bottom: 10px;">🔍</div>
            <h3 style="font-size: 1.2rem; font-weight: 700; color: #FFFFFF; margin-bottom: 6px;">No Events Found</h3>
            <p style="font-size: 0.85rem; color: #94A3B8; margin-bottom: 16px;">Try adjusting your search terms or clearing your location and date filters.</p>
            <button type="button" onclick="clearAllFilters()" class="btn-gradient-cta" style="padding: 8px 20px; font-size: 0.82rem; border-radius: 9999px;">
                Clear All Filters
            </button>
        </div>
    </div>

    <!-- 7. Bottom Navigation (5 navigation items) -->
    <nav class="discovery-bottom-nav" aria-label="Bottom Navigation">
        <a href="{{ route('events.index') }}" class="bottom-nav-link active" id="nav-bottom-home">
            <span style="font-size: 1.2rem;">🏠</span>
            <span>Home</span>
            <span class="nav-dot"></span>
        </a>
        <a href="#events-search-input" onclick="focusSearch()" class="bottom-nav-link" id="nav-bottom-explore">
            <span style="font-size: 1.2rem;">🧭</span>
            <span>Explore</span>
        </a>
        @auth
            <a href="{{ route('attendee.dashboard') }}" class="bottom-nav-link" id="nav-bottom-tickets">
                <span style="font-size: 1.2rem;">🎟️</span>
                <span>Tickets</span>
            </a>
        @else
            <a href="{{ route('login') }}" class="bottom-nav-link" id="nav-bottom-tickets">
                <span style="font-size: 1.2rem;">🎟️</span>
                <span>Tickets</span>
            </a>
        @endauth
        <a href="#trending-container" class="bottom-nav-link" id="nav-bottom-food">
            <span style="font-size: 1.2rem;">🍲</span>
            <span>Food</span>
        </a>
        @auth
            <a href="{{ route('attendee.dashboard') }}" class="bottom-nav-link" id="nav-bottom-profile">
                <span style="font-size: 1.2rem;">👤</span>
                <span>Profile</span>
            </a>
        @else
            <a href="{{ route('login') }}" class="bottom-nav-link" id="nav-bottom-profile">
                <span style="font-size: 1.2rem;">🔑</span>
                <span>Sign In</span>
            </a>
        @endauth
    </nav>
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
            const title = card.getAttribute('data-title') || '';
            const desc = card.getAttribute('data-desc') || '';
            const venue = card.getAttribute('data-venue') || '';
            const addr = card.getAttribute('data-address') || '';
            const cat = card.getAttribute('data-category') || '';
            const dateStr = card.getAttribute('data-date') || '';
            const eventDateText = card.getAttribute('data-event-date') || '';
            const eventDate = eventDateText
                ? new Date(`${eventDateText}T00:00:00`)
                : new Date(dateStr);
            const state = card.getAttribute('data-state') || '';
            const city = card.getAttribute('data-city') || '';
            const zip = card.getAttribute('data-zip') || '';
            const price = parseFloat(card.getAttribute('data-price') || '0');
            const available = card.getAttribute('data-available') === '1';
            const stateFilter = document.getElementById('filter-state').value;
            const cityFilter = document.getElementById('filter-city').value;
            const zipFilter = (document.getElementById('filter-zip').value || '').trim();

            let matchQuery = true;
            if (q) {
                matchQuery = title.includes(q) || desc.includes(q) || venue.includes(q) || addr.includes(q) || cat.includes(q);
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
            const matchPrice = (!minPrice || price >= parseFloat(minPrice)) && (!maxPrice || price <= parseFloat(maxPrice));
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
                card.style.display = 'flex';
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

    function focusSearch() {
        const el = document.getElementById('events-search-input');
        if (el) {
            el.focus();
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
</script>
@endsection
