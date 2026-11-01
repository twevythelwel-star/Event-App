@extends('layouts.app')

@section('title', ($event->title ?? 'Event Details') . ' — EventBite')
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
            $heroImg = $event->display_image_url;
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
                        {{ $event->category ?: 'Event' }}
                    </span>
                    @if($event->is_ordering_open)
                        <span style="background: rgba(16, 185, 129, 0.85); color: #FFFFFF; font-size: 11px; font-weight: 700; padding: 4px 12px; border-radius: 9999px;">
                            ✓ Taking orders
                        </span>
                    @endif
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

                <!-- TAB 1: TICKETS (host-configured types from TicketTierCatalog) -->
                <div class="tab-pane active" id="pane-tickets">
                    @if(empty($tiers))
                        <div class="card-navy" style="text-align: center; padding: 36px 20px;">
                            <h3 style="font-size: 16px; font-weight: 700; color: #FFFFFF; margin: 0 0 6px;">No tickets on sale</h3>
                            <p style="font-size: 13px; color: #94A3B8; margin: 0;">
                                {{ count($event->menuItems) > 0 ? 'This event sells food only. See the Food tab.' : 'The organiser hasn’t put tickets on sale yet.' }}
                            </p>
                        </div>
                    @else
                        <div style="display: flex; flex-direction: column; gap: 14px;">
                            @foreach($tiers as $tier)
                                <div class="card-navy" style="padding: 20px; display: flex; justify-content: space-between; gap: 16px; align-items: flex-start;">
                                    <div style="min-width: 0;">
                                        <h3 style="font-size: 16px; font-weight: 800; color: #FFFFFF; margin: 0;">{{ $tier['name'] }}</h3>
                                        @if($tier['description'])<p style="font-size: 13px; color: #94A3B8; margin: 4px 0 0;">{{ $tier['description'] }}</p>@endif
                                        @if($tier['sold_out'])
                                            <span class="eb-badge eb-badge-error" style="margin-top: 8px;">Sold out</span>
                                        @elseif($tier['available'] !== null && $tier['available'] <= 10)
                                            <span class="eb-badge eb-badge-warning" style="margin-top: 8px;">{{ $tier['available'] }} left</span>
                                        @endif
                                    </div>
                                    <div style="text-align: right; flex-shrink: 0;">
                                        <div style="font-size: 20px; font-weight: 900; color: #FFFFFF;">{{ \App\Support\Money::format($tier['price'], $currency) }}</div>
                                        <div style="font-size: 11px; color: #94A3B8;">per ticket</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
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
                                        <span style="font-size: 16px; font-weight: 800; color: #FFFFFF;">{{ \App\Support\Money::format($currency === 'USD' ? $item->price_usd : $item->price_jmd, $currency) }}</span>
                                        @auth
                                            <a href="{{ route('checkout.food', $event) }}" style="background: rgba(199, 255, 69, 0.14); border: 1px solid rgba(199, 255, 69, 0.35); color: #C7FF45; border-radius: 8px; padding: 6px 12px; font-size: 12px; font-weight: 700; text-decoration: none;">
                                                Pre-order
                                            </a>
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

            <!-- 4. Photos: this event's own flyer and dish images, never a fixed set -->
            @php
                $photos = collect([['src' => $event->flyer_path ? $event->display_image_url : null, 'caption' => $event->title]])
                    ->merge($event->menuItems->where('is_active', true)->filter(fn ($item) => !empty($item->image_url))
                        ->map(fn ($item) => ['src' => $item->image_url, 'caption' => $item->name]))
                    ->filter(fn ($photo) => !empty($photo['src']))
                    ->unique('src')->take(6)->values();
            @endphp
            @if($photos->isNotEmpty())
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <h3 style="font-size: 16px; font-weight: 800; color: #FFFFFF; margin: 0;">Photos</h3>
                    <span style="font-size: 11px; color: #94A3B8;">Illustrative images from this listing</span>
                </div>

                <div class="gallery-scroll">
                    @foreach($photos as $photo)
                        <div class="gallery-item" onclick="openLightbox(@js($photo['src']), @js($photo['caption']))">
                            <img src="{{ $photo['src'] }}" alt="{{ $photo['caption'] }}">
                            <div class="gallery-caption">{{ $photo['caption'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- 5. Host / Organizer Information -->
            <div class="card-navy">
                <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 14px;">
                    <div style="width: 48px; height: 48px; border-radius: 14px; background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%); display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 900; color: #FFFFFF;">
                        {{ substr($event->organizer_name ?? 'Island', 0, 1) }}
                    </div>
                    <div>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <h3 style="font-size: 16px; font-weight: 800; color: #FFFFFF; margin: 0;">{{ $event->organizer_name ?? 'EventBite Host' }}</h3>
                        </div>
                        <div style="font-size: 11px; color: #94A3B8; font-weight: 600;">Event organizer</div>
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
                <div style="margin-bottom: 14px;">
                    @if($fromPrice !== null)
                        <div style="font-size: 11px; color: #94A3B8; font-weight: 700;">From</div>
                        <div style="font-size: 26px; font-weight: 900; color: #FFFFFF;">{{ \App\Support\Money::format($fromPrice, $currency) }}</div>
                    @else
                        <div style="font-size: 15px; font-weight: 700; color: #FFFFFF;">Nothing on sale yet</div>
                    @endif
                    @unless($event->is_ordering_open)
                        <span class="eb-badge eb-badge-warning" style="margin-top: 6px;">Ordering paused</span>
                    @endunless
                </div>

                @auth
                    @if($event->is_ordering_open && $fromPrice !== null)
                        <a href="{{ route('checkout.tickets', $event) }}" class="btn-gradient-purchase" id="btn-blade-purchase-tickets">
                            <span>{{ empty($tiers) ? 'Order food' : 'Get tickets' }}</span>
                        </a>
                    @else
                        <a href="{{ route('attendee.dashboard') }}" class="btn-gradient-purchase" id="btn-blade-view-tickets">
                            <span>My tickets</span>
                        </a>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="btn-gradient-purchase" id="btn-blade-signin-purchase">
                        <span>Sign in to get tickets</span>
                    </a>
                @endauth

                <!-- Jump to Food Ordering -->
                @if(!empty($event->menuItems) && count($event->menuItems) > 0)
                    <button type="button" onclick="switchSection('food')" class="btn-glass" style="width: 100%; justify-content: center; margin-top: 10px; padding: 11px 16px; border-radius: 12px; font-size: 13px;">
                        <span>🍲</span>
                        <span>View the menu</span>
                    </button>
                @endif

                <div style="margin-top: 14px; font-size: 11px; color: #94A3B8; text-align: center; line-height: 1.4;">
                    Your QR ticket appears in My tickets after you order.
                </div>
            </div>
        </div>
    </div>

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
