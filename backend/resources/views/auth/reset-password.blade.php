@extends('layouts.app')

@section('title', 'Set New Password — EventBite')

@section('content')
<style>
    /* ==========================================================================
       Dribbble-Inspired Reset Password Experience
       ========================================================================== */
    .auth-wrapper {
        max-width: 480px;
        margin: 2.5rem auto 3.5rem;
        padding: 0 1rem;
    }

    .auth-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.07), 0 0 0 1px rgba(15, 23, 42, 0.03);
        padding: 2.5rem 2.25rem 2rem;
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

    .form-group {
        margin-bottom: 1.25rem;
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

    .btn-submit-reset {
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

    .btn-submit-reset:hover {
        background: linear-gradient(135deg, #047857 0%, #065f46 100%);
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(5, 150, 105, 0.35);
    }

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
