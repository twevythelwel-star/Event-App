@props([
    'type' => 'text', // text, avatar, image, food, event
    'lines' => 3,
    'width' => '100%',
    'height' => null,
])

@php
    $classes = match($type) {
        'text' => 'skeleton skeleton-text',
        'avatar' => 'skeleton skeleton-avatar',
        'image' => 'skeleton skeleton-image',
        'food' => 'skeleton skeleton-image image-placeholder food',
        'event' => 'skeleton skeleton-image image-placeholder event',
        default => 'skeleton',
    };
    $style = match($type) {
        'text' => "width: {$width};",
        'avatar' => '',
        'image' => "width: {$width}; height: {$height ?? '200px'};",
        'food' => "width: {$width}; height: {$height ?? '200px'};",
        'event' => "width: {$width}; height: {$height ?? '200px'};",
        default => '',
    };
@endphp

<div class="{{ $classes }}" style="{{ $style }}">
    @if($type === 'text')
        @for($i = 0; $i < $lines; $i++)
            <div style="width: {{ $i === $lines - 1 ? '60%' : '100%' }};"></div>
        @endfor
    @endif
</div>
