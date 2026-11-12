{{--
    Quantity stepper. Without JavaScript it is an ordinary number input; with it, − and +
    buttons step the value within min/max. The server re-validates every quantity.
    Props: name, value, min, max, label (accessible name, e.g. "Quantity of Jerk Chicken"), disabled.
--}}
@props([
    'name',
    'value' => 0,
    'min' => 0,
    'max' => 20,
    'label',
    'disabled' => false,
    'id' => null,
])

@php
    $inputId = $id ?? 'qty-' . \Illuminate\Support\Str::slug($name) . '-' . substr(md5($name), 0, 6);
    $intValue = (int) $value;
@endphp

<span class="eb-qty {{ $intValue > 0 ? 'is-active' : '' }}" data-qty-control data-has-qty="{{ $intValue > 0 ? 'true' : 'false' }}">
    <button type="button" class="eb-qty-btn" data-qty="down" aria-label="Decrease {{ $label }}" @disabled($disabled) hidden>−</button>
    <label class="sr-only" for="{{ $inputId }}">{{ $label }}</label>
    <input id="{{ $inputId }}" class="eb-qty-input" type="number" inputmode="numeric" name="{{ $name }}"
           value="{{ $intValue }}" min="{{ (int) $min }}" max="{{ (int) $max }}" step="1" @disabled($disabled) {{ $attributes }}>
    <button type="button" class="eb-qty-btn" data-qty="up" aria-label="Increase {{ $label }}" @disabled($disabled) hidden>+</button>
</span>

@once
    <script>
        // Progressive enhancement: reveal the − / + buttons, sync active state, and keep the value inside min/max.
        document.addEventListener('DOMContentLoaded', function () {
            function syncControl(control) {
                var input = control.querySelector('input');
                if (!input) return;
                var buttons = control.querySelectorAll('[data-qty]');
                var v = parseInt(input.value || '0', 10);
                var min = parseInt(input.min || '0', 10);
                var max = parseInt(input.max || '20', 10);
                control.dataset.hasQty = v > 0 ? 'true' : 'false';
                control.classList.toggle('is-active', v > 0);
                var foodCard = control.closest('.eb-food-card');
                if (foodCard) foodCard.classList.toggle('is-selected', v > 0);
                buttons.forEach(function (b) {
                    b.hidden = false;
                    if (input.disabled) { b.disabled = true; return; }
                    b.disabled = b.dataset.qty === 'down' ? v <= min : v >= max;
                });
            }
            window.ebSyncQtyControl = syncControl;
            document.querySelectorAll('[data-qty-control]').forEach(syncControl);

            document.addEventListener('click', function (e) {
                var button = e.target.closest('[data-qty-control] [data-qty]');
                if (!button || button.disabled) return;
                var control = button.closest('[data-qty-control]');
                var input = control && control.querySelector('input');
                if (!input || input.disabled) return;
                var min = parseInt(input.min || '0', 10);
                var max = parseInt(input.max || '20', 10);
                var v = parseInt(input.value || '0', 10) + (button.dataset.qty === 'up' ? 1 : -1);
                input.value = Math.max(min, Math.min(max, v));
                syncControl(control);
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });

            document.addEventListener('input', function (e) {
                var control = e.target.closest && e.target.closest('[data-qty-control]');
                if (control) syncControl(control);
            });
        });
    </script>
@endonce
