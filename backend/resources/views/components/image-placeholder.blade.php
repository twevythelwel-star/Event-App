@props([
    'type' => 'generic', // generic, food, event, venue
    'alt' => 'Placeholder image',
    'size' => 'medium', // small, medium, large
])

@php
    $sizes = [
        'small' => ['w' => '100px', 'h' => '100px', 'icon' => '24px'],
        'medium' => ['w' => '200px', 'h' => '200px', 'icon' => '32px'],
        'large' => ['w' => '400px', 'h' => '300px', 'icon' => '48px'],
    ];
    $config = $sizes[$size] ?? $sizes['medium'];
    $icons = [
        'generic' => '📷',
        'food' => '🍽️',
        'event' => '🎪',
        'venue' => '🏛️',
    ];
    $icon = $icons[$type] ?? $icons['generic'];
    $labels = [
        'generic' => 'No image uploaded',
        'food' => 'No food photo',
        'event' => 'No event image',
        'venue' => 'No venue photo',
    ];
@endphp

<div class="image-placeholder {{ $type }}" style="width: {{ $config['w'] }}; height: {{ $config['h'] }}; min-height: {{ $config['h'] }};">
    <div style="font-size: {{ $config['icon'] }}; margin-bottom: 8px; display: block;">{{ $icon }}</div>
    <span style="font-size: 14px; font-weight: 500;">{{ $labels[$type] ?? $labels['generic'] }}</span>
</div>
