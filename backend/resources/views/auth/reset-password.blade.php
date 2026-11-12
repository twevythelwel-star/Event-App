@extends('layouts.app')

@section('title', 'Set New Password — EventBite')

@section('content')
<style>
    /* ==========================================================================
       3D Soft Realism & Dark Mode Elevation — Reset Password Experience
       ========================================================================== */
    .auth-wrapper {
        max-width: 480px;
        margin: 2.5rem auto 3.5rem;
        padding: 0 1rem;
    }

    .auth-card {
        background-color: var(--color-surface);
        background-image: var(--gradient-surface-elevated-3d);
        border: 1px solid var(--color-border-strong);
        border-top-color: rgba(255, 255, 255, 0.16);
        border-radius: 24px;
        box-shadow: var(--shadow-elevated);
        padding: 2.5rem 2.25rem 2rem;
        position: relative;
        overflow: hidden;
        color: var(--color-text-primary);
    }

    .auth-card::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--color-accent) 0%, #38BDF8 50%, var(--color-purple) 100%);
    }

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
        background: var(--color-accent-subtle);
        color: var(--color-accent);
        border: 1px solid var(--color-accent-border);
        margin-bottom: 0.85rem;
    }

    .auth-title {
        font-family: var(--font-display);
        font-size: 1.75rem;
        font-weight: 800;
        letter-spacing: -0.03em;
        color: var(--color-text-primary);
        margin: 0 0 6px;
        line-height: 1.2;
    }

    .auth-subtitle {
        font-size: 0.86rem;
        color: var(--color-text-secondary);
        line-height: 1.5;
        margin: 0;
    }

    .form-group {
        margin-bottom: 1.25rem;
    }

    .form-label {
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--color-text-primary);
        display: block;
        margin-bottom: 0.45rem;
    }

    .input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }

    .input-icon-left {
        position: absolute;
        left: 14px;
        color: var(--color-text-muted);
        display: flex;
        align-items: center;
        justify-content: center;
        pointer-events: none;
    }

    .auth-input {
        width: 100%;
        padding: 12px 14px 12px 42px;
        background-color: var(--color-surface-recessed);
        background-image: var(--gradient-surface-recessed-3d);
        border: 1px solid var(--color-border);
        border-radius: 12px;
        box-shadow: var(--shadow-3d-inset);
        font-size: 0.92rem;
        color: var(--color-text-primary);
        outline: none;
        transition: all 0.15s ease;
        box-sizing: border-box;
    }

    .auth-input:focus {
        background-color: var(--color-background-secondary);
        background-image: none;
        border-color: var(--color-border-focus);
        box-shadow: var(--shadow-focus);
    }

    .input-toggle-btn {
        position: absolute;
        right: 12px;
        background: transparent;
        border: none;
        padding: 4px;
        color: var(--color-text-secondary);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
    }

    .input-toggle-btn:hover {
        color: var(--color-text-primary);
    }

    .btn-submit-reset {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 13px 20px;
        font-size: 0.95rem;
        font-weight: 800;
        color: var(--color-accent-text);
        background-color: var(--color-accent);
        background-image: var(--gradient-accent-3d);
        border: 1px solid var(--color-accent);
        border-radius: 12px;
        cursor: pointer;
        box-shadow: var(--shadow-btn-primary);
        text-shadow: 0 1px 0 rgba(255, 255, 255, 0.35);
        transition: all 0.15s ease;
    }

    .btn-submit-reset:hover {
        background-color: var(--color-accent-hover);
        background-image: var(--gradient-accent-hover-3d);
        transform: translateY(-1px);
        box-shadow: var(--shadow-btn-primary-hover);
    }

    .alert-danger {
        padding: 0.85rem 1rem;
        border-radius: 12px;
        background-color: var(--color-error-bg);
        border: 1px solid var(--color-error-border);
        color: var(--color-error-text);
        font-size: 0.84rem;
        margin-bottom: 1.35rem;
        display: flex;
        align-items: flex-start;
        gap: 0.65rem;
    }
</style>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <span class="auth-brand-badge">
                <span>🛡️</span>
                <span>Security Verification</span>
            </span>
            <h1 class="auth-title">Set New Password</h1>
            <p class="auth-subtitle">Create a secure password with at least 8 characters to secure your account.</p>
        </div>

        {{-- Errors --}}
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

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

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
                        value="{{ old('email', $email) }}"
                        required
                        autofocus
                        placeholder="name@example.com"
                        class="auth-input"
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">New Password</label>
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
                        autocomplete="new-password"
                        placeholder="Min 8 characters"
                        class="auth-input"
                        style="padding-right: 42px;"
                    >
                    <button
                        type="button"
                        class="input-toggle-btn"
                        onclick="toggleField('password')"
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
                <label for="password_confirmation" class="form-label">Confirm New Password</label>
                <div class="input-wrapper">
                    <span class="input-icon-left">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                        placeholder="Repeat new password"
                        class="auth-input"
                        style="padding-right: 42px;"
                    >
                    <button
                        type="button"
                        class="input-toggle-btn"
                        onclick="toggleField('password_confirmation')"
                        aria-label="Toggle password confirmation visibility"
                    >
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-submit-reset">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 6 9 17l-5-5"/>
                </svg>
                <span>Update Password & Sign In</span>
            </button>
        </form>
    </div>
</div>

<script>
function toggleField(id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.type = (el.type === 'password') ? 'text' : 'password';
}
</script>
@endsection
