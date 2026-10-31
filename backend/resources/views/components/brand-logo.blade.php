@props(['variant' => 'wordmark', 'alt' => 'EventBite'])
@php
    $isIcon = $variant === 'icon';
    $src = $isIcon ? 'icons/eventbite-48.png' : 'images/brand/eventbite-wordmark.webp';
    $width = $isIcon ? 40 : ($variant === 'compact' ? 144 : 180);
    $height = $isIcon ? 40 : ($variant === 'compact' ? 51 : 63);
@endphp
<img
    {{ $attributes->merge(['class' => 'brand-logo brand-logo--' . $variant]) }}
    src="{{ asset($src) }}"
    alt="{{ $alt }}"
    width="{{ $width }}"
    height="{{ $height }}"
    decoding="async"
>
