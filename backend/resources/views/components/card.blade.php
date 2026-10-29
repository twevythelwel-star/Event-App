@props([
    'variant' => 'panel',
    'title' => null,
    'subtitle' => null,
    'elevated' => false,
])

@php
    $variantClass = match($variant) {
        'event' => 'eb-event-card',
        'food' => 'eb-food-card',
        'ticket' => 'eb-ticket-card',
        'order' => 'eb-order-card',
        'payment' => 'eb-payment-card',
        'metric' => 'eb-metric-card metric-card',
        default => 'eb-panel card' . ($elevated ? ' eb-panel-elevated' : ''),
    };
@endphp

<div {{ $attributes->merge(['class' => $variantClass]) }}>
    @if($title || isset($headerActions))
        <div class="eb-card-header card-header">
            <div>
                @if($title)
                    <h3 class="eb-card-title card-title">{{ $title }}</h3>
                @endif
                @if($subtitle)
                    <p class="eb-card-subtitle card-subtitle">{{ $subtitle }}</p>
                @endif
            </div>
            @if(isset($headerActions))
                <div class="eb-cluster">
                    {{ $headerActions }}
                </div>
            @endif
        </div>
    @endif

    {{ $slot }}
</div>

