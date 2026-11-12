@extends('layouts.app')

@section('title', 'Forgot Password — EventBite')

@section('content')
<style>
    /* ==========================================================================
       3D Soft Realism & Dark Mode Elevation — Forgot Password Experience
       ========================================================================== */
    .auth-wrapper {
        max-width: 470px;
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
        background: linear-gradient(90deg, var(--color-warning) 0%, var(--color-accent) 50%, var(--color-purple) 100%);
    }

    .back-nav-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--color-text-secondary);
        text-decoration: none;
        margin-bottom: 1.5rem;
        transition: color 0.15s ease;
    }

    .back-nav-link:hover {
        color: var(--color-accent);
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
        background: var(--color-warning-bg);
        color: var(--color-warning-text);
        border: 1px solid var(--color-warning-border);
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
        margin-bottom: 1.35rem;
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

    .btn-submit-recovery {
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

    .btn-submit-recovery:hover {
        background-color: var(--color-accent-hover);
        background-image: var(--gradient-accent-hover-3d);
        transform: translateY(-1px);
        box-shadow: var(--shadow-btn-primary-hover);
    }

    .alert-status {
        padding: 0.85rem 1rem;
        border-radius: 12px;
        background-color: var(--color-success-bg);
        border: 1px solid var(--color-success-border);
        color: var(--color-success-text);
        font-size: 0.84rem;
        margin-bottom: 1.35rem;
        display: flex;
        align-items: flex-start;
        gap: 0.65rem;
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

    .security-note {
        margin-top: 1.5rem;
        padding: 0.9rem;
        background: var(--color-surface-recessed);
        border: 1px solid var(--color-border);
        box-shadow: var(--shadow-3d-inset);
        border-radius: 12px;
        font-size: 0.76rem;
        color: var(--color-text-secondary);
        line-height: 1.5;
        text-align: center;
    }
</style>

<div class="auth-wrapper">
    <div class="auth-card">
        <!-- Return Link -->
        <a href="{{ route('login') }}" class="back-nav-link">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"/>
                <polyline points="12 19 5 12 12 5"/>
            </svg>
            <span>Back to Sign In</span>
        </a>

        <!-- Header -->
        <div class="auth-header">
            <span class="auth-brand-badge">
                <span>🔐</span>
                <span>Account Recovery</span>
            </span>
            <h1 class="auth-title">Reset Password</h1>
            <p class="auth-subtitle">Enter your registered email address and we'll dispatch an expiring password-reset link.</p>
        </div>

        {{-- Status Notification --}}
        @if (session('status'))
            <div class="alert-status" role="status">
                <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor" style="flex-shrink: 0; margin-top: 1px;">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

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

        <!-- Form -->
        <form method="POST" action="{{ route('password.email') }}">
            @csrf

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

            <button type="submit" class="btn-submit-recovery">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="22" y1="2" x2="11" y2="13"/>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                </svg>
                <span>Send Password Reset Link</span>
            </button>
        </form>

        <div class="security-note">
            🛡️ Reset links expire after 60 minutes for security. If you don't receive an email within 2 minutes, check your spam or junk folder.
        </div>
    </div>
</div>
@endsection
