{{--
    Accessible 3D Recessed Loading Skeleton Component
    Props:
      - variant: 'card' | 'ticket' | 'metric' | 'table' | 'text' (default: 'card')
      - count: number of skeleton items to render (default: 1)
      - label: screen-reader loading announcement (default: 'Loading content…')
--}}
@props([
    'variant' => null,
    'type' => 'card',
    'count' => 1,
    'label' => 'Loading content…',
])

@php
    $variant = $variant ?: $type ?: 'card';
    $items = max(1, min(12, (int) $count));
@endphp

<div {{ $attributes->merge(['class' => 'eb-skeleton-group eb-skeleton-group--' . $variant]) }} role="status" aria-live="polite" aria-busy="true">
    <span class="sr-only">{{ $label }}</span>
    @for($i = 0; $i < $items; $i++)
        @if($variant === 'ticket')
            <div class="eb-skeleton-ticket" aria-hidden="true">
                <div class="eb-skeleton-ticket__main">
                    <div class="eb-skeleton eb-skeleton-pill"></div>
                    <div class="eb-skeleton eb-skeleton-title"></div>
                    <div class="eb-skeleton eb-skeleton-line eb-skeleton-line--short"></div>
                </div>
                <div class="eb-skeleton eb-skeleton-ticket__qr"></div>
            </div>
        @elseif($variant === 'metric')
            <div class="eb-skeleton-metric" aria-hidden="true">
                <div class="eb-skeleton eb-skeleton-kicker"></div>
                <div class="eb-skeleton eb-skeleton-metric__value eb-skeleton-value"></div>
                <div class="eb-skeleton eb-skeleton-line eb-skeleton-line--short"></div>
            </div>
        @elseif($variant === 'table')
            <div class="eb-skeleton-table" aria-hidden="true">
                @for($r = 0; $r < 4; $r++)
                    <div class="eb-skeleton-table-row">
                        <div class="eb-skeleton eb-skeleton-line eb-skeleton-line--md"></div>
                        <div class="eb-skeleton eb-skeleton-line eb-skeleton-line--sm"></div>
                        <div class="eb-skeleton eb-skeleton-line eb-skeleton-line--sm"></div>
                        <div class="eb-skeleton eb-skeleton-pill"></div>
                    </div>
                @endfor
            </div>
        @elseif($variant === 'text')
            <div class="eb-skeleton-text-block" aria-hidden="true">
                <div class="eb-skeleton eb-skeleton-line eb-skeleton-line--lg"></div>
                <div class="eb-skeleton eb-skeleton-line eb-skeleton-line--md"></div>
                <div class="eb-skeleton eb-skeleton-line eb-skeleton-line--sm"></div>
            </div>
        @else
            <div class="eb-skeleton-card" aria-hidden="true">
                <div class="eb-skeleton eb-skeleton-media"></div>
                <div class="eb-skeleton-body">
                    <div class="eb-skeleton eb-skeleton-pill"></div>
                    <div class="eb-skeleton eb-skeleton-title"></div>
                    <div class="eb-skeleton eb-skeleton-line eb-skeleton-line--md"></div>
                    <div class="eb-skeleton eb-skeleton-line eb-skeleton-line--sm"></div>
                </div>
            </div>
        @endif
    @endfor
</div>
