@props([
    'user' => null,
    'active' => null,
])

@php
    $authUser = $user ?? Auth::user();
    $isAuthenticated = $authUser !== null;
    $customerSurface = request()->routeIs('home', 'events.*', 'saved.*', 'attendee.dashboard', 'tickets.*', 'settings.*');

    // Customer mobile navigation: Home / Explore / My Tickets / Saved / Profile, with the active item in lime.
    // Home is the homepage (signed-in customers land on their dashboard there); Explore is event discovery.
    // Saved stays a tab for signed-in customers (docs/eventbite-mobile-design-audit.md).
    $items = [
        [
            'key' => 'home',
            'label' => 'Home',
            'icon' => 'home',
            'href' => route('home'),
            'visible' => true,
            'active' => $active === 'home' || request()->routeIs('home', 'attendee.dashboard'),
        ],
        [
            'key' => 'discover',
            'label' => 'Explore',
            'icon' => 'search',
            'href' => route('events.index'),
            'visible' => true,
            'active' => $active === 'discover' || request()->routeIs('events.*'),
        ],
        [
            'key' => 'tickets',
            'label' => 'My Tickets',
            'icon' => 'tickets',
            'href' => $isAuthenticated ? route('tickets.collection') : route('login'),
            'visible' => true,
            'active' => $active === 'tickets' || request()->routeIs('tickets.collection', 'tickets.show'),
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
            data-nav="{{ $item['key'] }}"
            @if($item['active']) aria-current="page" @endif
        >
            <x-icon :name="$item['icon']" size="md" />
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>
@endif
