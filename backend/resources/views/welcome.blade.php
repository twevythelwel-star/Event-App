@extends('layouts.app')

@section('title', 'Island Event — Live the Moment')
@section('page-title', 'Welcome to Island Event')

@section('navigation')
    <div class="nav-section-title">Discovery</div>
    <a href="{{ route('events.index') }}" class="{{ request()->routeIs('events.*') ? 'active' : '' }}">
        <span class="nav-icon">🌴</span>
        <span>Explore Events</span>
    </a>

    @auth
        @php
            $activeRole = Auth::user()->current_context_role ?? Auth::user()->role;
        @endphp
        <div class="nav-section-title">My Account</div>
        @if($activeRole === 'system_admin' || Auth::user()->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN))
            <a href="{{ route('system-admin.dashboard') }}">
                <span class="nav-icon">🛡️</span>
                <span>System Governance</span>
            </a>
        @endif
        @if($activeRole === 'admin' || Auth::user()->hasRole(\App\Enums\AccountType::ADMINISTRATOR))
            <a href="{{ route('admin.dashboard') }}">
                <span class="nav-icon">📋</span>
                <span>Admin Operations</span>
            </a>
        @endif
        @if($activeRole === 'event_host' || Auth::user()->hasRole(\App\Enums\AccountType::EVENT_HOST))
            <a href="{{ route('host.dashboard') }}">
                <span class="nav-icon">🎪</span>
                <span>Host Dashboard</span>
            </a>
        @endif
        <a href="{{ route('attendee.dashboard') }}">
            <span class="nav-icon">🎟️</span>
            <span>My Orders & Tickets</span>
        </a>
        <a href="{{ route('settings.show') }}">
            <span class="nav-icon">⚙️</span>
            <span>Preferences</span>
        </a>
    @else
        <div class="nav-section-title">Access</div>
        <a href="{{ route('login') }}">
            <span class="nav-icon">🔑</span>
            <span>Sign In</span>
        </a>
        <a href="{{ route('register') }}">
            <span class="nav-icon">✨</span>
            <span>Create Account</span>
        </a>
    @endauth
@endsection

