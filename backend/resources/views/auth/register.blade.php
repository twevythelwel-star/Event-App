@extends('layouts.app')

@section('title', 'Create Account — Island Events & Ticketing')

@section('content')
<div class="max-w-lg mx-auto my-8 sm:my-12">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-7 sm:p-8 space-y-6">
        <div class="text-center space-y-2">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-100">
                Attendee Registration
            </span>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Create Your Account</h1>
            <p class="text-xs sm:text-sm text-slate-500">Sign up to purchase tickets, pre-order culinary dishes, and receive instant digital QR passes.</p>
        </div>

        @if ($errors->any())
            <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 text-xs sm:text-sm space-y-1">
                <div class="font-semibold mb-1">Please fix the following:</div>
                @foreach ($errors->all() as $error)
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-rose-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>{{ $error }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="first_name" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">First Name <span class="text-rose-500">*</span></label>
                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" required autofocus placeholder="e.g. Marcus" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>
                <div>
                    <label for="last_name" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Last Name <span class="text-rose-500">*</span></label>
                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" required placeholder="e.g. Garvey" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>
            </div>

            <div>
                <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Email Address <span class="text-rose-500">*</span></label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="name@example.com" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
            </div>

            <div>
                <label for="phone" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Mobile Phone (Optional for SMS Digital Passes)</label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" placeholder="+1 (305) 555-0199" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                <p class="text-[11px] text-slate-400 mt-1">Supports United States (+1) mobile carriers for instant SMS pass delivery.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Password <span class="text-rose-500">*</span></label>
                    <input type="password" id="password" name="password" required autocomplete="new-password" placeholder="Min 8 characters" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>
                <div>
                    <label for="password_confirmation" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Confirm Password <span class="text-rose-500">*</span></label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" placeholder="Repeat password" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>
            </div>

            <div>
                <label for="preferred_display_currency" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Preferred Display Currency</label>
                <select id="preferred_display_currency" name="preferred_display_currency" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                    <option value="JMD" {{ old('preferred_display_currency', 'JMD') === 'JMD' ? 'selected' : '' }}>JMD — Jamaican Dollar (J$)</option>
                    <option value="USD" {{ old('preferred_display_currency') === 'USD' ? 'selected' : '' }}>USD — United States Dollar (US$)</option>
                </select>
            </div>

            <div class="flex items-start gap-2.5 pt-2">
                <input type="checkbox" id="agree_terms" name="agree_terms" value="1" {{ old('agree_terms') ? 'checked' : '' }} required class="w-4 h-4 mt-0.5 rounded text-indigo-600 border-slate-300 focus:ring-indigo-500 cursor-pointer">
                <label for="agree_terms" class="text-xs text-slate-600 leading-relaxed cursor-pointer select-none">
                    I agree to the <span class="text-indigo-600 font-medium">Terms of Service</span>, meal collection guidelines, and acknowledge the <span class="text-indigo-600 font-medium">Privacy Policy</span>.
                </label>
            </div>

            <button type="submit" class="w-full py-2.5 px-4 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-colors shadow-sm shadow-indigo-200 mt-2">
                Create My Account
            </button>
        </form>

        <div class="text-center pt-4 border-t border-slate-100 text-xs text-slate-500">
            Already have an account?
            <a href="{{ route('login') }}" class="font-semibold text-indigo-600 hover:text-indigo-700 ml-1">Sign in here</a>
        </div>
    </div>
</div>
@endsection
