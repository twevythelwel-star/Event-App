{{--
    Order price breakdown. Displays amounts the pricing services already calculated;
    it never adds anything up itself.
    Props: lines (array of ['label' => string, 'amount' => number]), fee (number|null, packaging fee),
           total (number), currency, note (optional text under the total).
--}}
@props([
    'lines' => [],
    'fee' => null,
    'total',
    'currency' => 'JMD',
    'note' => null,
])

<div {{ $attributes }}>
    <dl class="eb-price-summary">
        @foreach($lines as $line)
            <div class="eb-price-row">
                <dt>{{ $line['label'] }}</dt>
                <dd>{{ \App\Support\Money::format($line['amount'], $currency) }}</dd>
            </div>
        @endforeach
        @if($fee !== null && (float) $fee > 0)
            <div class="eb-price-row">
                <dt>Packaging fee</dt>
                <dd>{{ \App\Support\Money::format($fee, $currency) }}</dd>
            </div>
        @endif
        <div class="eb-price-row eb-price-row-total">
            <dt>Total</dt>
            <dd>{{ \App\Support\Money::format($total, $currency) }}</dd>
        </div>
    </dl>
    @if($note)<p class="eb-price-note">{{ $note }}</p>@endif
</div>
