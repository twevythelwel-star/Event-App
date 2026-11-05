@extends('layouts.app')

@section('title', 'Account settings — EventBite')
@section('page-title', 'Settings')

@php
    $user = Auth::user();
    $isSystemAdmin = $user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN);
    $isAdmin = $user->hasRole(\App\Enums\AccountType::ADMINISTRATOR);
    $sections = array_filter([
        'profile' => 'Profile',
        'security' => 'Password',
        'host' => $hostOrganizer ? 'Event defaults' : null,
        'payments' => $hostOrganizer ? 'Payments & refunds' : null,
        'access' => ($isAdmin || $isSystemAdmin) ? 'Administration access' : null,
    ]);
    $timezones = ['America/New_York' => 'Eastern', 'America/Chicago' => 'Central', 'America/Denver' => 'Mountain',
        'America/Phoenix' => 'Arizona', 'America/Los_Angeles' => 'Pacific', 'America/Anchorage' => 'Alaska', 'Pacific/Honolulu' => 'Hawaii'];
    $toggle = function (string $name, string $label, bool $checked, ?string $help = null) {
        return view('components.settings-toggle', compact('name', 'label', 'checked', 'help'))->render();
    };
@endphp

@section('content')
<div class="eb-stack" style="gap: var(--layout-section-gap);">
    <x-page-header title="Settings" :badge="$user->getActiveRole()->label()"
                   description="Your profile and password{{ $hostOrganizer ? ', and the defaults, payment instructions and refund policy for ' . $hostOrganizer->name : '' }}." />

    <nav aria-label="Settings sections" class="eb-cluster" style="gap: var(--space-2);">
        @foreach($sections as $id => $label)
            <a href="#{{ $id }}" class="eb-btn eb-btn-secondary eb-btn-sm">{{ $label }}</a>
        @endforeach
    </nav>

    <section id="profile">
        <x-settings-form title="Profile" description="Your name, sign-in email and mobile number." :action="route('settings.profile')">
            <div class="eb-grid-2">
                <x-form-field name="name" label="Full name" :value="old('name', $user->name)" :required="true" />
                <x-form-field name="email" type="email" label="Email address" :value="old('email', $user->email)" :required="true"
                              help="You sign in with this address and receipts are sent to it." />
                <x-form-field name="phone" type="tel" label="Mobile number" :value="old('phone', $user->phone)" placeholder="+1 305 555 0100"
                              help="Used for order and pickup messages." />
                <x-form-field name="preferred_display_currency" type="select" label="Display currency"
                              :value="old('preferred_display_currency', $user->preferred_display_currency ?? 'USD')"
                              :options="collect(\App\Support\Currency::supportedCodes())->mapWithKeys(fn ($code) => [$code => $code . ' — ' . \App\Support\Currency::of($code)->name])->all()"
                              help="Only changes how amounts are shown. You are always charged in the event's currency." />
            </div>
        </x-settings-form>
    </section>

    <section id="security">
        <x-settings-form title="Password" description="Use at least 10 characters. Changing it does not sign you out on this device." :action="route('settings.password')">
            <div class="eb-grid-3">
                <x-form-field name="current_password" type="password" label="Current password" :required="true" />
                <x-form-field name="new_password" type="password" label="New password" :required="true" :min="10" />
                <x-form-field name="new_password_confirmation" type="password" label="Confirm new password" :required="true" />
            </div>
        </x-settings-form>
    </section>

    @if($hostOrganizer)
        <form action="{{ route('settings.host') }}" method="POST" class="eb-stack" style="gap: var(--layout-section-gap);">
            @csrf
            <section id="host" class="eb-panel card" style="margin: 0;">
                <div class="eb-card-header card-header">
                    <div>
                        <h2 class="eb-card-title card-title">Event defaults</h2>
                        <p class="eb-card-subtitle card-subtitle">Starting values for new events from {{ $hostOrganizer->name }}. Existing events keep their own settings.</p>
                    </div>
                </div>
                <div class="eb-grid-3">
                    <x-form-field name="default_timezone" type="select" label="Time zone"
                                  :value="old('default_timezone', $hostDefaults->default_timezone ?? 'America/New_York')"
                                  :options="collect($timezones)->mapWithKeys(fn ($label, $tz) => [$tz => $label . ' (' . $tz . ')'])->all()" />
                    <x-form-field name="default_order_deadline_hours" type="number" label="Food order cutoff (hours before start)" :min="1" :max="168"
                                  :value="old('default_order_deadline_hours', $hostDefaults->default_order_deadline_hours ?? 24)" />
                    <x-form-field name="food_prep_buffer_percent" type="number" label="Food preparation buffer (%)" :min="0" :max="50"
                                  :value="old('food_prep_buffer_percent', $hostRule->food_prep_buffer_percent ?? 10)"
                                  help="Added on top of paid portions in Food to prepare." />
                </div>
                <x-form-field name="default_pickup_instructions" type="textarea" label="Pickup instructions" :rows="3"
                              :value="old('default_pickup_instructions', $hostDefaults->default_pickup_instructions)"
                              help="Shown to customers on the event page and their ticket." />
            </section>

            <section id="payments" class="eb-panel card" style="margin: 0;">
                <div class="eb-card-header card-header">
                    <div>
                        <h2 class="eb-card-title card-title">Payments &amp; refunds</h2>
                        <p class="eb-card-subtitle card-subtitle">What customers see when they choose a manual payment. Leave instructions empty to not offer that method. Card payments are not available yet.</p>
                    </div>
                </div>
                <div class="eb-stack">
                    {!! $toggle('enable_cash', 'Accept cash at the event', (bool) old('enable_cash', $hostPolicy->enable_cash ?? true)) !!}
                    <div class="eb-grid-3">
                        <x-form-field name="zelle_instructions" type="textarea" label="Zelle instructions" :rows="3"
                                      :value="old('zelle_instructions', $hostPolicy->zelle_instructions)" placeholder="Send to payments@yourorg.example and put your order number in the memo." />
                        <x-form-field name="cash_app_instructions" type="textarea" label="Cash App instructions" :rows="3"
                                      :value="old('cash_app_instructions', $hostPolicy->cash_app_instructions)" placeholder="Send to $YourCashtag with your order number." />
                        <x-form-field name="bank_transfer_instructions" type="textarea" label="Bank transfer instructions" :rows="3"
                                      :value="old('bank_transfer_instructions', $hostPolicy->bank_transfer_instructions)" />
                    </div>
                    <div class="eb-grid-2">
                        {!! $toggle('require_transfer_reference', 'Require a transfer reference', (bool) old('require_transfer_reference', $hostPolicy->require_transfer_reference ?? true), 'Customers must enter the confirmation number from their app or bank.') !!}
                        {!! $toggle('require_sender_name', 'Require the sender\'s name', (bool) old('require_sender_name', $hostPolicy->require_sender_name ?? false)) !!}
                    </div>
                    <div class="eb-grid-2">
                        <x-form-field name="refund_policy_deadline_hours" type="number" label="Refund requests close (hours before start)" :min="0" :max="168"
                                      :value="old('refund_policy_deadline_hours', $hostPolicy->refund_policy_deadline_hours)" />
                        <x-form-field name="refund_policy_notes" type="textarea" label="Refund policy" :rows="3"
                                      :value="old('refund_policy_notes', $hostPolicy->refund_policy_notes)"
                                      help="Shown on every event page. Avoid promising provider refunds; EventBite only records completed manual refunds." />
                        <label class="eb-form-field">Refund handling
                            <select name="refund_policy_mode" class="eb-input">
                                @foreach(['manual_full_only' => 'Full manual refunds only', 'manual_full_and_partial' => 'Full or partial manual refunds', 'non_refundable' => 'No refunds'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('refund_policy_mode', $hostPolicy->refund_policy_mode ?? 'manual_full_and_partial') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <x-form-field name="food_order_cancellation_deadline_hours" type="number" label="Food cancellation requests close (hours before event)" :min="0" :max="720"
                                      :value="old('food_order_cancellation_deadline_hours', $hostPolicy->food_order_cancellation_deadline_hours ?? 24)" />
                        <x-form-field name="food_order_modification_deadline_hours" type="number" label="Food modification requests close (hours before event)" :min="0" :max="720"
                                      :value="old('food_order_modification_deadline_hours', $hostPolicy->food_order_modification_deadline_hours ?? 24)"
                                      help="Changes are not editable in-app; customers must contact the organizer." />
                    </div>
                    <div class="eb-grid-3">
                        <label class="eb-form-field">Host cancellation
                            <select name="host_cancellation_policy" class="eb-input">
                                @foreach(['contact_organizer' => 'Contact organizer; no automatic refund', 'manual_refund_if_supported' => 'Manual refund if supported payment method'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('host_cancellation_policy', $hostPolicy->host_cancellation_policy ?? 'contact_organizer') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="eb-form-field">Postponement / rescheduling
                            <select name="postponement_policy" class="eb-input">
                                @foreach(['tickets_valid_for_new_date' => 'Existing tickets remain valid for the new date', 'contact_organizer' => 'Contact organizer for next steps'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('postponement_policy', $hostPolicy->postponement_policy ?? 'tickets_valid_for_new_date') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="eb-form-field">Weather cancellation
                            <select name="weather_cancellation_policy" class="eb-input">
                                @foreach(['contact_organizer' => 'Contact organizer; no automatic refund', 'reschedule_tickets_valid' => 'Reschedule; existing tickets remain valid', 'manual_refund_if_supported' => 'Manual refund if supported payment method'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('weather_cancellation_policy', $hostPolicy->weather_cancellation_policy ?? 'contact_organizer') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </div>
                <div class="eb-cluster" style="justify-content: flex-end; margin-top: var(--space-6);">
                    <button type="submit" class="eb-btn eb-btn-primary" data-prevent-double-submit="true">Save event and payment settings</button>
                </div>
            </section>
        </form>
    @endif

    @if($isAdmin || $isSystemAdmin)
        <section id="access" class="eb-panel card" style="margin: 0;">
            <div class="eb-card-header card-header">
                <div>
                    <h2 class="eb-card-title card-title">Administration access</h2>
                    <p class="eb-card-subtitle card-subtitle">
                        {{ $isSystemAdmin ? 'You have full platform access. Platform-wide settings, fees, locations and roles are managed in the admin console.' : 'What a system administrator has allowed you to do. Ask them to change it.' }}
                    </p>
                </div>
            </div>
            @if(!$isSystemAdmin && isset($settings['admin_scope']))
                <ul class="eb-grid-2" style="list-style: none; padding: 0; margin: 0 0 var(--space-4);">
                    @foreach(['can_manage_events' => 'Manage events', 'can_moderate_content' => 'Moderate content', 'can_support_orders' => 'Support orders', 'can_view_payments' => 'View payments', 'can_view_reports' => 'View reports', 'can_access_audit_logs' => 'View audit logs'] as $key => $label)
                        <li class="eb-cluster"><span class="eb-badge {{ $settings['admin_scope'][$key] ? 'eb-badge-success' : '' }}">{{ $settings['admin_scope'][$key] ? 'Allowed' : 'Not allowed' }}</span> {{ $label }}</li>
                    @endforeach
                </ul>
            @endif
            @can(\App\Support\AdminAuthorization::ACCESS)
                <x-button variant="secondary" icon="shield" :href="$isSystemAdmin ? route('admin.console.settings.edit') : route('admin.console.overview')">Open the admin console</x-button>
            @endcan
        </section>
    @endif
</div>
@endsection
