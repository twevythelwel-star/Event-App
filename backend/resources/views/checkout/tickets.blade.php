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
            <div class="eb-food-section-header">
                <div>
                    <span class="eb-food-section-kicker"><x-icon name="ticket" :size="13" /> Digital Event Passes</span>
                    <h2 id="tickets-heading" class="eb-text-section-title" style="margin: 2px 0 0;">Tickets</h2>
                </div>
                <span class="eb-badge eb-badge-neutral">{{ count($tiers) }} {{ \Illuminate\Support\Str::plural('tier', count($tiers)) }}</span>
            </div>

            @foreach($tiers as $tier)
                @php
                    $rawTier = collect(is_array($event->ticket_tiers) ? $event->ticket_tiers : [])->firstWhere('id', $tier['id']) ?? [];
                    $tierAccent = $rawTier['accentColor'] ?? 'indigo';
                    $tierCategory = $tier['category'] ?? ($rawTier['category'] ?? null);
                @endphp
                <div class="eb-checkout-row eb-tier-pass-row" data-accent="{{ $tierAccent }}" @unless($tier['on_sale']) aria-disabled="true" @endunless>
                    <div class="eb-tier-pass-info" style="min-width: 0; flex: 1;">
                        <div class="eb-cluster" style="gap: var(--space-2); align-items: center;">
                            <h3 class="eb-text-card-title" style="margin: 0;">{{ $tier['name'] }}</h3>
                            @if($tierCategory)
                                <span class="eb-badge eb-badge-purple">{{ $tierCategory }}</span>
                            @endif
                            @if($tier['early_bird'] ?? false)
                                <span class="eb-badge eb-badge-success">Early-bird price</span>
                            @endif
                        </div>

                        @if($tier['description'])
                            <p class="eb-text-supporting eb-clamp-2" style="margin: 4px 0 0;">{{ $tier['description'] }}</p>
                        @endif

                        <div class="eb-tier-price-row" style="margin: 8px 0 0; display: flex; flex-wrap: wrap; align-items: center; gap: 8px;">
                            <span class="eb-food-price-pill">
                                <span class="eb-text-price">{{ \App\Support\Money::formatMinor($tier['price_minor'], $tier['currency']) }}</span>
                            </span>
                            <x-tier-availability :tier="$tier" />
                        </div>

                        {{-- Smarter ticketing, said plainly: price steps, group prices, bundles, releases, holds. --}}
                        @if(!empty($tier['price_step']) && !$tier['price_step']['is_last'])
                            <p class="eb-help-text" style="margin: 4px 0 0;">{{ $tier['price_step']['left_at_price'] }} left at this price; the price goes up after that.</p>
                        @endif
                        @foreach($tier['group_discounts'] ?? [] as $rule)
                            <p class="eb-help-text" style="margin: 4px 0 0;">Buy {{ $rule['min_quantity'] }} or more: {{ $rule['percent'] }}% off each.</p>
                        @endforeach
                        @if(!empty($tier['bundle_items']))
                            <p class="eb-help-text" style="margin: 4px 0 0;">Includes food, collected with your ticket's QR code.</p>
                        @endif
                        @if(!empty($tier['next_release']) && $tier['next_release']['opens'])
                            <p class="eb-help-text" style="margin: 4px 0 0;">{{ $tier['next_release']['quantity'] }} more released {{ \Illuminate\Support\Carbon::createFromTimestamp($tier['next_release']['opens'])->setTimezone($event->timezone ?: config('app.timezone'))->format('M j, g:i A T') }}.</p>
                        @endif
                        @if(isset($holds[$tier['id']]))
                            <p class="eb-help-text" style="margin: 4px 0 0; color: var(--color-accent);">
                                {{ $holds[$tier['id']]['quantity'] }} held for you until {{ $holds[$tier['id']]['expires_at']->copy()->setTimezone($event->timezone ?: config('app.timezone'))->format('g:i A') }}{{ $holds[$tier['id']]['source'] === 'waitlist' ? ' (from the waitlist)' : '' }}.</p>
                        @endif
                        @if(($tier['sold_out'] ?? false) && $event->waitlist_enabled && $tier['sale_window'] === 'open' && !isset($holds[$tier['id']]))
                            @if($entry = ($waitlist[$tier['id']] ?? null))
                                <p class="eb-help-text" style="margin: 4px 0 0;">You're on the waitlist for {{ $entry->quantity }}.
                                    <button type="submit" form="leave-wl-{{ $entry->id }}" class="eb-btn eb-btn-ghost eb-btn-sm">Leave</button></p>
                            @else
                                <div class="eb-cluster" style="margin-top: 6px;">
                                    <label class="sr-only" for="wl-qty-{{ $tier['id'] }}">Tickets wanted</label>
                                    <input type="number" min="1" max="{{ $tier['max_per_order'] ?: 10 }}" value="1" id="wl-qty-{{ $tier['id'] }}" form="join-wl-{{ $tier['id'] }}" name="quantity" class="eb-input" style="width: 5rem;">
                                    <button type="submit" form="join-wl-{{ $tier['id'] }}" class="eb-btn eb-btn-secondary eb-btn-sm">Join the waitlist</button>
                                </div>
                            @endif
                        @endif
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

        @if($seatSelection ?? false)
            @include('checkout._seat-picker')
        @endif

        <div class="eb-checkout-bar">
            <p class="eb-text-supporting" style="margin: 0;">Prices are checked again before you pay.</p>
            <button type="submit" class="eb-btn eb-btn-primary">Continue</button>
        </div>
    </form>
    {{-- Waitlist forms sit outside the ticket form (no nested forms); their buttons point here with form="…". --}}
    @foreach($tiers as $tier)
        <form id="join-wl-{{ $tier['id'] }}" method="POST" action="{{ route('checkout.waitlist.join', $event) }}" hidden>@csrf<input type="hidden" name="tier_id" value="{{ $tier['id'] }}"></form>
    @endforeach
    @foreach(($waitlist ?? collect()) as $entry)
        <form id="leave-wl-{{ $entry->id }}" method="POST" action="{{ route('checkout.waitlist.leave', [$event, $entry->id]) }}" hidden>@csrf @method('DELETE')</form>
    @endforeach
    <p class="eb-help-text" style="text-align: center;">Tickets you choose are held for you for {{ $holdMinutes ?? 10 }} minutes while you finish your order.</p>
</div>
@endsection
