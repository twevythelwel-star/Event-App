{{--
    Order price breakdown. Shows amounts the pricing services already calculated; it never adds anything up.
    Props:
      lines        list of ['label' => string, 'amount_minor' => int] (or legacy 'amount' in major units)
      charges      list of ['kind' => platform_fee|host_fee|tax|discount, 'label' => string, 'amount_minor' => int]
      subtotal     item subtotal in minor units (optional; shown when there are charges)
      total        total in minor units (totalMinor) or, legacy, major units (total)
      currency     ISO code; formatting comes from config/money.php
      note         optional text under the total
      showGraphic  optional bool to render the order summary graphic header
      graphicKey   optional VisualAssets::illustration key (default 'order-fulfillment')
    Legacy: fee (major units) shows one "Packaging fee" line when no charges are given.
--}}
@props([
    'lines' => [],
    'charges' => null,
    'subtotalMinor' => null,
    'totalMinor' => null,
    'fee' => null,
    'total' => null,
    'currency' => null,
    'note' => null,
    'showGraphic' => false,
    'graphicKey' => 'food-order',
])

@php
    $currency = $currency ?: \App\Support\Currency::defaultCode();
    $fmt = fn ($line) => isset($line['amount_minor'])
        ? \App\Support\Money::formatMinor((int) $line['amount_minor'], $currency)
        : \App\Support\Money::format($line['amount'] ?? 0, $currency);
    $kindOrder = ['discount' => 0, 'host_fee' => 1, 'platform_fee' => 2, 'tax' => 3];
    $charges = collect($charges ?? [])->sortBy(fn ($c) => $kindOrder[$c['kind']] ?? 9)->values();
    $hasTax = $charges->contains(fn ($c) => $c['kind'] === 'tax');
    $illustration = $showGraphic ? \App\Support\VisualAssets::illustration($graphicKey) : null;
@endphp

<div {{ $attributes->merge(['class' => 'eb-price-summary-wrap']) }}>
    @if($showGraphic && $illustration)
        <div class="eb-price-summary-banner" aria-hidden="true">
            <img src="{{ $illustration['url'] }}" alt="" width="{{ $illustration['width'] ?? 72 }}" height="{{ $illustration['height'] ?? 54 }}" class="eb-price-summary-art" loading="lazy" decoding="async">
            <div class="eb-price-summary-banner-copy">
                <span class="eb-price-summary-banner-kicker">Verified EventBite Checkout</span>
                <span class="eb-price-summary-banner-sub">{{ count($lines) }} {{ \Illuminate\Support\Str::plural('line item', count($lines)) }} · {{ $currency }}</span>
            </div>
        </div>
    @endif
    <dl class="eb-price-summary">
        @foreach($lines as $line)
            <div class="eb-price-row">
                <dt>{{ $line['label'] }}</dt>
                <dd>{{ $fmt($line) }}</dd>
            </div>
        @endforeach

        @if($charges->isNotEmpty())
            @if($subtotalMinor !== null)
                <div class="eb-price-row eb-price-row-subtotal">
                    <dt>Item subtotal</dt>
                    <dd>{{ \App\Support\Money::formatMinor((int) $subtotalMinor, $currency) }}</dd>
                </div>
            @endif
            @foreach($charges as $charge)
                <div class="eb-price-row" data-charge-kind="{{ $charge['kind'] }}">
                    <dt>
                        {{ $charge['label'] }}
                        <span class="eb-price-kind">{{ match ($charge['kind']) { 'platform_fee' => 'EventBite fee', 'host_fee' => 'Organizer fee', 'tax' => 'Tax', 'discount' => 'Discount', default => '' } }}</span>
                    </dt>
                    <dd>{{ $charge['kind'] === 'discount' ? '−' : '' }}{{ \App\Support\Money::formatMinor((int) $charge['amount_minor'], $currency) }}</dd>
                </div>
            @endforeach
        @elseif($fee !== null && (float) $fee > 0)
            <div class="eb-price-row">
                <dt>Packaging fee</dt>
                <dd>{{ \App\Support\Money::format($fee, $currency) }}</dd>
            </div>
        @endif

        <div class="eb-price-row eb-price-row-total">
            <dt>Total</dt>
            <dd>{{ $totalMinor !== null ? \App\Support\Money::formatMinor((int) $totalMinor, $currency) : \App\Support\Money::format($total ?? 0, $currency) }}</dd>
        </div>
    </dl>
    @if($hasTax)
        <p class="eb-price-note">Tax is calculated from the rates configured for this event. It is not tax advice.</p>
    @endif
    @if($note)<p class="eb-price-note">{{ $note }}</p>@endif
</div>
