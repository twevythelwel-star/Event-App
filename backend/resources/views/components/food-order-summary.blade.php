{{--
    Reusable Order Summary panel with 3D order summary graphics for food ordering.
    Props:
      event              Event model (for packaging_fee_minor, food_service_fee_minor, food_fulfillment)
      currency           ISO currency code
      packagingFeeMinor  optional override in minor units
      serviceFeeMinor    optional override in minor units
      deliveryAvailable  bool
      method             'pickup' | 'delivery'
--}}
@props([
    'event' => null,
    'currency' => null,
    'packagingFeeMinor' => null,
    'serviceFeeMinor' => null,
    'deliveryAvailable' => null,
    'method' => 'pickup',
])

@php
    $currency = $currency ?: ($event?->currency ?: \App\Support\Currency::defaultCode());
    $fmt = fn (int $m) => \App\Support\Money::formatMinor($m, $currency);
    $pkgFee = $packagingFeeMinor !== null ? (int) $packagingFeeMinor : (int) ($event?->packaging_fee_minor ?? 0);
    $svcFee = $serviceFeeMinor !== null ? (int) $serviceFeeMinor : (int) ($event?->food_service_fee_minor ?? 0);
    $hasDelivery = $deliveryAvailable !== null ? (bool) $deliveryAvailable : ($event?->food_fulfillment === 'pickup_and_delivery');
    $illustration = \App\Support\VisualAssets::illustration('food-order');
@endphp

<aside class="eb-panel eb-food-summary" aria-labelledby="food-summary-heading" {{ $attributes }}>
    {{-- Order summary graphic banner --}}
    <div class="eb-food-summary-hero">
        <div class="eb-food-summary-graphic" aria-hidden="true">
            @if($illustration)
                <img src="{{ $illustration['url'] }}" alt="" width="{{ $illustration['width'] ?? 80 }}" height="{{ $illustration['height'] ?? 60 }}" class="eb-food-summary-art" loading="lazy" decoding="async">
            @else
                <span class="eb-food-summary-icon-orb"><x-icon name="utensils" :size="24" /></span>
            @endif
        </div>
        <div class="eb-food-summary-title-group">
            <div class="eb-cluster" style="justify-content: space-between; align-items: center; gap: var(--space-2); width: 100%;">
                <h2 id="food-summary-heading" class="eb-text-card-title" style="margin: 0;">Your food</h2>
                <span class="eb-badge eb-badge-brand eb-food-summary-count" data-summary-count>0 items</span>
            </div>
            <p class="eb-food-summary-fulfillment" data-summary-method>
                <x-icon name="clock" :size="13" />
                <span data-summary-method-label>{{ $method === 'delivery' && $hasDelivery ? 'Table / area delivery' : 'Event pickup' }}</span>
            </p>
        </div>
    </div>

    {{-- Selected food lines --}}
    <ul class="eb-food-summary-lines" data-summary-lines aria-live="polite">
        <li class="eb-food-summary-empty eb-text-supporting">Nothing chosen yet.</li>
    </ul>

    {{-- Perforated receipt divider --}}
    <div class="eb-food-summary-divider" aria-hidden="true"></div>

    {{-- Totals breakdown --}}
    <dl class="eb-food-summary-totals">
        <div>
            <dt>Food</dt>
            <dd data-summary-items>{{ $fmt(0) }}</dd>
        </div>
        @if($pkgFee > 0)
            <div>
                <dt>Packaging fee (per order)</dt>
                <dd data-summary-fee data-fee-minor="{{ $pkgFee }}">{{ $fmt($pkgFee) }}</dd>
            </div>
        @endif
        @if($svcFee > 0)
            <div>
                <dt>Food service fee (per order)</dt>
                <dd data-summary-fee data-fee-minor="{{ $svcFee }}">{{ $fmt($svcFee) }}</dd>
            </div>
        @endif
        <div class="eb-food-summary-total">
            <dt>Estimated food total</dt>
            <dd data-summary-total>{{ $fmt(0) }}</dd>
        </div>
    </dl>

    <div class="eb-food-summary-footer">
        <x-icon name="shield" :size="14" />
        <p class="eb-help-text" style="margin: 0;">Any taxes and platform fees are added on the next page, where you see the full total before you pay.</p>
    </div>
</aside>

