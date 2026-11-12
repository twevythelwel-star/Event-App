{{--
    A customer's digital pass summary card (wallet listings, attendee dashboard).
    Props: order (App\Models\Order, with its event loaded or loadable),
           href (link to the full ticket pass, optional).
    The scannable admission QR code itself belongs only on the full ticket page, never in listings.
--}}
@props([
    'order',
    'href' => null,
])

@php
    $event = $order->event;
    $status = (string) $order->status;
    $settled = in_array($status, ['paid', 'partially_refunded'], true);
    $void = in_array($status, ['refunded', 'cancelled', 'rejected', 'expired'], true);
    $checkedIn = !empty($order->checked_in_at);
    $keepsake = !$void && \App\Support\TicketLifecycle::eventIsOver($event);

    $itemsLoaded = $order->relationLoaded('items');
    $itemCount = $itemsLoaded ? (int) $order->items->sum('quantity') : null;
    $ticketItems = $itemsLoaded ? $order->items->where('category', 'Tickets') : collect();
    $foodItems = $itemsLoaded ? $order->items->where('category', '!=', 'Tickets') : collect();

    $tierDesign = [];
    if ($event && is_array($event->ticket_tiers) && $ticketItems->isNotEmpty()) {
        $tierDesign = collect($event->ticket_tiers)->firstWhere('id', $ticketItems->first()?->ticket_tier_id) ?? [];
    }

    $ticketTypeLabel = match (true) {
        $ticketItems->isNotEmpty() => $ticketItems->map(fn ($i) => $i->quantity . ' × ' . $i->name)->join(' · '),
        $foodItems->isNotEmpty() => 'Food Pre-Order Pass (' . (int) $foodItems->sum('quantity') . ' ' . \Illuminate\Support\Str::plural('item', (int) $foodItems->sum('quantity')) . ')',
        default => 'Digital Event Pass',
    };

    $theme = \App\Support\TicketTheme::for($event);
    [$admissionLabel, $admissionClass, $admissionIcon] = match (true) {
        $void => ['Void · Not valid', 'eb-badge-error', 'alert'],
        $keepsake => ['Keepsake · Event over', 'eb-badge-keepsake', 'star'],
        $checkedIn => ['Checked in at gate', 'eb-badge-success', 'check'],
        $settled => ['Admission ready', 'eb-badge-success', 'qr'],
        default => ['Awaiting payment', 'eb-badge-warning', 'clock'],
    };
@endphp

<article {{ $attributes->merge(['class' => 'eb-ticket-card eb-ticket-pass-card ' . $theme->cssClass() . ($void ? ' is-void' : '') . ($settled ? ' is-settled' : ''), 'style' => $theme->style(), 'data-theme' => $theme->key]) }}
    @if(!empty($tierDesign['designStyle'])) data-design="{{ $tierDesign['designStyle'] }}" @endif
    @if(!empty($tierDesign['accentColor'])) data-accent="{{ $tierDesign['accentColor'] }}" @endif>
    @if($event)
        <div class="eb-ticket-card-head">
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
                sizes="(min-width: 768px) 420px, 100vw"
                :fit="$isPhotoCover ? 'cover' : 'contain'"
                :style="$isPhotoCover ? ('object-position:' . e($event->flyer_position ?: '50% 35%')) : 'object-fit: contain;'" />
            <div class="eb-event-card-scrim"></div>
            <x-image-label :image="$ticketCover" corner="top-right" />
            <div class="eb-card-chip">@include('components.status-indicator', ['status' => $order->status])</div>
        </div>
    @endif

    <div class="eb-ticket-card-body">
        <div class="eb-ticket-pass-header">
            <div class="eb-ticket-pass-badges">
                <span class="eb-ticket-type-pill">
                    <x-icon name="ticket" size="sm" />
                    <span>{{ $ticketTypeLabel }}</span>
                </span>
                <span class="eb-badge {{ $admissionClass }} eb-badge-icon">
                    <x-icon :name="$admissionIcon" size="sm" />
                    <span>{{ $admissionLabel }}</span>
                </span>
            </div>

            <h3 class="eb-text-card-title eb-ticket-pass-title" style="margin: 4px 0 0;">{{ $event?->title ?? 'Event' }}</h3>

            @if($event)
                <div class="eb-ticket-meta-grid" style="margin-top: var(--space-2);">
                    <div class="eb-ticket-meta-cell">
                        <span class="eb-ticket-meta-label"><x-icon name="calendar" size="sm" /> Date &amp; Time</span>
                        <span class="eb-ticket-meta-val">{{ $event->date_str }}{{ $event->time_str ? ' · ' . $event->time_str : '' }}</span>
                    </div>
                    <div class="eb-ticket-meta-cell">
                        <span class="eb-ticket-meta-label"><x-icon name="map-pin" size="sm" /> Venue</span>
                        <span class="eb-ticket-meta-val">{{ $event->venue_name }}{{ $event->city ? ', ' . $event->city : '' }}</span>
                    </div>
                </div>
            @else
                <div style="margin-top: var(--space-1);">@include('components.status-indicator', ['status' => $order->status])</div>
            @endif
        </div>

        <div class="eb-ticket-card-tear" aria-hidden="true">
            <span class="eb-ticket-notch eb-ticket-notch-left"></span>
            <span class="eb-ticket-notch eb-ticket-notch-right"></span>
        </div>

        <dl class="eb-ticket-stub-facts">
            <div class="eb-ticket-serial-box">
                <dt class="eb-text-label">Ticket</dt>
                <dd class="eb-text-ticket-code eb-ticket-card-code" style="margin: 0;">{{ $order->ticket_code }}</dd>
            </div>
            <div>
                <dt class="eb-text-label">Total</dt>
                <dd class="eb-text-price" style="margin: 0; font-size: 1rem;">{{ \App\Support\Money::format($order->total_amount, $order->currency) }}</dd>
            </div>
            @if($itemCount !== null)
                <div>
                    <dt class="eb-text-label">Items</dt>
                    <dd style="margin: 0; font-weight: 700; color: var(--color-text-primary);">{{ $itemCount }}</dd>
                </div>
            @endif
            <div>
                <dt class="eb-text-label">Payment</dt>
                <dd style="margin: 0; font-size: 0.82rem; font-weight: 600; color: var(--color-text-secondary);">{{ \App\Support\PaymentStatusPresenter::methodLabel($order->method) }}</dd>
            </div>
        </dl>

        @if($href || trim((string) $slot) !== '')
            <div class="eb-ticket-card-actions">
                @if($href)
                    <a href="{{ $href }}" class="eb-btn eb-btn-primary eb-btn-sm">
                        <x-icon name="qr" size="sm" />
                        <span>View ticket</span>
                    </a>
                @endif
                {{ $slot }}
            </div>
        @endif
    </div>
</article>
