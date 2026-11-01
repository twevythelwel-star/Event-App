@extends('layouts.app')

@section('title', 'Become a host — EventBite')
@section('page-title', 'Become a host')

@section('content')
<div class="eb-stack" style="gap: var(--layout-section-gap); max-width: 860px;">
    <x-page-header title="Become an event host"
                   description="Hosts publish events, sell tickets and take food orders. An administrator reviews every application; you keep your attendee account while you wait."
                   :breadcrumbs="['My tickets & orders' => route('attendee.dashboard'), 'Become a host' => null]" />

    @if($existingApplication && $existingApplication->isPending())
        <x-alert type="info" title="Your application is being reviewed">
            We received your application for "{{ $existingApplication->organization_name }}" on {{ $existingApplication->created_at->format('F j, Y') }}. You will see the host tools here once it is approved.
        </x-alert>
    @elseif($existingApplication && $existingApplication->isRejected())
        <x-alert type="warning" title="Your last application was not approved">
            Reason given: {{ $existingApplication->rejection_reason }}. You can update your details and apply again below.
        </x-alert>
    @endif

    @if(!$existingApplication || !$existingApplication->isPending())
        <x-card title="Application" subtitle="Tell us who you are and what you plan to host.">
            <form action="{{ route('host.apply.submit') }}" method="POST" class="eb-stack" style="gap: var(--layout-form-gap);">
                @csrf
                <input type="hidden" name="country" value="US">
                <x-form-field name="organization_name" label="Organization or promoter name" :required="true"
                              :value="$existingApplication?->organization_name" placeholder="e.g. Riverside Cultural Foundation"
                              help="Shown on your event pages, tickets and receipts." />
                <div class="eb-grid-2">
                    <x-form-field name="contact_phone" type="tel" label="Contact phone" :required="true"
                                  :value="$existingApplication?->contact_phone ?? Auth::user()->phone" placeholder="+1 305 555 0100" />
                    <x-form-field name="website_or_social" label="Website or social profile (optional)"
                                  :value="$existingApplication?->website_or_social" placeholder="https://instagram.com/yourorg" />
                </div>
                <x-form-field name="description" type="textarea" label="What will you host?" :rows="4"
                              :value="$existingApplication?->description"
                              help="The kind of events, how often, and roughly how many guests." />
                <p class="eb-help-text" style="margin: 0;">EventBite currently supports events in the United States, priced in US dollars.</p>
                <div class="eb-cluster" style="justify-content: flex-end;">
                    <x-button variant="ghost" :href="route('attendee.dashboard')">Cancel</x-button>
                    <x-button type="submit" variant="primary">Submit application</x-button>
                </div>
            </form>
        </x-card>
    @endif
</div>
@endsection
