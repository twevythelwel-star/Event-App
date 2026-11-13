@extends('layouts.app')

@section('title', ($event->title ?? 'Event Details') . ' — EventBite')
@section('page-title', $event->title ?? 'Event Details')

@section('content')
@php
    $currency ??= ($event->currency ?: \App\Support\Currency::defaultCode());
    $tiers ??= \App\Services\Checkout\TicketTierCatalog::sellable($event);
    $menu ??= $event->menuItems->where('is_active', true)->sortBy('sort_order')->values();
    $availability ??= \App\Support\EventListing::availability($event);
    $fromPriceText ??= \App\Support\EventListing::fromPriceText($event);
    $canOrder ??= (in_array($availability['key'], ['on_sale', 'food'], true) || ($event->isOrderingAllowed() && $menu->isNotEmpty() && $event->supports_food_ordering));
    $paymentMethods ??= \App\Services\Checkout\PaymentOptions::forEvent($event);
    $chargeRules ??= (new \App\Services\Pricing\ChargeCalculator())->applicableRules($event);
    $packagingFeeMinor ??= ($menu->isNotEmpty() ? (int) $event->packaging_fee_minor : 0);
    $policy ??= \App\Models\HostPaymentPolicy::where('organizer_id', $event->organizer_id)->first();
    $eventQr ??= null;
@endphp
@push('head')
    <x-preload-image :image="$event->coverImage()" sizes="100vw" />
