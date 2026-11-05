{{--
    Empty state. With an illustration name (events, tickets, orders, notifications) it shows that library artwork;
    otherwise the icon tile. The artwork is decorative: the title and description say what is empty.
--}}
@props([
    'icon' => 'tickets',
    'illustration' => null,
    'title' => 'Nothing to display yet',
    'description' => null,
    'actionHref' => null,
    'actionLabel' => null,
])

@php $art = \App\Support\VisualAssets::emptyState($illustration); @endphp

<div {{ $attributes->merge(['class' => 'eb-empty-state empty-state']) }}>
    @if($art)
        <img src="{{ $art }}" alt="" class="eb-empty-state-art" width="200" height="138" loading="lazy" decoding="async">
    @else
        <div style="width: 48px; height: 48px; border-radius: var(--radius-md); background: var(--color-surface); border: 1px solid var(--color-border); display: inline-flex; align-items: center; justify-content: center; color: var(--color-accent); margin-bottom: var(--space-2);">
            <x-icon :name="$icon" size="lg" />
        </div>
    @endif
    <h3 class="eb-empty-state-title empty-state-title">{{ $title }}</h3>
    @if($description)
        <p class="eb-empty-state-desc empty-state-desc">{{ $description }}</p>
    @endif
    @if($actionHref && $actionLabel)
        <x-button variant="primary" :href="$actionHref">{{ $actionLabel }}</x-button>
    @endif
    {{ $slot }}
</div>
