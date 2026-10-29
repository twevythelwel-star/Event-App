@props([
    'user' => null,
])

@php
    $authUser = $user ?? Auth::user();
    $activeRole = $authUser ? ($authUser->current_context_role ?? $authUser->role ?? 'attendee') : null;

    $isSystemAdmin = $authUser && ($activeRole === 'system_admin' || $authUser->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN));
    $isDelegatedAdmin = $authUser && ($activeRole === 'admin' || $authUser->hasRole(\App\Enums\AccountType::ADMINISTRATOR));
    $isEventHost = $authUser && ($activeRole === 'event_host' || $authUser->hasRole(\App\Enums\AccountType::EVENT_HOST));

    // Check if the user has active Event Worker assignments
    $hasWorkerAssignment = false;
    if ($authUser && class_exists(\App\Models\EventWorkerAssignment::class)) {
        try {
            $hasWorkerAssignment = \App\Models\EventWorkerAssignment::where('user_id', $authUser->id)
                ->where('status', 'active')
                ->exists();
        } catch (\Throwable $e) {
            $hasWorkerAssignment = false;
        }
    }
@endphp

<div class="eb-sidebar-nav sidebar-nav" role="navigation" aria-label="Management sidebar navigation">
    <div class="eb-nav-section-title nav-section-title">Events Platform</div>
    <a href="{{ route('events.index') }}" class="eb-sidebar-link {{ request()->routeIs('events.index') || request()->is('/') ? 'active' : '' }}">
        <x-icon name="events" size="md" />
        <span>Events</span>
    </a>

    @auth
        @if($isEventHost || $isSystemAdmin)
            <div class="eb-nav-section-title nav-section-title">Host Operations</div>
            <a href="{{ route('host.dashboard') }}" class="eb-sidebar-link {{ request()->routeIs('host.dashboard') ? 'active' : '' }}">
                <x-icon name="dashboard" size="md" />
                <span>Dashboard</span>
            </a>
            <a href="{{ route('events.create') }}" class="eb-sidebar-link {{ request()->routeIs('events.create') ? 'active' : '' }}">
                <x-icon name="plus" size="md" />
                <span>Create Event</span>
            </a>
            <a href="{{ route('host.dashboard') }}#tickets" class="eb-sidebar-link">
                <x-icon name="tickets" size="md" />
                <span>Tickets</span>
            </a>
            <a href="{{ route('host.dashboard') }}#orders" class="eb-sidebar-link">
                <x-icon name="orders" size="md" />
                <span>Orders</span>
            </a>
            <a href="{{ route('host.dashboard') }}#menu" class="eb-sidebar-link">
                <x-icon name="menu" size="md" />
                <span>Menu Management</span>
            </a>
            <a href="{{ route('host.dashboard') }}#workers" class="eb-sidebar-link">
                <x-icon name="staff" size="md" />
                <span>Staff</span>
            </a>
            <a href="{{ route('host.dashboard') }}#checkin" class="eb-sidebar-link">
                <x-icon name="checkin" size="md" />
                <span>Check-in</span>
            </a>
            <a href="{{ route('host.dashboard') }}#finances" class="eb-sidebar-link">
                <x-icon name="reports" size="md" />
                <span>Reports</span>
            </a>
        @elseif($hasWorkerAssignment)
            <div class="eb-nav-section-title nav-section-title">Staff Duty Station</div>
            <a href="{{ route('attendee.dashboard') }}#staff-access" class="eb-sidebar-link">
                <x-icon name="checkin" size="md" />
                <span>Check-in</span>
            </a>
            <a href="{{ route('attendee.dashboard') }}#orders" class="eb-sidebar-link">
                <x-icon name="orders" size="md" />
                <span>Orders</span>
            </a>
        @endif

        @if($isDelegatedAdmin || $isSystemAdmin)
            <div class="eb-nav-section-title nav-section-title">Platform Administration</div>
            @can(\App\Support\AdminAuthorization::ACCESS)
                <a href="{{ route('admin.console.overview') }}" class="eb-sidebar-link {{ request()->routeIs('admin.console.*') ? 'active' : '' }}">
                    <x-icon name="shield" size="md" />
                    <span>Admin Console</span>
                </a>
            @endcan
            @if($isDelegatedAdmin)
                <a href="{{ route('admin.dashboard') }}" class="eb-sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <x-icon name="dashboard" size="md" />
                    <span>Admin Dashboard</span>
                </a>
            @endif
            <a href="{{ route('admin.host-applications') }}" class="eb-sidebar-link {{ request()->routeIs('admin.host-applications') ? 'active' : '' }}">
                <x-icon name="users" size="md" />
                <span>Users & Host Review</span>
            </a>
        @endif

        @if($isSystemAdmin)
            <a href="{{ route('admin.console.roles.index') }}" class="eb-sidebar-link {{ request()->routeIs('admin.console.roles.*') ? 'active' : '' }}">
                <x-icon name="roles" size="md" />
                <span>Roles and Permissions</span>
            </a>
            <a href="{{ route('admin.console.audit.index') }}" class="eb-sidebar-link">
                <x-icon name="audit" size="md" />
                <span>Audit Logs</span>
            </a>
        @endif

        <div class="eb-nav-section-title nav-section-title">My Account</div>
        <a href="{{ route('attendee.dashboard') }}" class="eb-sidebar-link {{ request()->routeIs('attendee.dashboard') ? 'active' : '' }}">
            <x-icon name="tickets" size="md" />
            <span>My Tickets & Orders</span>
        </a>
        <a href="{{ route('settings.show') }}" class="eb-sidebar-link {{ request()->routeIs('settings.show') ? 'active' : '' }}">
            <x-icon name="settings" size="md" />
            <span>Settings</span>
        </a>
    @else
        <div class="eb-nav-section-title nav-section-title">Account Access</div>
        <a href="{{ route('login') }}" class="eb-sidebar-link {{ request()->routeIs('login') ? 'active' : '' }}" id="sidebarLoginLink">
            <x-icon name="login" size="md" />
            <span>Log In</span>
        </a>
        <a href="{{ route('register') }}" class="eb-sidebar-link {{ request()->routeIs('register') ? 'active' : '' }}" id="sidebarRegisterLink">
            <x-icon name="plus" size="md" />
            <span>Create Account</span>
        </a>
    @endauth
</div>

