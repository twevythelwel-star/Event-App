@extends('layouts.app')

@section('title', 'Forgot Password — EventBite')

@section('content')
<style>
    /* ==========================================================================
       Dribbble-Inspired Forgot Password Experience
       ========================================================================== */
    .auth-wrapper {
        max-width: 470px;
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
        background: linear-gradient(90deg, #f59e0b 0%, #ec4899 50%, #4f46e5 100%);
    }

    .back-nav-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.8rem;
        font-weight: 600;
        color: #64748b;
        text-decoration: none;
        margin-bottom: 1.5rem;
        transition: color 0.15s ease;
    }

    .back-nav-link:hover {
        color: #0f172a;
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
        background: #fffbeb;
        color: #b45309;
        border: 1px solid #fde68a;
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
        margin-bottom: 1.35rem;
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
        border-color: #f59e0b;
        box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.15);
    }

    .btn-submit-recovery {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 13px 20px;
        font-size: 0.95rem;
        font-weight: 700;
        color: #ffffff;
        background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
        border: none;
        border-radius: 12px;
        cursor: pointer;
        box-shadow: 0 4px 14px rgba(217, 119, 6, 0.28);
        transition: all 0.15s ease;
    }

    .btn-submit-recovery:hover {
        background: linear-gradient(135deg, #b45309 0%, #92400e 100%);
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(217, 119, 6, 0.35);
    }

    .alert-status {
        padding: 0.85rem 1rem;
        border-radius: 12px;
        background-color: #dcfce7;
        border: 1px solid #bbf7d0;
        color: #15803d;
        font-size: 0.84rem;
        margin-bottom: 1.35rem;
        display: flex;
        align-items: flex-start;
        gap: 0.65rem;
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

    .security-note {
        margin-top: 1.5rem;
        padding: 0.9rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        font-size: 0.76rem;
        color: #64748b;
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
