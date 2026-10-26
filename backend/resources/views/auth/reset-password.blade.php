@extends('layouts.app')

@section('title', 'Set New Password — Island Events & Ticketing')

@section('content')
<div class="max-w-md mx-auto my-8 sm:my-14">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-7 sm:p-8 space-y-6">
        <div class="text-center space-y-2">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                Security Verification
            </span>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Set New Password</h1>
            <p class="text-xs sm:text-sm text-slate-500">Create a secure password with at least 8 characters.</p>
        </div>

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

        <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div>
                <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Email Address</label>
                <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required autofocus placeholder="name@example.com" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">New Password</label>
                <input type="password" id="password" name="password" required autocomplete="new-password" placeholder="Min 8 characters" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
            </div>

            <div>
                <label for="password_confirmation" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Confirm New Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" placeholder="Repeat new password" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
            </div>

            <button type="submit" class="w-full py-2.5 px-4 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-colors shadow-sm shadow-indigo-200 mt-2">
                Update Password & Sign In
            </button>
        </form>
    </div>
</div>
@endsection
