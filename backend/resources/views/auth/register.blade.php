@extends('layouts.app')

@section('title', 'Create Account — EventBite')

@section('content')
<style>
    /* ==========================================================================
       Dribbble-Inspired Enterprise Sign Up Experience
       ========================================================================== */
    .auth-wrapper {
        max-width: 540px;
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
        background: linear-gradient(90deg, #10b981 0%, #06b6d4 50%, #4f46e5 100%);
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

    /* Header */
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
        background: #ecfdf5;
        color: #059669;
        border: 1px solid #a7f3d0;
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
        margin-bottom: 1.2rem;
    }

    .form-row-2col {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    @media (max-width: 520px) {
        .form-row-2col {
            grid-template-columns: 1fr;
        }
        .social-auth-grid {
            grid-template-columns: 1fr;
        }
    }

    .form-label {
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #334155;
        display: block;
        margin-bottom: 0.45rem;
    }

    .form-label .required {
        color: #e11d48;
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

    .auth-input, .auth-select {
        width: 100%;
        padding: 11px 14px 11px 42px;
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        font-size: 0.92rem;
        color: #0f172a;
        outline: none;
        transition: all 0.15s ease;
        box-sizing: border-box;
    }

    .auth-input:focus, .auth-select:focus {
        background: #ffffff;
        border-color: #10b981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
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

    .field-hint {
        font-size: 0.72rem;
        color: #64748b;
        margin-top: 0.35rem;
    }

    /* Terms Checkbox */
    .terms-row {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin: 1rem 0 1.5rem;
    }

    .terms-row input[type="checkbox"] {
        width: 17px;
        height: 17px;
        margin-top: 2px;
        accent-color: #10b981;
        cursor: pointer;
        flex-shrink: 0;
    }

    .terms-label {
        font-size: 0.8rem;
        color: #475569;
        line-height: 1.5;
        cursor: pointer;
        user-select: none;
    }

    /* Submit CTA */
    .btn-submit-signup {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 13px 20px;
        font-size: 0.95rem;
        font-weight: 700;
        color: #ffffff;
        background: linear-gradient(135deg, #059669 0%, #047857 100%);
        border: none;
        border-radius: 12px;
        cursor: pointer;
        box-shadow: 0 4px 14px rgba(5, 150, 105, 0.28);
        transition: all 0.15s ease;
    }

    .btn-submit-signup:hover {
        background: linear-gradient(135deg, #047857 0%, #065f46 100%);
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(5, 150, 105, 0.35);
    }

    .btn-submit-signup:active {
        transform: translateY(0);
    }

    /* Error Alert */
    .alert-danger {
        padding: 0.85rem 1rem;
        border-radius: 12px;
        background-color: #fee2e2;
        border: 1px solid #fecaca;
        color: #b91c1c;
        font-size: 0.84rem;
        margin-bottom: 1.35rem;
        display: flex;
        align-items: flex-start;
        gap: 0.65rem;
        line-height: 1.45;
    }
</style>

<div class="auth-wrapper">
    <div class="auth-card">
        <!-- Segmented Tab Bar Switcher (Dribbble Signature) -->
        <div class="auth-switcher">
            <a href="{{ route('login') }}" class="auth-switch-btn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                    <polyline points="10 17 15 12 10 7"/>
                    <line x1="15" x2="3" y1="12" y2="12"/>
                </svg>
                <span>Sign In</span>
            </a>
            <a href="{{ route('register') }}" class="auth-switch-btn active" aria-current="page">
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
                <span>EventBite Registration</span>
            </span>
            <h1 class="auth-title">Join EventBite</h1>
            <p class="auth-subtitle">Create your attendee account to order culinary dishes and receive digital QR passes.</p>
        </div>


        {{-- Error Alerts --}}
        @if ($errors->any())
            <div class="alert-danger" role="alert">
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

        {{-- Form --}}
        <form method="POST" action="{{ route('register') }}">
            @csrf

            <!-- Name Fields (2 Columns) -->
            <div class="form-row-2col">
                <div class="form-group">
                    <label for="first_name" class="form-label">First Name <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <span class="input-icon-left">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                        </span>
                        <input
                            type="text"
                            id="first_name"
                            name="first_name"
                            value="{{ old('first_name') }}"
                            required
                            autofocus
                            placeholder="Marcus"
                            class="auth-input"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="last_name" class="form-label">Last Name <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <span class="input-icon-left">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                        </span>
                        <input
                            type="text"
                            id="last_name"
                            name="last_name"
                            value="{{ old('last_name') }}"
                            required
                            placeholder="Garvey"
                            class="auth-input"
                        >
                    </div>
                </div>
            </div>

            <!-- Email Address -->
            <div class="form-group">
                <label for="email" class="form-label">Email Address <span class="required">*</span></label>
                <div class="input-wrapper">
                    <span class="input-icon-left">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                        autocomplete="email"
                        placeholder="name@example.com"
                        class="auth-input"
                    >
                </div>
            </div>

            <!-- Mobile Phone (Optional) -->
            <div class="form-group">
                <label for="phone" class="form-label">Mobile Phone (Optional for SMS Tickets)</label>
                <div class="input-wrapper">
                    <span class="input-icon-left">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                        </svg>
                    </span>
                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        value="{{ old('phone') }}"
                        placeholder="+1 (305) 555-0199"
                        class="auth-input"
                    >
                </div>
                <div class="field-hint">Supports US (+1) carriers for instant SMS pass delivery & live status alerts.</div>
            </div>

            <!-- Password Fields (2 Columns) -->
            <div class="form-row-2col">
                <div class="form-group">
                    <label for="password" class="form-label">Password <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <span class="input-icon-left">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        </span>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            autocomplete="new-password"
                            placeholder="Min 8 characters"
                            class="auth-input"
                            style="padding-right: 40px;"
                        >
                        <button
                            type="button"
                            class="input-toggle-btn"
                            onclick="toggleInputVisibility('password')"
                            aria-label="Toggle password visibility"
                        >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password_confirmation" class="form-label">Confirm Password <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <span class="input-icon-left">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        </span>
                        <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            required
                            autocomplete="new-password"
                            placeholder="Repeat password"
                            class="auth-input"
                            style="padding-right: 40px;"
                        >
                        <button
                            type="button"
                            class="input-toggle-btn"
                            onclick="toggleInputVisibility('password_confirmation')"
                            aria-label="Toggle confirm password visibility"
                        >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Currency Preference -->
            <div class="form-group">
                <label for="preferred_display_currency" class="form-label">Preferred Currency Display</label>
                <div class="input-wrapper">
                    <span class="input-icon-left">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/>
                            <path d="M12 18V6"/>
                        </svg>
                    </span>
                    <select id="preferred_display_currency" name="preferred_display_currency" class="auth-select">
                        <option value="USD" {{ old('preferred_display_currency', 'USD') === 'USD' ? 'selected' : '' }}>USD — United States Dollar (US$)</option>
                        <option value="JMD" {{ old('preferred_display_currency') === 'JMD' ? 'selected' : '' }}>JMD — Jamaican Dollar (historical transactions)</option>
                    </select>
                </div>
            </div>

            <!-- Terms & Conditions Checkbox -->
            <div class="terms-row">
                <input type="checkbox" id="agree_terms" name="agree_terms" value="1" {{ old('agree_terms') ? 'checked' : '' }} required>
                <label for="agree_terms" class="terms-label">
                    I agree to the <span style="color: #059669; font-weight: 600;">Terms of Service</span>, culinary collection guidelines, and acknowledge the <span style="color: #059669; font-weight: 600;">Privacy Policy</span>.
                </label>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn-submit-signup">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <polyline points="16 11 18 13 22 9"/>
                </svg>
                <span>Create My Account</span>
            </button>
        </form>
    </div>
</div>

<script>
function toggleInputVisibility(fieldId) {
    var field = document.getElementById(fieldId);
    if (!field) return;
    field.type = (field.type === 'password') ? 'text' : 'password';
}
</script>
@endsection
