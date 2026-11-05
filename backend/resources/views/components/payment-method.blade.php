{{--
    One payment-method choice, rendered as a radio card. Group several under one name.
    Props: name, value, label, description, icon (x-icon name), checked, disabled.
    Which methods are offered is decided by the host's settings, not by this component.
--}}
@props([
    'name' => 'method',
    'value',
    'label',
    'description' => null,
    'icon' => 'payment',
    'checked' => false,
    'disabled' => false,
])

@php
    $id = 'pm-' . $name . '-' . $value;
    // Each method gets its own illustration when the caller passes the generic one.
    $icon = in_array($icon, ['payment', 'credit-card'], true)
        ? (['bank' => 'bank', 'zelle' => 'phone', 'cashapp' => 'phone', 'cash_app_pay' => 'phone', 'cash' => 'cash', 'card' => 'credit-card', 'paypal' => 'globe'][$value] ?? $icon)
        : $icon;
@endphp

<label class="eb-payment-option" for="{{ $id }}">
    <input type="radio" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" @checked($checked) @disabled($disabled) {{ $attributes }}>
    <span class="eb-payment-card">
        <span class="eb-payment-icon" aria-hidden="true"><x-icon :name="$icon" size="md" /></span>
        <span class="eb-payment-text">
            <strong>{{ $label }}</strong>
            @if($description)<span>{{ $description }}</span>@endif
        </span>
        <span class="eb-payment-radio" aria-hidden="true"></span>
    </span>
</label>
