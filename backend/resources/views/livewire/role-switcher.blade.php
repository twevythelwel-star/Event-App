<div class="livewire-role-switcher" style="display: flex; align-items: center; gap: 0.5rem;">
    @if(count($authorizedRoles) > 1)
        <label for="livewire_role_select" style="font-size: 0.8rem; color: var(--text-secondary); font-weight: 500;">
            Context:
        </label>
        <select 
            id="livewire_role_select" 
            wire:model.live="activeRole" 
            wire:change="switchRole($event.target.value)"
            style="background: rgba(255, 255, 255, 0.08); color: white; border: 1px solid var(--border-color); padding: 0.35rem 0.65rem; border-radius: 6px; font-size: 0.82rem; font-weight: 600; cursor: pointer;"
        >
            @foreach($authorizedRoles as $role)
                <option value="{{ $role }}">
                    {{ ucfirst(str_replace('_', ' ', $role)) }}
                </option>
            @endforeach
        </select>
    @else
        <span class="badge badge-active">{{ ucfirst(str_replace('_', ' ', $activeRole)) }}</span>
    @endif
</div>