@endpush
<style>
    /* 3D Soft Realism & Dark Mode Elevation for Event Details */
    .event-detail-root {
        background-color: var(--color-background);
        min-height: 100vh;
        color: var(--color-text-primary);
        font-family: var(--font-sans);
        margin: calc(-1 * var(--layout-page-padding-y)) calc(-1 * var(--layout-page-padding-x)) 0;
        padding-bottom: 5rem;
        overflow-wrap: anywhere;
    }

    /* The banner keeps the site's banner shape (21:9), and the event-cover shape (16:10) on phones, within limits */
    .detail-hero {
        position: relative;
        width: 100%;
        aspect-ratio: var(--ratio-banner, 21 / 9);
        min-height: 260px;
        max-height: 480px;
        overflow: hidden;
        background-color: var(--color-surface-recessed);
        border-bottom: 1px solid var(--color-border-strong);
    }
    @media (max-width: 639px) { .detail-hero { aspect-ratio: var(--ratio-event, 16 / 10); } }

    .detail-hero-img {
        position: relative;
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center 30%;
        filter: brightness(0.92);
    }

    .detail-hero-backdrop {
        position: absolute;
        inset: -40px;
        width: calc(100% + 80px);
        height: calc(100% + 80px);
        object-fit: cover;
        filter: blur(28px) saturate(1.3) brightness(0.68);
        transform: scale(1.1);
    }

    .detail-hero-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(7, 17, 31, 0.58) 0%, rgba(7, 17, 31, 0.18) 45%, rgba(7, 17, 31, 0.94) 90%, var(--color-background) 100%);
        pointer-events: none;
    }

    .detail-hero-nav {
        position: absolute;
        top: 20px;
        left: 0;
        right: 0;
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        z-index: 10;
    }

    .btn-glass {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background-color: rgba(13, 27, 45, 0.82);
        background-image: var(--gradient-secondary-btn-3d);
        backdrop-filter: blur(12px);
        color: var(--color-text-primary);
        border: 1px solid var(--color-border-strong);
        border-top-color: rgba(255, 255, 255, 0.18);
        border-radius: 9999px;
        padding: 8px 18px;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
        box-shadow: var(--shadow-btn-secondary);
        transition: transform 0.18s var(--ease-out-quart), border-color 0.18s ease, background-color 0.18s ease;
    }

    .btn-glass:hover {
        background-color: var(--color-surface-elevated);
        border-color: var(--color-accent-border);
        color: var(--color-text-primary);
        transform: translateY(-1px);
    }

    .icon-glass-btn {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background-color: rgba(13, 27, 45, 0.82);
        background-image: var(--gradient-secondary-btn-3d);
        backdrop-filter: blur(12px);
        border: 1px solid var(--color-border-strong);
        border-top-color: rgba(255, 255, 255, 0.18);
        color: var(--color-text-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 16px;
        box-shadow: var(--shadow-btn-secondary);
        transition: transform 0.18s var(--ease-out-quart), border-color 0.18s ease;
    }

    .icon-glass-btn:hover {
        border-color: var(--color-accent-border);
        transform: translateY(-1px);
    }

    .detail-main-layout {
        max-width: 1200px;
        margin: -44px auto 0;
        padding: 0 24px;
        position: relative;
        z-index: 5;
        display: grid;
        /* minmax(0, 1fr): long words and links wrap instead of widening the column past the screen */
        grid-template-columns: minmax(0, 1fr) 380px;
        gap: 32px;
        align-items: start;
    }

    @media (max-width: 900px) {
        .detail-main-layout {
            grid-template-columns: minmax(0, 1fr);
            margin-top: -20px;
            padding: 0 16px;
        }
    }

    .card-navy {
        background-color: var(--color-surface);
        background-image: var(--gradient-surface-3d);
        border-radius: 20px;
        border: 1px solid var(--color-border);
        border-top-color: rgba(255, 255, 255, 0.13);
        padding: 24px;
        box-shadow: var(--shadow-card);
    }

    .detail-well {
        background-color: var(--color-surface-recessed);
        border-radius: 14px;
        border: 1px solid var(--color-border-strong);
        box-shadow: var(--shadow-3d-inset);
        padding: 16px;
    }

    .detail-highlights-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(175px, 1fr));
        gap: 10px;
    }

    .detail-highlight-pill {
        display: flex;
        flex-direction: column;
        gap: 3px;
        padding: 11px 14px;
        border-radius: 12px;
        background-color: var(--color-surface-recessed);
        border: 1px solid var(--color-border);
        box-shadow: var(--shadow-3d-inset);
    }

    .detail-highlight-label {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--color-text-muted);
    }

    .detail-highlight-value {
        font-size: 13px;
        font-weight: 700;
        color: var(--color-text-primary);
    }

    .section-tab-nav {
        background-color: var(--color-surface-recessed);
        padding: 6px;
        border-radius: 16px;
        display: flex;
        border: 1px solid var(--color-border-strong);
        box-shadow: var(--shadow-3d-inset);
        gap: 6px;
        margin-bottom: 24px;
        overflow-x: auto;
    }

    .section-tab-btn {
        flex: 1;
        padding: 12px 16px;
        border-radius: 12px;
        border: 1px solid transparent;
        background-color: transparent;
        color: var(--color-text-secondary);
        font-weight: 700;
        font-size: 14px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        white-space: nowrap;
        transition: all 0.2s ease;
    }

    .section-tab-btn:hover:not(.active) {
        color: var(--color-text-primary);
        background-color: rgba(255, 255, 255, 0.04);
    }

    .section-tab-btn.active {
        background-color: var(--color-surface-elevated);
        background-image: var(--gradient-surface-elevated-3d);
        color: var(--color-accent);
        border-color: var(--color-accent-border);
        border-top-color: rgba(199, 255, 69, 0.45);
        box-shadow: var(--shadow-subtle);
    }

    .tab-pane {
        display: none;
    }

    .tab-pane.active {
        display: block;
    }

    .gallery-scroll {
        display: flex;
        gap: 14px;
        overflow-x: auto;
        padding-bottom: 12px;
        scroll-snap-type: x mandatory;
        -webkit-overflow-scrolling: touch;
    }

    .gallery-item {
        width: 220px;
        aspect-ratio: var(--ratio-photo, 4 / 3);
        border-radius: 14px;
        overflow: hidden;
        border: 1px solid var(--color-border-strong);
        border-top-color: rgba(255, 255, 255, 0.14);
        background-color: var(--color-surface-recessed);
        box-shadow: var(--shadow-subtle);
        position: relative;
        flex-shrink: 0;
        cursor: pointer;
        transition: transform 0.2s var(--ease-out-quart), border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .gallery-item:hover {
        transform: translateY(-3px);
        border-color: var(--color-accent-border);
        box-shadow: var(--shadow-card);
    }

    .gallery-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.35s var(--ease-out-quart);
    }
    .gallery-item:hover img {
        transform: scale(1.04);
    }
    .gallery-open { all: unset; display: block; width: 100%; height: 100%; cursor: zoom-in; }
    .gallery-open:focus-visible { outline: 2px solid var(--color-accent); outline-offset: -2px; }
    .gallery-tag { display: block; font-size: 10px; font-weight: 600; color: #CBD5E1; }

    .gallery-caption {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(180deg, rgba(0,0,0,0) 0%, rgba(7, 17, 31, 0.92) 100%);
        padding: 10px 12px 8px;
        font-size: 11px;
        font-weight: 700;
        color: #FFFFFF;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        pointer-events: none;
    }

    .btn-gradient-purchase {
        width: 100%;
        background-color: var(--color-accent);
        background-image: var(--gradient-accent-3d);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 14px;
        padding: 15px 24px;
        font-size: 15px;
        font-weight: 800;
        color: var(--color-accent-text);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        box-shadow: var(--shadow-btn-primary);
        transition: transform 0.18s var(--ease-out-quart), box-shadow 0.18s ease, background-color 0.18s ease;
        text-decoration: none;
        box-sizing: border-box;
    }

    .btn-gradient-purchase:hover {
        background-color: var(--color-accent-hover);
        transform: translateY(-2px);
        box-shadow: var(--shadow-btn-primary-hover);
        color: var(--color-accent-text);
    }

    .tier-option-card {
        background-color: var(--color-surface);
        background-image: var(--gradient-surface-3d);
        border: 1px solid var(--color-border);
        border-top-color: rgba(255, 255, 255, 0.13);
        border-radius: 16px;
        padding: 20px;
        display: flex;
        justify-content: space-between;
        gap: 16px;
        align-items: flex-start;
        box-shadow: var(--shadow-subtle);
        transition: transform 0.2s var(--ease-out-quart), border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .tier-option-card:hover {
        transform: translateY(-2px);
        border-color: var(--color-border-strong);
        border-top-color: rgba(199, 255, 69, 0.35);
        box-shadow: var(--shadow-card);
    }

    .menu-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 16px;
    }

    @media (max-width: 767px) {
        body:has(#event-detail-page) .eb-app-header { display: none !important; }
        body:has(#event-detail-page) main.content-canvas { padding-top: 0 !important; padding-left: 0 !important; padding-right: 0 !important; padding-bottom: 84px !important; }
        #event-detail-page .detail-hero { min-height: 0; height: auto; aspect-ratio: 4 / 5; max-height: 560px; border: 0; }
        #event-detail-page .detail-hero-img { position: absolute; inset: 0; }
        #event-detail-page .detail-hero-overlay { background: linear-gradient(180deg, rgba(7,17,31,.08) 35%, rgba(7,17,31,.45) 64%, rgba(7,17,31,.98) 100%); }
        #event-detail-page .detail-hero-nav { top: max(14px, env(safe-area-inset-top, 0px)); padding: 0 14px; }
        #event-detail-page .detail-hero-nav .btn-glass { width: 42px; height: 42px; padding: 0; justify-content: center; overflow: hidden; font-size: 0; }
        #event-detail-page .detail-hero-nav .btn-glass span:first-child { font-size: 1.4rem; }
        #event-detail-page .detail-hero-nav .btn-glass span:last-child { display: none; }
        #event-detail-page .detail-main-layout { margin: -56px 0 0; padding: 0 14px; gap: 18px; }
        #event-detail-page .card-navy { padding: 18px; border-radius: 18px; }
        #event-detail-page .section-tab-nav { position: sticky; top: 0; z-index: 20; margin: 0 -14px 4px; border-radius: 0; border-left: 0; border-right: 0; padding: 6px 10px; }
        #event-detail-page .section-tab-btn { min-height: 44px; padding: 8px 10px; font-size: .76rem; }
        #event-detail-page .gallery-item { width: min(70vw, 260px); }
        #event-detail-page .eb-buy-bar { bottom: calc(var(--layout-bottom-nav-height) + env(safe-area-inset-bottom, 0px)); padding: 9px 14px; }
        #event-detail-page .eb-buy-bar .eb-btn { min-width: 52%; min-height: 48px; border-radius: 999px; }
        .eb-customer-bottom-nav { height: calc(var(--layout-bottom-nav-height) + env(safe-area-inset-bottom, 0px)); padding-bottom: env(safe-area-inset-bottom, 0px); }
        /* The page canvas has no side padding here, so the root must not pull itself outwards (that scrolled sideways). */
        #event-detail-page.event-detail-root { margin-left: 0; margin-right: 0; }
        /* The app design: on phones the title block sits on the page itself, not in a card. */
        #event-detail-page .detail-intro.card-navy { background: transparent; border: 0; box-shadow: none; padding: 4px 2px 0; }
        #event-detail-page #venue-information { background: transparent; border: 0; box-shadow: none; padding: 0; gap: 12px; }
    }

    .detail-title { font-family: var(--font-display); font-size: clamp(1.75rem, 7vw, 2.4rem); font-weight: 800; color: #FFFFFF; line-height: 1.1; margin: 0 0 6px; letter-spacing: -0.02em; }
    .detail-kicker { display: flex; flex-wrap: wrap; gap: 4px 8px; margin: 0 0 8px; font-size: 0.95rem; font-weight: 600; color: var(--color-text-secondary); }
    .detail-kicker a { color: var(--color-text-secondary); text-decoration: none; }
    .detail-kicker a:hover { color: var(--color-accent); text-decoration: underline; }
    .detail-info-icon { flex-shrink: 0; display: inline-grid; place-items: center; width: 36px; height: 36px; border-radius: 10px; background: var(--color-accent-subtle); color: var(--color-accent); }
    .detail-subhead { font-size: 1rem; font-weight: 800; color: #FFFFFF; margin: 0 0 10px; }
    .detail-food-strip { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin: 0; padding: 0; list-style: none; }
    @media (min-width: 640px) { .detail-food-strip { grid-template-columns: repeat(6, minmax(0, 1fr)); } }
    .detail-food-strip button { position: relative; display: block; width: 100%; aspect-ratio: 4 / 3; padding: 0; border: 1px solid var(--color-border); border-radius: 12px; overflow: hidden; cursor: pointer; background: var(--color-surface-recessed); }
    .detail-food-strip img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .detail-food-strip .eb-image-label { font-size: 9px !important; padding: 2px 6px !important; left: 4px !important; bottom: 4px !important; }
    .detail-cta-inline .btn-gradient-purchase { border-radius: 9999px; min-height: 52px; font-size: 1rem; }
    @media (min-width: 901px) { .detail-cta-inline { display: none; } }
    .icon-glass-btn { text-decoration: none; }
    .icon-glass-btn.is-saved { color: var(--color-accent); border-color: var(--color-accent-border); }
    .icon-glass-btn.is-saved svg { fill: currentColor; }
    /* Tabs as an underlined row, as in the app design. */
    .section-tab-nav { background: transparent; border: 0; border-bottom: 1px solid var(--color-border); border-radius: 0; box-shadow: none; padding: 0; gap: 4px; }
    .section-tab-btn { border-radius: 0; border: 0; border-bottom: 3px solid transparent; padding: 12px 10px; }
    .section-tab-btn.active { background: transparent; background-image: none; box-shadow: none; color: #FFFFFF; border-color: transparent; border-bottom-color: var(--color-accent); }
</style>

<div class="event-detail-root" id="event-detail-page">
    <!-- Toast Notification -->
    <div id="detail-toast" style="display: none; position: fixed; top: 24px; left: 50%; transform: translateX(-50%); background-color: #1E293B; color: #FFFFFF; padding: 12px 24px; border-radius: 9999px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.15); z-index: 9999; font-size: 14px; font-weight: 600;"></div>

    <!-- 1. Cinematic Hero Section -->
    <div class="detail-hero">
        @php
            $cover = $event->coverImage();
            $heroImg = $cover->src;
            // A photograph (the host's flyer, or a labelled library photo) fills the banner; generated artwork is shown whole.
            $isPhoto = $event->hasFlyer() || $cover->illustrative;
        @endphp
        {{-- A generated flyer is text, so it is shown whole rather than cropped; uploaded artwork fills the banner,
             at the size the screen needs and cropped around the host's focal point. Behind a whole flyer, a blurred
             copy of the same artwork fills the full width. --}}
        @unless($isPhoto)
            <img src="{{ $heroImg }}" alt="" aria-hidden="true" class="detail-hero-backdrop">
        @endunless
        <x-responsive-image :image="$cover" :alt="$event->hasFlyer() ? ($event->flyer_alt_text ?: $event->title) : $cover->alt" class="detail-hero-img" sizes="100vw" :eager="true"
            :fit="$isPhoto ? 'cover' : 'contain'"
            :style="$isPhoto ? ('object-position:' . e($event->flyer_position ?: '50% 35%')) : 'background: transparent; filter: drop-shadow(0 18px 40px rgba(0,0,0,.5));'" />
        <div class="detail-hero-overlay"></div>
        <x-image-label :image="$cover" corner="top-left" />

        <!-- Floating Top Navigation -->
        <div class="detail-hero-nav">
            <a href="{{ route('events.index') }}" class="icon-glass-btn" id="btn-back-to-discovery" title="Back to all events">
                <x-icon name="arrow-left" size="md" />
                <span class="sr-only">Back to all events</span>
            </a>

            <div style="display: flex; align-items: center; gap: 10px;">
                @auth
                    @php $heroSaved = \App\Models\SavedEvent::where('user_id', auth()->id())->where('event_id', $event->id)->exists(); @endphp
                    <form method="POST" action="{{ route('events.save.toggle', $event->id) }}" style="margin: 0;">
                        @csrf
                        <button type="submit" class="icon-glass-btn{{ $heroSaved ? ' is-saved' : '' }}" aria-pressed="{{ $heroSaved ? 'true' : 'false' }}" title="{{ $heroSaved ? 'Remove from saved events' : 'Save this event' }}">
                            <x-icon name="heart" size="md" />
                            <span class="sr-only">Save this event</span>
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="icon-glass-btn" title="Sign in to save this event">
                        <x-icon name="heart" size="md" />
                        <span class="sr-only">Sign in to save this event</span>
                    </a>
                @endauth
                <button type="button" class="icon-glass-btn" onclick="shareCurrentEvent()" title="Share Event" aria-label="Share this event">
                    <x-icon name="share" size="md" />
                </button>
            </div>
        </div>
    </div>

    <!-- 2. Main Content Layout (Desktop: Left Info / Right Sticky Ticket Panel) -->
    <div class="detail-main-layout">
        <!-- LEFT COLUMN: Metadata, Tabs, Media Gallery & Organizer -->
        <div style="display: flex; flex-direction: column; gap: 28px;">
            <!-- Event Title, Description, Highlights & Venue Information -->
            <div class="card-navy detail-intro" style="position: relative; display: flex; flex-direction: column; gap: 18px;">
                <div>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px;">
                        @php $heroTone = ['success' => 'eb-badge-success', 'danger' => 'eb-badge-error', 'warning' => 'eb-badge-warning'][$availability['tone']] ?? 'eb-badge-info'; @endphp
                        <span class="eb-badge {{ $heroTone }}">
                            <span class="eb-badge-dot" aria-hidden="true"></span>
                            <span>{{ $availability['label'] }}</span>
                        </span>
                        @if($event->is_ordering_open)
                            <span class="eb-badge eb-badge-accent">
                                ✓ Taking orders
                            </span>
                        @endif
                    </div>

                    <h1 class="detail-title">
                        {{ $event->title }}
                    </h1>
                    {{-- The kind of event and its traditions on one line ("Music • Food • Culture" in the app design). --}}
                    <p class="detail-kicker">
                        <span>{{ $event->category ?: 'Event' }}</span>
                        @foreach($event->traditionLabels() as $tradition)
                            <span aria-hidden="true">•</span>
                            <a href="{{ route('events.index', ['q' => $tradition]) }}">{{ $tradition }}</a>
                        @endforeach
                    </p>

                    @if(!empty($event->tagline))
                        <p style="font-size: 15px; color: var(--color-text-secondary); line-height: 1.5; margin: 0; font-weight: 600;">
                            {{ $event->tagline }}
                        </p>
                    @endif
                </div>

                <!-- Date & Venue Primary Info -->
                @php
                    $fullAddress = trim(implode(', ', array_filter([
                        $event->street_address,
                        $event->city,
                        trim(implode(' ', array_filter([$event->state_code, $event->zip_code]))),
                    ]))) ?: $event->venue_address;
                    $venueTypeLabel = $event->venue_type ? (\App\Models\Event::VENUE_TYPES[$event->venue_type] ?? $event->venue_type) : null;
                @endphp
                <div class="detail-well" id="venue-information" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                    <div style="display: flex; gap: 12px; align-items: flex-start;">
                        <span class="detail-info-icon" aria-hidden="true"><x-icon name="calendar" size="md" /></span>
                        <div>
                            <div style="font-size: 10px; text-transform: uppercase; color: var(--color-text-muted); font-weight: 700; letter-spacing: 0.05em;">Date &amp; Time</div>
                            <div style="font-size: 14px; font-weight: 800; color: #FFFFFF; margin-top: 2px;">
                                {{ $event->date_str ?? ($event->date ? (is_string($event->date) ? date('l, F j, Y', strtotime($event->date)) : $event->date->format('l, F j, Y')) : 'Date TBA') }}
                            </div>
                            <div style="font-size: 12px; color: var(--color-text-secondary); margin-top: 2px;">
                                {{ $event->time_str ?: 'Time to be announced' }}@if($event->timezone) ({{ $event->timezone }})@endif
                            </div>
                            @if($event->doors_open_time)
                                <div style="font-size: 11px; color: var(--color-accent); font-weight: 600; margin-top: 3px;">
                                    Doors open: {{ $event->doors_open_time }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <div style="display: flex; gap: 12px; align-items: flex-start;">
                        <span class="detail-info-icon" aria-hidden="true"><x-icon name="map-pin" size="md" /></span>
                        <div>
                            <div style="font-size: 10px; text-transform: uppercase; color: var(--color-text-muted); font-weight: 700; letter-spacing: 0.05em;">Venue &amp; Location</div>
                            <div style="font-size: 14px; font-weight: 800; color: #FFFFFF; margin-top: 2px;">
                                {{ $event->venue_name ?: 'Venue to be announced' }}
                                @if($event->venue_building_name)
                                    <span style="font-weight: 600; color: var(--color-text-secondary);"> · {{ $event->venue_building_name }}</span>
                                @endif
                            </div>
                            @if($fullAddress)
                                <div style="font-size: 12px; color: var(--color-text-secondary); margin-top: 2px;">
                                    {{ $fullAddress }}
                                </div>
                            @endif
                            @if($venueTypeLabel)
                                <div style="font-size: 11px; color: var(--color-text-muted); margin-top: 3px;">
                                    {{ $venueTypeLabel }}
                                </div>
                            @endif
                            @if($event->latitude !== null && $event->longitude !== null && $event->location_verified_at !== null)
                                <div style="margin-top: 6px;">
                                    <a href="https://www.openstreetmap.org/?mlat={{ urlencode((string) $event->latitude) }}&mlon={{ urlencode((string) $event->longitude) }}#map=16/{{ urlencode((string) $event->latitude) }}/{{ urlencode((string) $event->longitude) }}" target="_blank" rel="noopener" style="font-size: 11px; font-weight: 700; color: var(--color-accent); text-decoration: none;">
                                        View verified map location ↗
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                @php $venuePhotos = $event->visiblePhotos('venue'); @endphp
                @if($venuePhotos->isNotEmpty())
                    {{-- The host's own photos of the venue: what the place looks like on arrival. --}}
                    <ul class="eb-venue-photos" aria-label="Photos of the venue">
                        @foreach($venuePhotos as $photo)
                            <li><x-responsive-image :image="$photo->responsiveImage()" :alt="$photo->alt_text" sizes="(min-width: 768px) 240px, 70vw" /></li>
                        @endforeach
                    </ul>
                @endif

                @if($event->venue_instructions || $event->parking_information || $event->accessibility_information)
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                        @if($event->venue_instructions)
                            <div class="detail-highlight-pill">
                                <span class="detail-highlight-label">Arrival &amp; Entry</span>
                                <span class="detail-highlight-value" style="font-weight: 500; font-size: 12px;">{{ $event->venue_instructions }}</span>
                            </div>
                        @endif
                        @if($event->parking_information)
                            <div class="detail-highlight-pill">
                                <span class="detail-highlight-label">Parking</span>
                                <span class="detail-highlight-value" style="font-weight: 500; font-size: 12px;">{{ $event->parking_information }}</span>
                            </div>
                        @endif
                        @if($event->accessibility_information)
                            <div class="detail-highlight-pill">
                                <span class="detail-highlight-label">Accessibility</span>
                                <span class="detail-highlight-value" style="font-weight: 500; font-size: 12px;">{{ $event->accessibility_information }}</span>
                            </div>
                        @endif
                    </div>
                @endif

                <!-- Event Description -->
                @if($event->summary || $event->description)
                    <div id="event-description" style="padding-top: 4px; border-top: 1px solid var(--color-border);">
                        <h2 style="font-size: 14px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-secondary); margin: 0 0 8px;">Event Description</h2>
                        @if($event->summary && $event->summary !== $event->description)
                            <p style="font-size: 14px; font-weight: 600; color: var(--color-text-primary); line-height: 1.55; margin: 0 0 8px;">{{ $event->summary }}</p>
                        @endif
                        @if($event->description)
                            <p style="font-size: 13.5px; color: var(--color-text-secondary); line-height: 1.65; margin: 0; white-space: pre-line;">{{ $event->description }}</p>
                        @endif
                    </div>
                @endif

                @php
                    // Dishes from the event's own menu that have a photo: the "Food Highlights" strip.
                    $foodHighlights = $menu->map(fn ($dish) => ['item' => $dish, 'image' => $dish->responsiveImage()])
                        ->filter(fn ($row) => $row['image'] !== null)->take(6)->values();
                @endphp
                @if($foodHighlights->isNotEmpty())
                    <div id="food-highlights">
                        <h2 class="detail-subhead">Food Highlights</h2>
                        <ul class="detail-food-strip">
                            @foreach($foodHighlights as $row)
                                <li>
                                    <button type="button" onclick="switchSection('food', true)" title="{{ $row['item']->name }}">
                                        <x-responsive-image :image="$row['image']" :alt="$row['item']->name . ' (see the menu)'" sizes="(min-width: 640px) 160px, 31vw" />
                                        <x-image-label :image="$row['image']" />
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if($canOrder)
                    {{-- Phones and tablets: the way to buy, right under the essentials (the side panel comes later). --}}
                    <div class="detail-cta-inline" id="detail-cta-inline">
                        @auth
                            <a href="{{ route('checkout.tickets', $event) }}" class="btn-gradient-purchase">
                                <x-icon name="ticket" size="md" />
                                <span>{{ collect($tiers)->contains('on_sale', true) ? 'Get Tickets' : 'Order Food' }}</span>
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="btn-gradient-purchase">
                                <x-icon name="ticket" size="md" />
                                <span>Sign in to get tickets</span>
                            </a>
                        @endauth
                    </div>
                @endif

                <!-- Event Highlights (derived strictly from populated event attributes) -->
                @php
                    $formatLabels = ['in_person' => 'In-Person Event', 'virtual' => 'Virtual / Online', 'hybrid' => 'Hybrid (In-Person + Online)'];
                    $ageLabels = ['all_ages' => 'All Ages Welcome', '18_plus' => '18+ Only', '21_plus' => '21+ Only', 'family_friendly' => 'Family Friendly'];
                    $dressLabels = ['casual' => 'Casual', 'smart_casual' => 'Smart Casual', 'island_chic' => 'Island Chic', 'semi_formal' => 'Semi-Formal', 'formal' => 'Formal / Black Tie', 'costume' => 'Themed / Costume'];
                    $highlights = array_filter([
                        'Format' => $event->event_format ? ($formatLabels[$event->event_format] ?? ucfirst(str_replace('_', ' ', (string) $event->event_format))) : null,
                        'Category' => $event->category ?: null,
                        'Traditions' => $event->traditionLabels() !== [] ? implode(', ', $event->traditionLabels()) : null,
                        'Food Service' => ($event->food_service_start_time && $event->food_service_end_time)
                            ? ($event->food_service_start_time . ' – ' . $event->food_service_end_time)
                            : ($event->supports_food_ordering && $menu->isNotEmpty() ? ($menu->count() . ' menu items available') : null),
                        'Age Policy' => $event->age_restriction ? ($ageLabels[$event->age_restriction] ?? ucfirst(str_replace('_', ' ', (string) $event->age_restriction))) : null,
                        'Dress Code' => $event->dress_code ? ($dressLabels[$event->dress_code] ?? ucfirst(str_replace('_', ' ', (string) $event->dress_code))) : null,
                        'Alcohol Service' => $event->serves_alcohol ? 'Served (21+ valid ID required)' : null,
                        'Starting Price' => $fromPriceText ?: null,
                    ]);
                @endphp
                @if($highlights !== [])
                    <div id="event-highlights" style="padding-top: 4px; border-top: 1px solid var(--color-border);">
                        <h2 style="font-size: 14px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-secondary); margin: 0 0 10px;">Event Highlights</h2>
                        <div class="detail-highlights-grid">
                            @foreach($highlights as $hLabel => $hValue)
                                <div class="detail-highlight-pill">
                                    <span class="detail-highlight-label">{{ $hLabel }}</span>
                                    <span class="detail-highlight-value">{{ $hValue }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- 3. Section Switcher Tabs (Tickets · Food · Details) -->
            <div>
                <div class="section-tab-nav" role="tablist" aria-label="Event information">
                    <button type="button" role="tab" class="section-tab-btn active" id="tab-tickets-btn" aria-selected="true" aria-controls="pane-tickets" onclick="switchSection('tickets')">
                        <span>Tickets</span>
                    </button>
                    <button type="button" role="tab" class="section-tab-btn" id="tab-food-btn" aria-selected="false" aria-controls="pane-food" tabindex="-1" onclick="switchSection('food')">
                        <span>Food ({{ $menu->count() }})</span>
                    </button>
                    <button type="button" role="tab" class="section-tab-btn" id="tab-details-btn" aria-selected="false" aria-controls="pane-details" tabindex="-1" onclick="switchSection('details')">
                        <span>Details</span>
                    </button>
                </div>

                <!-- TAB 1: TICKETS & MENU PREVIEW (host-configured types from TicketTierCatalog + dish previews) -->
                <div class="tab-pane active" id="pane-tickets" role="tabpanel" aria-labelledby="tab-tickets-btn" tabindex="0">
                    @if(empty($tiers))
                        <div class="card-navy" style="text-align: center; padding: 36px 20px;">
                            <h3 style="font-size: 16px; font-weight: 700; color: #FFFFFF; margin: 0 0 6px;">No tickets on sale</h3>
                            <p style="font-size: 13px; color: var(--color-text-secondary); margin: 0;">
                                {{ $menu->isNotEmpty() ? 'This event sells food only. See the Food tab.' : 'The organizer hasn’t put tickets on sale yet.' }}
                            </p>
                        </div>
                    @else
                        <div style="display: flex; flex-direction: column; gap: 14px;">
                            @foreach($tiers as $tier)
                                <div class="tier-option-card">
                                    <div style="min-width: 0;">
                                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                            <h3 style="font-size: 16px; font-weight: 800; color: #FFFFFF; margin: 0;">{{ $tier['name'] }}</h3>
                                            @if(!empty($tier['category']))
                                                <span class="eb-badge eb-badge-purple">{{ $tier['category'] }}</span>
                                            @endif
                                        </div>
                                        @if($tier['description'])<p style="font-size: 13px; color: var(--color-text-secondary); margin: 6px 0 0; line-height: 1.45;">{{ $tier['description'] }}</p>@endif
                                        <div style="margin-top: 10px; display: flex; flex-wrap: wrap; align-items: center; gap: 8px;">
                                            <x-tier-availability :tier="$tier" />
                                            @if(!empty($tier['max_per_order']))
                                                <span style="font-size: 11px; color: var(--color-text-muted);">Up to {{ (int) $tier['max_per_order'] }} per order</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div style="text-align: right; flex-shrink: 0; display: flex; flex-direction: column; align-items: flex-end; gap: 6px;">
                                        <div style="font-size: 21px; font-weight: 900; color: #FFFFFF; font-variant-numeric: tabular-nums;">{{ \App\Support\Money::formatMinor($tier['price_minor'], $tier['currency']) }}</div>
                                        <div style="font-size: 11px; color: var(--color-text-muted);">per ticket</div>
                                        @if($canOrder && !empty($tier['on_sale']))
                                            <a href="{{ auth()->check() ? route('checkout.tickets', $event) : route('login') }}" class="eb-btn eb-btn-secondary eb-btn-sm" style="margin-top: 4px; font-size: 12px; padding: 6px 12px;">
                                                Select
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($menu->isNotEmpty())
                        <div class="card-navy" id="menu-preview" style="margin-top: 22px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 14px;">
                                <div>
                                    <h3 style="font-size: 16px; font-weight: 800; color: #FFFFFF; margin: 0;">Menu Preview</h3>
                                    <p style="font-size: 12px; color: var(--color-text-secondary); margin: 2px 0 0;">
                                        {{ $menu->count() }} {{ \Illuminate\Support\Str::plural('item', $menu->count()) }} prepared for this event
                                    </p>
                                </div>
                                <button type="button" onclick="switchSection('food')" class="btn-glass" style="font-size: 12px; padding: 7px 14px;">
                                    <span>🍲</span>
                                    <span>View full menu ({{ $menu->count() }})</span>
                                </button>
                            </div>
                            <div class="menu-grid">
                                @foreach($menu->take(4) as $previewItem)
                                    <x-food-card :item="$previewItem" :currency="$currency" />
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <!-- TAB 2: FOOD & DINING (active menu items with dish photography) -->
                <div class="tab-pane" id="pane-food" role="tabpanel" aria-labelledby="tab-food-btn" tabindex="0">
                    @if($menu->isEmpty())
                        <div class="card-navy" style="text-align: center; padding: 36px 20px;">
                            <h3 style="font-size: 16px; font-weight: 700; color: #FFFFFF; margin: 0 0 6px;">No food menu</h3>
                            <p style="font-size: 13px; color: var(--color-text-secondary); margin: 0;">This event has no food to order in advance.</p>
                        </div>
                    @else
                        <div class="card-navy" style="margin-bottom: 16px; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
                            <div>
                                <p style="font-size: 13px; color: var(--color-text-primary); font-weight: 600; margin: 0;">
                                    @if($canOrder && $event->supports_food_ordering)
                                        Order food with your tickets or on its own, and collect it at the event with your order QR code.
                                    @else
                                        Food ordering is closed for this event. The menu is shown for reference.
                                    @endif
                                </p>
                                @if($event->food_service_start_time && $event->food_service_end_time)
                                    <p style="font-size: 12px; color: var(--color-text-secondary); margin: 4px 0 0;">
                                        Service hours: <strong style="color: #FFFFFF;">{{ $event->food_service_start_time }} – {{ $event->food_service_end_time }}</strong>
                                    </p>
                                @endif
                            </div>
                            @if($canOrder && $event->supports_food_ordering)
                                <a href="{{ auth()->check() ? route('checkout.tickets', $event) : route('login') }}" class="eb-btn eb-btn-primary eb-btn-sm">
                                    Order Food
                                </a>
                            @endif
                        </div>

                        <div class="menu-grid">
                            @foreach($menu as $item)
                                <x-food-card :item="$item" :currency="$currency" />
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- TAB 3: DETAILS: description, pickup, payment, fees, refunds and the event QR code -->
                <div class="tab-pane" id="pane-details" role="tabpanel" aria-labelledby="tab-details-btn" tabindex="0">
                    <div class="card-navy" style="display: flex; flex-direction: column; gap: 20px;">
                        <div>
                            <h3 style="font-size: 16px; font-weight: 800; color: #FFFFFF; margin: 0 0 8px;">About this event</h3>
                            <p style="font-size: 13px; color: #CBD5E1; line-height: 1.6; margin: 0; white-space: pre-line;">{{ $event->description ?: $event->tagline }}</p>
                        </div>

                        @if($event->pickup_instructions && $menu->isNotEmpty())
                            <div>
                                <h3 style="font-size: 15px; font-weight: 800; color: #FFFFFF; margin: 0 0 6px;">Food pickup</h3>
                                <p style="font-size: 13px; color: #CBD5E1; margin: 0;">{{ $event->pickup_instructions }}</p>
                            </div>
                        @endif

                        <div id="payment-methods">
                            <h3 style="font-size: 15px; font-weight: 800; color: #FFFFFF; margin: 0 0 8px;">Accepted payment methods</h3>
                            @if($paymentMethods === [])
                                <p style="font-size: 13px; color: #94A3B8; margin: 0;">The organizer has not set up a payment method yet, so orders can't be paid for at the moment.</p>
                            @else
                                <div class="eb-stack" style="gap: 8px;">
                                    @foreach($paymentMethods as $method)
                                        @php
                                            $pmVal = $method['value'] ?? '';
                                            $pmIcon = ['bank' => 'bank', 'zelle' => 'phone', 'cashapp' => 'phone', 'cash_app_pay' => 'phone', 'cash' => 'cash', 'card' => 'credit-card', 'paypal' => 'globe'][$pmVal] ?? ($method['icon'] ?? 'payment');
                                        @endphp
                                        <div class="eb-payment-card" style="display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-radius: 12px;">
                                            <span class="eb-payment-icon" data-method="{{ $pmVal }}" aria-hidden="true">
                                                <x-icon :name="$pmIcon" size="md" />
                                            </span>
                                            <div style="min-width: 0; flex: 1;">
                                                <strong style="color: #FFFFFF; font-size: 13px; display: block;">{{ $method['label'] }}</strong>
                                                <span style="font-size: 12px; color: #CBD5E1;">{{ $method['description'] }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <p style="font-size: 12px; color: #94A3B8; margin: 6px 0 0;">Transfers and cash are marked paid only after the organizer confirms them.</p>
                            @endif
                        </div>

                        <div id="fees-and-taxes">
                            <h3 style="font-size: 15px; font-weight: 800; color: #FFFFFF; margin: 0 0 6px;">Fees and taxes</h3>
                            @if($packagingFeeMinor <= 0 && $chargeRules === [])
                                <p style="font-size: 13px; color: #CBD5E1; margin: 0;">No fees or taxes are added. You pay the ticket and food prices shown.</p>
                            @else
                                <ul style="margin: 0; padding-left: 18px; font-size: 13px; color: #CBD5E1; line-height: 1.6;">
                                    @if($packagingFeeMinor > 0)
                                        <li><strong style="color: #FFFFFF;">Packaging fee</strong> (organizer fee): {{ \App\Support\Money::formatMinor($packagingFeeMinor, $currency) }} per order with food</li>
                                    @endif
                                    @foreach($chargeRules as $rule)
                                        <li><strong style="color: #FFFFFF;">{{ $rule->label }}</strong> ({{ strtolower($rule->kindLabel()) }}): {{ $rule->describe() }}</li>
                                    @endforeach
                                </ul>
                                <p style="font-size: 12px; color: #94A3B8; margin: 6px 0 0;">The exact amount of each fee and tax is shown, line by line, before you confirm your order.</p>
                            @endif
                        </div>

                        <div id="refund-policy">
                            <h3 style="font-size: 15px; font-weight: 800; color: #FFFFFF; margin: 0 0 6px;">Cancellations and refunds</h3>
                            @php $refundPolicyNotes = $event->refund_policy_notes ?: $policy?->refund_policy_notes; @endphp
                            @php $refundDeadlineHours = $event->refund_policy_deadline_hours ?? $policy?->refund_policy_deadline_hours; @endphp
                            @php $refundMode = $event->refund_policy_mode ?? $policy?->refund_policy_mode ?? 'manual_full_and_partial'; @endphp
                            @if(trim((string) $refundPolicyNotes) !== '')
                                <p style="font-size: 13px; color: #CBD5E1; margin: 0; white-space: pre-line;">{{ $refundPolicyNotes }}</p>
                            @else
                                <p style="font-size: 13px; color: #CBD5E1; margin: 0;">The organizer has not published a refund policy. Contact them before you buy if you may need a refund.</p>
                            @endif
                            @if($refundDeadlineHours !== null)
                                <p style="font-size: 12px; color: #94A3B8; margin: 6px 0 0;">Refund requests close {{ (int) $refundDeadlineHours }} hours before the event starts.</p>
                            @endif
                            <p style="font-size: 12px; color: #CBD5E1; margin: 6px 0 0;">
                                @if($refundMode === 'non_refundable') This event is marked non-refundable.
                                @elseif($refundMode === 'manual_full_only') Organizers may record full manual refunds for supported payment methods; partial refunds are not offered.
                                @else Organizers may record full or partial manual refunds for supported payment methods.
                                @endif
                            </p>
                            <p style="font-size: 12px; color: #94A3B8; margin: 6px 0 0;">EventBite cannot process provider-backed refunds. A cancellation request or approval is not a completed refund; the organizer must complete any supported manual refund and record its reference.</p>
                            @php $nonRefundableCategories = $event->non_refundable_ticket_categories ?? []; @endphp
                            @if(count($nonRefundableCategories))
                                <p style="font-size: 12px; color: #94A3B8; margin: 6px 0 0;">Non-refundable ticket types: {{ implode(', ', $nonRefundableCategories) }}.</p>
                            @endif
                            <p style="font-size: 12px; color: #94A3B8; margin: 6px 0 0;">
                                @if(($event->host_cancellation_policy ?? $policy?->host_cancellation_policy) === 'manual_refund_if_supported') If the host cancels, a manual refund may be recorded only for a supported payment method.
                                @else If the host cancels, contact the organizer; no automatic refund is promised.
                                @endif
                            </p>
                            <p style="font-size: 12px; color: #94A3B8; margin: 6px 0 0;">
                                @if(($event->postponement_policy ?? $policy?->postponement_policy ?? 'tickets_valid_for_new_date') === 'tickets_valid_for_new_date') If postponed or rescheduled, existing tickets remain valid for the new date.
                                @else If postponed or rescheduled, contact the organizer for next steps.
                                @endif
                            </p>
                            <p style="font-size: 12px; color: #94A3B8; margin: 6px 0 0;">
                                @if(($event->weather_cancellation_policy ?? $policy?->weather_cancellation_policy ?? 'contact_organizer') === 'reschedule_tickets_valid') For weather cancellation, the event will be rescheduled and existing tickets remain valid.
                                @elseif(($event->weather_cancellation_policy ?? $policy?->weather_cancellation_policy ?? 'contact_organizer') === 'manual_refund_if_supported') For weather cancellation, a manual refund may be recorded only for a supported payment method.
                                @else For weather cancellation, contact the organizer; no automatic refund is promised.
                                @endif
                            </p>
                            @php $foodCancelHours = $event->food_order_cancellation_deadline_hours ?? $policy?->food_order_cancellation_deadline_hours; @endphp
                            @php $foodModifyHours = $event->food_order_modification_deadline_hours ?? $policy?->food_order_modification_deadline_hours; @endphp
                            @if($foodCancelHours !== null)
                                <p style="font-size: 12px; color: #94A3B8; margin: 6px 0 0;">Food cancellation requests close {{ (int) $foodCancelHours }} hours before the event starts.</p>
                            @endif
                            @if($foodModifyHours !== null)
                                <p style="font-size: 12px; color: #94A3B8; margin: 6px 0 0;">Food orders cannot be modified in EventBite; contact the organizer at least {{ (int) $foodModifyHours }} hours before the event.</p>
                            @endif
                            @if($foodCancelHours !== null && $refundDeadlineHours !== null)
                                <p style="font-size: 12px; color: #94A3B8; margin: 6px 0 0;">If multiple cancellation cutoffs apply, the earliest deadline controls.</p>
                            @endif
                        </div>

                        <div id="food-safety-and-alcohol-policy" style="border-top: 1px solid rgba(255, 255, 255, 0.08); padding-top: 16px;">
                            <h3 style="font-size: 15px; font-weight: 800; color: #FFFFFF; margin: 0 0 6px;">Food safety, allergen &amp; alcohol compliance</h3>
                            <p style="font-size: 12px; color: #CBD5E1; line-height: 1.6; margin: 0;">
                                Event hosts and participating vendors are independently responsible for safe food preparation, proper cooking and holding temperatures, refrigeration, sanitation, allergen identification, and required state, county, and municipal food-service permits and inspections. Guests with severe allergies should confirm cross-contamination precautions directly with the organizer before ordering.
                            </p>
                            @if($event->serves_alcohol)
                                <p style="font-size: 12px; color: #FCD34D; line-height: 1.6; margin: 8px 0 0; font-weight: 600;">
                                    Alcohol Service &amp; Age Restriction Notice: Alcohol is served at this event subject to state and local liquor laws. Valid government-issued photo ID and minimum legal drinking age verification are strictly required at the venue.
                                </p>
                            @endif
                            <p style="font-size: 12px; color: #94A3B8; line-height: 1.5; margin: 6px 0 0;">
                                Purchasing an event ticket does not automatically authorize alcohol consumption, and EventBite does not grant an alcohol licence.
                            </p>
                            @auth
                                <details id="report-safety-issue" style="margin-top: 10px;" @if($errors->hasAny(['category', 'description', 'occurred_on'])) open @endif>
                                    <summary style="cursor: pointer; font-size: 12px; font-weight: 700; color: #FCD34D;">Report a safety issue at this event</summary>
                                    <form method="POST" action="{{ route('events.safety-report', $event->id) }}" style="display: grid; gap: 8px; margin-top: 8px;">
                                        @csrf
                                        <p style="font-size: 12px; color: #CBD5E1; margin: 0;">Your report goes to EventBite's compliance team, not the host. If someone is in danger, call 911.</p>
                                        <label style="font-size: 12px; color: #E2E8F0;">What kind of issue
                                            <select name="category" required class="eb-input" style="margin-top: 4px;">
                                                @foreach(\App\Models\Compliance\ComplianceSafetyReport::CATEGORIES as $value => $label)
                                                    <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label style="font-size: 12px; color: #E2E8F0;">What happened
                                            <textarea name="description" required minlength="20" maxlength="5000" rows="3" class="eb-input" style="margin-top: 4px;">{{ old('description') }}</textarea>
                                        </label>
                                        @error('description')<p style="font-size: 12px; color: #FCA5A5; margin: 0;">{{ $message }}</p>@enderror
                                        <label style="font-size: 12px; color: #E2E8F0;">When it happened (optional)
                                            <input type="date" name="occurred_on" value="{{ old('occurred_on') }}" max="{{ now()->toDateString() }}" class="eb-input" style="margin-top: 4px;">
                                        </label>
                                        <label style="font-size: 12px; color: #E2E8F0; display: flex; gap: 6px;"><input type="checkbox" name="may_contact" value="1"> EventBite may contact me about this report</label>
                                        <div><button type="submit" class="eb-btn eb-btn-secondary eb-btn-sm">Send report</button></div>
                                    </form>
                                </details>
                            @endauth
                        </div>

                        @if($eventQr)
                            <div id="event-qr" class="eb-promotional-qr-card" data-qr-type="promotional">
                                <div class="eb-promotional-qr-well">
                                    <img src="{{ $eventQr['image'] }}" alt="QR code linking to this event page" width="120" height="120" style="background: #FFFFFF; border-radius: 8px; padding: 6px; display: block;">
                                </div>
                                <div style="min-width: 0; flex: 1;">
                                    <span class="eb-badge eb-badge-info" style="margin-bottom: 6px;">Promotional Share Code · Not an Admission Ticket</span>
                                    <h3 style="font-size: 15px; font-weight: 800; color: #FFFFFF; margin: 0 0 4px;">Share this event</h3>
                                    <p style="font-size: 12px; color: #94A3B8; margin: 0 0 4px;">Scan to open this page. This code is for sharing only and is not a ticket.</p>
                                    <a href="{{ $eventQr['link'] }}" style="font-size: 12px; color: #C7FF45; word-break: break-all;">{{ $eventQr['link'] }}</a>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Schedule and line-up, as the host published them -->
            @php $publicSchedule = app(\App\Services\Events\ScheduleService::class)->publicSchedule($event); @endphp
            @if($publicSchedule->isNotEmpty())
                <div class="card-navy">
                    @include('events.partials.schedule', ['schedule' => $publicSchedule, 'performers' => $event->performers()->with('activities')->get(), 'headingLevel' => 3])
                </div>
            @endif

            <!-- Sponsors and vendors, as the host credited and approved them -->
            @php
                $partnersService = app(\App\Services\Events\PartnersService::class);
                $pageSponsors = $partnersService->sponsorsFor($event, 'page');
                $pageVendors = $partnersService->publicVendors($event);
                $vendorState = $partnersService->applicationsState($event);
            @endphp
            @if($pageSponsors->isNotEmpty() || $pageVendors->isNotEmpty() || $vendorState['open'])
                <div class="card-navy eb-stack" style="gap: var(--space-4);">
                    @include('events.partials.partners', ['sponsors' => $pageSponsors, 'vendors' => $pageVendors, 'vendorState' => $vendorState])
                </div>
            @endif

            <!-- 4. Photos: this event's own gallery, flyer and dish photos, never a fixed set -->
            @php
                // The host's gallery first (approved photos, in their order), then the flyer, then dishes with photos.
                // Each photo says what it is: the event's own, or a sample image labelled as illustrative.
                $photos = $event->visibleGallery()->toBase()->map(fn ($media) => ['image' => $media->responsiveImage(), 'caption' => $media->alt_text, 'kind' => 'event'])
                    ->when($event->hasFlyer(), fn ($c) => $c->push(['image' => $event->coverImage(), 'caption' => $event->flyer_alt_text ?: $event->title . ' flyer', 'kind' => 'flyer']))
                    ->merge($event->performers->filter(fn ($performer) => \App\Services\MediaStorageService::isStoredPath($performer->photo_path))->map(fn ($performer) => ['image' => \App\Support\ResponsiveImage::forPerformer($performer), 'caption' => $performer->photo_alt_text ?: 'Photo of ' . $performer->name, 'kind' => 'performer']))
                    ->merge($event->menuItems->where('is_active', true)->map(fn ($item) => ['item' => $item, 'image' => $item->responsiveImage()])
                        ->filter(fn ($row) => $row['image'] !== null && (!$row['image']->illustrative || \App\Support\ImageSubjects::sampleFitsMenuItem($row['item']->display_image_url, $row['item'])))
                        ->map(fn ($row) => ['image' => $row['image'], 'caption' => $row['item']->name, 'kind' => $row['image']->illustrative ? 'illustrative' : 'dish']))
                    ->unique(fn ($photo) => $photo['image']->src)->take(9)->values();
            @endphp
            @if($photos->isNotEmpty())
            <section aria-labelledby="photos-heading">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; gap: 12px;">
                    <h3 id="photos-heading" style="font-size: 16px; font-weight: 800; color: #FFFFFF; margin: 0;">Photos</h3>
                    <span style="font-size: 11px; color: #94A3B8;">From this listing</span>
                </div>

                <ul class="gallery-scroll" style="list-style: none; margin: 0; padding: 0;">
                    @foreach($photos as $photo)
                        <li class="gallery-item">
                            <button type="button" class="gallery-open" data-lightbox-src="{{ $photo['image']->src }}" data-lightbox-caption="{{ $photo['caption'] }}{{ $photo['kind'] === 'illustrative' ? ' (illustrative photo)' : '' }}"
                                aria-label="Open photo {{ $loop->iteration }} of {{ $loop->count }}: {{ $photo['caption'] }}" aria-haspopup="dialog">
                                {{-- A photo that cannot be loaded is left out rather than shown as a broken tile. --}}
                                <x-responsive-image :image="$photo['image']" alt="" sizes="220px" onerror="this.closest('.gallery-item').remove()" />
                            </button>
                            <div class="gallery-caption">
                                {{ $photo['caption'] }}
                                @if($photo['kind'] === 'illustrative')<span class="gallery-tag">Illustrative photo</span>@endif
                            </div>
                        </li>
                    @endforeach
                </ul>
                {{-- The photo viewer: a native dialog (focus moves in, Escape closes, focus returns to the photo opened),
                     with previous/next buttons and the arrow keys, and the caption read out with each photo. --}}
                <dialog class="eb-lightbox" id="photo-lightbox" aria-labelledby="photo-lightbox-caption">
                    <figure>
                        <img id="photo-lightbox-image" alt="" decoding="async">
                        <figcaption>
                            <span id="photo-lightbox-caption" aria-live="polite"></span>
                            <span class="eb-lightbox-nav">
                                <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" data-lightbox-step="-1" aria-label="Previous photo">‹</button>
                                <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" data-lightbox-step="1" aria-label="Next photo">›</button>
                                <button type="button" class="eb-btn eb-btn-primary eb-btn-sm" data-lightbox-close>Close</button>
                            </span>
                        </figcaption>
                    </figure>
                </dialog>
            </section>
            @endif

            <!-- 5. Host / Organizer Information -->
            <div class="card-navy" id="host-information">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; margin-bottom: 14px;">
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <div style="width: 48px; height: 48px; border-radius: 14px; background: linear-gradient(135deg, rgba(199, 255, 69, 0.22) 0%, rgba(139, 92, 246, 0.28) 100%); border: 1px solid var(--color-accent-border); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2), 0 4px 12px rgba(2, 6, 14, 0.4); display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 900; color: var(--color-accent);">
                            {{ mb_substr($event->organizer_name ?: 'EventBite', 0, 1) }}
                        </div>
                        <div>
                            <div style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-muted); font-weight: 700;">Host Information</div>
                            <h3 style="font-size: 16px; font-weight: 800; color: #FFFFFF; margin: 2px 0 0;">{{ $event->organizer_name ?? 'EventBite Host' }}</h3>
                            <div style="font-size: 11px; color: var(--color-text-secondary); font-weight: 600;">Event organizer</div>
                        </div>
                    </div>
                </div>

                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                    @if(!empty($event->organizer_phone))
                        <a href="tel:{{ $event->organizer_phone }}" class="btn-glass" style="font-size: 12px; padding: 6px 14px;">
                            <span>📞</span>
                            <span>{{ $event->organizer_phone }}</span>
                        </a>
                    @endif
                    @if(!empty($event->organizer_whatsapp))
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $event->organizer_whatsapp) }}" target="_blank" rel="noopener" class="btn-glass" style="font-size: 12px; padding: 6px 14px; color: #34D399;">
                            <span>💬</span>
                            <span>WhatsApp Host</span>
                        </a>
                    @endif
                    @if(!empty($event->organizer_email))
                        <a href="mailto:{{ $event->organizer_email }}" class="btn-glass" style="font-size: 12px; padding: 6px 14px;">
                            <span>✉️</span>
                            <span>{{ $event->organizer_email }}</span>
                        </a>
                    @endif
                </div>
            </div>

            <!-- 6. Cancellation & Refund Information Overview -->
            @php
                $overviewRefundNotes = $event->refund_policy_notes ?: $policy?->refund_policy_notes;
                $overviewRefundHours = $event->refund_policy_deadline_hours ?? $policy?->refund_policy_deadline_hours;
                $overviewRefundMode = $event->refund_policy_mode ?? $policy?->refund_policy_mode ?? 'manual_full_and_partial';
            @endphp
            <div class="card-navy" id="cancellation-refund-overview">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; margin-bottom: 10px;">
                    <div>
                        <div style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-muted); font-weight: 700;">Policy Summary</div>
                        <h3 style="font-size: 16px; font-weight: 800; color: #FFFFFF; margin: 2px 0 0;">Cancellation &amp; Refund Information</h3>
                    </div>
                    <button type="button" onclick="switchSection('details'); document.getElementById('refund-policy')?.scrollIntoView({behavior:'smooth'});" class="btn-glass" style="font-size: 11px; padding: 5px 12px;">
                        Full policy details &rarr;
                    </button>
                </div>
                <p style="font-size: 13px; color: var(--color-text-secondary); line-height: 1.55; margin: 0;">
                    @if(trim((string) $overviewRefundNotes) !== '')
                        {{ $overviewRefundNotes }}
                    @else
                        The organizer has not published a refund policy. Contact them before you buy if you may need a refund.
                    @endif
                </p>
                <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px;">
                    @if($overviewRefundHours !== null)
                        <span class="eb-badge eb-badge-info">Cutoff: {{ (int) $overviewRefundHours }}h before start</span>
                    @endif
                    <span class="eb-badge {{ $overviewRefundMode === 'non_refundable' ? 'eb-badge-error' : 'eb-badge-info' }}">
                        @if($overviewRefundMode === 'non_refundable') Non-refundable
                        @elseif($overviewRefundMode === 'manual_full_only') Full manual refunds only
                        @else Full or partial manual refunds
                        @endif
                    </span>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Sticky Ticket Reservation Panel -->
        <div style="position: sticky; top: 24px; display: flex; flex-direction: column; gap: 16px;">
            <div class="card-navy" style="box-shadow: var(--shadow-elevated); border-color: var(--color-border-strong); border-top-color: rgba(255, 255, 255, 0.16);">
                <div style="margin-bottom: 14px;">
                    @if($fromPriceText)
                        <div style="font-size: 28px; font-weight: 900; color: #FFFFFF; font-variant-numeric: tabular-nums; letter-spacing: -0.02em;">{{ $fromPriceText }}</div>
                        <div style="font-size: 11px; color: var(--color-text-secondary); font-weight: 700;">Ticket prices; fees and taxes are shown before you pay</div>
                    @endif
                    @php $tone = ['success' => 'eb-badge-success', 'danger' => 'eb-badge-error', 'warning' => 'eb-badge-warning'][$availability['tone']] ?? 'eb-badge-info'; @endphp
                    <span class="eb-badge {{ $tone }}" style="margin-top: 8px;">
                        <span class="eb-badge-dot" aria-hidden="true"></span>
                        <span>{{ $availability['label'] }}</span>
                    </span>
                </div>

                @if(!empty($tiers))
                    <div class="detail-well" style="margin-bottom: 14px; padding: 12px; display: flex; flex-direction: column; gap: 8px;">
                        @foreach(array_slice($tiers, 0, 3) as $summaryTier)
                            <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px; font-size: 12px;">
                                <span style="color: var(--color-text-primary); font-weight: 600;" class="eb-clamp-2">{{ $summaryTier['name'] }}</span>
                                <span style="color: var(--color-accent); font-weight: 800; font-variant-numeric: tabular-nums; flex-shrink: 0;">{{ \App\Support\Money::formatMinor($summaryTier['price_minor'], $summaryTier['currency']) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($canOrder)
                    @auth
                        <a href="{{ route('checkout.tickets', $event) }}" class="btn-gradient-purchase" id="btn-blade-purchase-tickets">
                            <span>{{ collect($tiers)->contains('on_sale', true) ? 'Get tickets' : 'Order food' }}</span>
                            <span aria-hidden="true">&rarr;</span>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="btn-gradient-purchase" id="btn-blade-signin-purchase">
                            <span>Sign in to get tickets</span>
                            <span aria-hidden="true">&rarr;</span>
                        </a>
                    @endauth
                @else
                    <p style="font-size: 13px; color: #CBD5E1; margin: 0;">{{ $availability['label'] }}. Tickets can't be bought right now.</p>
                @endif

                @auth
                    @php $isSaved = \App\Models\SavedEvent::where('user_id', auth()->id())->where('event_id', $event->id)->exists(); @endphp
                    <form method="POST" action="{{ route('events.save.toggle', $event->id) }}" style="margin-top: 10px;">
                        @csrf
                        <button type="submit" class="btn-glass" style="width: 100%; justify-content: center; padding: 11px 16px; border-radius: 12px; font-size: 13px;">
                            <x-icon name="heart" size="sm" />
                            <span>{{ $isSaved ? 'Saved — tap to remove' : 'Save event' }}</span>
                        </button>
                    </form>
                @endauth

                <!-- Jump to Food Ordering -->
                @if($menu->isNotEmpty())
                    <button type="button" onclick="switchSection('food')" class="btn-glass" style="width: 100%; justify-content: center; margin-top: 10px; padding: 11px 16px; border-radius: 12px; font-size: 13px;">
                        <span>🍲</span>
                        <span>View the menu ({{ $menu->count() }})</span>
                    </button>
                @endif

                <div style="margin-top: 14px; font-size: 11px; color: var(--color-text-secondary); text-align: center; line-height: 1.4;">
                    Your ticket QR code appears in My tickets once your payment is confirmed. <a href="#payment-methods" onclick="switchSection('details')" style="color: var(--color-accent); font-weight: 700;">Payment, fees and refunds</a>
                </div>
            </div>
        </div>
    </div>

    {{-- Phones: the price and the way to buy stay in reach above the bottom navigation, wherever the reader is --}}
    @if($canOrder)
        <div class="eb-buy-bar" role="region" aria-label="Buy tickets">
            <div style="min-width: 0;">
                @if($fromPriceText)<strong style="display: block; font-size: 1rem; color: #F8FAFC;">{{ $fromPriceText }}</strong>@endif
                <span style="font-size: 0.75rem; color: #A8B6C8;">{{ $availability['label'] }}</span>
            </div>
            @auth
                <a href="{{ route('checkout.tickets', $event) }}" class="eb-btn eb-btn-primary">{{ collect($tiers)->contains('on_sale', true) ? 'Get tickets' : 'Order food' }}</a>
            @else
                <a href="{{ route('login') }}" class="eb-btn eb-btn-primary">Sign in to buy</a>
            @endauth
        </div>
    @endif
</div>

<style>
    .eb-buy-bar { display: none; }
    @media (max-width: 1023px) {
        .eb-buy-bar {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            position: fixed; left: 0; right: 0; bottom: var(--layout-bottom-nav-height); z-index: 55;
            padding: 10px 16px; background: rgba(11, 25, 43, 0.96); border-top: 1px solid var(--color-border);
            backdrop-filter: blur(10px);
        }
        .eb-buy-bar .eb-btn { min-height: 44px; flex-shrink: 0; }
        .eb-buy-bar { transition: transform .2s ease, visibility .2s; }
        .eb-buy-bar.is-hidden { transform: translateY(120%); visibility: hidden; }
        .event-detail-root { padding-bottom: calc(5rem + 72px); }
    }
</style>

<script>
    // Tab switching logic: swaps the active section content
    function switchSection(tabName, reveal) {
        document.querySelectorAll('.section-tab-btn').forEach(btn => {
            btn.classList.remove('active');
            btn.setAttribute('aria-selected', 'false');
            btn.setAttribute('tabindex', '-1');
        });
        document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));

        const targetBtn = document.getElementById('tab-' + tabName + '-btn');
        const targetPane = document.getElementById('pane-' + tabName);

        if (targetBtn) {
            targetBtn.classList.add('active');
            targetBtn.setAttribute('aria-selected', 'true');
            targetBtn.removeAttribute('tabindex');
        }
        if (targetPane) {
            targetPane.classList.add('active');
            if (reveal) {
                const smooth = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                (targetBtn || targetPane).scrollIntoView({ behavior: smooth ? 'smooth' : 'auto', block: 'start' });
            }
        }
    }

    // Tabs: arrow keys move between them (WAI-ARIA tabs pattern); a #pane-food link opens that tab.
    (function () {
        const tabs = Array.from(document.querySelectorAll('.section-tab-btn[role="tab"]'));
        tabs.forEach((tab, i) => tab.addEventListener('keydown', event => {
            const step = { ArrowRight: 1, ArrowLeft: -1 }[event.key];
            if (!step) return;
            event.preventDefault();
            const next = tabs[(i + step + tabs.length) % tabs.length];
            next.click();
            next.focus();
        }));
        const fromHash = (location.hash || '').replace('#pane-', '');
        if (['tickets', 'food', 'details'].includes(fromHash)) switchSection(fromHash, true);

        // Phones: the bottom buy bar appears once the in-page Get Tickets button has scrolled away.
        const inline = document.getElementById('detail-cta-inline');
        const bar = document.querySelector('.eb-buy-bar');
        if (inline && bar && 'IntersectionObserver' in window) {
            new IntersectionObserver(([entry]) => {
                bar.classList.toggle('is-hidden', entry.isIntersecting || entry.boundingClientRect.top > 0);
            }).observe(inline);
        }
    })();


    // Share Event
    function shareCurrentEvent() {
        if (navigator.share) {
            navigator.share({
                title: document.title,
                url: window.location.href
            }).catch(() => {});
        } else {
            navigator.clipboard.writeText(window.location.href);
            showDetailToast('Event link copied to clipboard! 📋');
        }
    }

    function showDetailToast(msg) {
        const toast = document.getElementById('detail-toast');
        if (toast) {
            toast.innerText = msg;
            toast.style.display = 'block';
            setTimeout(() => { toast.style.display = 'none'; }, 3000);
        }
    }

    // Photo viewer for the gallery strip (see #photo-lightbox).
    (function () {
        const dialog = document.getElementById('photo-lightbox');
        if (!dialog || typeof dialog.showModal !== 'function') return;
        const image = document.getElementById('photo-lightbox-image');
        const caption = document.getElementById('photo-lightbox-caption');
        let index = 0, opener = null;
        const items = () => Array.from(document.querySelectorAll('[data-lightbox-src]'));
        function show(i) {
            const list = items();
            if (!list.length) return;
            index = (i + list.length) % list.length;
            const item = list[index];
            image.src = item.dataset.lightboxSrc;
            image.alt = item.dataset.lightboxCaption;
            caption.textContent = item.dataset.lightboxCaption + ' (' + (index + 1) + ' of ' + list.length + ')';
            dialog.querySelectorAll('[data-lightbox-step]').forEach(b => { b.hidden = list.length < 2; });
        }
        document.addEventListener('click', function (event) {
            const trigger = event.target.closest('[data-lightbox-src]');
            if (!trigger) return;
            opener = trigger;
            show(items().indexOf(trigger));
            dialog.showModal();
            dialog.querySelector('[data-lightbox-close]').focus();
        });
        dialog.addEventListener('click', function (event) {
            const step = event.target.closest('[data-lightbox-step]');
            if (step) { show(index + Number(step.dataset.lightboxStep)); return; }
            if (event.target.closest('[data-lightbox-close]') || event.target === dialog) dialog.close();
        });
        dialog.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowRight') { show(index + 1); event.preventDefault(); }
            if (event.key === 'ArrowLeft') { show(index - 1); event.preventDefault(); }
        });
        dialog.addEventListener('close', function () { image.removeAttribute('src'); if (opener) opener.focus(); });
    })();
</script>
@endsection
