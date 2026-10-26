@props(['title', 'description', 'action', 'method' => 'POST', 'scopeTag' => null])

<div class="card settings-card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;">
        <div>
            <h2 class="card-title">{{ $title }}</h2>
            <p class="card-subtitle">{{ $description }}</p>
        </div>
        @if($scopeTag)
            <span class="badge badge-scope">
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

        <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
            <button type="submit" class="btn btn-primary">
                Save Changes
            </button>
        </div>
    </form>
</div>
