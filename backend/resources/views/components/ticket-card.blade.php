{{--
    A customer's ticket/order summary card (wallet listings, confirmation page).
    Props: order (App\Models\Order, with its event loaded or loadable),
           href (link to the full ticket, optional).
    The QR code itself belongs on the full ticket page, not in listings.
--}}
@props([
    'order',
    'href' => null,
])

@php
    $event = $order->event;
    $itemCount = $order->relationLoaded('items') ? (int) $order->items->sum('quantity') : null;
@endphp

<article {{ $attributes->merge(['class' => 'eb-ticket-card']) }}>
    @if($event)
        <div class="eb-ticket-card-head">
            <img src="{{ $event->display_image_url }}" alt="" loading="lazy" decoding="async">
            <div class="eb-event-card-scrim"></div>
            <div class="eb-card-chip">@include('components.status-indicator', ['status' => $order->status])</div>
        </div>
    @endif
    <div class="eb-ticket-card-body">
        <div>
            <h3 class="eb-text-card-title" style="margin: 0;">{{ $event?->title ?? 'Event' }}</h3>
            @if($event)
                <div class="eb-card-meta" style="margin-top: var(--space-1);">
                    <span class="eb-card-meta-row"><x-icon name="calendar" size="sm" /><span>{{ $event->date_str }} · {{ $event->time_str }}</span></span>
                    <span class="eb-card-meta-row"><x-icon name="events" size="sm" /><span>{{ $event->venue_name }}</span></span>
                </div>
            @else
                <div style="margin-top: var(--space-1);">@include('components.status-indicator', ['status' => $order->status])</div>
            @endif
        </div>
        <div class="eb-ticket-card-tear" style="margin: 0;"></div>
        <dl style="display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: var(--space-3); margin: 0;">
            <div><dt class="eb-text-label">Ticket</dt><dd class="eb-text-ticket-code eb-ticket-card-code" style="margin: 0;">{{ $order->ticket_code }}</dd></div>
            <div><dt class="eb-text-label">Total</dt><dd class="eb-text-price" style="margin: 0; font-size: 1rem;">{{ \App\Support\Money::format($order->total_amount, $order->currency) }}</dd></div>
            @if($itemCount !== null)
                <div><dt class="eb-text-label">Items</dt><dd style="margin: 0; font-weight: 600;">{{ $itemCount }}</dd></div>
            @endif
        </dl>
        @if($href || trim((string) $slot) !== '')
            <div class="eb-ticket-card-actions">
                @if($href)<a href="{{ $href }}" class="eb-btn eb-btn-primary eb-btn-sm">View ticket</a>@endif
                {{ $slot }}
            </div>
        @endif
    </div>
</article>
