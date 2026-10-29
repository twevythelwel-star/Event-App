@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
    'icon' => null,
    'loading' => false,
    'disabled' => false,
    'block' => false,
    'ariaLabel' => null,
    'preventDoubleSubmit' => true,
])

@php
    $variantClass = match($variant) {
        'primary' => 'eb-btn-primary',
        'secondary' => 'eb-btn-secondary',
        'outline' => 'eb-btn-outline',
        'ghost' => 'eb-btn-ghost',
        'destructive', 'danger' => 'eb-btn-destructive',
        'icon' => 'eb-btn-icon',
        default => 'eb-btn-primary',
    };

    $sizeClass = match($size) {
        'sm' => 'eb-btn-sm',
        'lg' => 'eb-btn-lg',
        default => '',
    };

    $classes = trim( implode(' ', array_filter([
        'eb-btn',
        $variantClass,
        $sizeClass,
        $block ? 'eb-btn-block' : '',
        $loading ? 'eb-btn-loading' : '',
    ])) );

    $isDisabled = (bool) $disabled || (bool) $loading;
@endphp

@if($href && !$isDisabled)
    <a
        href="{{ $href }}"
        {{ $attributes->merge(['class' => $classes]) }}
        @if($ariaLabel) aria-label="{{ $ariaLabel }}" @endif
    >
        @if($icon)
            <x-icon :name="$icon" size="sm" />
        @endif
        @if($variant !== 'icon')
            <span>{{ $slot }}</span>
        @else
            {{ $slot }}
        @endif
    </a>
@else
    <button
        type="{{ $type }}"
        {{ $attributes->merge(['class' => $classes]) }}
        @if($isDisabled) disabled aria-disabled="true" @endif
        @if($loading) data-loading="true" aria-busy="true" @endif
        @if($ariaLabel) aria-label="{{ $ariaLabel }}" @endif
        @if($type === 'submit' && $preventDoubleSubmit) data-prevent-double-submit="true" @endif
    >
        @if($loading)
            <span class="eb-btn-spinner" aria-hidden="true"></span>
        @elseif($icon)
            <x-icon :name="$icon" size="sm" />
        @endif
        @if($variant !== 'icon')
            <span>{{ $slot }}</span>
        @else
            {{ $slot }}
        @endif
    </button>
@endif

