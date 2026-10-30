@extends('layouts.app')

@section('title', ($event->title ?? 'Event Details') . ' — Island Events')
@section('page-title', $event->title ?? 'Event Details')

@section('content')
<style>
    /* Premium Midnight-Navy Theme for Event Detail Screen */
    .event-detail-root {
        background-color: #0B132B;
        min-height: 100vh;
        color: #FFFFFF;
        font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        margin: -1.5rem -1rem 0;
        padding-bottom: 5rem;
    }

    .detail-hero {
        position: relative;
        width: 100%;
        height: 380px;
        overflow: hidden;
    }

    .detail-hero-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center 30%;
        filter: brightness(0.9);
    }

    .detail-hero-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(11, 19, 43, 0.55) 0%, rgba(11, 19, 43, 0.15) 45%, rgba(11, 19, 43, 0.95) 90%, #0B132B 100%);
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
        background-color: rgba(15, 23, 42, 0.75);
        backdrop-filter: blur(12px);
        color: #FFFFFF;
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 9999px;
        padding: 8px 18px;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-glass:hover {
        background-color: rgba(255, 255, 255, 0.15);
        color: #FFFFFF;
        transform: translateY(-1px);
    }

    .icon-glass-btn {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background-color: rgba(15, 23, 42, 0.75);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: #FFFFFF;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 16px;
        transition: all 0.2s ease;
    }

    .detail-main-layout {
        max-width: 1200px;
        margin: -40px auto 0;
        padding: 0 24px;
        position: relative;
        z-index: 5;
        display: grid;
        grid-template-columns: 1fr 380px;
        gap: 32px;
        align-items: start;
    }

    @media (max-width: 900px) {
        .detail-main-layout {
            grid-template-columns: 1fr;
            margin-top: -20px;
            padding: 0 16px;
        }
    }

    .card-navy {
        background-color: #162038;
        border-radius: 20px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        padding: 24px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
    }

    .section-tab-nav {
        background-color: #162038;
        padding: 6px;
        border-radius: 16px;
        display: flex;
        border: 1px solid rgba(255, 255, 255, 0.08);
        gap: 6px;
        margin-bottom: 24px;
    }

    .section-tab-btn {
        flex: 1;
        padding: 12px 16px;
        border-radius: 12px;
        border: none;
        background-color: transparent;
        color: #94A3B8;
        font-weight: 700;
        font-size: 14px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.2s ease;
    }

    .section-tab-btn.active {
        background-color: #4F46E5;
        color: #FFFFFF;
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.35);
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
        min-width: 190px;
        max-width: 210px;
        height: 135px;
        border-radius: 14px;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.12);
        position: relative;
        flex-shrink: 0;
        cursor: pointer;
    }

    .gallery-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .gallery-caption {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(180deg, rgba(0,0,0,0) 0%, rgba(15,23,42,0.9) 100%);
        padding: 8px 10px;
        font-size: 11px;
        font-weight: 700;
        color: #FFFFFF;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .btn-gradient-purchase {
        width: 100%;
        background: linear-gradient(135deg, #7C3AED 0%, #EC4899 50%, #F97316 100%);
        border: none;
        border-radius: 14px;
        padding: 16px 24px;
        font-size: 16px;
        font-weight: 800;
        color: #FFFFFF;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        box-shadow: 0 10px 25px rgba(236, 72, 153, 0.35);
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        text-decoration: none;
    }

    .btn-gradient-purchase:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 30px rgba(236, 72, 153, 0.45);
        color: #FFFFFF;
    }
</style>

