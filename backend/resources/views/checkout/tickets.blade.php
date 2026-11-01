@extends('layouts.app')

@section('title', 'Tickets — ' . $event->title)
@section('page-title', 'Choose tickets')

@section('content')
<div class="eb-checkout">
    @include('checkout._event-header')
    @include('checkout._steps', ['current' => 'Tickets'])

    <form method="POST" action="{{ route('checkout.tickets.save', $event) }}" class="eb-checkout-body">
        @csrf
        <section aria-labelledby="tickets-heading" class="eb-stack" style="gap: var(--space-3);">
            <h2 id="tickets-heading" class="eb-text-section-title" style="margin: 0;">Tickets</h2>
            @foreach($tiers as $tier)
                <div class="eb-checkout-row" @unless($tier['on_sale']) aria-disabled="true" @endunless>
                    <div style="min-width: 0;">
                        <h3 class="eb-text-card-title" style="margin: 0;">{{ $tier['name'] }}</h3>
                        @if($tier['description'])<p class="eb-text-supporting eb-clamp-2" style="margin: 2px 0 0;">{{ $tier['description'] }}</p>@endif
                        <p style="margin: 4px 0 0;">
                            <span class="eb-text-price">{{ \App\Support\Money::formatMinor($tier['price_minor'], $tier['currency']) }}</span>
                            <x-tier-availability :tier="$tier" />
                        </p>
                    </div>
                    <x-quantity-control
                        :name="'tiers[' . $tier['id'] . ']'"
                        :value="old('tiers.' . $tier['id'], $cart['tiers'][$tier['id']] ?? 0)"
                        :max="$tier['max']"
                        :disabled="!$tier['on_sale']"
                        :label="'Number of ' . $tier['name'] . ' tickets'" />
                </div>
            @endforeach
            @error('tiers.*')<x-alert type="error">{{ $message }}</x-alert>@enderror
        </section>

        <div class="eb-checkout-bar">
            <p class="eb-text-supporting" style="margin: 0;">Prices are checked again before you pay.</p>
            <button type="submit" class="eb-btn eb-btn-primary">Continue</button>
        </div>
    </form>
</div>
@endsection
