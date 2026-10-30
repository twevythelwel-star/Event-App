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

@php $inputId = $id ?? 'qty-' . \Illuminate\Support\Str::slug($name) . '-' . substr(md5($name), 0, 6); @endphp

<span class="eb-qty" data-qty-control>
    <button type="button" class="eb-qty-btn" data-qty="down" aria-label="Decrease {{ $label }}" @disabled($disabled) hidden>−</button>
    <label class="sr-only" for="{{ $inputId }}">{{ $label }}</label>
    <input id="{{ $inputId }}" class="eb-qty-input" type="number" inputmode="numeric" name="{{ $name }}"
           value="{{ (int) $value }}" min="{{ (int) $min }}" max="{{ (int) $max }}" step="1" @disabled($disabled) {{ $attributes }}>
    <button type="button" class="eb-qty-btn" data-qty="up" aria-label="Increase {{ $label }}" @disabled($disabled) hidden>+</button>
</span>

@once
    <script>
        // Progressive enhancement: reveal the − / + buttons and keep the value inside min/max.
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-qty-control]').forEach(function (control) {
                var input = control.querySelector('input');
                var buttons = control.querySelectorAll('[data-qty]');
                var sync = function () {
                    var v = parseInt(input.value || '0', 10);
                    buttons.forEach(function (b) {
                        if (input.disabled) { b.disabled = true; return; }
                        b.disabled = b.dataset.qty === 'down' ? v <= parseInt(input.min, 10) : v >= parseInt(input.max, 10);
                    });
                };
                buttons.forEach(function (button) {
                    button.hidden = false;
                    button.addEventListener('click', function () {
                        var v = parseInt(input.value || '0', 10) + (button.dataset.qty === 'up' ? 1 : -1);
                        input.value = Math.max(parseInt(input.min, 10), Math.min(parseInt(input.max, 10), v));
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                        sync();
                    });
                });
                input.addEventListener('input', sync);
                sync();
            });
        });
    </script>
@endonce
