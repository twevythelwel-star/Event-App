@extends('layouts.app')

@section('title', 'Ticket ' . $order->ticket_code)
@section('page-title', 'Your ticket')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/ticket-collection.css') }}">
    <script src="{{ asset('js/ticket-collection.js') }}" defer></script>
@endpush

@section('content')
@php
    $currency = $order->currency;
    $status = $order->status;
    $tz = $event?->timezone ?: 'America/New_York';
    $revoked = $order->token_revoked_at !== null;
    $void = $revoked || in_array($status, ['refunded', 'cancelled', 'rejected', 'expired'], true);
    // Ticket issuance: the admission QR code is shown only once the payment is settled (paid, or paid and
    // partly refunded) and the token has not been revoked. Until then the customer gets the order reference.
    $settled = !$revoked && in_array($status, ['paid', 'partially_refunded'], true);
    $partlyPaid = $status === 'partially_paid';
    $checkedIn = !empty($order->checked_in_at);
    // After the event the ticket stays as a keepsake: shown in full, but never again as an admission credential
    // (no QR code; the gate rejects it too, App\Support\TicketLifecycle).
    $keepsake = !$void && $event && \App\Support\TicketLifecycle::eventIsOver($event);
    $balanceMinor = max(0, (int) $order->total_minor - (int) $order->amount_paid_minor);
    $awaitingDetails = !$void && !$settled && $order->method !== 'cash';
    $tickets = $order->items->where('category', 'Tickets');
    $food = $order->items->where('category', '!=', 'Tickets');
    // The design the host chose for the ticket type bought (the ticket designer), shown as the ticket's accent.
    $tierDesign = collect(is_array($event?->ticket_tiers) ? $event->ticket_tiers : [])->firstWhere('id', $tickets->first()?->ticket_tier_id) ?? [];
    $local = fn ($time) => $time ? $time->copy()->setTimezone($tz)->format('M j, Y g:i A T') : null;
    // The ticket's look follows the kind of event the host declared (App\Support\TicketTheme); the host's own
    // ticket designer choices still apply on top, and the QR stays on plain white.
    $theme = \App\Support\TicketTheme::for($event);
    $cityState = $event ? collect([$event->city, $event->state_code])->filter()->implode(', ') : '';

    // Admission validity window, computed exactly as the gate does: doors_open_time on the event
    // date (event timezone) falling back to the event start, closing at the event end.
    $admissionWindow = null;
    if ($event) {
        $localDate = $event->starts_at?->copy()->setTimezone($tz)->format('Y-m-d')
            ?? ($event->event_date_iso ? \Illuminate\Support\Carbon::parse((string) $event->event_date_iso)->format('Y-m-d') : null);
        $gateOpens = null;
        if ($localDate && $event->doors_open_time) {
            $gateOpens = \Illuminate\Support\Carbon::createFromFormat('Y-m-d H:i', "{$localDate} {$event->doors_open_time}", $tz);
        }
        $gateOpens ??= $event->starts_at?->copy()->setTimezone($tz);
        $gateCloses = $event->ends_at?->copy()->setTimezone($tz);
        if ($gateOpens || $gateCloses) {
            $admissionWindow = trim(($gateOpens?->format('M j \a\t g:i A') ?? 'Event day') . ' – ' . ($gateCloses?->format('M j \a\t g:i A') ?? 'event end'), ' –');
        }
    }

    // Admission status presentation (distinct from payment status)
    [$admissionTitle, $admissionSub, $admissionBadgeClass, $admissionIcon] = match (true) {
        $revoked => ['Revoked · Not valid for entry', $order->revocation_reason ?: 'This ticket QR has been revoked on the server.', 'eb-badge-error', 'alert'],
        $void => ['Void · Not valid for entry', 'This pass cannot be used for admission or collection.', 'eb-badge-error', 'alert'],
        $keepsake => ['Keepsake · Not valid for entry', $checkedIn ? 'Admitted at the gate ' . $local($order->checked_in_at) . '.' : 'This event is over. This ticket was not scanned at the gate.', 'eb-badge-keepsake', 'star'],
        $checkedIn => ['Checked in at gate', $order->checked_in_at ? 'Scanned ' . $local($order->checked_in_at) : 'Admitted at gate', 'eb-badge-success', 'check'],
        $settled => ['Valid for gate entry', 'Present this individual admission pass at the gate.', 'eb-badge-success', 'qr'],
        default => ['Not yet issued for entry', 'Admission QR activates once payment is verified.', 'eb-badge-warning', 'clock'],
    };

    // Ticket type summary label
    $ticketTypeSummary = match (true) {
        $tickets->isNotEmpty() => $tickets->map(fn ($i) => $i->quantity . ' × ' . $i->name)->join(' · '),
        $food->isNotEmpty() => 'Advance Food Pre-Order Pass',
        default => 'General Event Pass',
    };

    $methodIconName = match ((string) $order->method) {
        'bank' => 'bank',
        'zelle', 'cashapp', 'cash_app_pay' => 'phone',
        'cash' => 'cash',
        'paypal' => 'globe',
        default => 'credit-card',
    };

    // What the customer should do next, in one line.
    [$nextTitle, $nextDetail, $nextTone] = match (true) {
        $revoked => ['Ticket revoked', $order->revocation_reason ?: 'This ticket QR code was revoked and cannot be used for entry.', 'error'],
        $status === 'refunded' => ['Refunded', 'This ticket was refunded and can’t be used for entry.', 'error'],
        $status === 'rejected' => ['Payment not accepted', $order->rejection_reason ?: 'The organizer couldn’t match your payment. Contact them to sort it out.', 'error'],
        in_array($status, ['cancelled', 'expired'], true) => ['Cancelled', 'This order was cancelled and can’t be used.', 'error'],
        $keepsake => ['A keepsake from this event', 'This event is over, so your ticket is kept here as a souvenir. It is no longer valid for admission.', 'info'],
        $checkedIn => ['Checked in', 'This ticket was scanned at the gate. Enjoy the event.', 'success'],
        $settled => ['You’re all set', 'Payment confirmed. Show this QR code at the gate' . ($food->isNotEmpty() ? ' and when you collect your food.' : '.'), 'success'],
        $partlyPaid => ['Part of your payment received', 'The organizer has confirmed part of your payment. Your ticket is issued once the rest is paid.', 'warning'],
        $status === 'payment_submitted' || $status === 'under_verification' => ['Payment being checked', 'The organizer is checking your payment. Your ticket QR code appears here when they confirm it.', 'warning'],
        $order->method === 'cash' => ['Pay at pickup', 'Pay staff in cash when you arrive, quoting your order number. They’ll mark this ticket paid.', 'warning'],
        default => ['Waiting for your payment', 'Send the transfer using the details below, then add your reference.', 'warning'],
    };
