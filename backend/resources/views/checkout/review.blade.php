@extends('layouts.app')

@section('title', 'Checkout — ' . $event->title)
@section('page-title', 'Checkout')

@section('content')
@php
    $currency = $quote['currency'];
    $lines = collect($quote['lines'])->map(fn ($line) => [
        'label' => $line['quantity'] . ' × ' . $line['label'] . ($line['detail'] ? ' (' . $line['detail'] . ')' : ''),
        'amount' => $line['amount'],
    ])->all();
    $selectedMethod = old('method', count($methods) === 1 ? $methods[0]['value'] : null);
@endphp
<div class="eb-checkout">
    @include('checkout._event-header')
    @include('checkout._steps', ['current' => 'Checkout'])

    @if($quote['problems'] !== [])
        <x-alert type="warning" title="Please update your order">
            <ul style="margin: 4px 0 0; padding-left: 18px;">
                @foreach($quote['problems'] as $problem)<li>{{ $problem }}</li>@endforeach
            </ul>
            <a href="{{ route($backRoute, $event) }}" style="color: inherit; font-weight: 700;">Change your order</a>
        </x-alert>
    @endif

    <section class="eb-card eb-checkout-body" aria-labelledby="summary-heading">
        <h2 id="summary-heading" class="eb-text-section-title" style="margin: 0 0 var(--space-3);">Your order</h2>
        @if($quote['empty'])
            <x-empty-state icon="tickets" title="Nothing in your order" description="Choose tickets or food to continue."
                :action-href="route($backRoute, $event)" action-label="Choose items" />
        @else
            <x-price-summary :lines="$lines" :fee="$quote['fee']" :total="$quote['total']" :currency="$currency"
                note="Prices come from the organiser and are checked again when you place the order." />
            <a href="{{ route($backRoute, $event) }}" class="eb-text-label" style="display: inline-block; margin-top: var(--space-3);">Change order</a>
        @endif
    </section>

    @unless($quote['empty'])
        <form method="POST" action="{{ route('checkout.place', $event) }}" class="eb-card eb-checkout-body" novalidate>
            @csrf
            <input type="hidden" name="quoted_total" value="{{ number_format($quote['total'], 2, '.', '') }}">

            <h2 class="eb-text-section-title" style="margin: 0 0 var(--space-3);">Your details</h2>
            <x-form-field name="customer_phone" type="tel" label="Phone or WhatsApp"
                :value="old('customer_phone', auth()->user()->phone)" :required="true" placeholder="+1 305 555 0142"
                help="We text your ticket here." :error="$errors->first('customer_phone')" />

            <fieldset style="border: 0; padding: 0; margin: var(--space-5) 0 0;" aria-describedby="method-help">
                <legend class="eb-text-section-title" style="margin-bottom: var(--space-3);">Payment method</legend>
                @if($methods === [])
                    <x-alert type="error">The organiser hasn't set up a way to pay yet, so orders can't be placed. Please check back later.</x-alert>
                @else
                    <div class="eb-stack" style="gap: var(--space-2);">
                        @foreach($methods as $method)
                            <x-payment-method :value="$method['value']" :label="$method['label']" :description="$method['description']"
                                :icon="$method['icon']" :checked="$selectedMethod === $method['value']" required />
                        @endforeach
                    </div>
                    <p id="method-help" class="eb-help-text" style="margin: var(--space-2) 0 0;">Your ticket shows Pending until the organiser confirms your payment.</p>
                    @error('method')<p class="eb-field-error" role="alert">{{ $message }}</p>@enderror
                @endif
            </fieldset>

            @if(collect($methods)->contains(fn ($m) => $m['value'] !== 'cash'))
                <details class="eb-checkout-details" @if(old('payment_reference')) open @endif>
                    <summary>Already sent a transfer? Add the reference</summary>
                    <div class="eb-stack" style="gap: var(--space-3); margin-top: var(--space-3);">
                        <x-form-field name="payment_reference" label="Transfer reference (optional)" :value="old('payment_reference')"
                            placeholder="e.g. NCB-109284" :error="$errors->first('payment_reference')" />
                        <x-form-field name="sender_name" label="Name on the paying account (optional)" :value="old('sender_name')"
                            :error="$errors->first('sender_name')" />
                    </div>
                </details>
            @endif

            <div class="eb-checkout-bar">
                <span><span class="eb-text-label" style="display: block;">Total</span><span class="eb-text-price">{{ \App\Support\Money::format($quote['total'], $currency) }}</span></span>
                <button type="submit" class="eb-btn eb-btn-primary" data-prevent-double-submit="true"
                        @disabled($methods === [] || $quote['problems'] !== [])>Place order</button>
            </div>
        </form>
    @endunless
</div>
@endsection
