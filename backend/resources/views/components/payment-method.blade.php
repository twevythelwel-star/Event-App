{{--
    One payment-method choice, rendered as a 3D radio card. Group several under one name.
    Props: name, value, label, description, icon (x-icon name), checked, disabled.
    Which methods are offered is decided strictly by the host's configured settings and platform rules
    (PaymentOptions::forEvent / PaymentMethods::availableFor), never by this component.
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
    // Each method gets its own recognizable illustration when the caller passes the generic one.
    $icon = in_array($icon, ['payment', 'credit-card'], true)
        ? (['bank' => 'bank', 'zelle' => 'phone', 'cashapp' => 'phone', 'cash_app_pay' => 'phone', 'cash' => 'cash', 'card' => 'credit-card', 'paypal' => 'globe'][$value] ?? $icon)
        : $icon;

    $methodBadge = match ((string) $value) {
        'bank' => 'Bank wire / transfer',
        'zelle' => 'Mobile banking transfer',
        'cashapp', 'cash_app_pay' => '$Cashtag transfer',
        'cash' => 'In-person at event',
        'card' => 'Card checkout',
        'paypal' => 'PayPal wallet',
        default => null,
    };
@endphp

<label class="eb-payment-option" for="{{ $id }}" data-payment-method="{{ $value }}">
    <input type="radio" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" @checked($checked) @disabled($disabled) {{ $attributes }}>
    <span class="eb-payment-card">
        <span class="eb-payment-icon" data-method="{{ $value }}" aria-hidden="true">
            <x-icon :name="$icon" size="md" />
        </span>
        <span class="eb-payment-text">
            <span class="eb-payment-head-row">
                <strong>{{ $label }}</strong>
                @if($methodBadge)
                    <span class="eb-payment-method-tag">{{ $methodBadge }}</span>
                @endif
            </span>
            @if($description)<span>{{ $description }}</span>@endif
        </span>
        <span class="eb-payment-radio" aria-hidden="true"></span>
    </span>
</label>
