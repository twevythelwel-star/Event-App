<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Island Events — Digital Ticketing & Food Operations')</title>
    
    <!-- Modern Enterprise Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            /* Brand Identity */
            --color-navy: #111827;
            --color-charcoal: #1F2937;
            --color-indigo: #4F46E5;
            --color-indigo-soft: #EEF2FF;
            --color-indigo-hover: #4338CA;
            --color-indigo-subtle: #E0E7FF;

            /* Surface & Neutrals */
            --color-white: #FFFFFF;
            --color-bg-page: #F8FAFC;
            --color-bg-card: #FFFFFF;
            --color-bg-card-hover: #F1F5F9;
            --color-border: #E2E8F0;
            --color-border-subtle: #F1F5F9;
            --color-border-strong: #CBD5E1;

            /* Typography */
            --color-text-primary: #111827;
            --color-text-secondary: #64748B;
            --color-text-muted: #94A3B8;
            --color-text-inverse: #FFFFFF;

            /* Semantic Statuses */
            --color-success: #15803D;
            --color-success-bg: #DCFCE7;
            --color-success-border: #BBF7D0;
            --color-warning: #B45309;
            --color-warning-bg: #FEF3C7;
            --color-warning-border: #FDE68A;
            --color-error: #B91C1C;
            --color-error-bg: #FEE2E2;
            --color-error-border: #FECACA;
            --color-info: #0369A1;
            --color-info-bg: #E0F2FE;
            --color-info-border: #BAE6FD;

            /* Geometry & Elevation */
            --sidebar-width: 270px;
            --header-height: 68px;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --radius-xl: 20px;
            --shadow-sm: 0 1px 2px 0 rgba(16, 24, 40, 0.05);
            --shadow-card: 0 1px 3px 0 rgba(16, 24, 40, 0.06), 0 1px 2px -1px rgba(16, 24, 40, 0.04);
            --shadow-md: 0 4px 6px -1px rgba(16, 24, 40, 0.08), 0 2px 4px -2px rgba(16, 24, 40, 0.05);
            --shadow-lg: 0 10px 15px -3px rgba(16, 24, 40, 0.08), 0 4px 6px -4px rgba(16, 24, 40, 0.03);
            --transition-speed: 0.18s;
        }

        /* Reset & Base */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--color-bg-page);
            color: var(--color-text-primary);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Application Shell */
        .app-shell {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }

        /* Persistent Desktop Sidebar */
        aside.app-sidebar {
            width: var(--sidebar-width);
            background: var(--color-white);
            border-right: 1px solid var(--color-border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 40;
            transition: transform var(--transition-speed) ease-in-out;
        }

        .sidebar-brand {
            height: var(--header-height);
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            border-bottom: 1px solid var(--color-border);
            text-decoration: none;
            color: var(--color-text-primary);
            gap: 0.75rem;
        }

        .brand-logo-icon {
            width: 36px;
            height: 36px;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, var(--color-indigo) 0%, #312E81 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--color-white);
            font-size: 1.25rem;
            box-shadow: 0 2px 4px rgba(79, 70, 229, 0.25);
            flex-shrink: 0;
        }

        .brand-text-wrap {
            display: flex;
            flex-direction: column;
        }

        .brand-name {
            font-size: 0.98rem;
            font-weight: 700;
            letter-spacing: -0.015em;
            color: var(--color-navy);
        }

        .brand-subtitle {
            font-size: 0.7rem;
            color: var(--color-text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .sidebar-nav {
            flex: 1;
            padding: 1.25rem 0.85rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .nav-section-title {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--color-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 0.75rem 0.85rem 0.35rem;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.65rem 0.85rem;
            border-radius: var(--radius-md);
            color: var(--color-text-secondary);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all var(--transition-speed) ease;
        }

        .sidebar-nav a:hover {
            background-color: var(--color-bg-page);
            color: var(--color-text-primary);
        }

        .sidebar-nav a.active {
            background-color: var(--color-indigo-soft);
            color: var(--color-indigo);
            font-weight: 600;
        }

        .sidebar-nav a .nav-icon {
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            flex-shrink: 0;
        }

        .sidebar-footer {
            padding: 1rem 1.25rem;
            border-top: 1px solid var(--color-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #FAFAFA;
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
            background: var(--color-indigo);
            color: var(--color-white);
            font-size: 0.82rem;
            font-weight: 700;
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
            font-weight: 600;
            color: var(--color-text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-role-badge-text {
            font-size: 0.7rem;
            color: var(--color-text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Main View Wrapper */
        .app-main {
            margin-left: var(--sidebar-width);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            min-height: 100vh;
        }

        /* Top Navigation Header */
        header.app-header {
            height: var(--header-height);
            background: var(--color-white);
            border-bottom: 1px solid var(--color-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            position: sticky;
            top: 0;
            z-index: 30;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .mobile-menu-toggle {
            display: none;
            background: transparent;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            width: 38px;
            height: 38px;
            cursor: pointer;
            color: var(--color-text-primary);
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
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
            font-size: 0.75rem;
        }

        .breadcrumb-current {
            color: var(--color-text-primary);
            font-weight: 600;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-action-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: 1px solid var(--color-border);
            background: var(--color-white);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--color-text-secondary);
            cursor: pointer;
            position: relative;
            transition: all var(--transition-speed);
        }

        .header-action-btn:hover {
            color: var(--color-text-primary);
            background-color: var(--color-bg-page);
        }

        .header-badge-dot {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 8px;
            height: 8px;
            background: var(--color-indigo);
            border-radius: 50%;
            border: 2px solid var(--color-white);
        }

        /* Page Content Canvas */
        main.content-canvas {
            flex: 1;
            padding: 2rem;
            max-width: 1440px;
            width: 100%;
            margin: 0 auto;
        }

        /* Cards & Surfaces */
        .card {
            background: var(--color-bg-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: box-shadow var(--transition-speed) ease;
        }

        .card:hover {
            box-shadow: var(--shadow-md);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--color-border-subtle);
        }

        .card-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--color-text-primary);
            letter-spacing: -0.01em;
        }

        .card-subtitle {
            font-size: 0.85rem;
            color: var(--color-text-secondary);
            margin-top: 0.2rem;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.55rem 1.1rem;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid transparent;
            text-decoration: none;
            transition: all var(--transition-speed) ease;
            min-height: 40px;
        }

        .btn-primary {
            background-color: var(--color-indigo);
            color: var(--color-white);
            box-shadow: 0 1px 2px rgba(79, 70, 229, 0.2);
        }

        .btn-primary:hover {
            background-color: var(--color-indigo-hover);
        }

        .btn-secondary {
            background-color: var(--color-white);
            color: var(--color-text-primary);
            border-color: var(--color-border);
        }

        .btn-secondary:hover {
            background-color: var(--color-bg-page);
            border-color: var(--color-border-strong);
        }

        .btn-danger {
            background-color: var(--color-error);
            color: var(--color-white);
        }

        .btn-danger:hover {
            background-color: #991B1B;
        }

        .btn-sm {
            padding: 0.35rem 0.75rem;
            font-size: 0.8rem;
            min-height: 32px;
            border-radius: var(--radius-sm);
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.65rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
            line-height: 1;
        }

        .badge-active, .badge-paid, .badge-operational, .badge-healthy, .badge-verified {
            background-color: var(--color-success-bg);
            color: var(--color-success);
            border: 1px solid var(--color-success-border);
        }

        .badge-pending, .badge-awaiting_payment, .badge-payment_submitted, .badge-degraded, .badge-under_verification {
            background-color: var(--color-warning-bg);
            color: var(--color-warning);
            border: 1px solid var(--color-warning-border);
        }

        .badge-suspended, .badge-failed, .badge-rejected, .badge-cancelled {
            background-color: var(--color-error-bg);
            color: var(--color-error);
            border: 1px solid var(--color-error-border);
        }

        .badge-info, .badge-scope {
            background-color: var(--color-info-bg);
            color: var(--color-info);
            border: 1px solid var(--color-info-border);
        }

        /* Metric KPI Cards */
        .metric-card {
            background: var(--color-white);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: 1.25rem 1.5rem;
            box-shadow: var(--shadow-card);
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .metric-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--color-text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .metric-value {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--color-text-primary);
            letter-spacing: -0.02em;
        }

        .metric-footer {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.78rem;
            font-weight: 500;
        }

        /* Layout Grid Helpers */
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.25rem;
        }

        .grid-4 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
        }

        /* Data Tables */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
            text-align: left;
        }

        table.data-table th {
            background-color: var(--color-bg-page);
            color: var(--color-text-secondary);
            font-weight: 600;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--color-border);
            white-space: nowrap;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        table.data-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--color-border-subtle);
            color: var(--color-text-primary);
            vertical-align: middle;
        }

        table.data-table tr:hover td {
            background-color: #FAFAFA;
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            color: var(--color-text-secondary);
        }

        .empty-state-icon {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
            opacity: 0.7;
        }

        .empty-state-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--color-text-primary);
            margin-bottom: 0.25rem;
        }

        .empty-state-desc {
            font-size: 0.85rem;
            color: var(--color-text-muted);
            max-width: 400px;
            margin: 0 auto 1.25rem;
        }

        /* Flash Alerts */
        .flash-alert {
            padding: 1rem 1.25rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.88rem;
            font-weight: 500;
        }

        .flash-success {
            background-color: var(--color-success-bg);
            color: var(--color-success);
            border: 1px solid var(--color-success-border);
        }

        .flash-error {
            background-color: var(--color-error-bg);
            color: var(--color-error);
            border: 1px solid var(--color-error-border);
        }

        /* Mobile Drawer & Backdrop */
        .mobile-drawer-backdrop {
            display: none;
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            background: rgba(16, 24, 40, 0.4);
            backdrop-filter: blur(4px);
            z-index: 45;
        }

        .mobile-drawer-backdrop.active {
            display: block;
        }

        /* Responsive Breakpoints */
        @media (max-width: 992px) {
            aside.app-sidebar {
                transform: translateX(-100%);
                box-shadow: var(--shadow-lg);
            }

            aside.app-sidebar.open {
                transform: translateX(0);
            }

            .app-main {
                margin-left: 0;
            }

            .mobile-menu-toggle {
                display: flex;
            }

            header.app-header {
                padding: 0 1rem;
            }

            main.content-canvas {
                padding: 1.25rem;
            }
        }

        @media (max-width: 640px) {
            .desktop-only {
                display: none !important;
            }
        }

        /* Accessible Motion */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>

    @livewireStyles
</head>
<body>
    <div class="app-shell">
        <!-- Mobile Drawer Backdrop -->
        <div class="mobile-drawer-backdrop" id="drawerBackdrop" onclick="toggleMobileMenu()"></div>

        <!-- Desktop & Mobile Sidebar -->
        <aside class="app-sidebar" id="appSidebar">
            <a href="{{ route('home') }}" class="sidebar-brand">
                <div class="brand-logo-icon">🌴</div>
                <div class="brand-text-wrap">
                    <span class="brand-name">Island Events</span>
                    <span class="brand-subtitle">United States Platform</span>
                </div>
            </a>

            <div class="sidebar-nav">
                @hasSection('navigation')
                    @yield('navigation')
                @else
                    <!-- Default Navigation Fallback -->
                    <div class="nav-section-title">Events Platform</div>
                    <a href="{{ route('events.index') }}" class="{{ request()->routeIs('events.*') || request()->is('/') ? 'active' : '' }}">
                        <span class="nav-icon">🌴</span>
                        <span>Browse Events</span>
                    </a>

                    @auth
                        @php
                            $activeRole = Auth::user()->current_context_role ?? Auth::user()->role;
                        @endphp

                        <div class="nav-section-title">My Account</div>
                        @if($activeRole === 'system_admin' || Auth::user()->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN))
                            <a href="{{ route('system-admin.dashboard') }}" class="{{ request()->routeIs('system-admin.dashboard') ? 'active' : '' }}">
                                <span class="nav-icon">🛡️</span>
                                <span>System Governance</span>
                            </a>
                        @endif

                        @if($activeRole === 'admin' || Auth::user()->hasRole(\App\Enums\AccountType::ADMINISTRATOR))
                            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                                <span class="nav-icon">📋</span>
                                <span>Admin Operations</span>
                            </a>
                            <a href="{{ route('admin.host-applications') }}" class="{{ request()->routeIs('admin.host-applications') ? 'active' : '' }}">
                                <span class="nav-icon">📑</span>
                                <span>Host Review Desk</span>
                            </a>
                        @endif

                        @if($activeRole === 'event_host' || Auth::user()->hasRole(\App\Enums\AccountType::EVENT_HOST))
                            <a href="{{ route('host.dashboard') }}" class="{{ request()->routeIs('host.dashboard') ? 'active' : '' }}">
                                <span class="nav-icon">🎪</span>
                                <span>Host Dashboard</span>
                            </a>
                            <a href="{{ route('events.create') }}" class="{{ request()->routeIs('events.create') ? 'active' : '' }}">
                                <span class="nav-icon">➕</span>
                                <span>Create Event</span>
                            </a>
                        @endif

                        <a href="{{ route('attendee.dashboard') }}" class="{{ request()->routeIs('attendee.dashboard') ? 'active' : '' }}">
                            <span class="nav-icon">🎟️</span>
                            <span>My Orders & Tickets</span>
                        </a>

                        @if(!Auth::user()->hasRole(\App\Enums\AccountType::EVENT_HOST) && !Auth::user()->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN))
                            <a href="{{ route('host.apply') }}" class="{{ request()->routeIs('host.apply') ? 'active' : '' }}">
                                <span class="nav-icon">✨</span>
                                <span>Become a Host</span>
                            </a>
                        @endif

                        <a href="{{ route('settings.show') }}" class="{{ request()->routeIs('settings.show') ? 'active' : '' }}">
                            <span class="nav-icon">⚙️</span>
                            <span>Settings & Security</span>
                        </a>
                    @else
                        <div class="nav-section-title">Account Access</div>
                        <a href="{{ route('login') }}" class="{{ request()->routeIs('login') ? 'active' : '' }}" id="sidebarLoginLink">
                            <span class="nav-icon">🔑</span>
                            <span>Log In</span>
                        </a>
                        <a href="{{ route('register') }}" class="{{ request()->routeIs('register') ? 'active' : '' }}" id="sidebarRegisterLink">
                            <span class="nav-icon">✨</span>
                            <span>Create Account</span>
                        </a>
                    @endauth
                @endif
            </div>

            @auth
            <div class="sidebar-footer">
                <div class="user-profile-preview">
                    <div class="user-avatar">
                        {{ strtoupper(substr(Auth::user()->first_name ?? Auth::user()->name ?? 'U', 0, 1)) }}{{ strtoupper(substr(Auth::user()->last_name ?? '', 0, 1)) }}
                    </div>
                    <div class="user-info-text">
                        <span class="user-display-name">{{ Auth::user()->name }}</span>
                        <span class="user-role-badge-text">{{ Auth::user()->getActiveRole()->label() }}</span>
                    </div>
                </div>

                <form action="{{ route('logout') }}" method="POST" style="margin: 0;">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm" title="Sign Out" aria-label="Sign Out" style="padding: 0.35rem 0.5rem;">
                        <span style="font-size: 0.9rem;">🚪</span>
                    </button>
                </form>
            </div>
            @else
            <div class="sidebar-footer" style="flex-direction: column; gap: 0.6rem; align-items: stretch; background: var(--color-indigo-soft); border-color: var(--color-indigo-subtle);">
                <div style="font-size: 0.78rem; font-weight: 700; color: var(--color-navy); display: flex; align-items: center; gap: 0.4rem;">
                    <span>🌴</span>
                    <span>Join Island Events</span>
                </div>
                <div style="display: flex; gap: 0.4rem;">
                    <a href="{{ route('login') }}" class="btn btn-secondary btn-sm" style="flex: 1; text-align: center;" id="sidebarFooterLoginBtn">Sign In</a>
                    <a href="{{ route('register') }}" class="btn btn-primary btn-sm" style="flex: 1; text-align: center;" id="sidebarFooterRegisterBtn">Sign Up</a>
                </div>
            </div>
            @endauth
        </aside>

        <!-- Main Content Canvas -->
        <div class="app-main">
            <!-- Top Header -->
            <header class="app-header">
                <div class="header-left">
                    <button class="mobile-menu-toggle" onclick="toggleMobileMenu()" aria-label="Toggle Navigation Drawer">
                        ☰
                    </button>
                    <div class="breadcrumb-wrap">
                        <span class="breadcrumb-item">Platform</span>
                        <span class="breadcrumb-separator">&rsaquo;</span>
                        <span class="breadcrumb-current">@yield('page-title', 'Dashboard')</span>
                    </div>
                </div>

                <div class="header-actions">
                    @guest
                        <a href="{{ route('login') }}" class="btn btn-secondary btn-sm" id="headerLoginBtn" style="font-weight: 600;">
                            Log In
                        </a>
                        <a href="{{ route('register') }}" class="btn btn-primary btn-sm" id="headerRegisterBtn" style="font-weight: 600;">
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

                        <a href="{{ route($userDashboardRoute) }}" class="btn btn-secondary btn-sm desktop-only" style="display: inline-flex; align-items: center; gap: 0.35rem;" title="Go to My Dashboard">
                            <span>📊</span>
                            <span>Dashboard</span>
                        </a>

                        <a href="{{ route('settings.show') }}" class="header-action-btn" title="Account Settings" aria-label="Account Settings">
                            ⚙️
                        </a>

                        <form action="{{ route('logout') }}" method="POST" style="margin: 0; display: inline;">
                            @csrf
                            <button type="submit" class="header-action-btn" title="Sign Out" aria-label="Sign Out">
                                🚪
                            </button>
                        </form>
                    @endauth
                </div>
            </header>

            <!-- Main Canvas -->
            <main class="content-canvas">
                <!-- Flash Notification Messages -->
                @if(session('success'))
                    <div class="flash-alert flash-success" role="alert">
                        <span style="font-size: 1.1rem;">✓</span>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if(session('error'))
                    <div class="flash-alert flash-error" role="alert">
                        <span style="font-size: 1.1rem;">✗</span>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <!-- Minimal JavaScript for Mobile Drawer Toggle -->
    <script>
        function toggleMobileMenu() {
            const sidebar = document.getElementById('appSidebar');
            const backdrop = document.getElementById('drawerBackdrop');
            if (sidebar && backdrop) {
                const isOpen = sidebar.classList.contains('open');
                if (isOpen) {
                    sidebar.classList.remove('open');
                    backdrop.classList.remove('active');
                } else {
                    sidebar.classList.add('open');
                    backdrop.classList.add('active');
                }
            }
        }
    </script>

    @livewireScripts
</body>
</html>
