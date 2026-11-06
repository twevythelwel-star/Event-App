@extends('layouts.app')

@section('title', 'My vendor applications — EventBite')
@section('page-title', 'My vendor applications')

@section('content')
<div class="eb-stack" style="gap: var(--space-4); max-width: 820px;">
    <h1 class="eb-text-page-title" style="margin: 0;">My vendor applications</h1>
    @forelse($applications as $app)
        @php $currency = $app->event?->currency ?: 'USD'; @endphp
        <article class="eb-panel" style="margin: 0;" aria-labelledby="mine-{{ $app->id }}">
            <div class="eb-cluster" style="justify-content: space-between;">
                <h2 id="mine-{{ $app->id }}" class="eb-text-card-title" style="margin: 0;">{{ $app->event?->title ?? 'Event' }}</h2>
                <span class="eb-badge {{ ['approved' => 'eb-badge-success', 'declined' => 'eb-badge-error', 'pending' => 'eb-badge-warning'][$app->status] ?? '' }}">{{ $app->statusLabel() }}</span>
            </div>
            <p class="eb-text-supporting" style="margin: 4px 0;">{{ $app->business_name }} · {{ $app->categoryLabel() }} · applied {{ $app->created_at->format('M j, Y') }}</p>
            @if($app->status === 'approved')
                <p style="margin: 0;"><strong>Your booth:</strong> {{ $app->offer?->name ?? 'to be confirmed' }}{{ $app->booth_label ? ' · ' . $app->booth_label : '' }}
                    @if((int) $app->fee_minor > 0) · <strong>Fee:</strong> {{ \App\Support\Money::formatMinor((int) $app->fee_minor, $currency) }} ({{ strtolower(\App\Models\VendorApplication::FEE_STATUSES[$app->fee_status] ?? $app->fee_status) }}) — pay the host directly as they arrange.@endif</p>
            @endif
            @if($app->decision_note)<p style="margin: 4px 0 0;"><strong>From the host:</strong> {{ $app->decision_note }}</p>@endif
            @if(in_array($app->status, ['pending', 'approved'], true) && !($app->status === 'approved' && $app->fee_status === 'paid'))
                <form method="POST" action="{{ route('vendors.withdraw', $app->id) }}" onsubmit="return confirm('Withdraw this application?');" style="margin-top: var(--space-2);">
                    @csrf
                    <button class="eb-btn eb-btn-ghost eb-btn-sm" type="submit">Withdraw</button>
                </form>
            @endif
        </article>
    @empty
        <x-empty-state title="No applications yet" description="When an event is taking vendors, apply from its page." illustration="orders" />
    @endforelse
</div>
@endsection
