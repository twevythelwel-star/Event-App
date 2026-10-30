{{--
    Loading indicator with a text label (announced politely to screen readers).
    Props: label, block (centered in its own area instead of inline).
--}}
@props([
    'label' => 'Loading…',
    'block' => false,
])

<div {{ $attributes->merge(['class' => $block ? 'eb-loading-block' : '']) }} role="status" aria-live="polite">
    <span class="eb-loading"><span class="eb-spinner" aria-hidden="true"></span><span>{{ $label }}</span></span>
</div>
