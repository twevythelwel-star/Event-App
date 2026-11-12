{{--
    A past event's ticket, kept as a keepsake (checkout/ticket.blade.php). Shows what the ticket was and whether it was
    used, never a QR code. The share button carries only the event's public details for the decorative image:
    no QR token, ticket code, order number, holder name or payment information (public/js/ticket-collection.js).
--}}
@php
    $shareWhen = collect([\App\Services\ReceiptService::eventDate($event), \App\Services\ReceiptService::eventTime($event)])->filter()->implode(' · ');
    $shareWhere = collect([$event->venue_name, $event->city, $event->state_code])->filter()->implode(', ');
    $shareCover = $event->coverImage();
@endphp
<div class="eb-keepsake-panel">
    <span class="eb-keepsake-stamp">Keepsake</span>
    <p class="eb-text-supporting" style="margin: 0;">
        This event is over. Your ticket stays here to remember it by. It is <strong>not valid for admission</strong>, and showing it at a gate will not admit anyone.
    </p>
    <dl class="eb-keepsake-facts">
        <div><dt>Ticket</dt><dd>{{ $ticketTypeSummary }}</dd></div>
        <div><dt>Ticket number</dt><dd class="eb-keepsake-code">{{ $order->ticket_code }}</dd></div>
        <div><dt>Purchase reference</dt><dd>Order {{ $order->order_number }}@if($order->created_at)<br><span class="eb-help-text">Bought {{ $local($order->created_at) }}</span>@endif</dd></div>
        <div><dt>Admission</dt><dd>{{ $checkedIn ? 'Admitted ' . $local($order->checked_in_at) : 'Not scanned at the gate' }}</dd></div>
    </dl>
    <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" data-no-print data-keepsake-share
            data-title="{{ $event->title }}"
            data-kicker="{{ $theme->label }} · Keepsake"
            data-when="{{ $shareWhen }}"
            data-where="{{ $shareWhere }}"
            data-category="{{ $ticketTypeSummary }}"
            data-stamp="Keepsake"
            data-keepsake="1"
            data-accent="{{ $theme->accent }}"
            data-accent2="{{ $theme->accent2 }}"
            data-art="{{ $shareCover->src }}"
            data-illustrative="{{ $shareCover->label ?? '' }}"
            data-logo="{{ asset('images/brand/eventbite-logo-600.png') }}"
            data-filename="{{ \Illuminate\Support\Str::slug((string) $event->title) ?: 'event' }}-keepsake.png">
        <x-icon name="sparkles" size="sm" /> <span data-confirm-label>Share keepsake image</span>
    </button>
    <p class="eb-help-text" style="margin: 0; font-size: 0.78rem;">The image shows the event's artwork, name, date and place only: no ticket number, QR code, name or payment details.</p>
</div>
