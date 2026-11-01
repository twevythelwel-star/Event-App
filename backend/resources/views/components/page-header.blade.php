{{--
    Standard page heading for every app page: breadcrumbs, title, short description, an optional badge and
    the page's primary actions (slot "actions").
    Props: title, description, breadcrumbs (['Label' => url, ..., 'Current page' => null]), badge.
--}}
@props([
    'title',
    'description' => null,
    'breadcrumbs' => [],
    'badge' => null,
])

<header {{ $attributes->merge(['class' => 'eb-page-header']) }}>
    <div style="min-width: 0;">
        @if($breadcrumbs)
            <nav aria-label="Breadcrumb" class="eb-text-label" style="display: flex; flex-wrap: wrap; gap: var(--space-2); margin-bottom: var(--space-2); color: var(--color-text-muted);">
                @foreach($breadcrumbs as $label => $url)
                    @if($url)
                        <a href="{{ $url }}" style="color: var(--color-text-secondary);">{{ $label }}</a>
                        <span aria-hidden="true">&rsaquo;</span>
                    @else
                        <span aria-current="page" style="color: var(--color-text-primary);">{{ $label }}</span>
                    @endif
                @endforeach
            </nav>
        @endif
        <div class="eb-cluster">
            <h1 class="eb-text-page-title" style="margin: 0;">{{ $title }}</h1>
            @if($badge)<span class="eb-badge eb-badge-purple">{{ $badge }}</span>@endif
        </div>
        @if($description)
            <p class="eb-text-supporting" style="margin: var(--space-2) 0 0; max-width: 68ch;">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="eb-cluster">{{ $actions }}</div>
    @endisset
</header>