@section('content')
<style>
    /* ==========================================================================
       Island Event Cinematic Welcome & Onboarding Experience
       ========================================================================== */
    .welcome-screen-wrap {
        position: relative;
        width: 100%;
        margin-top: -0.5rem;
    }

    /* Cinematic Full-Screen Hero Visual */
    .hero-cinematic {
        position: relative;
        min-height: 85vh;
        border-radius: var(--radius-xl, 24px);
        background-color: #0f172a;
        background-image: 
            linear-gradient(to top, rgba(15, 23, 42, 0.96) 0%, rgba(15, 23, 42, 0.6) 45%, rgba(15, 23, 42, 0.3) 100%),
            url('{{ asset('images/island_festival_sunset.jpg') }}');
        background-size: cover;
        background-position: center bottom;
        background-repeat: no-repeat;
        box-shadow: 0 24px 48px -12px rgba(15, 23, 42, 0.25);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        padding: 3.5rem 3rem;
        color: #ffffff;
    }

    @media (max-width: 768px) {
        .hero-cinematic {
            min-height: 88vh;
            padding: 2rem 1.5rem;
            border-radius: var(--radius-lg, 16px);
            justify-content: flex-end;
        }
    }

    /* Ambient Subtle Glow */
    .hero-cinematic::before {
        content: "";
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at 80% 20%, rgba(244, 63, 94, 0.15), transparent 40%),
                    radial-gradient(circle at 20% 80%, rgba(124, 58, 237, 0.2), transparent 50%);
        pointer-events: none;
    }

    .hero-content {
        position: relative;
        z-index: 2;
        max-width: 720px;
    }

    /* Brand Header Badge */
    .hero-brand-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 14px;
        border-radius: 9999px;
        background: rgba(255, 255, 255, 0.14);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.25);
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #ffffff;
        margin-bottom: 1.25rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    /* Headline: Live the Moment */
    .hero-headline {
        font-size: 3.75rem;
        font-weight: 900;
        letter-spacing: -0.04em;
        line-height: 1.05;
        color: #ffffff;
        margin: 0 0 1.1rem;
        text-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
    }

    @media (max-width: 768px) {
        .hero-headline {
            font-size: 2.65rem;
            line-height: 1.1;
        }
    }

    .hero-tagline {
        font-size: 1.15rem;
        line-height: 1.6;
        color: #e2e8f0;
        margin: 0 0 2rem;
        max-width: 580px;
        text-shadow: 0 2px 8px rgba(0, 0, 0, 0.35);
    }

    @media (max-width: 768px) {
        .hero-tagline {
            font-size: 0.98rem;
            margin-bottom: 1.65rem;
        }
    }

    /* Action Button Group */
    .hero-actions {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.5rem;
    }

    /* Main CTA: Purple-to-Coral Gradient */
    .btn-gradient-cta {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 14px 28px;
        border-radius: 9999px;
        font-size: 1rem;
        font-weight: 800;
        color: #ffffff;
        text-decoration: none;
        background: linear-gradient(135deg, #7c3aed 0%, #ec4899 50%, #f97316 100%);
        border: none;
        cursor: pointer;
        box-shadow: 0 8px 24px -4px rgba(236, 72, 153, 0.45), 0 0 0 1px rgba(255, 255, 255, 0.2) inset;
        transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        min-height: 48px;
    }

    .btn-gradient-cta:hover {
        transform: translateY(-2px) scale(1.02);
        box-shadow: 0 12px 30px -4px rgba(236, 72, 153, 0.6), 0 0 0 1px rgba(255, 255, 255, 0.3) inset;
        color: #ffffff;
    }

    .btn-gradient-cta:active {
        transform: translateY(0) scale(1);
    }

    /* Secondary Public Discovery Button */
    .btn-ghost-discovery {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 14px 24px;
        border-radius: 9999px;
        font-size: 0.95rem;
        font-weight: 700;
        color: #ffffff;
        text-decoration: none;
        background: rgba(255, 255, 255, 0.12);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        cursor: pointer;
        transition: all 0.2s ease;
        min-height: 48px;
    }

    .btn-ghost-discovery:hover {
        background: rgba(255, 255, 255, 0.22);
        border-color: rgba(255, 255, 255, 0.5);
        transform: translateY(-1px);
        color: #ffffff;
    }

    /* Discreet Login Link for Returning Users */
    .returning-user-row {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.86rem;
        color: #cbd5e1;
    }

    .returning-user-row a {
        color: #ffffff;
        font-weight: 700;
        text-decoration: underline;
        text-underline-offset: 3px;
        transition: color 0.15s ease;
    }

    .returning-user-row a:hover {
        color: #fbcfe8;
    }

    /* Editorial Feature Highlights (Desktop Grid) */
    .highlights-section {
        margin-top: 2.25rem;
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.25rem;
    }

    @media (max-width: 900px) {
        .highlights-section {
            grid-template-columns: 1fr;
        }
    }

    .highlight-card {
        background: var(--color-white, #ffffff);
        border: 1px solid var(--color-border, #e2e8f0);
        border-radius: var(--radius-lg, 16px);
        padding: 1.5rem;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        transition: transform 0.18s ease, box-shadow 0.18s ease;
    }

    .highlight-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px -4px rgba(15, 23, 42, 0.08);
    }

    .highlight-icon-wrap {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        margin-bottom: 0.5rem;
    }

    .highlight-title {
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--color-navy, #111827);
        margin: 0;
    }

    .highlight-desc {
        font-size: 0.86rem;
        color: var(--color-text-secondary, #64748b);
        line-height: 1.5;
        margin: 0;
    }
</style>

<div class="welcome-screen-wrap">
    <!-- Cinematic Full-Screen Festival Hero -->
    <div class="hero-cinematic">
        <div class="hero-content">
            <!-- Brand Badge -->
            <div class="hero-brand-badge">
                <span>🌴</span>
                <span>Island Event Platform</span>
            </div>

            <!-- Headline -->
            <h1 class="hero-headline">Live the Moment.</h1>

            <!-- Supporting Introduction -->
            <p class="hero-tagline">
                Curated culinary gatherings, authentic Caribbean music festivals, and streamlined digital ticketing across the United States.
            </p>

            <!-- Actions -->
            <div class="hero-actions">
                @guest
                    <!-- Primary CTA with Purple-to-Coral Gradient -->
                    <a href="{{ route('register') }}" class="btn-gradient-cta" id="welcome-get-started-btn">
                        <span>Get Started</span>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"/>
                            <polyline points="12 5 19 12 12 19"/>
                        </svg>
                    </a>

                    <!-- Clear Route into Public Event Discovery -->
                    <a href="{{ route('events.index') }}" class="btn-ghost-discovery" id="welcome-explore-events-btn">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/>
                        </svg>
                        <span>Explore Events</span>
                    </a>
                @else
                    <!-- Authenticated User Reassurance Action -->
                    <a href="{{ route('events.index') }}" class="btn-gradient-cta">
                        <span>Browse Live Events</span>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"/>
                            <polyline points="12 5 19 12 12 19"/>
                        </svg>
                    </a>

                    <a href="{{ route('attendee.dashboard') }}" class="btn-ghost-discovery">
                        <span>Go to My Dashboard</span>
                    </a>
                @endguest
            </div>

            <!-- Discreet Login Link for Returning Users -->
            @guest
                <div class="returning-user-row">
                    <span>Already have an Island Event account?</span>
                    <a href="{{ route('login') }}" id="welcome-discreet-login-link">Sign In</a>
                </div>
            @else
                <div class="returning-user-row">
                    <span>Welcome back, <strong>{{ Auth::user()->first_name ?? Auth::user()->name }}</strong>.</span>
                    <a href="{{ route('attendee.dashboard') }}">Open Tickets & Passes &rarr;</a>
                </div>
            @endguest
        </div>
    </div>

    <!-- Editorial Feature Highlights (Desktop / Tablet) -->
    <div class="highlights-section">
        <div class="highlight-card">
            <div class="highlight-icon-wrap" style="background: #eef2ff; color: #4f46e5;">
                🎟️
            </div>
            <h2 class="highlight-title">Guaranteed Digital Entry Passes</h2>
            <p class="highlight-desc">
                Instant encrypted QR admission passes sent directly to your phone via SMS and stored in your digital wallet.
            </p>
        </div>

        <div class="highlight-card">
            <div class="highlight-icon-wrap" style="background: #ecfdf5; color: #059669;">
                🍲
            </div>
            <h2 class="highlight-title">Advance Caribbean Culinary Orders</h2>
            <p class="highlight-desc">
                Pre-order authentic escovitch snapper, smoked jerk chicken, and festival meals directly from verified culinary masters.
            </p>
        </div>

        <div class="highlight-card">
            <div class="highlight-icon-wrap" style="background: #fffbeb; color: #d97706;">
                ⚡
            </div>
            <h2 class="highlight-title">Express Festival Redemptions</h2>
            <p class="highlight-desc">
                Skip long event lines with designated express meal collection stations validated in seconds by event staff scanners.
            </p>
        </div>
    </div>
</div>
@endsection
