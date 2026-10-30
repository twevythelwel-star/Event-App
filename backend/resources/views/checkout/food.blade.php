@extends('layouts.app')

@section('title', 'Food — ' . $event->title)
@section('page-title', 'Add food')

@section('content')
<div class="eb-checkout">
    @include('checkout._event-header')
    @include('checkout._steps', ['current' => 'Food'])

    <form method="POST" action="{{ route('checkout.food.save', $event) }}" class="eb-checkout-body">
        @csrf
        <section aria-labelledby="food-heading" class="eb-stack" style="gap: var(--space-3);">
            <div>
                <h2 id="food-heading" class="eb-text-section-title" style="margin: 0;">Food &amp; drinks</h2>
                <p class="eb-text-supporting" style="margin: 2px 0 0;">Optional. Collect at the event with your ticket code.</p>
            </div>

            @foreach($menu as $item)
                @php
                    $saved = $cart['items'][$item->id] ?? [];
                    $soldOut = $item->available_stock !== null && (int) $item->available_stock <= 0;
                    $max = $item->available_stock === null ? 20 : max(0, min(20, (int) $item->available_stock));
                    $groups = $item->pricing_type === 'customizable' && is_array($item->custom_option_groups) ? $item->custom_option_groups : [];
                @endphp
                <x-food-card :item="$item" :currency="$currency" layout="row">
                    <div class="eb-stack" style="gap: var(--space-2); align-items: flex-end; width: 100%;">
                        @foreach($groups as $group)
                            @php $groupId = $group['id'] ?? ('g' . $loop->index); @endphp
                            <label class="eb-text-label" style="width: 100%;">
                                {{ $group['title'] ?? 'Option' }}
                                <select name="items[{{ $item->id }}][choices][{{ $groupId }}]" class="eb-select eb-input" style="width: 100%; margin-top: 2px;" @disabled($soldOut)>
                                    @foreach($group['choices'] ?? [] as $index => $choice)
                                        @php $extra = $currency === 'USD' ? ($choice['extraPriceUSD'] ?? 0) : ($choice['extraPriceJMD'] ?? 0); @endphp
                                        <option value="{{ $index }}" @selected((int) old("items.{$item->id}.choices.{$groupId}", $saved['choices'][$groupId] ?? 0) === $index)>
                                            {{ $choice['label'] ?? 'Choice' }}{{ (float) $extra > 0 ? ' (+' . \App\Support\Money::format($extra, $currency) . ')' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        @endforeach
                        @if($item->pricing_type === 'custom_amount')
                            <label class="eb-text-label" style="width: 100%;">
                                Your amount ({{ $currency }})
                                <input type="number" name="items[{{ $item->id }}][amount]" min="0" step="0.01" inputmode="decimal" class="eb-input"
                                       value="{{ old("items.{$item->id}.amount", $saved['amount'] ?? '') }}" style="width: 100%; margin-top: 2px;" @disabled($soldOut)>
                            </label>
                        @endif
                        <x-quantity-control
                            :name="'items[' . $item->id . '][quantity]'"
                            :value="old('items.' . $item->id . '.quantity', $saved['quantity'] ?? 0)"
                            :max="$max"
                            :disabled="$soldOut"
                            :label="'Quantity of ' . $item->name" />
                    </div>
                </x-food-card>
            @endforeach
            @if($errors->any())<x-alert type="error">{{ $errors->first() }}</x-alert>@endif
        </section>

        <div class="eb-checkout-bar">
            <a href="{{ route('checkout.tickets', $event) }}" class="eb-btn eb-btn-secondary">Back</a>
            <button type="submit" class="eb-btn eb-btn-primary">Continue to checkout</button>
        </div>
    </form>
</div>
@endsection
