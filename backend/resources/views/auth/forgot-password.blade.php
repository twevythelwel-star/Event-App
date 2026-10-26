@extends('layouts.app')

@section('title', 'Forgot Password — Island Events & Ticketing')

@section('content')
<div class="max-w-md mx-auto my-8 sm:my-14">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-7 sm:p-8 space-y-6">
        <div class="text-center space-y-2">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                Account Recovery
            </span>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Reset Your Password</h1>
            <p class="text-xs sm:text-sm text-slate-500">Enter your registered email address and we'll dispatch an expiring password-reset link.</p>
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

        <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Email Address</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="name@example.com" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
            </div>

            <button type="submit" class="w-full py-2.5 px-4 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-colors shadow-sm shadow-indigo-200 mt-2">
                Send Reset Link
            </button>
        </form>

        <div class="text-center pt-4 border-t border-slate-100 text-xs text-slate-500">
            Remember your credentials?
            <a href="{{ route('login') }}" class="font-semibold text-indigo-600 hover:text-indigo-700 ml-1">Back to sign in</a>
        </div>
    </div>
</div>
@endsection
