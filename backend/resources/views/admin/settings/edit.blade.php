@extends('layouts.admin')

@section('title', 'Platform Settings')
@section('breadcrumbs')<span aria-hidden="true">›</span><span aria-current="page">Platform Settings</span>@endsection
@section('heading', 'Platform Settings')
@section('subheading', 'Defaults that apply across EventBite. Payment provider keys and other credentials are kept in the server environment and never appear here.')

@section('content')
    @php
        $value = fn (string $field) => old($field, $settings->{$field});
        $selectedCurrencies = (array) old('supported_currencies', $settings->supported_currencies ?? []);
        $selectedMethods = (array) old('supported_payment_methods', $settings->supported_payment_methods ?? []);
    @endphp
    <form method="POST" action="{{ route('admin.console.settings.update') }}">
        @csrf
        <section class="ac-card" aria-labelledby="brand-heading">
            <div class="ac-card-head"><h2 id="brand-heading">Branding and region</h2></div>
            <div class="ac-card-body ac-form-row">
                <div class="ac-field">
                    <label for="platform_name">Platform name</label>
                    <input class="ac-input" id="platform_name" name="platform_name" value="{{ $value('platform_name') }}" required maxlength="80" @error('platform_name') aria-invalid="true" @enderror>
                    @error('platform_name')<span class="ac-error">{{ $message }}</span>@enderror
                </div>
                <div class="ac-field">
                    <label for="default_timezone">Default time zone</label>
                    <select class="ac-select" id="default_timezone" name="default_timezone">
                        @foreach($timezones as $zone)<option value="{{ $zone }}" @selected($value('default_timezone') === $zone)>{{ $zone }}</option>@endforeach
                    </select>
                </div>
                <div class="ac-field">
                    <label for="default_locale">Default language</label>
                    <select class="ac-select" id="default_locale" name="default_locale">
                        @foreach($locales as $code => $label)<option value="{{ $code }}" @selected($value('default_locale') === $code)>{{ $label }}</option>@endforeach
                    </select>
                </div>
            </div>
        </section>

        <section class="ac-card ac-section" aria-labelledby="payments-heading" style="margin-top:16px">
            <div class="ac-card-head"><h2 id="payments-heading">Event defaults and payments</h2></div>
            <div class="ac-card-body ac-form-row">
                <fieldset style="border:0;padding:0;margin:0">
                    <legend class="ac-label">Currencies hosts can price in</legend>
                    @foreach($currencies as $currency)
                        <label class="ac-check"><input type="checkbox" name="supported_currencies[]" value="{{ $currency }}" @checked(in_array($currency, $selectedCurrencies, true))> {{ $currency }}</label>
                    @endforeach
                    @error('supported_currencies')<span class="ac-error">{{ $message }}</span>@enderror
                </fieldset>
                <fieldset style="border:0;padding:0;margin:0">
                    <legend class="ac-label">Payment methods attendees can use</legend>
                    @foreach($paymentMethods as $method => $label)
                        <label class="ac-check"><input type="checkbox" name="supported_payment_methods[]" value="{{ $method }}" @checked(in_array($method, $selectedMethods, true))> {{ $label }}</label>
                    @endforeach
                    <span class="ac-hint">Each host still sets their own account details and instructions.</span>
                    @error('supported_payment_methods')<span class="ac-error">{{ $message }}</span>@enderror
                </fieldset>
            </div>
        </section>

        <section class="ac-card ac-section" aria-labelledby="security-heading" style="margin-top:16px">
            <div class="ac-card-head"><h2 id="security-heading">Security and data retention</h2></div>
            <div class="ac-card-body">
                <label class="ac-check">
                    <input type="hidden" name="mandatory_2fa_for_admins" value="0">
                    <input type="checkbox" name="mandatory_2fa_for_admins" value="1" @checked((bool) $value('mandatory_2fa_for_admins'))>
                    <span><strong>Require two-factor sign-in for administrators</strong><span class="ac-cell-sub">Strongly recommended. Turning it off needs confirmation.</span></span>
                </label>
                <div class="ac-form-row" style="margin-top:14px">
                    <div class="ac-field">
                        <label for="admin_session_timeout_minutes">Administrator session timeout (minutes)</label>
                        <input class="ac-input" type="number" id="admin_session_timeout_minutes" name="admin_session_timeout_minutes" min="5" max="480" value="{{ $value('admin_session_timeout_minutes') }}" required>
                        @error('admin_session_timeout_minutes')<span class="ac-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="ac-field">
                        <label for="attendee_session_timeout_days">Attendee session length (days)</label>
                        <input class="ac-input" type="number" id="attendee_session_timeout_days" name="attendee_session_timeout_days" min="1" max="90" value="{{ $value('attendee_session_timeout_days') }}" required>
                        @error('attendee_session_timeout_days')<span class="ac-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="ac-field">
                        <label for="audit_retention_days">Keep audit history (days)</label>
                        <input class="ac-input" type="number" id="audit_retention_days" name="audit_retention_days" min="365" max="3650" value="{{ $value('audit_retention_days') }}" required>
                        <span class="ac-hint">At least 365 days.</span>
                        @error('audit_retention_days')<span class="ac-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="ac-field">
                        <label for="pii_scrub_days">Remove customer contact details after (days)</label>
                        <input class="ac-input" type="number" id="pii_scrub_days" name="pii_scrub_days" min="30" max="3650" value="{{ $value('pii_scrub_days') }}" required>
                        @error('pii_scrub_days')<span class="ac-error">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>
        </section>

        <div class="ac-card ac-section" style="margin-top:16px">
            <div class="ac-card-body">
                @error('confirm_risky')
                    <div class="ac-alert warning" role="alert"><x-icon name="warning" size="sm" /><span>{{ $message }}</span></div>
                @enderror
                <label class="ac-check">
                    <input type="hidden" name="confirm_risky" value="0">
                    <input type="checkbox" name="confirm_risky" value="1">
                    <span>I understand that some of these changes weaken security or stop attendees paying a certain way</span>
                </label>
                <span class="ac-hint">Only needed when a change is flagged. Every saved change is recorded in the audit log.</span>
                <div class="ac-form-actions"><button class="ac-btn ac-btn-primary" type="submit" data-loading-text="Saving…">Save settings</button></div>
            </div>
        </div>
    </form>
@endsection
