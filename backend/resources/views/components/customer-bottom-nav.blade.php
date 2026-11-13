@props([
    'user' => null,
    'active' => null,
])

@php
    $authUser = $user ?? Auth::user();
    $isAuthenticated = $authUser !== null;
    $foodScreen = request()->routeIs('events.index') && request()->query('category') === 'food';

    // The app's main bar, on every page: Home / Explore / Tickets / Food / Sign In (Saved and Profile when signed in),
    // with the active item in lime. It stays at the bottom of the screen while scrolling and ends above the legal links.
    // Home is the homepage (signed-in customers land on their dashboard there); Explore is event discovery;
    // Food is the Food & Experience screen. Saved stays a tab for signed-in customers (docs/eventbite-mobile-design-audit.md).
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
            'active' => $active === 'discover' || (request()->routeIs('events.*') && !$foodScreen),
        ],
        [
            'key' => 'tickets',
            'label' => 'Tickets',
            'icon' => 'tickets',
            'href' => $isAuthenticated ? route('tickets.collection') : route('login'),
            'visible' => true,
            'active' => $active === 'tickets' || request()->routeIs('tickets.collection', 'tickets.show'),
        ],
        [
            'key' => 'food',
            'label' => 'Food',
            'icon' => 'utensils',
            'href' => route('events.index', ['category' => 'food']),
            'visible' => true,
            'active' => $active === 'food' || $foodScreen,
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
            'label' => $isAuthenticated ? 'Profile' : 'Sign In',
            'icon' => $isAuthenticated ? 'profile' : 'login',
            'href' => $isAuthenticated ? route('settings.show') : route('login'),
            'visible' => true,
            'active' => $active === 'profile' || request()->routeIs('settings.*'),
        ],
    ];

    $visibleItems = array_values(array_filter($items, fn (array $item) => $item['visible'] && !empty($item['href'])));
@endphp

<nav class="eb-customer-bottom-nav" aria-label="Main navigation">
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
