{{--
    The food ordering builder: pickup or delivery, meal packages (Method A), build-your-own items (Method B) and a
    live summary. Used by checkout (inside its form) and by the host's attendee preview ($preview = true, nothing
    is submitted). Needs $event, $menu, $packages, $menuById, $windows, $cart and $currency.
--}}
@php
    // Prices in minor units for the live summary. The server prices the order again at checkout; these only
    // show the guest what they are building.
    $minor = fn ($major) => \App\Support\Money::parse((string) ($major ?? 0), $currency)->minor();
    $fmt = fn (int $m) => \App\Support\Money::formatMinor($m, $currency);
    $savedPackages = collect($cart['packages'] ?? []);
    $food = (array) ($cart['food'] ?? []);
    $delivery = $event->food_fulfillment === 'pickup_and_delivery';
    $orderFees = (int) ($event->packaging_fee_minor ?? 0) + (int) ($event->food_service_fee_minor ?? 0);
    $packageIndex = 0;
    $sections = $menu->groupBy(fn ($item) => $item->category ?: 'Menu');
@endphp
<div class="eb-food-order" data-food-builder data-currency="{{ $currency }}">
    <div class="eb-stack" style="gap: var(--space-6);">
        @if($windows !== [] || $delivery)
            <fieldset class="eb-panel" style="margin: 0;">
                <legend class="eb-text-section-title" style="padding: 0;">How you'll get your food</legend>
                @if($event->pickup_instructions)<p class="eb-text-supporting" style="margin: 0 0 var(--space-3);">{{ $event->pickup_instructions }}</p>@endif
                @if($delivery)
                    <div class="eb-cluster" style="gap: var(--space-4); margin-bottom: var(--space-3);">
                        <label class="eb-choice-label"><input type="radio" name="food[method]" value="pickup" @checked(($food['method'] ?? 'pickup') !== 'delivery') data-food-method> <span>Pick up</span></label>
                        <label class="eb-choice-label"><input type="radio" name="food[method]" value="delivery" @checked(($food['method'] ?? '') === 'delivery') data-food-method> <span>Deliver to my table or area</span></label>
                    </div>
                    <div data-delivery-fields @if(($food['method'] ?? '') !== 'delivery') hidden @endif>
                        <x-form-field name="food[delivery_location]" id="food-delivery-location" label="Table number or area" :value="$food['delivery_location'] ?? ''" placeholder="e.g. Table 12, or the blue tent" />
                        @if($event->food_delivery_instructions)<p class="eb-help-text">{{ $event->food_delivery_instructions }}</p>@endif
                    </div>
                @endif
                @if($windows !== [])
                    <div data-pickup-fields @if(($food['method'] ?? '') === 'delivery') hidden @endif>
                        <label for="food-pickup-window" class="eb-label">Pickup time</label>
                        <select id="food-pickup-window" name="food[pickup_window]" class="eb-select eb-input">
                            <option value="">Choose a pickup time</option>
                            @foreach($windows as $window)
                                @php $key = \App\Services\Events\EventSchedule::windowKey($window); @endphp
                                <option value="{{ $key }}" @selected(($food['pickup_window'] ?? '') === $key)>{{ \Illuminate\Support\Carbon::createFromFormat('H:i', $window['start'])->format('g:i A') }} – {{ \Illuminate\Support\Carbon::createFromFormat('H:i', $window['end'])->format('g:i A') }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </fieldset>
        @endif

        @if($packages->isNotEmpty())
            <section aria-labelledby="packages-heading" class="eb-stack" style="gap: var(--space-3);">
                <div>
                    <h2 id="packages-heading" class="eb-text-section-title" style="margin: 0;">Meal packages</h2>
                    <p class="eb-text-supporting" style="margin: 2px 0 0;">Combinations put together by the host. Make your choices, swap what's allowed and add extras.</p>
                </div>
                @foreach($packages as $package)
                    @php $entries = $savedPackages->where('menu_item_id', $package->id)->values(); @endphp
                    <div class="eb-stack" style="gap: var(--space-3);" data-package-group>
                        @foreach($entries->isEmpty() ? [null] : $entries as $entry)
                            @include('checkout._package-card', ['package' => $package, 'entry' => $entry, 'index' => $packageIndex++])
                        @endforeach
                        <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm" data-package-another style="align-self: flex-start; white-space: normal; text-align: left; max-width: 100%;">＋ Add another {{ $package->name }}, built differently</button>
                    </div>
                @endforeach
            </section>
        @endif

        @if($menu->isNotEmpty())
            <section aria-labelledby="food-heading" class="eb-stack" style="gap: var(--space-3);">
                <div>
                    <h2 id="food-heading" class="eb-text-section-title" style="margin: 0;">{{ $packages->isNotEmpty() ? 'Build your own meal' : 'Food & drinks' }}</h2>
                    <p class="eb-text-supporting" style="margin: 2px 0 0;">Choose individual items in any combination. Collect at the event with your ticket code.</p>
                </div>
                @foreach($sections as $section => $items)
                    <h3 class="eb-text-label" style="margin: var(--space-2) 0 0;">{{ $section }}</h3>
                    @foreach($items as $item)
                        @php
                            $saved = $cart['items'][$item->id] ?? [];
                            $soldOut = $item->available_stock !== null && (int) $item->available_stock <= 0;
                            $cap = $item->max_per_order ? min(20, (int) $item->max_per_order) : 20;
                            $max = $item->available_stock === null ? $cap : max(0, min($cap, (int) $item->available_stock));
                            $groups = $item->pricing_type === 'customizable' && is_array($item->custom_option_groups) ? $item->custom_option_groups : [];
                        @endphp
                        <x-food-card :item="$item" :currency="$currency" layout="row" data-food-line data-name="{{ $item->name }}" data-unit-minor="{{ $item->priceMinorIn($currency) }}">
                            <div class="eb-stack" style="gap: var(--space-2); align-items: flex-end; width: 100%;">
                                @foreach($groups as $group)
                                    @php
                                        $groupId = $group['id'] ?? ('g' . $loop->index);
                                        $groupMax = max(1, (int) ($group['max'] ?? 1));
                                        $required = (bool) ($group['required'] ?? true);
                                        $savedChoice = old("items.{$item->id}.choices.{$groupId}", $saved['choices'][$groupId] ?? ($required ? 0 : ''));
                                    @endphp
                                    @if($groupMax > 1)
                                        <fieldset class="eb-food-options" data-max="{{ $groupMax }}">
                                            <legend class="eb-text-label">{{ $group['title'] ?? 'Options' }} <span class="eb-help-text">({{ $required ? 'choose 1 to ' . $groupMax : 'up to ' . $groupMax }})</span></legend>
                                            @foreach($group['choices'] ?? [] as $index => $choice)
                                                @php $extra = $minor($currency === 'USD' ? ($choice['extraPriceUSD'] ?? 0) : ($choice['extraPriceJMD'] ?? 0)); @endphp
                                                <label class="eb-choice-label">
                                                    <input type="checkbox" name="items[{{ $item->id }}][choices][{{ $groupId }}][]" value="{{ $index }}" data-extra-minor="{{ $extra }}" data-label="{{ $choice['label'] ?? '' }}"
                                                           @checked(in_array($index, array_map('intval', (array) $savedChoice), true) && $savedChoice !== '') @disabled($soldOut)>
                                                    <span>{{ $choice['label'] ?? 'Choice' }}{{ $extra > 0 ? ' (+' . $fmt($extra) . ')' : '' }}</span>
                                                </label>
                                            @endforeach
                                        </fieldset>
                                    @else
                                        <label class="eb-text-label" style="width: 100%;">
                                            {{ $group['title'] ?? 'Option' }}{{ $required ? '' : ' (optional)' }}
                                            <select name="items[{{ $item->id }}][choices][{{ $groupId }}]" class="eb-select eb-input" style="width: 100%; margin-top: 2px;" @disabled($soldOut)>
                                                @unless($required)<option value="" data-extra-minor="0">None</option>@endunless
                                                @foreach($group['choices'] ?? [] as $index => $choice)
                                                    @php $extra = $minor($currency === 'USD' ? ($choice['extraPriceUSD'] ?? 0) : ($choice['extraPriceJMD'] ?? 0)); @endphp
                                                    <option value="{{ $index }}" data-extra-minor="{{ $extra }}" data-label="{{ $choice['label'] ?? '' }}" @selected((string) $savedChoice !== '' && (int) $savedChoice === $index)>
                                                        {{ $choice['label'] ?? 'Choice' }}{{ $extra > 0 ? ' (+' . $fmt($extra) . ')' : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </label>
                                    @endif
                                @endforeach
                                @if($item->pricing_type === 'custom_amount')
                                    <label class="eb-text-label" style="width: 100%;">
                                        Your amount ({{ $currency }})
                                        <input type="number" name="items[{{ $item->id }}][amount]" min="0" step="0.01" inputmode="decimal" class="eb-input" data-custom-amount
                                               value="{{ old("items.{$item->id}.amount", $saved['amount'] ?? '') }}" style="width: 100%; margin-top: 2px;" @disabled($soldOut)>
                                    </label>
                                @endif
                                @if($item->allow_special_instructions)
                                    <label class="eb-text-label" style="width: 100%;">
                                        Special instructions (optional)
                                        <input type="text" name="items[{{ $item->id }}][instructions]" maxlength="300" class="eb-input" value="{{ old("items.{$item->id}.instructions", $saved['instructions'] ?? '') }}" style="width: 100%; margin-top: 2px;" @disabled($soldOut)>
                                    </label>
                                @endif
                                <x-quantity-control
                                    :name="'items[' . $item->id . '][quantity]'"
                                    :value="old('items.' . $item->id . '.quantity', $saved['quantity'] ?? 0)"
                                    :max="$max"
                                    :disabled="$soldOut"
                                    :label="'Quantity of ' . $item->name"
                                    data-line-qty />
                                @if($item->max_per_order)<p class="eb-help-text" style="margin: 0;">Limit {{ $item->max_per_order }} per customer</p>@endif
                            </div>
                        </x-food-card>
                    @endforeach
                @endforeach
            </section>
        @endif
    </div>

    <aside class="eb-panel eb-food-summary" aria-labelledby="food-summary-heading">
        <h2 id="food-summary-heading" class="eb-text-card-title" style="margin: 0;">Your food</h2>
        <ul class="eb-food-summary-lines" data-summary-lines aria-live="polite"><li class="eb-text-supporting">Nothing chosen yet.</li></ul>
        <dl class="eb-food-summary-totals">
            <div><dt>Food</dt><dd data-summary-items>{{ $fmt(0) }}</dd></div>
            @if((int) $event->packaging_fee_minor > 0)<div><dt>Packaging fee (per order)</dt><dd data-summary-fee data-fee-minor="{{ (int) $event->packaging_fee_minor }}">{{ $fmt((int) $event->packaging_fee_minor) }}</dd></div>@endif
            @if((int) $event->food_service_fee_minor > 0)<div><dt>Food service fee (per order)</dt><dd data-summary-fee data-fee-minor="{{ (int) $event->food_service_fee_minor }}">{{ $fmt((int) $event->food_service_fee_minor) }}</dd></div>@endif
            <div class="eb-food-summary-total"><dt>Estimated food total</dt><dd data-summary-total>{{ $fmt(0) }}</dd></div>
        </dl>
        <p class="eb-help-text" style="margin: 0;">Any taxes and platform fees are added on the next page, where you see the full total before you pay.</p>
    </aside>
</div>


@once
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-food-builder]').forEach(function (form) {
        var currency = form.dataset.currency;
        var money = function (minor) {
            return new Intl.NumberFormat('en-US', { style: 'currency', currency: currency }).format(minor / 100);
        };
        var toMinor = function (value) { var n = parseFloat(value); return isFinite(n) && n > 0 ? Math.round(n * 100) : 0; };
        var qty = function (input) { var n = parseInt(input && input.value || '0', 10); return isFinite(n) && n > 0 ? n : 0; };
        var lines = form.querySelector('[data-summary-lines]');

        // Checkbox groups never take more choices than the host allows.
        form.addEventListener('change', function (event) {
            var box = event.target;
            var group = box.closest && box.closest('[data-max]');
            if (box.type === 'checkbox' && group) {
                var checked = group.querySelectorAll('input[type=checkbox]:checked');
                if (checked.length > parseInt(group.dataset.max, 10)) { box.checked = false; }
            }
            if (box.matches('[data-food-method]')) {
                var delivery = box.value === 'delivery';
                var d = form.querySelector('[data-delivery-fields]'); if (d) d.hidden = !delivery;
                var p = form.querySelector('[data-pickup-fields]'); if (p) p.hidden = delivery;
            }
            update();
        });
        form.addEventListener('input', update);

        // "Add another, built differently": a copy of the package card with its own index.
        var nextIndex = form.querySelectorAll('[data-package-card]').length;
        form.addEventListener('click', function (event) {
            var button = event.target.closest('[data-package-another]');
            if (!button) return;
            var group = button.closest('[data-package-group]');
            var source = group.querySelector('[data-package-card]');
            var copy = source.cloneNode(true);
            var from = source.dataset.index, to = String(nextIndex++);
            copy.dataset.index = to;
            copy.querySelectorAll('[name]').forEach(function (field) {
                field.name = field.name.replace('packages[' + from + ']', 'packages[' + to + ']');
                if (field.id) { field.id = field.id + '-' + to; }
                if (field.type === 'checkbox' || field.type === 'radio') { field.checked = field.defaultChecked; }
                else if (field.matches('[data-line-qty] input, input[data-line-qty]')) { field.value = 1; }
                else if (field.tagName === 'INPUT' && field.type !== 'hidden') { field.value = field.type === 'number' ? 0 : ''; }
            });
            copy.querySelectorAll('label[for]').forEach(function (label) { label.htmlFor = label.htmlFor + '-' + to; });
            var qtyInput = copy.querySelector('[data-line-qty] input, input[data-line-qty]');
            if (qtyInput) qtyInput.value = 1;
            button.before(copy);
            update();
        });

        function update() {
            var rows = [], items = 0;
            form.querySelectorAll('[data-package-card]').forEach(function (card) {
                var count = qty(card.querySelector('[data-line-qty] input, input[data-line-qty]'));
                var unit = parseInt(card.dataset.baseMinor, 10);
                card.querySelectorAll('select[data-priced] option:checked, input[data-priced]:checked').forEach(function (o) { unit += parseInt(o.dataset.extraMinor || '0', 10); });
                card.querySelectorAll('input[data-addon]').forEach(function (a) { unit += qty(a) * parseInt(a.dataset.priceMinor, 10); });
                var out = card.querySelector('[data-package-unit]');
                if (out) out.textContent = money(unit);
                if (count > 0) { rows.push([count + ' × ' + card.dataset.name, unit * count]); items += unit * count; }
            });
            form.querySelectorAll('[data-food-line]').forEach(function (line) {
                var count = qty(line.querySelector('[data-line-qty] input, input[data-line-qty]'));
                if (count === 0) return;
                var custom = line.querySelector('[data-custom-amount]');
                var unit = custom ? toMinor(custom.value) : parseInt(line.dataset.unitMinor, 10);
                line.querySelectorAll('select option:checked, input[type=checkbox]:checked').forEach(function (o) { unit += parseInt(o.dataset.extraMinor || '0', 10); });
                rows.push([count + ' × ' + line.dataset.name, unit * count]);
                items += unit * count;
            });
            lines.innerHTML = '';
            if (rows.length === 0) {
                var empty = document.createElement('li'); empty.className = 'eb-text-supporting'; empty.textContent = 'Nothing chosen yet.'; lines.appendChild(empty);
            }
            rows.forEach(function (row) {
                var li = document.createElement('li');
                var name = document.createElement('span'); name.textContent = row[0];
                var amount = document.createElement('span'); amount.textContent = money(row[1]);
                li.append(name, amount); lines.appendChild(li);
            });
            var fees = 0;
            form.querySelectorAll('[data-summary-fee]').forEach(function (fee) { fees += items > 0 ? parseInt(fee.dataset.feeMinor, 10) : 0; });
            form.querySelector('[data-summary-items]').textContent = money(items);
            form.querySelector('[data-summary-total]').textContent = money(items + fees);
        }
        update();
    });
    });
</script>
@endonce
