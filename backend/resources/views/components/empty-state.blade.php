@props([
    'icon' => 'tickets',
    'title' => 'Nothing to display yet',
    'description' => null,
    'actionHref' => null,
    'actionLabel' => null,
])

<div {{ $attributes->merge(['class' => 'eb-empty-state empty-state']) }}>
    <div style="width: 48px; height: 48px; border-radius: var(--radius-md); background: var(--color-surface); border: 1px solid var(--color-border); display: inline-flex; align-items: center; justify-content: center; color: var(--color-accent); margin-bottom: var(--space-2);">
        <x-icon :name="$icon" size="lg" />
    </div>
    <h3 class="eb-empty-state-title empty-state-title">{{ $title }}</h3>
    @if($description)
        <p class="eb-empty-state-desc empty-state-desc">{{ $description }}</p>
    @endif
    @if($actionHref && $actionLabel)
        <x-button variant="primary" :href="$actionHref">{{ $actionLabel }}</x-button>
    @endif
    {{ $slot }}
</div>