<div class="event-detail-root" id="event-detail-page">
    <!-- Toast Notification -->
    <div id="detail-toast" style="display: none; position: fixed; top: 24px; left: 50%; transform: translateX(-50%); background-color: #1E293B; color: #FFFFFF; padding: 12px 24px; border-radius: 9999px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.15); z-index: 9999; font-size: 14px; font-weight: 600;"></div>

    <!-- 1. Cinematic Hero Section -->
    <div class="detail-hero">
        @php
            $heroImg = !empty($event->flyer_path) ? asset('storage/' . $event->flyer_path) : (
                !empty($event->flyer_url) ? $event->flyer_url : asset('images/island_festival_sunset.jpg')
            );
        @endphp
        <img src="{{ $heroImg }}" alt="{{ $event->title }}" class="detail-hero-img">
        <div class="detail-hero-overlay"></div>

        <!-- Floating Top Navigation -->
        <div class="detail-hero-nav">
            <a href="{{ route('events.index') }}" class="btn-glass" id="btn-back-to-discovery">
                <span>&larr;</span>
                <span>Back to All Events</span>
            </a>

            <div style="display: flex; align-items: center; gap: 10px;">
                <button type="button" class="icon-glass-btn" id="btn-favorite-toggle" onclick="toggleDetailFavorite()" title="Save to Favorites">
                    <span id="fav-icon">🤍</span>
                </button>
                <button type="button" class="icon-glass-btn" onclick="shareCurrentEvent()" title="Share Event">
                    <span>🔗</span>
                </button>
            </div>
        </div>
    </div>

    <!-- 2. Main Content Layout (Desktop: Left Info / Right Sticky Ticket Panel) -->
    <div class="detail-main-layout">
        <!-- LEFT COLUMN: Metadata, Tabs, Media Gallery & Organizer -->
        <div style="display: flex; flex-direction: column; gap: 28px;">
            <!-- Event Title & Overview -->
            <div class="card-navy" style="position: relative;">
                <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px;">
                    <span style="background: rgba(99, 102, 241, 0.85); color: #FFFFFF; font-size: 11px; font-weight: 800; padding: 4px 12px; border-radius: 9999px; text-transform: uppercase;">
                        🌴 {{ $event->locationCategory ?? 'Island Event' }}
                    </span>
                    @if($event->is_ordering_open)
                        <span style="background: rgba(16, 185, 129, 0.85); color: #FFFFFF; font-size: 11px; font-weight: 700; padding: 4px 12px; border-radius: 9999px;">
                            ✓ Kitchen Accepting Pre-Orders
                        </span>
                    @endif
                    <span style="background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.15); color: #CBD5E1; font-size: 11px; font-weight: 600; padding: 4px 12px; border-radius: 9999px;">
                        Verified Host
                    </span>
                </div>

                <h1 style="font-size: 28px; font-weight: 900; color: #FFFFFF; line-height: 1.25; margin: 0 0 10px; letter-spacing: -0.01em;">
                    {{ $event->title }}
                </h1>

                @if(!empty($event->tagline))
                    <p style="font-size: 15px; color: #94A3B8; line-height: 1.5; margin: 0 0 18px; font-weight: 500;">
                        {{ $event->tagline }}
                    </p>
                @endif

                <!-- Metadata Highlights Grid -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; padding: 16px; background-color: #0F172A; border-radius: 14px; border: 1px solid rgba(255, 255, 255, 0.06);">
                    <div style="display: flex; gap: 10px; align-items: flex-start;">
                        <span style="font-size: 20px;">📅</span>
                        <div>
                            <div style="font-size: 10px; text-transform: uppercase; color: #94A3B8; font-weight: 700;">Date & Time</div>
                            <div style="font-size: 13px; font-weight: 700; color: #FFFFFF;">
                                {{ $event->date_str ?? ($event->date ? (is_string($event->date) ? date('l, F j, Y', strtotime($event->date)) : $event->date->format('l, F j, Y')) : 'Date TBA') }}
                            </div>
                            <div style="font-size: 11px; color: #94A3B8;">
                                {{ $event->time_str ?? '12:00 PM – 8:00 PM' }} ({{ $event->timezone ?? 'EST' }})
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; align-items: flex-start;">
                        <span style="font-size: 20px;">📍</span>
                        <div>
                            <div style="font-size: 10px; text-transform: uppercase; color: #94A3B8; font-weight: 700;">Venue & Location</div>
                            <div style="font-size: 13px; font-weight: 700; color: #FFFFFF;">
                                {{ $event->venue_name ?? $event->venue ?? 'Waterfront Pavilion' }}
                            </div>
                            <div style="font-size: 11px; color: #94A3B8;">
                                {{ $event->venue_address ?? 'United States' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Section Switcher Tabs (Tickets · Food · Details) -->
            <div>
                <div class="section-tab-nav" role="tablist">
                    <button type="button" class="section-tab-btn active" id="tab-tickets-btn" onclick="switchSection('tickets')">
                        <span>🎟️</span>
                        <span>Tickets & Passes</span>
                    </button>
                    <button type="button" class="section-tab-btn" id="tab-food-btn" onclick="switchSection('food')">
                        <span>🍲</span>
                        <span>Food & Dining ({{ count($event->menuItems ?? []) }})</span>
                    </button>
                    <button type="button" class="section-tab-btn" id="tab-details-btn" onclick="switchSection('details')">
                        <span>ℹ️</span>
                        <span>Event Guide</span>
                    </button>
                </div>

                <!-- TAB 1: TICKETS -->
                <div class="tab-pane active" id="pane-tickets">
                    <div style="display: flex; flex-direction: column; gap: 14px;">
                        <!-- Tier 1: General Admission -->
                        <div class="card-navy" style="padding: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                <div>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <h3 style="font-size: 16px; font-weight: 800; color: #FFFFFF; margin: 0;">General Admission Pass</h3>
                                        <span style="background: rgba(99, 102, 241, 0.2); color: #818CF8; font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 9999px;">POPULAR</span>
                                    </div>
                                    <p style="font-size: 13px; color: #94A3B8; margin: 4px 0 10px;">
                                        Includes full event entry, access to live reggae performance stage, and mobile QR pass.
                                    </p>
                                </div>
                                <div style="text-align: right; flex-shrink: 0;">
                                    <div style="font-size: 20px; font-weight: 900; color: #FFFFFF;">$15.00</div>
                                    <div style="font-size: 10px; color: #94A3B8;">USD per pass</div>
                                </div>
                            </div>

                            <div style="display: flex; flex-direction: column; gap: 4px; margin-bottom: 14px; font-size: 12px; color: #CBD5E1;">
                                <div>✓ Admission to waterfront grounds & stage</div>
                                <div>✓ Instant digital QR ticket delivered to mobile</div>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 12px; border-top: 1px solid rgba(255, 255, 255, 0.08);">
                                <span style="font-size: 12px; color: #94A3B8;">Passes available: <strong style="color: #10B981;">200 remaining</strong></span>
                                <div style="display: flex; align-items: center; gap: 10px; background: #0F172A; padding: 4px 8px; border-radius: 8px;">
                                    <button type="button" onclick="adjustQty('ga', -1)" style="background: none; border: none; color: #FFFFFF; font-size: 16px; cursor: pointer; padding: 0 4px;">-</button>
                                    <span id="qty-ga" style="font-size: 14px; font-weight: 800; min-width: 20px; text-align: center;">1</span>
                                    <button type="button" onclick="adjustQty('ga', 1)" style="background: none; border: none; color: #FFFFFF; font-size: 16px; cursor: pointer; padding: 0 4px;">+</button>
                                </div>
                            </div>
                        </div>

                        <!-- Tier 2: VIP Waterfront Lounge -->
                        <div class="card-navy" style="padding: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                <div>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <h3 style="font-size: 16px; font-weight: 800; color: #FFFFFF; margin: 0;">VIP Waterfront Lounge</h3>
                                        <span style="background: rgba(236, 72, 153, 0.2); color: #F472B6; font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 9999px;">VIP LOUNGE</span>
                                    </div>
                                    <p style="font-size: 13px; color: #94A3B8; margin: 4px 0 10px;">
                                        Express VIP lane entry, reserved shaded oceanfront seating, and 1 complimentary island rum punch voucher.
                                    </p>
                                </div>
                                <div style="text-align: right; flex-shrink: 0;">
                                    <div style="font-size: 20px; font-weight: 900; color: #FFFFFF;">$45.00</div>
                                    <div style="font-size: 10px; color: #94A3B8;">USD per pass</div>
                                </div>
                            </div>

                            <div style="display: flex; flex-direction: column; gap: 4px; margin-bottom: 14px; font-size: 12px; color: #CBD5E1;">
                                <div>✓ Express check-in lane & dedicated bar server</div>
                                <div>✓ 1 Complimentary artisanal beverage included</div>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 12px; border-top: 1px solid rgba(255, 255, 255, 0.08);">
                                <span style="font-size: 12px; color: #94A3B8;">Passes available: <strong style="color: #10B981;">45 remaining</strong></span>
                                <div style="display: flex; align-items: center; gap: 10px; background: #0F172A; padding: 4px 8px; border-radius: 8px;">
                                    <button type="button" onclick="adjustQty('vip', -1)" style="background: none; border: none; color: #FFFFFF; font-size: 16px; cursor: pointer; padding: 0 4px;">-</button>
                                    <span id="qty-vip" style="font-size: 14px; font-weight: 800; min-width: 20px; text-align: center;">0</span>
                                    <button type="button" onclick="adjustQty('vip', 1)" style="background: none; border: none; color: #FFFFFF; font-size: 16px; cursor: pointer; padding: 0 4px;">+</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: FOOD & DINING -->
                <div class="tab-pane" id="pane-food">
                    @if(empty($event->menuItems) || count($event->menuItems) === 0)
                        <div class="card-navy" style="text-align: center; padding: 36px 20px;">
                            <span style="font-size: 36px;">🍲</span>
                            <h3 style="font-size: 16px; font-weight: 700; color: #FFFFFF; margin: 10px 0 6px;">Menu In Preparation</h3>
                            <p style="font-size: 13px; color: #94A3B8; margin: 0;">The host is finalizing seasonal dishes and pricing tiers.</p>
                        </div>
                    @else
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px;">
                            @foreach($event->menuItems as $item)
                                <div class="card-navy" style="padding: 16px; display: flex; flex-direction: column; justify-content: space-between;">
                                    <div>
                                        <div style="font-size: 10px; text-transform: uppercase; color: #818CF8; font-weight: 700;">{{ $item->category ?? 'Caribbean Specialty' }}</div>
                                        <h4 style="font-size: 15px; font-weight: 800; color: #FFFFFF; margin: 4px 0 6px;">{{ $item->name }}</h4>
                                        <p style="font-size: 12px; color: #94A3B8; margin: 0 0 12px; line-height: 1.4;">{{ $item->description ?? 'Authentic Island dish freshly prepared.' }}</p>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 10px; border-top: 1px solid rgba(255, 255, 255, 0.08);">
                                        <span style="font-size: 16px; font-weight: 800; color: #FFFFFF;">${{ number_format($item->price_usd ?? $item->price, 2) }}</span>
                                        @auth
                                            <button type="button" onclick="document.getElementById('preorder-modal').style.display='block'" style="background: rgba(99, 102, 241, 0.2); border: 1px solid rgba(99, 102, 241, 0.35); color: #818CF8; border-radius: 8px; padding: 6px 12px; font-size: 12px; font-weight: 700; cursor: pointer;">
                                                Pre-Order
                                            </button>
                                        @else
                                            <a href="{{ route('login') }}" style="background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.15); color: #CBD5E1; border-radius: 8px; padding: 6px 12px; font-size: 11px; font-weight: 700; text-decoration: none;">
                                                Sign In to Order
                                            </a>
                                        @endauth
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- TAB 3: DETAILS & EVENT GUIDE -->
                <div class="tab-pane" id="pane-details">
                    <div class="card-navy" style="display: flex; flex-direction: column; gap: 18px;">
                        <div>
                            <h3 style="font-size: 16px; font-weight: 800; color: #FFFFFF; margin: 0 0 8px;">About This Experience</h3>
                            <p style="font-size: 13px; color: #CBD5E1; line-height: 1.6; margin: 0;">
                                {{ $event->description ?? 'Join us for this special Caribbean food and entertainment event. All food packages are prepared fresh on-site and redeemed with instant digital QR passes.' }}
                            </p>
                        </div>

                        <div style="background: #0F172A; border-radius: 12px; padding: 14px; border: 1px solid rgba(255, 255, 255, 0.06); font-size: 12px; color: #94A3B8;">
                            <strong style="color: #FFFFFF; display: block; margin-bottom: 6px;">Food Collection & Pass Protocol:</strong>
                            <div>&bull; Present your QR ticket pass at the designated food station.</div>
                            <div>&bull; Orders are verified in real-time by island staff terminals.</div>
                            <div>&bull; Pre-ordered meals are held fresh under temperature control.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Authentic Media Section (Horizontal Gallery) -->
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <h3 style="font-size: 16px; font-weight: 800; color: #FFFFFF; margin: 0;">Event & Atmosphere Gallery</h3>
                    <span style="font-size: 11px; color: #94A3B8;">5 verified event photos</span>
                </div>

                <div class="gallery-scroll">
                    <div class="gallery-item" onclick="openLightbox('{{ asset('images/escovitch_fish.jpg') }}', 'Authentic Escovitch Snapper')">
                        <img src="{{ asset('images/escovitch_fish.jpg') }}" alt="Escovitch Fish">
                        <div class="gallery-caption">Signature Dish</div>
                    </div>
                    <div class="gallery-item" onclick="openLightbox('{{ asset('images/island_music_reggae_concert.jpg') }}', 'Live Music Stage')">
                        <img src="{{ asset('images/island_music_reggae_concert.jpg') }}" alt="Reggae Stage">
                        <div class="gallery-caption">Live Reggae Stage</div>
                    </div>
                    <div class="gallery-item" onclick="openLightbox('{{ asset('images/island_festival_sunset.jpg') }}', 'Sunset Waterfront')">
                        <img src="{{ asset('images/island_festival_sunset.jpg') }}" alt="Festival Sunset">
                        <div class="gallery-caption">Waterfront Atmosphere</div>
                    </div>
                    <div class="gallery-item" onclick="openLightbox('{{ asset('images/jerk_chicken.jpg') }}', 'Smoked Jerk Chicken')">
                        <img src="{{ asset('images/jerk_chicken.jpg') }}" alt="Jerk Chicken">
                        <div class="gallery-caption">Pimento Smoked Jerk</div>
                    </div>
                    <div class="gallery-item" onclick="openLightbox('{{ asset('images/rum_punch.jpg') }}', 'Island Rum Punch')">
                        <img src="{{ asset('images/rum_punch.jpg') }}" alt="Rum Punch">
                        <div class="gallery-caption">Island Refreshments</div>
                    </div>
                </div>
            </div>

            <!-- 5. Host / Organizer Information -->
            <div class="card-navy">
                <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 14px;">
                    <div style="width: 48px; height: 48px; border-radius: 14px; background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%); display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 900; color: #FFFFFF;">
                        {{ substr($event->organizer_name ?? 'Island', 0, 1) }}
                    </div>
                    <div>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <h3 style="font-size: 16px; font-weight: 800; color: #FFFFFF; margin: 0;">{{ $event->organizer_name ?? 'Island Events Host' }}</h3>
                            <span style="color: #10B981;">✓</span>
                        </div>
                        <div style="font-size: 11px; color: #10B981; font-weight: 600;">Verified Event Producer</div>
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
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $event->organizer_whatsapp) }}" target="_blank" class="btn-glass" style="font-size: 12px; padding: 6px 14px; color: #34D399;">
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
        </div>

        <!-- RIGHT COLUMN: Sticky Ticket Reservation Panel -->
        <div style="position: sticky; top: 24px; display: flex; flex-direction: column; gap: 16px;">
            <div class="card-navy" style="box-shadow: 0 20px 40px rgba(0, 0, 0, 0.45); border: 1px solid rgba(255, 255, 255, 0.12);">
                <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 14px;">
                    <div>
                        <div style="font-size: 10px; text-transform: uppercase; color: #94A3B8; font-weight: 700;">Starting From</div>
                        <div style="font-size: 26px; font-weight: 900; color: #FFFFFF;">$15.00</div>
                    </div>
                    <span style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #34D399; padding: 3px 8px; border-radius: 9999px; font-size: 11px; font-weight: 700;">
                        ✓ Live Inventory
                    </span>
                </div>

                <!-- Live Subtotal Summary -->
                <div style="background: #0F172A; border-radius: 12px; padding: 14px; margin-bottom: 18px; border: 1px solid rgba(255, 255, 255, 0.06);">
                    <div style="display: flex; justify-content: space-between; font-size: 13px; color: #CBD5E1; margin-bottom: 6px;">
                        <span>Selected Passes:</span>
                        <strong id="summary-qty-label" style="color: #FFFFFF;">1 pass</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 15px; font-weight: 800; color: #FFFFFF; padding-top: 8px; border-top: 1px solid rgba(255, 255, 255, 0.1);">
                        <span>Total Amount:</span>
                        <span id="summary-total-label" style="color: #38BDF8;">$15.00 USD</span>
                    </div>
                </div>

                <!-- Purchase Button -->
                @auth
                    @if($event->is_ordering_open)
                        <button type="button" class="btn-gradient-purchase" id="btn-blade-purchase-tickets" onclick="document.getElementById('preorder-modal').style.display='block'">
                            <span>🎟️</span>
                            <span id="btn-purchase-label">Get Tickets & Pre-Order Meals</span>
                        </button>
                    @else
                        <a href="{{ route('attendee.dashboard') }}" class="btn-gradient-purchase" id="btn-blade-view-tickets">
                            <span>🎟️</span>
                            <span>View Digital Tickets</span>
                        </a>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="btn-gradient-purchase" id="btn-blade-signin-purchase">
                        <span>🔒</span>
                        <span>Sign In to Get Tickets</span>
                    </a>
                @endauth

                <!-- Jump to Food Ordering -->
                @if(!empty($event->menuItems) && count($event->menuItems) > 0)
                    <button type="button" onclick="switchSection('food')" class="btn-glass" style="width: 100%; justify-content: center; margin-top: 10px; padding: 11px 16px; border-radius: 12px; font-size: 13px;">
                        <span>🍲</span>
                        <span>View Caribbean Menu Offerings</span>
                    </button>
                @endif

                <div style="margin-top: 14px; font-size: 11px; color: #94A3B8; text-align: center; line-height: 1.4;">
                    🔒 Instant QR pass issuance. Authorized Island Events Payments guarantee.
                </div>
            </div>
        </div>
    </div>

    <!-- Pre-Order Modal Dialog -->
    @auth
        @php
            // Display only. Prices and availability are re-checked by OrderPlacementService on submit.
            $orderCurrency = $event->currency === 'USD' ? 'USD' : 'JMD';
            $priceKey = $orderCurrency === 'JMD' ? 'price_jmd' : 'price_usd';
            $sellableTiers = collect(is_array($event->ticket_tiers) ? $event->ticket_tiers : [])
                ->filter(fn ($tier) => isset($tier['id']) && is_numeric($tier[$priceKey] ?? ($orderCurrency === 'JMD' ? ($tier['priceJMD'] ?? null) : ($tier['priceUSD'] ?? null))))
                ->values();
            $formatPrice = fn ($amount) => ($orderCurrency === 'JMD' ? 'J$' : 'US$') . number_format((float) $amount, 2);
            $lineIndex = 0;
        @endphp
        @if($event->is_ordering_open && (count($event->menuItems) > 0 || $sellableTiers->isNotEmpty()))
            <div id="preorder-modal" role="dialog" aria-modal="true" aria-labelledby="preorder-title"
                 style="display: {{ session('error') || $errors->any() ? 'block' : 'none' }}; position: fixed; inset: 0; background: rgba(7, 17, 31, 0.8); backdrop-filter: blur(8px); z-index: 1000; overflow-y: auto; padding: 2rem 1rem;">
                <div class="eb-card" style="max-width: 580px; margin: 2rem auto; padding: 2rem;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem; border-bottom: 1px solid var(--color-border-subtle); padding-bottom: 1rem;">
                        <div>
                            <h3 id="preorder-title" style="font-size: 1.35rem; font-weight: 800; margin: 0;">Tickets &amp; food</h3>
                            <p style="font-size: 0.9rem; color: var(--color-text-secondary); margin-top: 0.2rem;">{{ $event->title }} · prices in {{ $orderCurrency }}</p>
                        </div>
                        <button type="button" aria-label="Close" onclick="document.getElementById('preorder-modal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; color: var(--color-text-secondary); cursor: pointer;">✕</button>
                    </div>

                    @if(session('error'))
                        <div class="eb-alert eb-alert-error" role="alert" style="margin-bottom: 1rem;">{{ session('error') }}</div>
                    @endif
                    @if($errors->any())
                        <div class="eb-alert eb-alert-error" role="alert" style="margin-bottom: 1rem;">
                            @foreach($errors->all() as $message)<div>{{ $message }}</div>@endforeach
                        </div>
                    @endif

                    <form action="{{ route('orders.store', $event->slug ?? $event->id) }}" method="POST" id="order-form">
                        @csrf
                        <input type="hidden" name="currency" value="{{ $orderCurrency }}">
                        <input type="hidden" name="customer_name" value="{{ Auth::user()->name }}">
                        <input type="hidden" name="email" value="{{ Auth::user()->email }}">
                        {{-- One key per form: a double submit returns the first order instead of creating another. --}}
                        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">

                        @if($sellableTiers->isNotEmpty())
                            <fieldset style="border: 0; padding: 0; margin: 0 0 1.25rem;">
                                <legend style="font-weight: 700; font-size: 0.95rem; margin-bottom: 0.5rem;">Tickets</legend>
                                @foreach($sellableTiers as $tier)
                                    @php
                                        $tierPrice = $tier[$priceKey] ?? ($orderCurrency === 'JMD' ? $tier['priceJMD'] : $tier['priceUSD']);
                                        $left = $tier['quantity_available'] ?? $tier['quantityAvailable'] ?? $tier['availableStock'] ?? null;
                                        $soldOut = $left !== null && (int) $left <= 0;
                                        $max = min(20, (int) ($tier['max_per_order'] ?? $tier['maxPerOrder'] ?? 10), $left === null ? 20 : max(0, (int) $left));
                                        $i = $lineIndex++;
                                    @endphp
                                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 0.75rem 1rem; border: 1px solid var(--color-border-subtle); border-radius: 12px; margin-bottom: 0.5rem;">
                                        <div>
                                            <div style="font-weight: 700;">{{ $tier['name'] ?? 'Ticket' }}</div>
                                            <div style="font-size: 0.85rem; color: var(--color-text-secondary);">
                                                {{ $formatPrice($tierPrice) }}
                                                @if($soldOut) · <strong style="color: var(--color-error-text);">Sold out</strong>
                                                @elseif($left !== null && (int) $left <= 10) · {{ (int) $left }} left
                                                @endif
                                            </div>
                                        </div>
                                        <input type="hidden" name="items[{{ $i }}][menu_item_id]" value="{{ $tier['id'] }}">
                                        <label class="sr-only" for="qty-{{ $i }}">Quantity of {{ $tier['name'] ?? 'Ticket' }}</label>
                                        <input id="qty-{{ $i }}" type="number" name="items[{{ $i }}][quantity]" value="{{ old("items.$i.quantity", 0) }}" min="0" max="{{ $max }}" @disabled($soldOut)
                                               class="eb-input" style="width: 72px; text-align: center; font-weight: 700;">
                                    </div>
                                @endforeach
                            </fieldset>
                        @endif

                        @if(count($event->menuItems) > 0)
                            <fieldset style="border: 0; padding: 0; margin: 0 0 1.25rem;">
                                <legend style="font-weight: 700; font-size: 0.95rem; margin-bottom: 0.5rem;">Food &amp; drinks</legend>
                                @foreach($event->menuItems as $item)
                                    @php
                                        $i = $lineIndex++;
                                        $soldOut = $item->available_stock !== null && (int) $item->available_stock <= 0;
                                    @endphp
                                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 0.75rem 1rem; border: 1px solid var(--color-border-subtle); border-radius: 12px; margin-bottom: 0.5rem;">
                                        <div>
                                            <div style="font-weight: 700;">{{ $item->name }}</div>
                                            <div style="font-size: 0.85rem; color: var(--color-text-secondary);">
                                                {{ $item->pricing_type === 'customizable' ? 'From ' : '' }}{{ $formatPrice($orderCurrency === 'JMD' ? $item->price_jmd : $item->price_usd) }}
                                                @if($soldOut) · <strong style="color: var(--color-error-text);">Sold out</strong>
                                                @elseif($item->available_stock !== null && (int) $item->available_stock <= 10) · {{ (int) $item->available_stock }} left
                                                @endif
                                            </div>
                                        </div>
                                        <input type="hidden" name="items[{{ $i }}][menu_item_id]" value="{{ $item->id }}">
                                        <label class="sr-only" for="qty-{{ $i }}">Quantity of {{ $item->name }}</label>
                                        <input id="qty-{{ $i }}" type="number" name="items[{{ $i }}][quantity]" value="{{ old("items.$i.quantity", 0) }}" min="0" max="20" @disabled($soldOut)
                                               class="eb-input" style="width: 72px; text-align: center; font-weight: 700;">
                                    </div>
                                @endforeach
                            </fieldset>
                        @endif

                        <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1.5rem;">
                            <div>
                                <label for="order-phone" class="eb-text-label" style="display: block; margin-bottom: 0.25rem;">Phone or WhatsApp (your ticket is texted here)</label>
                                <input id="order-phone" type="tel" name="customer_phone" class="eb-input" value="{{ old('customer_phone', Auth::user()->phone) }}" required minlength="7" autocomplete="tel" placeholder="+1 876 555 0142" style="width: 100%;">
                            </div>
                            <div>
                                <label for="order-method" class="eb-text-label" style="display: block; margin-bottom: 0.25rem;">Payment method</label>
                                <select id="order-method" name="method" class="eb-input" required style="width: 100%;">
                                    <option value="bank" @selected(old('method') === 'bank')>Bank transfer</option>
                                    <option value="cash" @selected(old('method') === 'cash')>Cash at pickup</option>
                                </select>
                                <p style="font-size: 0.8rem; color: var(--color-text-secondary); margin: 0.35rem 0 0;">Your ticket shows Pending until the organiser confirms payment.</p>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                            <button type="button" class="eb-btn eb-btn-secondary" onclick="document.getElementById('preorder-modal').style.display='none'">Cancel</button>
                            <button type="submit" class="eb-btn eb-btn-primary" data-prevent-double-submit="true">Place order</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endauth
