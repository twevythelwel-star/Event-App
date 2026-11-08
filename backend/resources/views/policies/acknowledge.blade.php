@extends('layouts.app')

@section('title', 'Policy updates — EventBite')
@section('page-title', 'Policy updates')

@section('content')
<div class="eb-stack" style="gap: var(--layout-section-gap); max-width: 860px;">
    <x-page-header title="Policy updates" description="EventBite has published new or updated policies that apply to you. Read each one and tick it to accept it." />

    <x-alert :flash="true" />
    @if($errors->any())<x-alert type="error">{{ $errors->first() }}</x-alert>@endif

    @if($outstanding === [])
        <x-alert type="success">You are up to date with every policy.</x-alert>
        <div><x-button variant="primary" :href="route('home')">Continue</x-button></div>
    @else
        <x-alert type="warning" title="Why you are seeing this">
            Until you accept these, you can browse and read, but you cannot buy tickets, create or publish events, or make other changes.
        </x-alert>
        <form method="POST" action="{{ route('policies.acknowledge.store') }}" class="eb-stack">
            @csrf
            @foreach($outstanding as $name => $policy)
                <section class="eb-card eb-stack" style="padding: 1.25rem; gap: 0.6rem;" aria-labelledby="policy-{{ $name }}">
                    <div class="eb-cluster" style="justify-content: space-between; gap: 0.5rem;">
                        <h2 id="policy-{{ $name }}" class="eb-text-section-title" style="margin: 0;">{{ $policy['title'] }}</h2>
                        <span class="eb-badge {{ $policy['state'] === 'updated' ? 'eb-badge-warning' : 'eb-badge-error' }}">{{ $policy['state'] === 'updated' ? 'Updated' : 'New' }} · version {{ $policy['version'] }}</span>
                    </div>
                    <p class="eb-help-text" style="margin: 0;">
                        {{ \App\Models\Compliance\CompliancePolicyVersion::CATEGORIES[$policy['category']] ?? 'Policy' }}
                        @if($policy['effective_at']) · in effect from {{ $policy['effective_at']->format('j M Y') }}@endif
                    </p>
                    @if($policy['change_summary'] && $policy['state'] === 'updated')
                        <p style="margin: 0;"><strong>What changed:</strong> {{ $policy['change_summary'] }}</p>
                    @endif
                    <div style="white-space: pre-line; max-height: 18rem; overflow: auto; padding: 0.75rem; border: 1px solid var(--color-border); border-radius: var(--radius-sm);" tabindex="0" aria-label="Full text of {{ $policy['title'] }}">{{ $policy['body'] ?? $policy['description'] }}</div>
                    <label style="display: flex; gap: 0.6rem; align-items: flex-start;">
                        <input type="checkbox" name="policies[]" value="{{ $name }}">
                        <span>I have read and accept <strong>{{ $policy['title'] }}</strong> (version {{ $policy['version'] }}).</span>
                    </label>
                </section>
            @endforeach
            <div><x-button type="submit" variant="primary">Accept the ticked policies</x-button></div>
        </form>
    @endif
</div>
@endsection
