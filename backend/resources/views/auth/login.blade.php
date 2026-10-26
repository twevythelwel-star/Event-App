@extends('layouts.app')

@section('title', 'Sign In — Island Events & Ticketing')

@section('content')
<div class="max-w-md mx-auto my-8 sm:my-14">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-7 sm:p-8 space-y-6">
        <div class="text-center space-y-2">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-100">
                Secure Authentication
            </span>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Welcome Back</h1>
            <p class="text-xs sm:text-sm text-slate-500">Sign in to access your digital tickets, kitchen pre-orders, and management tools.</p>
        </div>

        @if (session('status'))
            <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-xs sm:text-sm flex items-start gap-2.5">
                <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 text-xs sm:text-sm space-y-1">
                @foreach ($errors->all() as $error)
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-rose-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>{{ $error }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Email Address</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="name@example.com" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
            </div>

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">Password</label>
                    <a href="{{ route('password.request') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-700 transition-colors">Forgot password?</a>
                </div>
                <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Enter password" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
            </div>

            <div class="flex items-center gap-2 pt-1">
                <input type="checkbox" id="remember" name="remember" value="1" {{ old('remember') ? 'checked' : '' }} class="w-4 h-4 rounded text-indigo-600 border-slate-300 focus:ring-indigo-500 cursor-pointer">
                <label for="remember" class="text-xs text-slate-600 cursor-pointer select-none">Remember this browser for 30 days</label>
            </div>

            <button type="submit" class="w-full py-2.5 px-4 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-colors shadow-sm shadow-indigo-200 mt-2">
                Sign In
            </button>
        </form>

        <div class="text-center pt-4 border-t border-slate-100 text-xs text-slate-500">
            Don't have an account yet?
            <a href="{{ route('register') }}" class="font-semibold text-indigo-600 hover:text-indigo-700 ml-1">Create attendee account</a>
        </div>

        <!-- Role-Based Access Demo Quick-Selector -->
        <div class="mt-6 pt-5 border-t border-slate-200/80">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-600">Platform Profiles & Login Presets</span>
                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-100">Click to Pre-Fill</span>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-left">
                <!-- System Admin -->
                <button type="button" onclick="fillCredentials('superadmin@islandevents.example', 'SecureIslandSuperPass2026!#')" class="p-2.5 rounded-xl border border-purple-200 bg-purple-50/70 hover:bg-purple-100/80 transition-all text-left cursor-pointer group">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-purple-900 group-hover:text-purple-950">System Admin</span>
                        <span class="text-[10px] font-mono text-purple-700 bg-purple-200/60 px-1.5 py-0.5 rounded font-semibold">Root</span>
                    </div>
                    <div class="text-[11px] text-purple-700 truncate mt-0.5">superadmin@islandevents.example</div>
                    <div class="text-[10px] text-purple-600/80 mt-1">Platform health, logs & provisioning</div>
                </button>

                <!-- Delegated Admin -->
                <button type="button" onclick="fillCredentials('admin@islandevents.jm', 'Password123!')" class="p-2.5 rounded-xl border border-blue-200 bg-blue-50/70 hover:bg-blue-100/80 transition-all text-left cursor-pointer group">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-blue-900 group-hover:text-blue-950">Administrator</span>
                        <span class="text-[10px] font-mono text-blue-700 bg-blue-200/60 px-1.5 py-0.5 rounded font-semibold">Ops</span>
                    </div>
                    <div class="text-[11px] text-blue-700 truncate mt-0.5">admin@islandevents.jm</div>
                    <div class="text-[10px] text-blue-600/80 mt-1">Disputes, host approval & moderation</div>
                </button>

                <!-- Event Host -->
                <button type="button" onclick="fillCredentials('host@islandevents.jm', 'Password123!')" class="p-2.5 rounded-xl border border-amber-200 bg-amber-50/70 hover:bg-amber-100/80 transition-all text-left cursor-pointer group">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-amber-900 group-hover:text-amber-950">Event Host</span>
                        <span class="text-[10px] font-mono text-amber-700 bg-amber-200/60 px-1.5 py-0.5 rounded font-semibold">Organizer</span>
                    </div>
                    <div class="text-[11px] text-amber-700 truncate mt-0.5">host@islandevents.jm</div>
                    <div class="text-[10px] text-amber-600/80 mt-1">Studio, menu, orders & cash receipts</div>
                </button>

                <!-- Attendee -->
                <button type="button" onclick="fillCredentials('attendee@islandevents.jm', 'Password123!')" class="p-2.5 rounded-xl border border-emerald-200 bg-emerald-50/70 hover:bg-emerald-100/80 transition-all text-left cursor-pointer group">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-emerald-900 group-hover:text-emerald-950">Attendee</span>
                        <span class="text-[10px] font-mono text-emerald-700 bg-emerald-200/60 px-1.5 py-0.5 rounded font-semibold">Default</span>
                    </div>
                    <div class="text-[11px] text-emerald-700 truncate mt-0.5">attendee@islandevents.jm</div>
                    <div class="text-[10px] text-emerald-600/80 mt-1">Orders, active tickets & QR pass</div>
                </button>
            </div>
            
            <p class="text-[11px] text-slate-500 mt-2.5 text-center">Click any role card to automatically populate the credentials.</p>
        </div>
    </div>
</div>

<script>
function fillCredentials(email, password) {
    var emailField = document.getElementById('email');
    var passwordField = document.getElementById('password');
    if (emailField && passwordField) {
        emailField.value = email;
        passwordField.value = password;
        passwordField.focus();
    }
}
</script>
@endsection
