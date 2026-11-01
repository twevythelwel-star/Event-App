@extends('layouts.app')

@section('title', 'Verify your email — EventBite')
@section('page-title', 'Verify your email')

@section('content')
<div style="max-width: 520px; margin: var(--space-10) auto;">
    <x-card>
        <div class="eb-stack" style="text-align: center; align-items: center;">
            <x-icon name="check" size="lg" />
            <h1 class="eb-text-page-title" style="margin: 0;">Verify your email</h1>
            <p class="eb-text-supporting" style="margin: 0;">
                We sent a link to <strong>{{ Auth::user()->email }}</strong>. Open it to confirm your address. Check your spam folder if it has not arrived after a few minutes.
            </p>
            <form method="POST" action="{{ route('verification.send') }}" style="width: 100%;">
                @csrf
                <x-button type="submit" variant="primary" :block="true">Send the link again</x-button>
            </form>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="eb-btn eb-btn-ghost eb-btn-sm">Sign out</button>
            </form>
        </div>
    </x-card>
</div>
@endsection
