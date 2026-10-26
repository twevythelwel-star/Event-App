@props(['user'])

@php
    $authorizedRoles = $user ? $user->getAuthorizedRoles() : [];
    $activeRole = $user ? $user->getActiveRole()->value : 'attendee';

    $roleLabels = [
        'system_admin' => 'System Administrator',
        'admin' => 'Administrator',
        'event_host' => 'Event Host',
        'attendee' => 'Attendee',
    ];
@endphp

@if(count($authorizedRoles) > 1)
<div class="role-switcher-container" style="display: inline-flex; align-items: center;">
    <form action="{{ route('context.switch') }}" method="POST" style="display: flex; align-items: center; gap: 0.5rem; margin: 0;">
        @csrf
        <label for="target_role" style="font-size: 0.78rem; color: var(--color-text-secondary); font-weight: 500;">
            Role:
        </label>
        <select 
            name="target_role" 
            id="target_role" 
            onchange="this.form.submit()" 
            style="background: var(--color-indigo-soft); color: var(--color-indigo); border: 1px solid var(--color-indigo-subtle); padding: 0.35rem 0.75rem; border-radius: var(--radius-sm); font-size: 0.8rem; font-weight: 600; cursor: pointer; outline: none;"
        >
            @foreach($authorizedRoles as $role)
                <option value="{{ $role }}" {{ $role === $activeRole ? 'selected' : '' }}>
                    {{ $roleLabels[$role] ?? ucfirst($role) }}
                </option>
            @endforeach
        </select>
    </form>
</div>
@else
<div class="single-role-badge">
    <span class="badge badge-scope">
        {{ $roleLabels[$activeRole] ?? ucfirst($activeRole) }}
    </span>
</div>
@endif
