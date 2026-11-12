@props([
    'user' => null,
    'active' => null,
])

@php
    $authUser = $user ?? Auth::user();
    $isAuthenticated = $authUser !== null;
    $customerSurface = request()->routeIs('home', 'events.*', 'saved.*', 'attendee.dashboard', 'tickets.*', 'settings.*');

    // Customer mobile navigation: Discover / My Tickets / Saved / Profile (docs/eventbite-mobile-design-audit.md).
    // Home is the logo; order history stays reachable from the dashboard's Orders section.
    $items = [
        [
            'key' => 'discover',
            'label' => 'Discover',
            'icon' => 'search',
            'href' => route('events.index'),
            'visible' => true,
            'active' => $active === 'discover' || request()->routeIs('home', 'events.*'),
        ],
        [
            'key' => 'tickets',
            'label' => 'My Tickets',
            'icon' => 'tickets',
            'href' => $isAuthenticated ? route('tickets.collection') : null,
            'visible' => $isAuthenticated,
            'active' => $active === 'tickets' || request()->routeIs('attendee.dashboard', 'tickets.collection', 'tickets.show'),
        ],
        [
            'key' => 'saved',
            'label' => 'Saved',
            'icon' => 'heart',
            'href' => $isAuthenticated ? route('saved.index') : null,
            'visible' => $isAuthenticated,
            'active' => $active === 'saved' || request()->routeIs('saved.*'),
        ],
        [
            'key' => 'profile',
            'label' => 'Profile',
            'icon' => 'profile',
            'href' => $isAuthenticated ? route('settings.show') : route('login'),
            'visible' => true,
            'active' => $active === 'profile' || request()->routeIs('settings.*'),
        ],
    ];

    $visibleItems = array_values(array_filter($items, fn (array $item) => $item['visible'] && !empty($item['href'])));
@endphp

@if($customerSurface)
<nav class="eb-customer-bottom-nav eb-customer-bottom-nav-mobile-only" aria-label="Customer mobile navigation">
    @foreach($visibleItems as $item)
        <a
            href="{{ $item['href'] }}"
            class="eb-customer-nav-item {{ $item['active'] ? 'active' : '' }}"
            @if($item['active']) aria-current="page" @endif
        >
            <x-icon :name="$item['icon']" size="md" />
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>
@endif