</div>

<script>
    // Tab switching logic: swaps the active section content
    function switchSection(tabName) {
        document.querySelectorAll('.section-tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));

        const targetBtn = document.getElementById('tab-' + tabName + '-btn');
        const targetPane = document.getElementById('pane-' + tabName);

        if (targetBtn) targetBtn.classList.add('active');
        if (targetPane) targetPane.classList.add('active');
    }

    // Ticket Quantities & Live Subtotal Calculator
    let gaQty = 1;
    let vipQty = 0;

    function adjustQty(tier, delta) {
        if (tier === 'ga') {
            gaQty = Math.max(0, gaQty + delta);
            document.getElementById('qty-ga').innerText = gaQty;
        } else if (tier === 'vip') {
            vipQty = Math.max(0, vipQty + delta);
            document.getElementById('qty-vip').innerText = vipQty;
        }

        const totalQty = gaQty + vipQty;
        const totalCost = (gaQty * 15) + (vipQty * 45);

        document.getElementById('summary-qty-label').innerText = totalQty + (totalQty === 1 ? ' pass' : ' passes');
        document.getElementById('summary-total-label').innerText = '$' + totalCost.toFixed(2) + ' USD';
        
        const purchaseLabel = document.getElementById('btn-purchase-label');
        if (purchaseLabel) {
            purchaseLabel.innerText = totalQty > 0 ? 'Get ' + totalQty + ' Ticket' + (totalQty > 1 ? 's' : '') + ' • $' + totalCost.toFixed(2) : 'Select Passes to Continue';
        }
    }

    // Favorite Toggle
    let isFaved = false;
    function toggleDetailFavorite() {
        isFaved = !isFaved;
        document.getElementById('fav-icon').innerText = isFaved ? '❤️' : '🤍';
        showDetailToast(isFaved ? 'Event saved to your Island Favorites! ❤️' : 'Removed from favorites.');
    }

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

    function openLightbox(url, caption) {
        showDetailToast('Viewing: ' + caption);
    }
</script>
@endsection
