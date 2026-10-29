@props([
    'user' => null,
    'active' => null,
])

@php
    $authUser = $user ?? Auth::user();
    $isAuthenticated = $authUser !== null;

    // Customer Mobile Navigation items:
    // Home is public; My Tickets, Orders, and Profile require an authenticated account.
    $items = [
        [
            'key' => 'home',
            'label' => 'Home',
            'icon' => 'home',
            'href' => route('events.index'),
            'visible' => true,
            'active' => $active === 'home' || request()->routeIs('events.*') || request()->is('/'),
        ],
        [
            'key' => 'tickets',
            'label' => 'My Tickets',
            'icon' => 'tickets',
            'href' => $isAuthenticated ? route('attendee.dashboard') : null,
            'visible' => $isAuthenticated,
            'active' => $active === 'tickets' || request()->routeIs('attendee.dashboard'),
        ],
        [
            'key' => 'orders',
            'label' => 'Orders',
            'icon' => 'orders',
            'href' => $isAuthenticated ? (route('attendee.dashboard') . '#orders') : null,
            'visible' => $isAuthenticated,
            'active' => $active === 'orders',
        ],
        [
            'key' => 'profile',
            'label' => 'Profile',
            'icon' => 'profile',
            'href' => $isAuthenticated ? route('settings.show') : route('login'),
            'visible' => $isAuthenticated,
            'active' => $active === 'profile' || request()->routeIs('settings.*'),
        ],
    ];

    $visibleItems = array_values(array_filter($items, fn (array $item) => $item['visible'] && !empty($item['href'])));
@endphp

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