@endphp
<div class="eb-checkout eb-ticket-pass-page">
    <p id="ticket-live-status" class="sr-only" aria-live="polite"></p>
    @auth<a href="{{ $keepsake ? route('tickets.collection', ['tab' => 'past']) : route('tickets.collection') }}" class="eb-help-text" style="text-decoration: none;" data-no-print>← My Tickets</a>@endauth
    <x-alert :type="$nextTone" :title="$nextTitle">{{ $nextDetail }}</x-alert>

    <article class="eb-ticket-card eb-digital-pass {{ $theme->cssClass() }} {{ $settled && !$keepsake ? 'is-settled' : '' }} {{ $keepsake ? 'is-keepsake' : '' }} {{ $void ? 'is-void' : '' }}"
             style="{{ $theme->style() }}" data-theme="{{ $theme->key }}"
             aria-labelledby="ticket-title"
             @if($tickets->isNotEmpty())
                 data-design="{{ $tierDesign['designStyle'] ?? 'premium_minimal' }}"
                 data-accent="{{ $tierDesign['accentColor'] ?? 'indigo' }}"
             @endif>

        {{-- 1. Event Artwork Header Integration --}}
        @if($event)
            <div class="eb-ticket-card-head eb-digital-pass-head" style="view-transition-name: ticket-art-{{ $order->id }};">
                @php
                    $ticketCover = $event->coverImage();
                    $isPhotoCover = $event->hasFlyer() || $ticketCover->illustrative;
                @endphp
                @unless($isPhotoCover)
                    <img src="{{ $ticketCover->src }}" alt="" aria-hidden="true" class="eb-ticket-head-backdrop" loading="lazy" decoding="async">
                @endunless
                <x-responsive-image
                    :image="$ticketCover"
                    alt=""
                    sizes="(min-width: 640px) 560px, 100vw"
                    :eager="true"
                    :fit="$isPhotoCover ? 'cover' : 'contain'"
                    :style="$isPhotoCover ? ('object-position:' . e($event->flyer_position ?: '50% 35%')) : 'object-fit: contain;'" />
                <div class="eb-event-card-scrim"></div>
                <x-image-label :image="$ticketCover" corner="top-right" />
                <div class="eb-card-chip">@include('components.status-indicator', ['status' => $status])</div>
            </div>
            @php $ticketKeepsake = $event->relationLoaded('mediaItems') ? $event->visibleGallery()->first() : null; @endphp
            @if($ticketKeepsake)
                <figure class="eb-ticket-keepsake">
                    <x-responsive-image :image="$ticketKeepsake->responsiveImage()" :alt="$ticketKeepsake->alt_text" sizes="(min-width: 640px) 560px, 100vw" />
                    <figcaption>From this event · {{ $ticketKeepsake->alt_text }}</figcaption>
                </figure>
            @endif
        @endif

        <div class="eb-ticket-card-body eb-digital-pass-body">
            {{-- 2. Pass Kicker, Ticket Type, Event Title, Date & Venue --}}
            <div class="eb-pass-identity">
                <span class="eb-ticket-theme-kicker">{{ $theme->label }} · {{ $keepsake ? 'Keepsake' : 'Admission pass' }}</span>
                <div class="eb-pass-top-bar">
                    <span class="eb-ticket-type-pill">
                        <x-icon name="ticket" size="sm" />
                        <span>{{ $ticketTypeSummary }}</span>
                    </span>
                    @if($event?->category)
                        <span class="eb-badge eb-badge-neutral">{{ $event->category }}</span>
                    @endif
                </div>

                <h1 id="ticket-title" class="eb-text-page-title eb-pass-event-title" style="margin: 4px 0 0;">{{ $event?->title ?? 'Your order' }}</h1>

                @if($event)
                    <div class="eb-ticket-meta-grid" style="margin-top: var(--space-3);">
                        <div class="eb-ticket-meta-cell">
                            <span class="eb-ticket-meta-label"><x-icon name="calendar" size="sm" /> Date &amp; Time</span>
                            <span class="eb-ticket-meta-val">
                                {{ collect([\App\Services\ReceiptService::eventDate($event), \App\Services\ReceiptService::eventTime($event)])->filter()->implode(' · ') }}
                            </span>
                        </div>
                        <div class="eb-ticket-meta-cell">
                            <span class="eb-ticket-meta-label"><x-icon name="map-pin" size="sm" /> Venue</span>
                            <span class="eb-ticket-meta-val">{{ \App\Services\ReceiptService::venue($event) }}</span>
                        </div>
                        @if($cityState !== '')
                            <div class="eb-ticket-meta-cell">
                                <span class="eb-ticket-meta-label"><x-icon name="globe" size="sm" /> City &amp; state</span>
                                <span class="eb-ticket-meta-val">{{ $cityState }}</span>
                            </div>
                        @endif
                        @if($order->customer_name)
                            <div class="eb-ticket-meta-cell">
                                <span class="eb-ticket-meta-label"><x-icon name="user" size="sm" /> Pass Holder</span>
                                <span class="eb-ticket-meta-val">{{ $order->customer_name }}</span>
                            </div>
                        @endif
                        <div class="eb-ticket-meta-cell">
                            <span class="eb-ticket-meta-label"><x-icon :name="$methodIconName" size="sm" /> Payment Method</span>
                            <span class="eb-ticket-meta-val">{{ \App\Support\PaymentStatusPresenter::methodLabel($order->method) }} ({{ $currency }})</span>
                        </div>
                    </div>
                @endif
            </div>

            {{-- 3. Payment Status & Admission Status Strip --}}
            <div class="eb-pass-status-strip" role="region" aria-label="Ticket and payment status">
                <div class="eb-pass-status-item">
                    <span class="eb-text-label">Payment status</span>
                    <div class="eb-pass-status-badge-row">
                        @include('components.status-indicator', ['status' => $status])
                        <span class="eb-help-text">{{ \App\Support\PaymentStatusPresenter::methodLabel($order->method) }}</span>
                    </div>
                </div>
                <div class="eb-pass-status-item">
                    <span class="eb-text-label">Admission status</span>
                    <div class="eb-pass-status-badge-row">
                        <span class="eb-badge {{ $admissionBadgeClass }} eb-badge-icon">
                            <x-icon :name="$admissionIcon" size="sm" />
                            <span>{{ $admissionTitle }}</span>
                        </span>
                    </div>
                    <span class="eb-help-text">{{ $admissionSub }}</span>
                </div>
            </div>

            @php $orderSeats = app(\App\Services\Events\SeatingService::class)->seatsFor($order); @endphp
            @if($orderSeats->isNotEmpty())
                <div class="eb-pass-seat-banner">
                    <span class="eb-text-label"><x-icon name="star" size="sm" /> Assigned {{ \Illuminate\Support\Str::plural('Seat', $orderSeats->count()) }}</span>
                    <span class="eb-text-ticket-code" style="font-size: 1rem;">{{ $orderSeats->map(fn ($s) => $s->spokenName())->join(' · ') }}</span>
                </div>
            @endif

            @if($status === 'refunded')
                <img src="{{ \App\Support\VisualAssets::illustration('refund')['url'] }}" alt="" width="180" height="135" style="display: block; margin: 0 auto; max-width: 60%; height: auto;">
            @endif

            {{-- 4. Perforated Stub & Unobstructed Admission QR Well --}}
            @unless($void)
                <div class="eb-ticket-card-tear" aria-hidden="true">
                    <span class="eb-ticket-notch eb-ticket-notch-left"></span>
                    <span class="eb-ticket-notch eb-ticket-notch-right"></span>
                </div>

                <div class="eb-pass-qr-section">
                    @if($keepsake)
                        @include('tickets.partials.keepsake-panel')
                    @elseif($settled)
                        <div class="eb-pass-qr-kicker eb-ticket-success-badge">
                            <svg class="eb-icon eb-icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path class="eb-check-draw" d="M20 6L9 17l-5-5" />
                            </svg>
                            <span>Official Attendee Admission Pass · Scan at Gate</span>
                        </div>
                        @if(($attendeeTickets ?? collect())->isNotEmpty())
                            {{-- One QR code per attendee, each its own single-use credential, in the event's approved design.
                                 The artwork carries its own quiet zone and frame; nothing is drawn over it. --}}
                            @php $seatCount = $attendeeTickets->count(); $tierNames = $order->items->keyBy('id'); @endphp
                            <div class="eb-attendee-qr-list" data-attendee-count="{{ $seatCount }}">
                                @php $canReissue = auth()->check() && auth()->user()->can('reissue', $attendeeTickets->first()) && !$checkedIn; @endphp
                                @foreach($attendeeTickets as $attendee)
                                    <x-ticket-attendee-qr :attendee="$attendee" :order="$order" :event="$event" :of="$seatCount"
                                        :tier-name="$tierNames->get($attendee->order_item_id)?->name ?? 'Admission'" :ticket-qr="$ticketQr" :can-reissue="$canReissue" />
                                @endforeach
                            </div>
                            <p class="eb-help-text" style="margin: var(--space-2) 0 0; text-align: center;">
                                One single-use QR code per guest{{ $admissionWindow ? ' · valid ' . $admissionWindow : '' }}. Each guest shows their own code at the gate. Food items are collected separately by event staff.
                            </p>
                        @else
                            {{-- High-contrast, unobstructed QR well: pure white quiet zone, zero decorative graphics over QR modules --}}
                            <div class="eb-ticket-qr-frame">
                                <div class="eb-ticket-qr-well" data-qr-type="admission">
                                    {!! \App\Support\QrCode::svg($order->secure_ticket_token, 220, 'QR code for ticket ' . $order->ticket_code) !!}
                                </div>
                            </div>
                            <p class="eb-help-text" style="margin: var(--space-2) 0 0; text-align: center;">
                                Single-use admission QR{{ $admissionWindow ? ' · valid ' . $admissionWindow : '' }}. Food items are collected separately by event staff.
                            </p>
                        @endif
                    @else
                        <img src="{{ \App\Support\VisualAssets::illustration('payment')['url'] }}" alt="" width="180" height="135" style="display: block; margin: 0 auto var(--space-2); max-width: 60%; height: auto;">
                        <p class="eb-text-supporting" style="margin: 0;">Your ticket QR code appears here once your payment is confirmed.</p>
                    @endif

                    {{-- 5. Ticket Number & Order Reference Presentation --}}
                    @unless($keepsake)
                    <div class="eb-ticket-serial-plate">
                        <div class="eb-ticket-serial-main">
                            <span class="eb-text-label">{{ $settled ? 'Ticket code' : 'Order reference' }}</span>
                            <span class="eb-text-ticket-code eb-ticket-card-code" id="ticket-code-value">{{ $order->ticket_code }}</span>
                        </div>
                        <div class="eb-ticket-serial-sub">
                            <span>Order {{ $order->order_number }}</span>
                            @if($order->created_at)
                                <span>· Placed {{ $local($order->created_at) }}</span>
                            @endif
                            @if((int) ($order->token_version ?? 1) > 1)
                                <span>· QR v{{ (int) $order->token_version }}{{ $order->token_reissued_at ? ' (reissued ' . $local($order->token_reissued_at) . ')' : '' }}</span>
                            @endif
                        </div>
                        <div class="eb-ticket-serial-actions" data-no-print>
                            <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm" onclick="navigator.clipboard && navigator.clipboard.writeText('{{ $order->ticket_code }}'); this.textContent = 'Copied ✓';">
                                Copy code
                            </button>
                            @if($event && ($event->starts_at || $event->event_date_iso))
                                <a class="eb-btn eb-btn-ghost eb-btn-sm" href="{{ route('tickets.calendar', $order) }}" download data-confirm-text="Added to calendar"><span data-confirm-label>Add to calendar</span></a>
                            @endif
                            {{-- Apple Wallet: a clean entry pass for the holder, only while the ticket is valid for entry. --}}
                            @if($settled && !$checkedIn && auth()->check() && ($order->user_id === null || (int) $order->user_id === (int) auth()->id()) && app(\App\Services\Wallet\AppleWalletConfig::class)->enabled())
                                <a class="eb-btn eb-btn-sm eb-wallet-btn" href="{{ route('tickets.wallet', $order) }}" data-confirm-text="Opening Apple Wallet"><span data-confirm-label>Add to Apple Wallet</span></a>
                            @endif
                            @if($settled && !$checkedIn)
                                <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" onclick="window.print()">
                                    Print ticket
                                </button>
                                <form method="POST" action="{{ route('tickets.reissue', $order) }}" style="display: inline-block; margin: 0;">
                                    @csrf
                                    <button type="submit" class="eb-btn eb-btn-ghost eb-btn-sm" data-loading-text="Replacing QR…">
                                        Replace QR code
                                    </button>
                                </form>
                            @endif
                        </div>
                        @if($settled && !$checkedIn)
                            <p class="eb-help-text" style="margin: var(--space-2) 0 0; font-size: 0.78rem;" data-no-print>
                                One-time admission pass: screenshots can be copied, and only the first scan at the gate is admitted. If your QR was shared by mistake, click <strong>Replace QR code</strong> to deactivate the old code immediately.
                            </p>
                        @endif
                    </div>
                    @endunless
                </div>
            @endunless

            <div class="eb-ticket-card-tear" aria-hidden="true">
                <span class="eb-ticket-notch eb-ticket-notch-left"></span>
                <span class="eb-ticket-notch eb-ticket-notch-right"></span>
            </div>

            {{-- 6. Financial Breakdown & Receipt --}}
            <x-price-summary
                :lines="$order->items->map(fn ($item) => ['label' => $item->quantity . ' × ' . $item->name, 'amount_minor' => (int) $item->line_total_minor])->all()"
                :charges="$order->charges->map(fn ($c) => ['kind' => $c->kind, 'label' => $c->label, 'amount_minor' => (int) $c->amount_minor])->all()"
                :subtotal-minor="(int) $order->subtotal_minor"
                :total-minor="(int) $order->total_minor"
                :currency="$currency" />

            @if((int) $order->refunded_minor > 0)
                <p class="eb-help-text" style="margin: 0;">Refunded: {{ \App\Support\Money::formatMinor((int) $order->refunded_minor, $currency) }}</p>
            @endif

            @if($settled || $partlyPaid || (int) $order->amount_paid_minor > 0)
                <div class="eb-pass-receipt-box" aria-label="Receipt">
                    <span class="eb-text-label">Receipt</span>
                    <span class="eb-help-text">Paid {{ \App\Support\Money::formatMinor((int) $order->amount_paid_minor, $currency) }} by {{ \App\Support\PaymentStatusPresenter::methodLabel($order->method) }}@if($order->verified_at), confirmed {{ $local($order->verified_at) }}@endif.</span>
                    @if($balanceMinor > 0 && !$void)<span class="eb-help-text">Still to pay: {{ \App\Support\Money::formatMinor($balanceMinor, $currency) }}</span>@endif
                </div>
            @endif

            <p class="eb-help-text" style="margin: 0;">
                Payment: {{ \App\Support\PaymentStatusPresenter::label($status) }} · {{ \App\Support\PaymentStatusPresenter::methodLabel($order->method) }} · {{ $currency }}
                @if($order->payment_reference && in_array($order->method, ['bank', 'zelle', 'cashapp'], true)) · your reference {{ $order->payment_reference }}@endif
            </p>
            <p class="eb-help-text" style="margin: 0;">Order {{ $order->order_number }} · placed {{ $local($order->created_at) }}</p>

            @if(!$void && !$settled && $order->reservation_expires_at)
                <p class="eb-help-text" style="margin: 0;">Inventory held until {{ $local($order->reservation_expires_at) }}. Complete payment confirmation before this time to keep this reservation.</p>
            @endif

            @php $ticketSponsors = $event ? app(\App\Services\Events\PartnersService::class)->sponsorsFor($event, 'ticket') : collect(); @endphp
            @if($ticketSponsors->isNotEmpty())
                <p class="eb-ticket-sponsors"><span class="eb-text-label">With thanks to</span> {{ $ticketSponsors->pluck('name')->join(' · ') }}</p>
            @endif

            <div class="eb-ticket-card-brand">
                <span class="brand-band"><x-brand-logo variant="compact" alt="EventBite ticket" style="width: 112px;" /></span>
            </div>
        </div>
    </article>

    {{-- Transfer: pass paid tickets to someone else (their account, their QR; this QR then stops working). --}}
    @php
        $transferable = false;
        try { app(\App\Services\Checkout\TicketTransferService::class)->assertTransferable($order); $transferable = (int) $order->user_id === (int) auth()->id(); } catch (\App\Services\OrderPlacementException) {}
        $pendingTransfer = \App\Models\TicketTransfer::where('order_id', $order->id)->where('status', 'pending')->where('expires_at', '>', now())->first();
    @endphp
    @if($order->is_complimentary)
        <p class="eb-help-text" style="text-align: center;">Complimentary ticket from {{ $event?->organizer_name ?: 'the host' }}.</p>
    @endif
    @if($transferable || $pendingTransfer)
        <section class="eb-card eb-checkout-body" aria-labelledby="transfer-heading">
            <h2 id="transfer-heading" class="eb-text-section-title" style="margin: 0 0 var(--space-2);">Transfer these tickets</h2>
            @if(session('transfer_link'))
                <x-alert type="success" title="Send this link to the new holder">
                    <p style="margin: 0 0 4px;">They open it and sign in with the email you entered. Only you see this link.</p>
                    <input class="eb-input" readonly value="{{ session('transfer_link') }}" onclick="this.select()" aria-label="Transfer link">
                </x-alert>
            @endif
            @if($pendingTransfer)
                <form method="POST" action="{{ route('tickets.transfer.cancel', $pendingTransfer->id) }}" class="eb-cluster">
                    @csrf
                    <span>Waiting for {{ $pendingTransfer->to_email }} to accept (until {{ $pendingTransfer->expires_at->copy()->setTimezone($tz)->format('M j, g:i A') }}).</span>
                    <button class="eb-btn eb-btn-ghost eb-btn-sm" type="submit">Cancel transfer</button>
                </form>
            @else
                <p class="eb-text-supporting" style="margin: 0 0 var(--space-3);">The new holder gets a new QR code; this one stops working once they accept.</p>
                <form method="POST" action="{{ route('tickets.transfer.start', $order) }}" class="eb-cluster" style="align-items: flex-end;">
                    @csrf
                    <div class="eb-form-group" style="flex: 1 1 240px; margin: 0;">
                        <label class="eb-label" for="transfer-email">Their EventBite email</label>
                        <input class="eb-input" type="email" id="transfer-email" name="email" required maxlength="190">
                    </div>
                    <button class="eb-btn eb-btn-secondary" type="submit" onclick="return confirm('Transfer these tickets? Your QR code will stop working once they accept.');">Transfer</button>
                </form>
                @error('transfer')<div class="eb-field-error" role="alert"><span>{{ $message }}</span></div>@enderror
            @endif
        </section>
    @endif

    @if($awaitingDetails)
        <section class="eb-card eb-checkout-body eb-payment-instructions-card" aria-labelledby="pay-heading">
            <div class="eb-payment-instructions-head">
                <span class="eb-payment-icon" data-method="{{ $order->method }}" aria-hidden="true">
                    <x-icon :name="$methodIconName" size="md" />
                </span>
                <div>
                    <h2 id="pay-heading" class="eb-text-section-title" style="margin: 0;">How to pay ({{ \App\Support\PaymentStatusPresenter::methodLabel($order->method) }})</h2>
                    <p class="eb-help-text" style="margin: 2px 0 0;">Follow the organizer's instructions below, then submit your transfer reference.</p>
                </div>
            </div>

            @if($instructions)
                <div class="eb-checkout-instructions">{{ $instructions }}</div>
            @else
                <p class="eb-text-supporting" style="margin: 0;">The organizer will send you payment details.</p>
            @endif

            <div class="eb-payment-due-callout">
                <div>
                    <span class="eb-text-label">Amount due</span>
                    <strong class="eb-text-price" style="display: block; font-size: 1.2rem;">{{ \App\Support\Money::formatMinor($balanceMinor, $currency) }}</strong>
                </div>
                <div>
                    <span class="eb-text-label">Required memo / description</span>
                    <strong class="eb-text-ticket-code" style="display: block; font-size: 1.05rem;">{{ $order->ticket_code }}</strong>
                </div>
            </div>

            <p class="eb-text-supporting" style="margin: 0;">Amount due: <strong>{{ \App\Support\Money::formatMinor($balanceMinor, $currency) }}</strong>. Use <strong>{{ $order->ticket_code }}</strong> as the payment description so it can be matched.</p>
            <p class="eb-help-text" style="margin: 0 0 var(--space-2);">A screenshot or reference is not proof that money arrived: your order stays pending until the organizer confirms the payment.</p>

            <form method="POST" action="{{ route('tickets.payment-reference', $order) }}" class="eb-stack" style="gap: var(--space-3);">
                @csrf
                <x-form-field name="payment_reference" label="Transfer reference" :value="old('payment_reference', $order->payment_reference)"
                    :required="true" placeholder="e.g. NCB-109284" help="From your bank or app receipt." />
                <x-form-field name="sender_name" label="Name on the paying account (optional)" :value="old('sender_name', $order->sender_name)" />
                <div><button type="submit" class="eb-btn eb-btn-primary" data-prevent-double-submit="true">
                    {{ $status === 'payment_submitted' ? 'Update reference' : 'I’ve sent the payment' }}
                </button></div>
            </form>
        </section>
    @endif

    <p style="text-align: center;"><a href="{{ route('attendee.dashboard') }}" class="eb-text-label">All my tickets</a></p>
</div>
@endsection
