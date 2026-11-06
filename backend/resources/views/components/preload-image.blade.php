{{--
    Preloads a page's main image (its Largest Contentful Paint) so the browser fetches it before it reaches the <img>.
    Use inside @push('head'). Picks the best format the image has; browsers that cannot use that type skip the hint
    and load the image normally. Props: image (App\Support\ResponsiveImage), sizes (as on the <img>).
--}}
@props(['image', 'sizes' => '100vw'])
@php
    /** @var \App\Support\ResponsiveImage $image */
    $type = array_key_exists('image/avif', $image->sources) ? 'image/avif' : (array_key_first($image->sources) ?? null);
@endphp
@if($type)
    <link rel="preload" as="image" type="{{ $type }}" imagesrcset="{{ $image->sources[$type] }}" imagesizes="{{ $sizes }}" fetchpriority="high">
@else
    <link rel="preload" as="image" href="{{ $image->src }}" fetchpriority="high">
@endif
