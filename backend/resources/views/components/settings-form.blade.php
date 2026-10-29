@props(['title', 'description', 'action', 'method' => 'POST', 'scopeTag' => null])

<div class="eb-panel card settings-card" style="margin-bottom: var(--space-6);">
    <div class="eb-card-header card-header">
        <div>
            <h2 class="eb-card-title card-title">{{ $title }}</h2>
            <p class="eb-card-subtitle card-subtitle">{{ $description }}</p>
        </div>
        @if($scopeTag)
            <span class="eb-badge eb-badge-purple badge badge-scope">
                {{ $scopeTag }}
            </span>
        @endif
    </div>

    <form action="{{ $action }}" method="POST">
        @csrf
        @if(in_array(strtoupper($method), ['PUT', 'PATCH', 'DELETE']))
            @method($method)
        @endif

        {{ $slot }}

        <div style="margin-top: var(--space-6); display: flex; justify-content: flex-end; gap: var(--space-3);">
            <button type="submit" class="eb-btn eb-btn-primary btn btn-primary" data-prevent-double-submit="true">
                Save Changes
            </button>
        </div>
    </form>
</div>
