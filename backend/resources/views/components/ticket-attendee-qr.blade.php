{{--
    One guest's admission ticket: their own single-use QR code in the event's approved design, and their ticket
    details (holder, type, number, payment and admission status). The QR artwork carries its own quiet zone and frame;
    nothing is drawn over it. Generation and verification live in App\Services (this component only displays).

    Props: attendee (AttendeeTicket), order (Order), event (?Event), of (guests on the order), tierName (string),
    ticketQr (TicketQrService), canReissue (bool).
--}}
@props(['attendee', 'order', 'event' => null, 'of' => 1, 'tierName' => 'Admission', 'ticketQr', 'canReissue' => false])
@php
    $tz = $event?->timezone ?: 'America/New_York';
    $at = fn ($time) => $time ? $time->copy()->setTimezone($tz)->format('M j, g:i A') : '';
    $art = $attendee->checked_in_at ? null : $ticketQr->svg($attendee, 'QR code for ticket ' . $attendee->ticket_number . ', guest ' . $attendee->sequence . ' of ' . $of);
    $status = \App\Support\TicketStatuses::for($order, $attendee, $event);
    $paymentLabels = ['unpaid' => 'Not paid', 'submitted' => 'Being checked', 'partially_paid' => 'Part paid', 'paid' => 'Paid',
        'partially_refunded' => 'Part refunded', 'refunded' => 'Refunded', 'charged_back' => 'Charged back', 'not_collected' => 'Not collected'];
@endphp
<figure class="eb-attendee-qr" data-attendee-ticket="{{ $attendee->ticket_number }}" data-qr-status="{{ $attendee->qr_status }}" data-ticket-status="{{ $status['lifecycle'] }}">
    <figcaption class="eb-attendee-qr-head">
        <span class="eb-text-label">Guest {{ $attendee->sequence }} of {{ $of }} · {{ $tierName }}</span>
        @if($attendee->checked_in_at)
            <span class="eb-badge eb-badge-success">Admitted {{ $at($attendee->checked_in_at) }}</span>
        @endif
    </figcaption>

    @if($attendee->checked_in_at)
        <p class="eb-text-supporting eb-attendee-qr-used">This guest is in. Their QR code can’t be used again.</p>
    @elseif($art)
        <div class="eb-attendee-qr-art" data-qr-type="admission">{!! $art !!}</div>
    @else
        <div class="eb-attendee-qr-pending" role="status">
            <p class="eb-text-supporting" style="margin: 0 0 var(--space-2);">This guest’s QR code is still being prepared. Your ticket is safe; nothing is charged or issued twice.</p>
            <form method="POST" action="{{ route('tickets.qr.retry', $order) }}" style="margin: 0;">
                @csrf
                <button type="submit" class="eb-btn eb-btn-secondary eb-btn-sm" data-loading-text="Preparing…">Try again</button>
            </form>
        </div>
    @endif

    <dl class="eb-attendee-qr-facts">
        <div><dt>Ticket holder</dt><dd>{{ $order->customer_name ?: 'Guest' }}</dd></div>
        <div><dt>Ticket type</dt><dd>{{ $tierName }}</dd></div>
        <div><dt>Ticket number</dt><dd class="eb-text-ticket-code eb-attendee-qr-number">{{ $attendee->ticket_number }}</dd></div>
        <div><dt>Payment</dt><dd data-payment-status="{{ $status['payment'] }}">{{ $paymentLabels[$status['payment']] ?? ucfirst(str_replace('_', ' ', $status['payment'])) }}</dd></div>
        <div><dt>Admission</dt><dd data-admission-status="{{ $status['admission'] }}">{{ $status['admission'] === 'admitted' ? 'Admitted' : 'Not yet admitted' }}</dd></div>
    </dl>

    @if($canReissue && !$attendee->checked_in_at)
        <details class="eb-attendee-qr-reissue" data-no-print>
            <summary class="eb-help-text">Replace this guest’s QR code</summary>
            <form method="POST" action="{{ route('tickets.guests.reissue', [$order, $attendee]) }}" class="eb-stack" style="gap: var(--space-2); margin: var(--space-2) 0 0;">
                @csrf
                <label class="eb-help-text" for="reissue-reason-{{ $attendee->id }}">Why? The old code stops working at once.</label>
                <input class="eb-input" id="reissue-reason-{{ $attendee->id }}" name="reason" required minlength="3" maxlength="200" placeholder="Screenshot was shared">
                <button type="submit" class="eb-btn eb-btn-ghost eb-btn-sm" data-loading-text="Replacing…">Replace QR code</button>
            </form>
        </details>
    @endif
</figure>
