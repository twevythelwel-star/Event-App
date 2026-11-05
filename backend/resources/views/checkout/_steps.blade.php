{{-- Step indicator for the ordering flow. $steps comes from CheckoutController::steps(); $current is the active label. --}}
<nav aria-label="Order steps" class="eb-checkout-steps">
    <ol>
        @php $currentIndex = collect($steps)->search(fn ($s) => $s['label'] === $current); @endphp
        @foreach($steps as $i => $step)
            @php $isCurrent = $step['label'] === $current; $isDone = $currentIndex !== false && $i < $currentIndex; @endphp
            <li @class(['is-current' => $isCurrent, 'is-done' => $isDone]) @if($isCurrent) aria-current="step" @endif>
                <span class="eb-checkout-step-num" aria-hidden="true">@if($isDone)<x-icon name="check" size="sm" />@else{{ $i + 1 }}@endif</span>
                @if($isDone)<span class="sr-only">Done:</span>@endif
                @if(!$isCurrent && $step['route'])
                    <a href="{{ route($step['route'], $event) }}">{{ $step['label'] }}</a>
                @else
                    <span>{{ $step['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
