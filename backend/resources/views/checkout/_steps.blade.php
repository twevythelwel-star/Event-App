{{-- Step indicator for the ordering flow. $steps comes from CheckoutController::steps(); $current is the active label. --}}
<nav aria-label="Order steps" class="eb-checkout-steps">
    <ol>
        @foreach($steps as $i => $step)
            @php $isCurrent = $step['label'] === $current; @endphp
            <li @class(['is-current' => $isCurrent]) @if($isCurrent) aria-current="step" @endif>
                <span class="eb-checkout-step-num" aria-hidden="true">{{ $i + 1 }}</span>
                @if(!$isCurrent && $step['route'])
                    <a href="{{ route($step['route'], $event) }}">{{ $step['label'] }}</a>
                @else
                    <span>{{ $step['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
