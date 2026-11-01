@extends('layouts.app')

@section('title', 'Ticket ' . $order->ticket_code)
@section('page-title', 'Your ticket')

@section('content')
@php
    $currency = $order->currency;
    $status = $order->status;
    $tz = $event?->timezone ?: 'America/New_York';
    $void = in_array($status, ['refunded', 'cancelled', 'rejected', 'expired'], true);
    // Ticket issuance: the admission QR code is shown only once the payment is settled (paid, or paid and
    // partly refunded). Until then the customer gets the order reference to pay with, not a ticket.
    $settled = in_array($status, ['paid', 'partially_refunded'], true);
    $partlyPaid = $status === 'partially_paid';
    $checkedIn = !empty($order->checked_in_at);
    $balanceMinor = max(0, (int) $order->total_minor - (int) $order->amount_paid_minor);
    $awaitingDetails = !$void && !$settled && $order->method !== 'cash';
    $tickets = $order->items->where('category', 'Tickets');
    $food = $order->items->where('category', '!=', 'Tickets');
    $local = fn ($time) => $time ? $time->copy()->setTimezone($tz)->format('M j, Y g:i A T') : null;

    // What the customer should do next, in one line.
    [$nextTitle, $nextDetail, $nextTone] = match (true) {
        $status === 'refunded' => ['Refunded', 'This ticket was refunded and can’t be used for entry.', 'error'],
        $status === 'rejected' => ['Payment not accepted', $order->rejection_reason ?: 'The organizer couldn’t match your payment. Contact them to sort it out.', 'error'],
        in_array($status, ['cancelled', 'expired'], true) => ['Cancelled', 'This order was cancelled and can’t be used.', 'error'],
        $checkedIn => ['Checked in', 'This ticket was scanned at the gate. Enjoy the event.', 'success'],
        $settled => ['You’re all set', 'Payment confirmed. Show this QR code at the gate' . ($food->isNotEmpty() ? ' and when you collect your food.' : '.'), 'success'],
        $partlyPaid => ['Part of your payment received', 'The organizer has confirmed part of your payment. Your ticket is issued once the rest is paid.', 'warning'],
        $status === 'payment_submitted' || $status === 'under_verification' => ['Payment being checked', 'The organizer is checking your payment. Your ticket QR code appears here when they confirm it.', 'warning'],
        $order->method === 'cash' => ['Pay at pickup', 'Pay staff in cash when you arrive, quoting your order number. They’ll mark this ticket paid.', 'warning'],
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
                    <p class="eb-text-supporting" style="margin: 4px 0 0;">
                        {{ collect([\App\Services\ReceiptService::eventDate($event), \App\Services\ReceiptService::eventTime($event)])->filter()->implode(' · ') }}<br>
                        {{ \App\Services\ReceiptService::venue($event) }}
                    </p>
                @endif
            </div>

            @unless($void)
                <div class="eb-ticket-card-tear" style="margin: 0;"></div>
                <div style="text-align: center;">
                    @if($settled)
                        <div class="eb-ticket-qr-well">{!! \App\Support\QrCode::svg($order->secure_ticket_token, 220, 'QR code for ticket ' . $order->ticket_code) !!}</div>
                    @else
                        <p class="eb-text-supporting" style="margin: 0;">Your ticket QR code appears here once your payment is confirmed.</p>
                    @endif
                    <p style="margin: var(--space-3) 0 0;">
                        <span class="eb-text-label">{{ $settled ? 'Ticket code' : 'Order reference' }}</span><br>
                        <span class="eb-text-ticket-code eb-ticket-card-code" style="font-size: 1.25rem;">{{ $order->ticket_code }}</span>
                    </p>
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
            @if($settled || $partlyPaid || (int) $order->amount_paid_minor > 0)
                <div class="eb-stack" style="gap: 2px;" aria-label="Receipt">
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
        </div>
    </article>

    @if($awaitingDetails)
        <section class="eb-card eb-checkout-body" aria-labelledby="pay-heading">
            <h2 id="pay-heading" class="eb-text-section-title" style="margin: 0 0 var(--space-2);">How to pay</h2>
            @if($instructions)
                <div class="eb-checkout-instructions">{{ $instructions }}</div>
            @else
                <p class="eb-text-supporting" style="margin: 0;">The organizer will send you payment details.</p>
            @endif
            <p class="eb-text-supporting" style="margin: var(--space-3) 0;">Amount due: <strong>{{ \App\Support\Money::formatMinor($balanceMinor, $currency) }}</strong>. Use <strong>{{ $order->ticket_code }}</strong> as the payment description so it can be matched.</p>
            <p class="eb-help-text" style="margin: 0 0 var(--space-3);">A screenshot or reference is not proof that money arrived: your order stays pending until the organizer confirms the payment.</p>

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
