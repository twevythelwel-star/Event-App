{{--
    One ticket in the "My Tickets" collection: the event's artwork first, then what the ticket is.
    Props: order (App\Models\Order with event and items loaded),
           transfer (App\Models\TicketTransfer, for a ticket passed to someone else: shown, not opened).
    Never shows the admission QR code; the full ticket page does, and only while it is valid for entry.
--}}
@props(['order', 'transfer' => null])

@php
    $event = $order->event;
    $state = $transfer ? 'transferred' : \App\Support\TicketLifecycle::stateOf($order);
    $theme = \App\Support\TicketTheme::for($event);
    $tz = $event?->timezone ?: 'America/New_York';
    $tickets = $order->relationLoaded('items') ? $order->items->where('category', 'Tickets') : collect();
    $category = $tickets->isNotEmpty()
        ? $tickets->map(fn ($i) => ($i->quantity > 1 ? $i->quantity . ' × ' : '') . $i->name)->join(' · ')
        : ($order->relationLoaded('items') && $order->items->isNotEmpty() ? 'Food pre-order' : 'Event pass');
    [$stamp, $stampClass] = match ($state) {
        'past' => ['Keepsake', 'is-keepsake'],
        'active' => [$order->checked_in_at ? 'Checked in' : 'Ready for entry', 'is-active'],
        'upcoming' => ['Awaiting payment', 'is-pending'],
        'refunded' => ['Refunded', 'is-void'],
        'cancelled' => [$order->token_revoked_at ? 'Revoked' : \Illuminate\Support\Str::headline((string) $order->status), 'is-void'],
        'transferred' => ['Transferred', 'is-void'],
        default => ['Ticket', ''],
    };
    $when = $event ? collect([\App\Services\ReceiptService::eventDate($event), \App\Services\ReceiptService::eventTime($event)])->filter()->implode(' · ') : '';
    $where = $event ? collect([$event->venue_name, $event->city])->filter()->implode(', ') : '';
    $href = $transfer ? null : route('tickets.show', $order);
    $tag = $href ? 'a' : 'div';
    $cover = $event?->coverImage();
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'eb-collectible ' . $theme->cssClass() . ' ' . $stampClass]) }}
    style="{{ $theme->style() }}" data-state="{{ $state }}" data-theme="{{ $theme->key }}"
    @if($href) aria-label="{{ $stamp }}: {{ $event?->title ?? 'Your order' }}{{ $when ? ', ' . $when : '' }}" @endif>
    <div class="eb-collectible-art" style="view-transition-name: ticket-art-{{ $order->id }};">
        @if($cover)
            <x-responsive-image :image="$cover" alt="" sizes="(min-width: 1024px) 300px, (min-width: 640px) 45vw, 92vw"
                fit="cover" :style="'object-position:' . e($event->flyer_position ?: '50% 35%')" />
            <x-image-label :image="$cover" corner="top-right" />
        @endif
        <span class="eb-collectible-stamp">{{ $stamp }}</span>
    </div>
    <div class="eb-collectible-body">
        <span class="eb-ticket-theme-kicker">{{ $theme->label }}</span>
        <h3 class="eb-collectible-title">{{ $event?->title ?? 'Your order' }}</h3>
        @if($when)<p class="eb-collectible-meta"><x-icon name="calendar" size="sm" /> <span>{{ $when }}</span></p>@endif
        @if($where)<p class="eb-collectible-meta"><x-icon name="map-pin" size="sm" /> <span>{{ $where }}</span></p>@endif
        <div class="eb-collectible-foot">
            <span class="eb-collectible-category"><x-icon name="ticket" size="sm" /> {{ $category }}</span>
            @if($transfer)
                <span class="eb-help-text">To {{ \App\Services\CredentialMaskingService::maskEmail((string) $transfer->to_email) }}{{ $transfer->accepted_at ? ' · ' . $transfer->accepted_at->copy()->setTimezone($tz)->format('M j, Y') : '' }}</span>
            @else
                <span class="eb-collectible-code">{{ $order->ticket_code }}</span>
            @endif
        </div>
    </div>
</{{ $tag }}>
