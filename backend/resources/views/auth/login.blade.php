@extends('layouts.app')

@section('title', 'Sign In — EventBite')

@section('content')
<style>
    /* ==========================================================================
       Dribbble-Inspired Enterprise Auth Experience (Sign In / Sign Up)
       ========================================================================== */
    .auth-wrapper {
        max-width: 490px;
        margin: 2rem auto 3.5rem;
        padding: 0 1rem;
    }

    .auth-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.07), 0 0 0 1px rgba(15, 23, 42, 0.03);
        padding: 2.25rem 2.25rem 2rem;
        position: relative;
        overflow: hidden;
    }

    .auth-card::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #4f46e5 0%, #06b6d4 50%, #10b981 100%);
    }

    /* Segmented Top Pill Switcher (Dribbble Signature) */
    .auth-switcher {
        display: flex;
        background: #f1f5f9;
        padding: 4px;
        border-radius: 9999px;
        margin-bottom: 1.85rem;
        border: 1px solid #e2e8f0;
    }

    .auth-switch-btn {
        flex: 1;
        text-align: center;
        padding: 8px 16px;
        font-size: 0.86rem;
        font-weight: 700;
        border-radius: 9999px;
        text-decoration: none;
        color: #64748b;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .auth-switch-btn.active {
        background: #ffffff;
        color: #0f172a;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
    }

    .auth-switch-btn:hover:not(.active) {
        color: #1e293b;
    }

    /* Header & Branding */
    .auth-header {
        text-align: center;
        margin-bottom: 1.75rem;
    }

    .auth-brand-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        border-radius: 9999px;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        background: #eef2ff;
        color: #4f46e5;
        border: 1px solid #e0e7ff;
        margin-bottom: 0.85rem;
    }


    .auth-title {
        font-size: 1.75rem;
        font-weight: 800;
        letter-spacing: -0.03em;
        color: #0f172a;
        margin: 0 0 6px;
        line-height: 1.2;
    }

    .auth-subtitle {
        font-size: 0.86rem;
        color: #64748b;
        line-height: 1.5;
        margin: 0;
    }

    /* Social Sign-In Buttons */
    .social-auth-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-bottom: 1.5rem;
    }

    .btn-social {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 10px 14px;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        background: #ffffff;
        font-size: 0.84rem;
        font-weight: 600;
        color: #334155;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .btn-social:hover {
        background: #f8fafc;
        border-color: #94a3b8;
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(0,0,0,0.04);
    }

    /* Modern Divider */
    .auth-divider {
        display: flex;
        align-items: center;
        margin: 1.25rem 0 1.5rem;
        color: #94a3b8;
        font-size: 0.76rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .auth-divider::before,
    .auth-divider::after {
        content: "";
        flex: 1;
        height: 1px;
        background: #e2e8f0;
    }

    .auth-divider span {
        padding: 0 12px;
    }

    /* Form Fields */
    .form-group {
        margin-bottom: 1.25rem;
    }

    .form-label-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.45rem;
    }

    .form-label {
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #334155;
        display: block;
    }

    .form-link {
        font-size: 0.78rem;
        font-weight: 600;
        color: #4f46e5;
        text-decoration: none;
        transition: color 0.15s ease;
    }

    .form-link:hover {
        color: #4338ca;
        text-decoration: underline;
    }

    .input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }

    .input-icon-left {
        position: absolute;
        left: 14px;
        color: #94a3b8;
        display: flex;
        align-items: center;
        justify-content: center;
        pointer-events: none;
    }

    .auth-input {
        width: 100%;
        padding: 12px 14px 12px 42px;
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        font-size: 0.92rem;
        color: #0f172a;
        outline: none;
        transition: all 0.15s ease;
        box-sizing: border-box;
    }

    .auth-input:focus {
        background: #ffffff;
        border-color: #4f46e5;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
    }

    .input-toggle-btn {
        position: absolute;
        right: 12px;
        background: transparent;
        border: none;
        padding: 4px;
        color: #64748b;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
    }

    .input-toggle-btn:hover {
        color: #0f172a;
    }

    /* Checkbox & Security Row */
    .form-options-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin: 0.4rem 0 1.5rem;
    }

    .checkbox-label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.82rem;
        color: #475569;
        cursor: pointer;
        user-select: none;
    }

    .checkbox-label input[type="checkbox"] {
        width: 16px;
        height: 16px;
        accent-color: #4f46e5;
        cursor: pointer;
    }

    .security-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.74rem;
        font-weight: 600;
        color: #059669;
    }

    /* Primary Action CTA */
    .btn-submit-auth {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 13px 20px;
        font-size: 0.95rem;
        font-weight: 700;
        color: #ffffff;
        background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
        border: none;
        border-radius: 12px;
        cursor: pointer;
        box-shadow: 0 4px 14px rgba(79, 70, 229, 0.28);
        transition: all 0.15s ease;
    }

    .btn-submit-auth:hover {
        background: linear-gradient(135deg, #4338ca 0%, #312e81 100%);
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(79, 70, 229, 0.35);
    }

    .btn-submit-auth:active {
        transform: translateY(0);
    }

    /* Alerts */
    .alert-banner {
        padding: 0.85rem 1rem;
        border-radius: 12px;
        font-size: 0.84rem;
        margin-bottom: 1.35rem;
        display: flex;
        align-items: flex-start;
        gap: 0.65rem;
        line-height: 1.45;
    }

    .alert-status {
        background-color: #dcfce7;
        border: 1px solid #bbf7d0;
        color: #15803d;
    }

    .alert-danger {
        background-color: #fee2e2;
        border: 1px solid #fecaca;
        color: #b91c1c;
    }

    /* Local Demo Presets (Regression Safe) */
    .demo-presets-wrap {
        margin-top: 1.75rem;
        padding-top: 1.35rem;
        border-top: 1px solid #e2e8f0;
    }

    .demo-presets-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.75rem;
    }

    .demo-presets-title {
        font-size: 0.76rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #475569;
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }

    .demo-presets-pill {
        font-size: 0.68rem;
        font-weight: 700;
        padding: 0.2rem 0.55rem;
        border-radius: 9999px;
        background: #eef2ff;
        color: #4f46e5;
        border: 1px solid #e0e7ff;
    }

    .demo-presets-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.65rem;
    }

    @media (max-width: 480px) {
        .demo-presets-grid {
            grid-template-columns: 1fr;
        }
        .social-auth-grid {
            grid-template-columns: 1fr;
        }
    }

    .demo-preset-card {
        padding: 0.75rem;
        border-radius: 10px;
        text-align: left;
        cursor: pointer;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        transition: all 0.15s ease;
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
    }

    .demo-preset-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 3px 8px rgba(0,0,0,0.06);
    }

    .demo-preset-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.15rem;
    }

    .demo-preset-label {
        font-size: 0.78rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }

    .demo-preset-tag {
        font-size: 0.65rem;
        font-weight: 700;
        padding: 0.1rem 0.4rem;
        border-radius: 4px;
        text-transform: uppercase;
    }

    .demo-preset-email {
        font-size: 0.72rem;
        color: #64748b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .demo-preset-blurb {
        font-size: 0.68rem;
        color: #94a3b8;
        line-height: 1.35;
        margin-top: 0.15rem;
    }

    .fill-notification {
        display: none;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        padding: 0.5rem 0.75rem;
        margin-top: 0.75rem;
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        border-radius: 8px;
        font-size: 0.76rem;
        font-weight: 600;
        color: #047857;
        animation: fadeIn 0.2s ease-in-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-4px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<div class="auth-wrapper">
    <div class="auth-card">
        <!-- Segmented Tab Bar Switcher (Dribbble Architecture) -->
        <div class="auth-switcher">
            <a href="{{ route('login') }}" class="auth-switch-btn active" aria-current="page">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                    <polyline points="10 17 15 12 10 7"/>
                    <line x1="15" x2="3" y1="12" y2="12"/>
                </svg>
                <span>Sign In</span>
            </a>
            <a href="{{ route('register') }}" class="auth-switch-btn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <line x1="19" x2="19" y1="8" y2="14"/>
                    <line x1="22" x2="16" y1="11" y2="11"/>
                </svg>
                <span>Create Account</span>
            </a>
        </div>

        <!-- Header -->
        <div class="auth-header">
            <span class="auth-brand-badge">
                <x-brand-logo variant="icon" class="auth-brand-icon" />
                <span>EventBite Platform</span>
            </span>
            <h1 class="auth-title">Welcome Back</h1>
            <p class="auth-subtitle">Sign in to view tickets, kitchen orders, or management desks.</p>
        </div>

        {{-- Status Notification --}}
        @if (session('status'))
            <div class="alert-banner alert-status" role="status">
                <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor" style="flex-shrink: 0; margin-top: 1px;">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        {{-- Error Summary --}}
        @if ($errors->any())
            <div class="alert-banner alert-danger" role="alert">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0; margin-top: 1px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Main Sign-In Form --}}
        <form method="POST" action="{{ route('login') }}" id="loginForm">
            @csrf

            <!-- Email Address Field -->
            <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <div class="input-wrapper">
                    <span class="input-icon-left">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="20" height="16" x="2" y="4" rx="2"/>
                            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                        </svg>
                    </span>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="email"
                        placeholder="name@example.com"
                        class="auth-input"
                    >
                </div>
            </div>

            <!-- Password Field -->
            <div class="form-group">
                <div class="form-label-row">
                    <label for="password" class="form-label">Password</label>
                    <a href="{{ route('password.request') }}" class="form-link">Forgot password?</a>
                </div>
                <div class="input-wrapper">
                    <span class="input-icon-left">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </span>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••••••"
                        class="auth-input"
                        style="padding-right: 42px;"
                    >
                    <button
                        type="button"
                        id="togglePasswordBtn"
                        class="input-toggle-btn"
                        aria-label="Toggle password visibility"
                        title="Show / hide password"
                        onclick="togglePasswordVisibility()"
                    >
                        <svg id="eyeIcon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        <svg id="eyeOffIcon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                            <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
                            <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/>
                            <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/>
                            <line x1="2" x2="22" y1="2" y2="22"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Remember & Security Row -->
            <div class="form-options-row">
                <label for="remember" class="checkbox-label">
                    <input type="checkbox" id="remember" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                    <span>Remember this device</span>
                </label>

                <span class="security-badge">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/>
                        <path d="m9 12 2 2 4-4"/>
                    </svg>
                    <span>256-Bit SSL</span>
                </span>
            </div>

            <!-- Sign In Submit Button -->
            <button type="submit" class="btn-submit-auth" id="submitLoginBtn">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                    <polyline points="10 17 15 12 10 7"/>
                    <line x1="15" x2="3" y1="12" y2="12"/>
                </svg>
                <span>Sign In to Account</span>
            </button>
        </form>

        <!-- Notification pill when demo credentials clicked -->
        <div id="fillNotification" class="fill-notification" role="status">
            <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <span id="fillNotificationText">Credentials filled</span>
        </div>

        {{-- Local-development login presets: strictly rendered in APP_ENV=local per regression test invariants --}}
        @if (app()->environment('local'))
            @php
                $demoPassword = \Database\Seeders\DemoAccountsSeeder::DEMO_PASSWORD;
                $loginPresets = [
                    [
                        'label' => 'Event Host',
                        'tag' => 'Organizer',
                        'email' => 'host@islandevents.example',
                        'password' => $demoPassword,
                        'blurb' => 'Kitchen prep, menus & cash receipts',
                        'color' => '#d97706',
                        'bg' => '#fffbeb',
                        'border' => '#fde68a',
                        'icon' => '🎪'
                    ],
                    [
                        'label' => 'Attendee',
                        'tag' => 'Guest',
                        'email' => 'attendee@islandevents.example',
                        'password' => $demoPassword,
                        'blurb' => 'Orders, active tickets & QR pass',
                        'color' => '#059669',
                        'bg' => '#ecfdf5',
                        'border' => '#a7f3d0',
                        'icon' => '🎟️'
                    ],
                    [
                        'label' => 'Administrator',
                        'tag' => 'Ops',
                        'email' => 'admin@islandevents.example',
                        'password' => $demoPassword,
                        'blurb' => 'Disputes, host approval & moderation',
                        'color' => '#2563eb',
                        'bg' => '#eff6ff',
                        'border' => '#bfdbfe',
                        'icon' => '📋'
                    ],
                    [
                        'label' => 'System Admin',
                        'tag' => 'Root',
                        'email' => config('events.system_admin.email'),
                        'password' => config('events.system_admin.password') ?: $demoPassword,
                        'blurb' => 'Platform health, logs & provisioning',
                        'color' => '#7e22ce',
                        'bg' => '#faf5ff',
                        'border' => '#e9d5ff',
                        'icon' => '🛡️'
                    ],
                ];
            @endphp
            <div class="demo-presets-wrap">
                <div class="demo-presets-header">
                    <span class="demo-presets-title">
                        <span>✨</span>
                        <span>Local Demo Accounts</span>
                    </span>
                    <span class="demo-presets-pill">Click to Pre-Fill</span>
                </div>

                <div class="demo-presets-grid">
                    @foreach ($loginPresets as $preset)
                        <button
                            type="button"
                            onclick="fillCredentials(@js($preset['email']), @js($preset['password']), @js($preset['label']))"
                            class="demo-preset-card"
                            style="border-color: {{ $preset['border'] }}; background-color: {{ $preset['bg'] }};"
                        >
                            <div class="demo-preset-top">
                                <span class="demo-preset-label" style="color: {{ $preset['color'] }};">
                                    <span>{{ $preset['icon'] }}</span>
                                    <span>{{ $preset['label'] }}</span>
                                </span>
                                <span class="demo-preset-tag" style="background: #ffffff; color: {{ $preset['color'] }}; border: 1px solid {{ $preset['border'] }};">
                                    {{ $preset['tag'] }}
                                </span>
                            </div>
                            <div class="demo-preset-email" style="color: {{ $preset['color'] }};">
                                {{ $preset['email'] }}
                            </div>
                            <div class="demo-preset-blurb">
                                {{ $preset['blurb'] }}
                            </div>
                        </button>
                    @endforeach
                </div>

                <p style="font-size: 0.72rem; color: #64748b; text-align: center; margin-top: 0.65rem;">
                    Local development only. Click any card to fill credentials and test perspectives.
                </p>
            </div>
        @endif
    </div>
</div>

<script>
function fillCredentials(email, password, label) {
    var emailField = document.getElementById('email');
    var passwordField = document.getElementById('password');
    var notification = document.getElementById('fillNotification');
    var notificationText = document.getElementById('fillNotificationText');

    if (emailField && passwordField) {
        emailField.value = email;
        passwordField.value = password;
        emailField.focus();

        if (notification && notificationText) {
            notificationText.textContent = 'Loaded ' + (label || 'demo') + ' credentials into form.';
            notification.style.display = 'inline-flex';
            setTimeout(function() {
                notification.style.display = 'none';
            }, 3500);
        }
    }
}

function togglePasswordVisibility() {
    var passwordField = document.getElementById('password');
    var eyeIcon = document.getElementById('eyeIcon');
    var eyeOffIcon = document.getElementById('eyeOffIcon');
    if (!passwordField) return;

    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        if (eyeIcon) eyeIcon.style.display = 'none';
        if (eyeOffIcon) eyeOffIcon.style.display = 'block';
    } else {
        passwordField.type = 'password';
        if (eyeIcon) eyeIcon.style.display = 'block';
        if (eyeOffIcon) eyeOffIcon.style.display = 'none';
    }
}
</script>
@endsection
