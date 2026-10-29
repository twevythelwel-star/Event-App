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
        <label for="target_role" class="eb-text-label" style="color: var(--color-text-secondary); font-size: 0.78rem;">
            Role:
        </label>
        <select
            name="target_role"
            id="target_role"
            onchange="this.form.submit()"
            class="eb-select"
            style="min-height: 36px; padding: 0.35rem 0.75rem; font-size: 0.8125rem; font-weight: 600; width: auto;"
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
    <span class="eb-badge eb-badge-purple badge badge-scope">
        {{ $roleLabels[$activeRole] ?? ucfirst($activeRole) }}
    </span>
</div>
@endif
