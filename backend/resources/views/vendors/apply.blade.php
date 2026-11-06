@extends('layouts.app')

@section('title', 'Apply as a vendor — ' . $event->title)
@section('page-title', 'Vendor application')

@section('content')
@php $fmt = fn (int $minor) => \App\Support\Money::formatMinor($minor, $currency); @endphp
<div class="eb-stack" style="gap: var(--space-5); max-width: 760px;">
    <div>
        <p class="eb-text-supporting" style="margin: 0;"><a href="{{ route('events.show', $event->id) }}">← {{ $event->title }}</a></p>
        <h1 class="eb-text-page-title" style="margin: 0;">Take part as a vendor, exhibitor or sponsor</h1>
        <p class="eb-text-supporting" style="margin: 0;">Apply to {{ $event->organizer_name ?: 'the host' }}. They review every application and reply here, under <a href="{{ route('vendors.mine') }}">My vendor applications</a>.</p>
    </div>

    @if($existing)
        <x-alert type="info" title="You have already applied">Your application is {{ strtolower($existing->statusLabel()) }}. <a href="{{ route('vendors.mine') }}">See it</a>.</x-alert>
    @elseif(!$state['open'])
        <x-alert type="warning" title="Not taking applications">{{ $state['reason'] }}</x-alert>
    @else
        @if($offers->isNotEmpty())
            <section class="eb-panel" style="margin: 0;" aria-labelledby="booths-heading">
                <h2 id="booths-heading" class="eb-text-card-title" style="margin: 0 0 var(--space-2);">Booths</h2>
                <ul class="eb-stack" style="gap: var(--space-2); margin: 0; padding-left: 1.1rem;">
                    @foreach($offers as $offer)
                        <li><strong>{{ $offer->name }}</strong> · {{ $offer->price_minor > 0 ? $fmt($offer->price_minor) : 'No fee' }}
                            @if($offer->category) · for {{ strtolower(\App\Models\VendorApplication::CATEGORIES[$offer->category]) }}s @endif
                            @if($offer->remaining() !== null) · {{ $offer->remaining() === 0 ? 'fully booked' : $offer->remaining() . ' left' }} @endif
                            @if($offer->description)<br><span class="eb-help-text">{{ $offer->description }}</span>@endif</li>
                    @endforeach
                </ul>
                <p class="eb-help-text" style="margin: var(--space-2) 0 0;">Fees are agreed with the host when they approve you and paid to them directly.</p>
            </section>
        @endif

        <form method="POST" action="{{ route('vendors.apply.store', $event->id) }}" class="eb-panel eb-stack" style="gap: var(--space-3); margin: 0;">
            @csrf
            @error('application')<x-alert type="error">{{ $message }}</x-alert>@enderror
            <div class="eb-grid-2">
                <x-form-field name="category" id="v-category" type="select" label="I am a" :value="old('category', 'food')" :options="\App\Models\VendorApplication::CATEGORIES" :required="true" />
                @if($offers->isNotEmpty())
                    <x-form-field name="offer_id" id="v-offer" type="select" label="Booth I would like" :value="old('offer_id')" :options="['' => 'No preference'] + $offers->where(fn ($o) => $o->remaining() !== 0)->pluck('name', 'id')->all()" />
                @endif
                <x-form-field name="business_name" id="v-business" label="Business name" :value="old('business_name')" :required="true" />
                <x-form-field name="contact_name" id="v-contact" label="Contact name" :value="old('contact_name', auth()->user()->name)" :required="true" />
                <x-form-field name="email" id="v-email" type="email" label="Email" :value="old('email', auth()->user()->email)" :required="true" />
                <x-form-field name="phone" id="v-phone" type="tel" label="Phone" :value="old('phone', auth()->user()->phone)" :required="true" />
            </div>
            <x-form-field name="description" id="v-description" type="textarea" label="What you will sell, show or sponsor" :value="old('description')" :required="true" />
            <x-form-field name="website" id="v-website" label="Website (optional, https://)" :value="old('website')" />
            <x-form-field name="needs" id="v-needs" type="textarea" label="What you need (optional)" :value="old('needs')" placeholder="e.g. power, water, a table, space for a grill" />
            <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="show_publicly" value="1" @checked(old('show_publicly', true))><span>If approved, list my business on the event page</span></label>
            @if($event->vendor_terms)
                <details class="eb-panel" style="margin: 0;"><summary class="eb-text-label">Vendor terms</summary><p style="white-space: pre-line; margin: var(--space-2) 0 0;">{{ $event->vendor_terms }}</p></details>
                <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="agree" value="1" required><span>I agree to the vendor terms</span></label>
                @error('agree')<div class="eb-field-error" role="alert"><span>{{ $message }}</span></div>@enderror
            @endif
            <div><button type="submit" class="eb-btn eb-btn-primary" data-prevent-double-submit="true">Send application</button></div>
        </form>
    @endif
</div>
@endsection
