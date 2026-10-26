@extends('layouts.app')

@section('title', 'Island Events — Curated Culinary Gatherings & Digital Ticketing')
@section('page-title', 'Welcome to Island Events')

@section('navigation')
    <div class="nav-section-title">Discovery</div>
    <a href="{{ route('events.index') }}" class="active">
        <span class="nav-icon">🌴</span>
        <span>Explore Events</span>
    </a>

    @auth
        <div class="nav-section-title">My Account</div>
        <a href="{{ route('dashboard') }}">
            <span class="nav-icon">📊</span>
            <span>Dashboard</span>
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
<div class="welcome-experience-container">
    <!-- Hero Banner -->
    <div class="card" style="background: linear-gradient(135deg, var(--color-navy) 0%, #1e1b4b 100%); color: var(--color-white); border: none; padding: 3rem 2.5rem; position: relative; overflow: hidden; margin-bottom: 2rem;">
        <div style="position: absolute; right: -40px; bottom: -40px; font-size: 14rem; opacity: 0.04; pointer-events: none; user-select: none;">🌴</div>
        <div style="max-width: 700px; position: relative; z-index: 1;">
            <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(79, 70, 229, 0.25); border: 1px solid rgba(129, 140, 248, 0.4); padding: 0.35rem 0.85rem; border-radius: var(--radius-full); font-size: 0.78rem; font-weight: 700; color: #C7D2FE; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1.25rem;">
                <span>🇺🇸</span> United States Premier Event Platform
            </div>
            <h1 style="font-size: 2.5rem; font-weight: 800; line-height: 1.15; letter-spacing: -0.025em; margin-bottom: 1rem; color: #FFFFFF;">
                Curated Culinary Gatherings & Exclusive Island Experiences
            </h1>
            <p style="font-size: 1.1rem; line-height: 1.6; color: #CBD5E1; margin-bottom: 2rem; max-width: 620px;">
                Secure digital entry passes, reserve authentic handcrafted plates in advance, and enjoy streamlined, verified QR access at premier cultural and culinary events across the United States.
            </p>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="{{ route('events.index') }}" class="btn btn-primary" style="background: var(--color-indigo); padding: 0.85rem 1.75rem; font-size: 1rem; font-weight: 600;">
                    <span>🌴 Browse Live Events</span>
                </a>
                @guest
                    <a href="{{ route('register') }}" class="btn" style="background: rgba(255, 255, 255, 0.1); color: #FFFFFF; border: 1px solid rgba(255, 255, 255, 0.25); padding: 0.85rem 1.75rem; font-size: 1rem; font-weight: 600;">
                        <span>Create Guest Account</span>
                    </a>
                @else
                    <a href="{{ route('dashboard') }}" class="btn" style="background: rgba(255, 255, 255, 0.1); color: #FFFFFF; border: 1px solid rgba(255, 255, 255, 0.25); padding: 0.85rem 1.75rem; font-size: 1rem; font-weight: 600;">
                        <span>Go to Dashboard</span>
                    </a>
                @endguest
            </div>
        </div>
    </div>

    <!-- 3 Value Pillars -->
    <div class="grid-3" style="margin-bottom: 2rem;">
        <div class="card" style="padding: 1.75rem;">
            <div style="width: 44px; height: 44px; border-radius: var(--radius-md); background: var(--color-indigo-soft); display: flex; align-items: center; justify-content: center; font-size: 1.35rem; margin-bottom: 1.25rem;">
                🎟️
            </div>
            <h2 style="font-size: 1.15rem; font-weight: 700; color: var(--color-navy); margin-bottom: 0.5rem;">Guaranteed Entry Passes</h2>
            <p style="color: var(--color-text-secondary); font-size: 0.9rem; line-height: 1.55;">
                Every reservation issues a unique, cryptographically signed QR pass for rapid gate validation and anti-counterfeit protection.
            </p>
        </div>

        <div class="card" style="padding: 1.75rem;">
            <div style="width: 44px; height: 44px; border-radius: var(--radius-md); background: #DCFCE7; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; margin-bottom: 1.25rem;">
                🍗
            </div>
            <h2 style="font-size: 1.15rem; font-weight: 700; color: var(--color-navy); margin-bottom: 0.5rem;">Advance Food Pre-Orders</h2>
            <p style="color: var(--color-text-secondary); font-size: 0.9rem; line-height: 1.55;">
                Pre-order signature dishes like Jamaican Jerk Chicken, Braised Oxtail, and Festival dumplings directly from verified culinary vendors.
            </p>
        </div>

        <div class="card" style="padding: 1.75rem;">
            <div style="width: 44px; height: 44px; border-radius: var(--radius-md); background: #FEF3C7; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; margin-bottom: 1.25rem;">
                ⚡
            </div>
            <h2 style="font-size: 1.15rem; font-weight: 700; color: var(--color-navy); margin-bottom: 0.5rem;">Express QR Fulfillment</h2>
            <p style="color: var(--color-text-secondary); font-size: 0.9rem; line-height: 1.55;">
                Collect your meals at dedicated pre-order stations without enduring long queues, verified in real-time through staff worker terminals.
            </p>
        </div>
    </div>

    <!-- Host Callout -->
    <div class="card" style="background: var(--color-white); border: 1px solid var(--color-border); padding: 2rem 2.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;">
        <div>
            <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--color-navy); margin-bottom: 0.25rem;">Are you an Event Organizer or Culinary Vendor?</h2>
            <p style="color: var(--color-text-secondary); font-size: 0.92rem; margin: 0;">
                Host your food festival, cultural showcase, or private gathering with real-time inventory and revenue tracking.
            </p>
        </div>
        <a href="{{ route('host.apply') }}" class="btn" style="background: var(--color-navy); color: var(--color-white); padding: 0.75rem 1.5rem; font-weight: 600; font-size: 0.92rem;">
            <span>Apply as Event Host →</span>
        </a>
    </div>
</div>
@endsection
