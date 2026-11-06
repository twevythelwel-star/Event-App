@extends('layouts.app')

@section('title', 'Accept tickets — EventBite')
@section('page-title', 'Accept tickets')

@section('content')
<div class="eb-checkout">
    @if(!$transfer || !$transfer->isOpen())
        <x-alert type="error" title="This transfer link can't be used">It has expired, was cancelled, or the tickets were already accepted. Ask the sender for a new link.</x-alert>
    @elseif(!$emailMatches)
        <x-alert type="warning" title="Sign in with the right account">These tickets were sent to {{ $transfer->to_email }}. You are signed in as {{ auth()->user()->email }}. Sign out and sign in with that email to accept them.</x-alert>
    @else
        <section class="eb-card eb-checkout-body" aria-labelledby="accept-heading">
            <h1 id="accept-heading" class="eb-text-page-title" style="margin: 0 0 var(--space-2);">Tickets for you</h1>
            <p class="eb-text-supporting" style="margin: 0 0 var(--space-3);">
                {{ $order?->items->whereNotNull('ticket_tier_id')->sum('quantity') }} {{ \Illuminate\Support\Str::plural('ticket', $order?->items->whereNotNull('ticket_tier_id')->sum('quantity')) }}
                for <strong>{{ \App\Models\Event::withoutGlobalScopes()->find($order?->event_id)?->title }}</strong>. Once you accept, they are yours, with a new QR code.</p>
            <form method="POST" action="{{ route('tickets.transfer.confirm', $token) }}">
                @csrf
                <button type="submit" class="eb-btn eb-btn-primary" data-prevent-double-submit="true">Accept the tickets</button>
            </form>
        </section>
    @endif
</div>
@endsection
