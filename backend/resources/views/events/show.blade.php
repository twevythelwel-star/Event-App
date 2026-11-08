@extends('layouts.app')

@section('title', ($event->title ?? 'Event Details') . ' — EventBite')
@section('page-title', $event->title ?? 'Event Details')

@section('content')
@push('head')
    <x-preload-image :image="$event->coverImage()" sizes="100vw" />
@endpush
<style>
    /* Premium Midnight-Navy Theme for Event Detail Screen */
    .event-detail-root {
        background-color: #0B132B;
        min-height: 100vh;
        color: #FFFFFF;
        font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        margin: calc(-1 * var(--layout-page-padding-y)) calc(-1 * var(--layout-page-padding-x)) 0;
        padding-bottom: 5rem;
        overflow-wrap: anywhere;
    }

    /* The banner keeps the site's banner shape (21:9), and the event-cover shape (16:10) on phones, within limits */
    .detail-hero {
        position: relative;
        width: 100%;
        aspect-ratio: var(--ratio-banner, 21 / 9);
        min-height: 240px;
        max-height: 480px;
        overflow: hidden;
    }
    @media (max-width: 639px) { .detail-hero { aspect-ratio: var(--ratio-event, 16 / 10); } }

    .detail-hero-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center 30%;
        filter: brightness(0.9);
    }

    .detail-hero-backdrop {
        position: absolute;
        inset: -40px;
        width: calc(100% + 80px);
        height: calc(100% + 80px);
        object-fit: cover;
        filter: blur(28px) saturate(1.3) brightness(0.7);
        transform: scale(1.1);
    }
    .detail-hero-img { position: relative; }

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
        width: 200px;
        aspect-ratio: var(--ratio-photo, 4 / 3);
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
    .gallery-open { all: unset; display: block; width: 100%; height: 100%; cursor: zoom-in; }
    .gallery-open:focus-visible { outline: 2px solid #C7FF45; outline-offset: -2px; }
    .gallery-tag { display: block; font-size: 10px; font-weight: 600; color: #CBD5E1; }

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
            $cover = $event->coverImage();
            $heroImg = $cover->src;
        @endphp
        {{-- A generated flyer is text, so it is shown whole rather than cropped; uploaded artwork fills the banner,
             at the size the screen needs and cropped around the host's focal point. Behind a whole flyer, a blurred
             copy of the same artwork fills the full width. --}}
        @unless($event->hasFlyer())
            <img src="{{ $heroImg }}" alt="" aria-hidden="true" class="detail-hero-backdrop">
        @endunless
        <x-responsive-image :image="$cover" :alt="$event->hasFlyer() ? ($event->flyer_alt_text ?: $event->title) : $cover->alt" class="detail-hero-img" sizes="100vw" :eager="true"
            :fit="$event->hasFlyer() ? 'cover' : 'contain'"
            :style="$event->hasFlyer() ? '' : 'background: transparent; filter: drop-shadow(0 18px 40px rgba(0,0,0,.5));'" />
        <div class="detail-hero-overlay"></div>

        <!-- Floating Top Navigation -->
        <div class="detail-hero-nav">
            <a href="{{ route('events.index') }}" class="btn-glass" id="btn-back-to-discovery">
                <span>&larr;</span>
                <span>Back to All Events</span>
            </a>

            <div style="display: flex; align-items: center; gap: 10px;">
                <button type="button" class="icon-glass-btn" onclick="shareCurrentEvent()" title="Share Event" aria-label="Share this event">
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
                    @foreach($event->traditionLabels() as $tradition)
                        <a href="{{ route('events.index', ['q' => $tradition]) }}" style="border: 1px solid rgba(255, 255, 255, 0.28); color: #F8FAFC; font-size: 11px; font-weight: 700; padding: 3px 11px; border-radius: 9999px; text-decoration: none;">{{ $tradition }}</a>
                    @endforeach
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
                                {{ $event->time_str ?: 'Time to be announced' }}@if($event->timezone) ({{ $event->timezone }})@endif
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; align-items: flex-start;">
                        <span style="font-size: 20px;">📍</span>
                        <div>
                            <div style="font-size: 10px; text-transform: uppercase; color: #94A3B8; font-weight: 700;">Venue & Location</div>
                            <div style="font-size: 13px; font-weight: 700; color: #FFFFFF;">
                                {{ $event->venue_name ?: 'Venue to be announced' }}
                            </div>
                            <div style="font-size: 11px; color: #94A3B8;">
                                {{ $event->venue_address }}
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
                        <span>Food & Dining ({{ $menu->count() }})</span>
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
                                {{ $menu->isNotEmpty() ? 'This event sells food only. See the Food tab.' : 'The organizer hasn’t put tickets on sale yet.' }}
                            </p>
                        </div>
                    @else
                        <div style="display: flex; flex-direction: column; gap: 14px;">
                            @foreach($tiers as $tier)
                                <div class="card-navy" style="padding: 20px; display: flex; justify-content: space-between; gap: 16px; align-items: flex-start;">
                                    <div style="min-width: 0;">
                                        <h3 style="font-size: 16px; font-weight: 800; color: #FFFFFF; margin: 0;">{{ $tier['name'] }}</h3>
                                        @if($tier['description'])<p style="font-size: 13px; color: #94A3B8; margin: 4px 0 0;">{{ $tier['description'] }}</p>@endif
                                        <div style="margin-top: 8px;"><x-tier-availability :tier="$tier" /></div>
                                    </div>
                                    <div style="text-align: right; flex-shrink: 0;">
                                        <div style="font-size: 20px; font-weight: 900; color: #FFFFFF;">{{ \App\Support\Money::formatMinor($tier['price_minor'], $tier['currency']) }}</div>
                                        <div style="font-size: 11px; color: #94A3B8;">per ticket</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- TAB 2: FOOD & DINING (active menu items only, as the organizer wrote them) -->
                <div class="tab-pane" id="pane-food">
                    @if($menu->isEmpty())
                        <div class="card-navy" style="text-align: center; padding: 36px 20px;">
                            <h3 style="font-size: 16px; font-weight: 700; color: #FFFFFF; margin: 0 0 6px;">No food menu</h3>
                            <p style="font-size: 13px; color: #94A3B8; margin: 0;">This event has no food to order in advance.</p>
                        </div>
                    @else
                        <p style="font-size: 13px; color: #94A3B8; margin: 0 0 12px;">
                            @if($canOrder && $event->supports_food_ordering)
                                Order food with your tickets or on its own, and collect it at the event with your order QR code.
                            @else
                                Food ordering is closed for this event. The menu is shown for reference.
                            @endif
                        </p>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px;">
                            @foreach($menu as $item)
                                <div class="card-navy" style="padding: 16px; display: flex; flex-direction: column; justify-content: space-between;">
                                    <div>
                                        @if($item->category)<div style="font-size: 10px; text-transform: uppercase; color: #818CF8; font-weight: 700;">{{ $item->category }}</div>@endif
                                        <h4 style="font-size: 15px; font-weight: 800; color: #FFFFFF; margin: 4px 0 6px;">{{ $item->name }}</h4>
                                        @if($item->description)<p style="font-size: 12px; color: #94A3B8; margin: 0 0 8px; line-height: 1.4;">{{ $item->description }}</p>@endif
                                        @if(!empty($item->dietary_tags) && is_array($item->dietary_tags))
                                            <div style="display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 6px;">
                                                @foreach($item->dietary_tags as $tag)
                                                    <span style="font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 9999px; background: rgba(16, 185, 129, 0.18); color: #6EE7B7;">{{ \App\Models\MenuItem::DIETARY_TAGS[$tag] ?? $tag }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                        @if(!empty($item->allergens) && is_array($item->allergens))
                                            <div style="font-size: 11px; color: #FCD34D; margin-bottom: 6px; font-weight: 600;">
                                                Allergens: {{ implode(', ', array_map(fn ($a) => \App\Models\MenuItem::ALLERGENS[$a] ?? $a, $item->allergens)) }}
                                            </div>
                                        @endif
                                        @if($item->pricing_type === 'customizable' && !empty($item->custom_option_groups))
                                            <span class="eb-badge eb-badge-info" style="margin-bottom: 8px;">Choose options</span>
                                        @endif
                                        @if($item->available_stock !== null && (int) $item->available_stock <= 0)
                                            <span class="eb-badge eb-badge-error" style="margin-bottom: 8px;">Sold out</span>
                                        @endif
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 10px; border-top: 1px solid rgba(255, 255, 255, 0.08);">
                                        <span style="font-size: 16px; font-weight: 800; color: #FFFFFF;">
                                            @if($item->pricing_type === 'custom_amount')
                                                You choose the amount
                                            @else
                                                {{ $item->priceMoney()->display() }}
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- TAB 3: DETAILS: description, pickup, payment, fees, refunds and the event QR code -->
                <div class="tab-pane" id="pane-details">
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
                            <h3 style="font-size: 15px; font-weight: 800; color: #FFFFFF; margin: 0 0 6px;">Accepted payment methods</h3>
                            @if($paymentMethods === [])
                                <p style="font-size: 13px; color: #94A3B8; margin: 0;">The organizer has not set up a payment method yet, so orders can't be paid for at the moment.</p>
                            @else
                                <ul style="margin: 0; padding-left: 18px; font-size: 13px; color: #CBD5E1; line-height: 1.6;">
                                    @foreach($paymentMethods as $method)
                                        <li><strong style="color: #FFFFFF;">{{ $method['label'] }}</strong>: {{ $method['description'] }}</li>
                                    @endforeach
                                </ul>
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
                        </div>

                        @if($eventQr)
                            <div id="event-qr" style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                                <img src="{{ $eventQr['image'] }}" alt="QR code linking to this event page" width="120" height="120" style="background: #FFFFFF; border-radius: 8px; padding: 6px;">
                                <div>
                                    <h3 style="font-size: 15px; font-weight: 800; color: #FFFFFF; margin: 0 0 4px;">Share this event</h3>
                                    <p style="font-size: 12px; color: #94A3B8; margin: 0;">Scan to open this page. This code is for sharing only and is not a ticket.</p>
                                    <a href="{{ $eventQr['link'] }}" style="font-size: 12px; color: #C7FF45;">{{ $eventQr['link'] }}</a>
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
                    ->merge($event->menuItems->where('is_active', true)->map(fn ($item) => ['item' => $item, 'image' => $item->responsiveImage()])
                        ->filter(fn ($row) => $row['image'] !== null)
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
            <div class="card-navy">
                <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 14px;">
                    <div style="width: 48px; height: 48px; border-radius: 14px; background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%); display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 900; color: #FFFFFF;">
                        {{ mb_substr($event->organizer_name ?: 'EventBite', 0, 1) }}
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
                    @if($fromPriceText)
                        <div style="font-size: 26px; font-weight: 900; color: #FFFFFF;">{{ $fromPriceText }}</div>
                        <div style="font-size: 11px; color: #94A3B8; font-weight: 700;">Ticket prices; fees and taxes are shown before you pay</div>
                    @endif
                    @php $tone = ['success' => 'eb-badge-success', 'danger' => 'eb-badge-error', 'warning' => 'eb-badge-warning'][$availability['tone']] ?? 'eb-badge-info'; @endphp
                    <span class="eb-badge {{ $tone }}" style="margin-top: 6px;">{{ $availability['label'] }}</span>
                </div>

                @if($canOrder)
                    @auth
                        <a href="{{ route('checkout.tickets', $event) }}" class="btn-gradient-purchase" id="btn-blade-purchase-tickets">
                            <span>{{ collect($tiers)->contains('on_sale', true) ? 'Get tickets' : 'Order food' }}</span>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="btn-gradient-purchase" id="btn-blade-signin-purchase">
                            <span>Sign in to get tickets</span>
                        </a>
                    @endauth
                @else
                    <p style="font-size: 13px; color: #CBD5E1; margin: 0;">{{ $availability['label'] }}. Tickets can't be bought right now.</p>
                @endif

                <!-- Jump to Food Ordering -->
                @if($menu->isNotEmpty())
                    <button type="button" onclick="switchSection('food')" class="btn-glass" style="width: 100%; justify-content: center; margin-top: 10px; padding: 11px 16px; border-radius: 12px; font-size: 13px;">
                        <span>🍲</span>
                        <span>View the menu</span>
                    </button>
                @endif

                <div style="margin-top: 14px; font-size: 11px; color: #94A3B8; text-align: center; line-height: 1.4;">
                    Your ticket QR code appears in My tickets once your payment is confirmed. <a href="#payment-methods" onclick="switchSection('details')" style="color: #C7FF45;">Payment, fees and refunds</a>
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
        .event-detail-root { padding-bottom: calc(5rem + 72px); }
    }
</style>

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
