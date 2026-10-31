@extends('layouts.app')

@section('title', 'Ticket ' . $order->ticket_code)
@section('page-title', 'Your ticket')

@section('content')
@php
    $currency = $order->currency;
    $status = $order->status;
    $void = in_array($status, ['refunded', 'cancelled', 'rejected', 'expired'], true);
    $paid = in_array($status, ['paid', 'partially_paid'], true);
    $checkedIn = !empty($order->checked_in_at);
    $awaitingDetails = !$void && !$paid && $order->method !== 'cash';
    $tickets = $order->items->where('category', 'Tickets');
    $food = $order->items->where('category', '!=', 'Tickets');

    // What the customer should do next, in one line.
    [$nextTitle, $nextDetail, $nextTone] = match (true) {
        $status === 'refunded' => ['Refunded', 'This ticket was refunded and can’t be used for entry.', 'error'],
        $status === 'rejected' => ['Payment not accepted', $order->rejection_reason ?: 'The organiser couldn’t match your payment. Contact them to sort it out.', 'error'],
        in_array($status, ['cancelled', 'expired'], true) => ['Cancelled', 'This order was cancelled and can’t be used.', 'error'],
        $checkedIn => ['Checked in', 'This ticket was scanned at the gate. Enjoy the event.', 'success'],
        $paid => ['You’re all set', 'Show this QR code at the gate' . ($food->isNotEmpty() ? ' and when you collect your food.' : '.'), 'success'],
        $status === 'payment_submitted' || $status === 'under_verification' => ['Payment being checked', 'The organiser is checking your payment. Your ticket turns Verified when they confirm it.', 'warning'],
        $order->method === 'cash' => ['Pay at pickup', 'Pay staff in cash when you arrive. They’ll mark this ticket paid.', 'warning'],
        default => ['Waiting for your payment', 'Send the transfer using the details below, then add your reference.', 'warning'],
    };
@endphp
<div class="eb-checkout">
    <x-alert :type="$nextTone" :title="$nextTitle">{{ $nextDetail }}</x-alert>

    <article class="eb-ticket-card" aria-labelledby="ticket-title">
        @if($event)
            <div class="eb-ticket-card-head">
                <img src="{{ $event->display_image_url }}" alt="">
                <div class="eb-event-card-scrim"></div>
                <div class="eb-card-chip">@include('components.status-indicator', ['status' => $status])</div>
            </div>
        @endif
        <div class="eb-ticket-card-body">
            <div>
                <h1 id="ticket-title" class="eb-text-page-title" style="margin: 0;">{{ $event?->title ?? 'Your order' }}</h1>
                @if($event)
                    <p class="eb-text-supporting" style="margin: 4px 0 0;">{{ $event->date_str }} · {{ $event->time_str }}<br>{{ $event->venue_name }}</p>
                @endif
            </div>

            @unless($void)
                <div class="eb-ticket-card-tear" style="margin: 0;"></div>
                <div style="text-align: center;">
                    <div class="eb-ticket-qr-well">{!! \App\Support\QrCode::svg($order->secure_ticket_token, 220, 'QR code for ticket ' . $order->ticket_code) !!}</div>
                    <p style="margin: var(--space-3) 0 0;">
                        <span class="eb-text-label">Ticket code</span><br>
                        <span class="eb-text-ticket-code eb-ticket-card-code" style="font-size: 1.25rem;">{{ $order->ticket_code }}</span>
                    </p>
                    @unless($paid)
                        <p class="eb-help-text" style="margin: var(--space-2) 0 0;">Gate staff can admit you once this ticket is Verified.</p>
                    @endunless
                </div>
            @endunless

            <div class="eb-ticket-card-tear" style="margin: 0;"></div>
            <x-price-summary
                :lines="$order->items->map(fn ($item) => ['label' => $item->quantity . ' × ' . $item->name, 'amount_minor' => (int) $item->line_total_minor])->all()"
                :charges="$order->charges->map(fn ($c) => ['kind' => $c->kind, 'label' => $c->label, 'amount_minor' => (int) $c->amount_minor])->all()"
                :subtotal-minor="(int) $order->subtotal_minor" :total-minor="(int) $order->total_minor" :currency="$currency" />
            @if((int) $order->refunded_minor > 0)
                <p class="eb-help-text" style="margin: 0;">Refunded: {{ \App\Support\Money::formatMinor((int) $order->refunded_minor, $currency) }}</p>
            @endif
            <p class="eb-help-text" style="margin: 0;">Order {{ $order->order_number }} · placed {{ $order->created_at?->format('M j, Y g:i A') }}</p>
        </div>
    </article>

    @if($awaitingDetails)
        <section class="eb-card eb-checkout-body" aria-labelledby="pay-heading">
            <h2 id="pay-heading" class="eb-text-section-title" style="margin: 0 0 var(--space-2);">How to pay</h2>
            @if($instructions)
                <div class="eb-checkout-instructions">{{ $instructions }}</div>
            @else
                <p class="eb-text-supporting" style="margin: 0;">The organiser will send you payment details.</p>
            @endif
            <p class="eb-text-supporting" style="margin: var(--space-3) 0;">Amount: <strong>{{ \App\Support\Money::format($order->total_amount, $currency) }}</strong>. Use <strong>{{ $order->ticket_code }}</strong> as the payment description so it can be matched.</p>

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
