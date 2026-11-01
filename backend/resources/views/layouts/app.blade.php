<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#11172f">
    <meta name="description" content="Discover events, order food, and manage EventBite tickets and payments.">
    <meta property="og:site_name" content="EventBite">
    <meta property="og:title" content="@yield('title', 'EventBite — Events, Tickets & Food')">
    <meta property="og:image" content="{{ asset('images/brand/eventbite-wordmark.webp') }}">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/eventbite-32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <title>@yield('title', 'EventBite — Events, Tickets & Food')</title>

    <!-- Modern Sans-Serif Typography with System Fallbacks -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">

    <!-- Modular EventBite Design System Stylesheets -->
    <link rel="stylesheet" href="/css/01-tokens.css">
    <link rel="stylesheet" href="/css/02-typography-layout.css">
    <link rel="stylesheet" href="/css/03-buttons-forms.css">
    <link rel="stylesheet" href="/css/04-surfaces-cards.css">
    <link rel="stylesheet" href="/css/05-navigation-motion.css">
    <link rel="stylesheet" href="/css/06-components.css">
    <link rel="stylesheet" href="{{ asset('css/brand.css') }}">

    <style>
        :root {
            --color-background: #07111F;
            --color-background-secondary: #0B192B;
            --color-surface: #10243A;
            --color-surface-elevated: #152C45;
            --color-accent: #C7FF45;
            --color-accent-hover: #D7FF77;
            --color-accent-text: #14210A;
            --color-purple: #8B5CF6;
            --color-purple-text: #C4B5FD;
            --color-text-primary: #F8FAFC;
            --color-text-secondary: #A8B6C8;
            --color-text-muted: #8494A8;
            --color-border: #26384D;
            --color-success: #22C55E;
            --color-warning: #F59E0B;
            --color-error: #EF4444;
        }

        .brand-text-wrap {
            display: flex;
            flex-direction: column;
        }

        .brand-subtitle {
            font-size: 0.7rem;
            color: var(--color-text-secondary);
            font-weight: 600;
            letter-spacing: 0.04em;
        }

        .user-profile-preview {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            overflow: hidden;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--color-accent-subtle);
            color: var(--color-accent);
            border: 1px solid var(--color-accent-border);
            font-size: 0.82rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            text-transform: uppercase;
        }

        .user-info-text {
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .user-display-name {
            font-size: 0.84rem;
            font-weight: 700;
            color: var(--color-text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-role-badge-text {
            font-size: 0.72rem;
            color: var(--color-text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .header-left,
        .header-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .mobile-menu-toggle {
            display: none;
            background: var(--color-background-secondary);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            width: 44px;
            height: 44px;
            cursor: pointer;
            color: var(--color-text-primary);
            align-items: center;
            justify-content: center;
        }

        .breadcrumb-wrap {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .breadcrumb-item {
            color: var(--color-text-secondary);
            text-decoration: none;
            font-weight: 500;
        }

        .breadcrumb-separator {
            color: var(--color-text-muted);
        }

        .breadcrumb-current {
            color: var(--color-text-primary);
            font-weight: 700;
        }

        main.content-canvas {
            flex: 1;
            padding: var(--layout-page-padding-y) var(--layout-page-padding-x) calc(var(--layout-bottom-nav-height) + 1.5rem);
            max-width: var(--layout-max-width);
            width: 100%;
            margin: 0 auto;
        }

        .mobile-drawer-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(7, 17, 31, 0.78);
            backdrop-filter: blur(4px);
            z-index: 45;
        }

        .mobile-drawer-backdrop.active {
            display: block;
        }

        @media (max-width: 1023px) {
            .mobile-menu-toggle {
                display: inline-flex;
            }
        }
    </style>

    @livewireStyles
</head>
<body>
    <div class="eb-app-shell app-shell">
        <!-- Mobile Drawer Backdrop -->
        <div class="mobile-drawer-backdrop" id="drawerBackdrop" onclick="toggleMobileMenu()"></div>

        <!-- Management & Platform Sidebar -->
        <aside class="eb-management-sidebar app-sidebar" id="appSidebar">
            <a href="{{ route('home') }}" class="eb-sidebar-brand sidebar-brand">
                <div class="brand-text-wrap">
                    <x-brand-logo variant="compact" />
                    <span class="brand-subtitle">Events, tickets &amp; food</span>
                </div>
            </a>

            @hasSection('navigation')
                <div class="eb-sidebar-nav sidebar-nav" role="navigation" aria-label="Section navigation">
                    @yield('navigation')
                </div>
            @else
                <x-management-sidebar :user="Auth::user()" />
            @endif

            @auth
                <div class="eb-sidebar-footer sidebar-footer">
                    <div class="user-profile-preview">
                        <div class="user-avatar" aria-hidden="true">
                            {{ strtoupper(substr(Auth::user()->first_name ?? Auth::user()->name ?? 'U', 0, 1)) }}{{ strtoupper(substr(Auth::user()->last_name ?? '', 0, 1)) }}
                        </div>
                        <div class="user-info-text">
                            <span class="user-display-name">{{ Auth::user()->name }}</span>
                            <span class="user-role-badge-text">{{ Auth::user()->getActiveRole()->label() }}</span>
                        </div>
                    </div>

                    <form action="{{ route('logout') }}" method="POST" style="margin: 0;">
                        @csrf
                        <button type="submit" class="eb-btn eb-btn-icon" title="Sign Out" aria-label="Sign Out">
                            <x-icon name="logout" size="sm" />
                        </button>
                    </form>
                </div>
            @else
                <div class="eb-sidebar-footer sidebar-footer" style="flex-direction: column; align-items: stretch;">
                    <div class="eb-text-label" style="display: flex; align-items: center; gap: 0.5rem;">
                        <x-icon name="tickets" size="sm" />
                        <span>Join EventBite</span>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="{{ route('login') }}" class="eb-btn eb-btn-secondary eb-btn-sm" style="flex: 1;" id="sidebarFooterLoginBtn">Sign In</a>
                        <a href="{{ route('register') }}" class="eb-btn eb-btn-primary eb-btn-sm" style="flex: 1;" id="sidebarFooterRegisterBtn">Sign Up</a>
                    </div>
                </div>
            @endauth
        </aside>

        <!-- Main Content Canvas -->
        <div class="eb-app-main app-main">
            <!-- Top Header -->
            <header class="eb-app-header app-header">
                <div class="header-left">
                    <button type="button" class="mobile-menu-toggle" onclick="toggleMobileMenu()" aria-label="Toggle Navigation Drawer">
                        <x-icon name="menu-bars" size="md" />
                    </button>
                    <nav class="breadcrumb-wrap" aria-label="Breadcrumb">
                        <a class="breadcrumb-item" href="{{ route('home') }}">EventBite</a>
                        <span class="breadcrumb-separator" aria-hidden="true">&rsaquo;</span>
                        <span class="breadcrumb-current" aria-current="page">@yield('page-title', 'Dashboard')</span>
                    </nav>
                </div>

                <div class="header-actions">
                    @guest
                        <a href="{{ route('login') }}" class="eb-btn eb-btn-secondary eb-btn-sm" id="headerLoginBtn">
                            Log In
                        </a>
                        <a href="{{ route('register') }}" class="eb-btn eb-btn-primary eb-btn-sm" id="headerRegisterBtn">
                            Sign Up
                        </a>
                    @endguest

                    @auth
                        @include('components.role-switcher', ['user' => Auth::user()])

                        @php
                            $userDashboardRoute = match(Auth::user()->current_context_role ?? Auth::user()->role) {
                                'system_admin' => 'system-admin.dashboard',
                                'admin' => 'admin.dashboard',
                                'event_host' => 'host.dashboard',
                                default => 'attendee.dashboard',
                            };
                        @endphp

                        <a href="{{ route($userDashboardRoute) }}" class="eb-btn eb-btn-secondary eb-btn-sm desktop-only" title="Go to My Dashboard">
                            <x-icon name="dashboard" size="sm" />
                            <span>Dashboard</span>
                        </a>

                        <a href="{{ route('settings.show') }}" class="eb-btn-icon header-action-btn" title="Account Settings" aria-label="Account Settings">
                            <x-icon name="settings" size="sm" />
                        </a>

                        <form action="{{ route('logout') }}" method="POST" style="margin: 0; display: inline;">
                            @csrf
                            <button type="submit" class="eb-btn-icon header-action-btn" title="Sign Out" aria-label="Sign Out">
                                <x-icon name="logout" size="sm" />
                            </button>
                        </form>
                    @endauth
                </div>
            </header>

            <!-- Main Canvas -->
            <main class="content-canvas">
                @foreach(['success', 'status'] as $flashKey)
                    @if(session($flashKey) && is_string(session($flashKey)))
                        <div class="eb-alert eb-alert-success flash-alert flash-success" role="status">
                            <x-icon name="check" size="md" />
                            <span>{{ session($flashKey) }}</span>
                        </div>
                    @endif
                @endforeach

                @if(session('error') || $errors->has('error'))
                    {{-- 'error' is the key controllers use for a refused action; field errors show beside their fields --}}
                    <div class="eb-alert eb-alert-error flash-alert flash-error" role="alert">
                        <x-icon name="alert" size="md" />
                        <span>{{ session('error') ?: $errors->first('error') }}</span>
                    </div>
                @endif

                @yield('content')
            </main>

            <!-- Customer Mobile Bottom Navigation (Home, My Tickets, Orders, Profile) -->
            <x-customer-bottom-nav :user="Auth::user()" />
        </div>
    </div>

    <!-- Accessible Drawer & Duplicate-Submission Protection -->
    <script>
        function toggleMobileMenu() {
            const sidebar = document.getElementById('appSidebar');
            const backdrop = document.getElementById('drawerBackdrop');
            if (sidebar && backdrop) {
                const isOpen = sidebar.classList.contains('open');
                sidebar.classList.toggle('open', !isOpen);
                backdrop.classList.toggle('active', !isOpen);
            }
        }

        document.addEventListener('submit', function (event) {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) return;
            const submitBtn = form.querySelector('button[type="submit"][data-prevent-double-submit="true"]');
            if (!submitBtn) return;
            if (form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }
            form.dataset.submitting = 'true';
            submitBtn.setAttribute('aria-disabled', 'true');
            submitBtn.setAttribute('data-loading', 'true');
        });
    </script>

    @livewireScripts
</body>
</html>
