{{--
    The one sidebar for every signed-in profile. Sections appear by role, so each profile sees only what it can
    open; every link goes to a page or to a section that exists on that page.
--}}
@props([
    'user' => null,
])

@php
    $authUser = $user ?? Auth::user();
    $isSystemAdmin = $authUser?->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN) ?? false;
    $isAdmin = $authUser?->hasRole(\App\Enums\AccountType::ADMINISTRATOR) ?? false;
    $isHost = $authUser?->hasRole(\App\Enums\AccountType::EVENT_HOST) ?? false;

    // Staff duty: one link per event whose terminal this person can open right now.
    $dutyEvents = collect();
    if ($authUser) {
        $dutyEvents = \App\Models\EventWorkerAssignment::withoutGlobalScopes()
            ->where('user_id', $authUser->id)
            ->with(['event' => fn ($q) => $q->withoutGlobalScopes()])
            ->get()
            ->filter(fn ($assignment) => $assignment->isCurrentlyActive() && $assignment->event)
            ->unique('event_id')
            ->values();
    }

    $link = fn (bool $active) => 'eb-sidebar-link' . ($active ? ' active' : '');
@endphp

<nav class="eb-sidebar-nav sidebar-nav" aria-label="Main navigation">
    <div class="eb-nav-section-title nav-section-title">Discover</div>
    <a href="{{ route('events.index') }}" class="{{ $link(request()->routeIs('events.index', 'events.show', 'home')) }}" @if(request()->routeIs('events.index')) aria-current="page" @endif>
        <x-icon name="events" size="md" />
        <span>Events</span>
    </a>

    @auth
        @if($isHost || $isSystemAdmin)
            <div class="eb-nav-section-title nav-section-title">Hosting</div>
            <a href="{{ route('host.dashboard') }}" class="{{ $link(request()->routeIs('host.dashboard')) }}" @if(request()->routeIs('host.dashboard')) aria-current="page" @endif>
                <x-icon name="dashboard" size="md" />
                <span>Host dashboard</span>
            </a>
            <a href="{{ route('events.create') }}" class="{{ $link(request()->routeIs('events.create')) }}" @if(request()->routeIs('events.create')) aria-current="page" @endif>
                <x-icon name="plus" size="md" />
                <span>Create event</span>
            </a>
            <a href="{{ route('host.dashboard') }}#review" class="eb-sidebar-link">
                <x-icon name="payment" size="md" />
                <span>Payments to review</span>
            </a>
            <a href="{{ route('host.dashboard') }}#orders" class="eb-sidebar-link">
                <x-icon name="orders" size="md" />
                <span>Orders</span>
            </a>
            <a href="{{ route('host.dashboard') }}#forecast" class="eb-sidebar-link">
                <x-icon name="utensils" size="md" />
                <span>Food to prepare</span>
            </a>
            <a href="{{ route('host.dashboard') }}#workers" class="{{ $link(request()->routeIs('host.events.staff')) }}">
                <x-icon name="staff" size="md" />
                <span>Staff</span>
            </a>
        @endif

        @if($dutyEvents->isNotEmpty())
            <div class="eb-nav-section-title nav-section-title">Staff duty</div>
            @foreach($dutyEvents as $assignment)
                @php $active = request()->routeIs('worker.terminal') && (string) request()->route('event')?->id === (string) $assignment->event_id; @endphp
                <a href="{{ route('worker.terminal', $assignment->event_id) }}" class="{{ $link($active) }}" @if($active) aria-current="page" @endif>
                    <x-icon name="checkin" size="md" />
                    <span>{{ \Illuminate\Support\Str::limit($assignment->event->title, 26) }}</span>
                </a>
            @endforeach
        @endif

        @if($isAdmin || $isSystemAdmin)
            <div class="eb-nav-section-title nav-section-title">Administration</div>
            @can(\App\Support\AdminAuthorization::ACCESS)
                <a href="{{ route('admin.console.overview') }}" class="eb-sidebar-link">
                    <x-icon name="shield" size="md" />
                    <span>Admin console</span>
                </a>
            @endcan
            <a href="{{ route('admin.host-applications') }}" class="{{ $link(request()->routeIs('admin.host-applications')) }}" @if(request()->routeIs('admin.host-applications')) aria-current="page" @endif>
                <x-icon name="users" size="md" />
                <span>Host applications</span>
            </a>
        @endif

        <div class="eb-nav-section-title nav-section-title">My account</div>
        <a href="{{ route('attendee.dashboard') }}" class="{{ $link(request()->routeIs('attendee.dashboard', 'tickets.show')) }}" @if(request()->routeIs('attendee.dashboard')) aria-current="page" @endif>
            <x-icon name="tickets" size="md" />
            <span>My tickets &amp; orders</span>
        </a>
        @if(!$isHost && !$isAdmin && !$isSystemAdmin)
            <a href="{{ route('host.apply') }}" class="{{ $link(request()->routeIs('host.apply')) }}" @if(request()->routeIs('host.apply')) aria-current="page" @endif>
                <x-icon name="calendar" size="md" />
                <span>Become a host</span>
            </a>
        @endif
        <a href="{{ route('settings.show') }}" class="{{ $link(request()->routeIs('settings.show')) }}" @if(request()->routeIs('settings.show')) aria-current="page" @endif>
            <x-icon name="settings" size="md" />
            <span>Settings</span>
        </a>
    @else
        <div class="eb-nav-section-title nav-section-title">Account</div>
        <a href="{{ route('login') }}" class="{{ $link(request()->routeIs('login')) }}" id="sidebarLoginLink">
            <x-icon name="login" size="md" />
            <span>Log in</span>
        </a>
        <a href="{{ route('register') }}" class="{{ $link(request()->routeIs('register')) }}" id="sidebarRegisterLink">
            <x-icon name="plus" size="md" />
            <span>Create account</span>
        </a>
    @endauth
</nav>
