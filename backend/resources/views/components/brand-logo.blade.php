{{--
    The EventBite logo, from the official "Eventbite fork.jpg" (the source of truth). The derivatives only have the
    flat navy background removed (see docs/VISUAL_ASSET_LIBRARY.md), so the logo sits on any dark surface without a
    box. Width and height follow each derivative's real proportions, so it is never stretched or cropped.

    Variants:
      wordmark  the full lockup, 180 px wide (default)
      compact   the full lockup, 144 px wide (sidebars)
      icon      the fork-pin mark alone, 40 px high
      email     the full lockup as PNG with an absolute URL (mail clients do not all show WebP)
--}}
@props(['variant' => 'wordmark', 'alt' => 'EventBite'])
@php
    // Lockup 1594 × 543, mark 450 × 543 (the trimmed logo in the 2048 × 2048 source).
    [$src, $width, $height, $srcset] = match ($variant) {
        'icon' => ['images/brand/eventbite-mark-128.png', 33, 40, 'images/brand/eventbite-mark-256.png 2x'],
        'compact' => ['images/brand/eventbite-logo-360.webp', 144, 49, 'images/brand/eventbite-logo-600.webp 2x'],
        'email' => ['images/brand/eventbite-logo-600.png', 220, 75, null],
        default => ['images/brand/eventbite-logo-360.webp', 180, 61, 'images/brand/eventbite-logo-600.webp 2x'],
    };
@endphp
<img
    {{ $attributes->merge(['class' => 'brand-logo brand-logo--' . $variant]) }}
    src="{{ asset($src) }}"
    @if($srcset) srcset="{{ asset($src) }} 1x, {{ asset(strtok($srcset, ' ')) }} 2x" @endif
    alt="{{ $alt }}"
    width="{{ $width }}"
    height="{{ $height }}"
    decoding="async"
>
