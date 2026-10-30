{{--
    KPI tile. Every figure should say what it counts (definition) and over what period (note).
    Props: label, value (pre-formatted), note, definition, href (optional link).
--}}
@props([
    'label',
    'value',
    'note' => null,
    'definition' => null,
    'href' => null,
])

<div {{ $attributes->merge(['class' => 'eb-metric-card']) }}>
    @if($href)<a href="{{ $href }}" class="eb-card-link">@endif
        <span class="eb-metric-label">{{ $label }}</span>
        <span class="eb-metric-value" style="display: block;">{{ $value }}</span>
        @if($note)<span class="eb-metric-footer">{{ $note }}</span>@endif
    @if($href)</a>@endif
    @if($definition)<p class="eb-help-text" style="margin: var(--space-1) 0 0;">{{ $definition }}</p>@endif
</div>
