@extends('layouts.app')

@section('title', 'Account Settings — Island Events')
@section('page-title', 'Account Settings')

@section('navigation')
    <div class="nav-section-title">Settings Navigation</div>
    <a href="{{ route('settings.show') }}" class="active">
        <span class="nav-icon">👤</span>
        <span>Personal Profile</span>
    </a>
    <a href="#security">
        <span class="nav-icon">🔒</span>
        <span>Password & Security</span>
    </a>
    @if(Auth::user()->getActiveRole() === \App\Enums\AccountType::SYSTEM_ADMIN)
        <a href="#system">
            <span class="nav-icon">🌐</span>
            <span>Platform Configuration</span>
        </a>
    @endif
    @if(Auth::user()->getActiveRole() === \App\Enums\AccountType::EVENT_HOST)
        <a href="#host">
            <span class="nav-icon">🎪</span>
            <span>Host Event Defaults</span>
        </a>
        <a href="#payments">
            <span class="nav-icon">💳</span>
            <span>Payment Policies</span>
        </a>
    @endif
    @if(Auth::user()->getActiveRole() === \App\Enums\AccountType::ATTENDEE)
        <a href="#currency">
            <span class="nav-icon">💱</span>
            <span>Display Currency</span>
        </a>
    @endif
@endsection

@section('content')
<div class="settings-view">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.35rem;">
                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--color-navy); letter-spacing: -0.02em;">
                    Account & Preferences
                </h1>
                <span class="badge badge-scope">Scope: {{ Auth::user()->getActiveRole()->label() }}</span>
            </div>
            <p style="color: var(--color-text-secondary); font-size: 0.92rem;">
                Manage profile credentials, scoped host preferences, and security authentication settings.
            </p>
        </div>
    </div>

    <style>
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }
        .form-label {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--color-text-primary);
        }
        .form-input {
            width: 100%;
            background: var(--color-white);
            border: 1px solid var(--color-border);
            color: var(--color-text-primary);
            padding: 0.65rem 0.85rem;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            outline: none;
            transition: border-color var(--transition-speed);
        }
        .form-input:focus {
            border-color: var(--color-indigo);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
        }
    </style>

    <!-- 1. Personal Profile (Common) -->
    <div id="profile">
        @component('components.settings-form', [
            'title' => 'Personal Profile & Contact Information',
            'description' => 'Update your official contact email, full name, and mobile telephone number.',
            'action' => route('settings.profile'),
            'scopeTag' => 'All Account Tiers'
        ])
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-input" value="{{ Auth::user()->name }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-input" value="{{ Auth::user()->email }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Mobile Phone (SMS & Alerts)</label>
                    <input type="text" name="phone" class="form-input" value="{{ Auth::user()->phone }}" placeholder="+1-555-019-2834">
                </div>
                <div class="form-group">
                    <label class="form-label">Preferred Display Currency</label>
                    <select name="preferred_display_currency" class="form-input">
                        <option value="USD" {{ (Auth::user()->preferred_display_currency ?? 'USD') === 'USD' ? 'selected' : '' }}>USD — US Dollar ($)</option>
                        <option value="JMD" {{ (Auth::user()->preferred_display_currency ?? 'USD') === 'JMD' ? 'selected' : '' }}>JMD — Jamaican Dollar (Archived)</option>
                    </select>
                </div>
            </div>
        @endcomponent
    </div>

    <!-- 2. Password & Security -->
    <div id="security">
        @component('components.settings-form', [
            'title' => 'Password & Two-Factor Authentication',
            'description' => 'Enforce strong cryptographic credentials. Minimum 8 characters required.',
            'action' => route('settings.password'),
            'scopeTag' => 'Credential Security'
        ])
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-input" required minlength="8">
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="new_password_confirmation" class="form-input" required minlength="8">
                </div>
            </div>
        @endcomponent
    </div>

    <!-- 3. Host-Specific Event Defaults -->
    @if(Auth::user()->getActiveRole() === \App\Enums\AccountType::EVENT_HOST)
    <div id="host">
        @component('components.settings-form', [
            'title' => 'Host Event Defaults & Cutoff Rules',
            'description' => 'Set default event locations, timezones, and advance food prep buffers.',
            'action' => route('settings.show'),
            'scopeTag' => 'Event Host Scope'
        ])
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Default Currency</label>
                    <select name="default_currency" class="form-input">
                        <option value="USD" selected>USD (US Dollar - $)</option>
                        <option value="JMD">JMD (Jamaican Dollar - Coming Soon)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Default Timezone</label>
                    <select name="default_timezone" class="form-input">
                        <option value="America/New_York" selected>America/New_York (Eastern Time - EDT/EST)</option>
                        <option value="America/Chicago">America/Chicago (Central Time)</option>
                        <option value="America/Los_Angeles">America/Los_Angeles (Pacific Time)</option>
                        <option value="America/Jamaica">America/Jamaica (EST / UTC-5)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Kitchen Safety Buffer %</label>
                    <input type="number" name="food_prep_buffer_percent" class="form-input" value="10" min="0" max="50">
                </div>
                <div class="form-group">
                    <label class="form-label">Default Order Cutoff (Hours Before Event)</label>
                    <input type="number" name="default_cutoff_hours" class="form-input" value="24" min="1" max="168">
                </div>
            </div>
        @endcomponent
    </div>
    @endif

    <!-- 4. System Administrator Platform Governance Settings -->
    @if(Auth::user()->getActiveRole() === \App\Enums\AccountType::SYSTEM_ADMIN)
    <div id="system">
        @component('components.settings-form', [
            'title' => 'Root Platform Infrastructure Settings',
            'description' => 'Configure connection pooling, queue concurrency, and storage buckets.',
            'action' => route('settings.show'),
            'scopeTag' => 'Superadmin Only'
        ])
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Storage Provider</label>
                    <input type="text" class="form-input" value="Cloudflare R2 (S3 Compatible)" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">Storage Bucket</label>
                    <input type="text" class="form-input" value="island-events-flyers-prod" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">Master Database Password</label>
                    <input type="text" class="form-input" value="prod_••••••••9012" readonly style="font-family: monospace;">
                </div>
                <div class="form-group">
                    <label class="form-label">Payment Webhook Secret</label>
                    <input type="text" class="form-input" value="whsec_••••••••4567" readonly style="font-family: monospace;">
                </div>
            </div>
        @endcomponent
    </div>
    @endif
</div>
@endsection
