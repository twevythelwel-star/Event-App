@extends('layouts.app')

@section('title', 'Host applications — EventBite')
@section('page-title', 'Host applications')

@section('content')
<div class="eb-stack" style="gap: var(--layout-section-gap);">
    <x-page-header title="Host applications"
                   :badge="$pendingApplications->count() . ' waiting'"
                   description="Approving gives the applicant the host role and an organizer for their events. Every decision is recorded in the audit log."
                   :breadcrumbs="['Admin console' => route('admin.console.overview'), 'Host applications' => null]" />

    @if($pendingApplications->isEmpty())
        <x-empty-state icon="users" title="No applications waiting" description="New host applications appear here for review." />
    @else
        @foreach($pendingApplications as $app)
            @php
                $site = (string) $app->website_or_social;
                // Only real web addresses become links; anything else (including javascript: URLs) is shown as text.
                $siteIsLink = preg_match('#^https?://#i', $site) === 1;
            @endphp
            <x-card :title="$app->organization_name" :subtitle="'Submitted ' . $app->created_at->format('F j, Y g:i A T') . ' · ' . ($app->country === 'US' ? 'United States' : $app->country)">
                <dl class="eb-grid-3" style="margin: 0 0 var(--space-4);">
                    <div><dt class="eb-text-label">Applicant</dt><dd style="margin: 0;">{{ $app->user?->name ?? 'Account removed' }}</dd></div>
                    <div><dt class="eb-text-label">Email</dt><dd style="margin: 0;">{{ $app->user?->email ?? '—' }}</dd></div>
                    <div><dt class="eb-text-label">Phone</dt><dd style="margin: 0;">{{ $app->contact_phone }}</dd></div>
                    @if($site !== '')
                        <div><dt class="eb-text-label">Website or social</dt><dd style="margin: 0; overflow-wrap: anywhere;">
                            @if($siteIsLink)<a href="{{ $site }}" target="_blank" rel="noopener noreferrer nofollow">{{ $site }}</a>@else{{ $site }}@endif
                        </dd></div>
                    @endif
                </dl>
                @if($app->description)
                    <p class="eb-text-body" style="margin: 0 0 var(--space-4);">{{ $app->description }}</p>
                @endif
                <div class="eb-cluster" style="justify-content: flex-end;">
                    <form action="{{ route('admin.host-applications.reject', $app->id) }}" method="POST" class="eb-cluster" data-reject-form>
                        @csrf
                        <label class="sr-only" for="reason-{{ $app->id }}">Reason for rejecting</label>
                        <input id="reason-{{ $app->id }}" name="reason" class="eb-input" style="min-width: 16rem;" required minlength="5" maxlength="1000"
                               placeholder="Reason (shown to the applicant)">
                        <button type="submit" class="eb-btn eb-btn-destructive eb-btn-sm">Reject</button>
                    </form>
                    <form action="{{ route('admin.host-applications.approve', $app->id) }}" method="POST"
                          onsubmit="return confirm('Approve this applicant as an event host?');">
                        @csrf
                        <button type="submit" class="eb-btn eb-btn-primary eb-btn-sm">Approve</button>
                    </form>
                </div>
            </x-card>
        @endforeach
    @endif
</div>
@endsection
