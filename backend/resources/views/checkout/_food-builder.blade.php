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
            <fieldset class="eb-panel eb-food-fulfillment-panel" style="margin: 0;">
                <legend class="eb-text-section-title" style="padding: 0;">How you'll get your food</legend>
                @if($event->pickup_instructions)<p class="eb-text-supporting" style="margin: 0 0 var(--space-3);">{{ $event->pickup_instructions }}</p>@endif
                @if($delivery)
                    <div class="eb-option-tiles-grid" style="margin-bottom: var(--space-3);">
                        <label class="eb-choice-label eb-option-tile {{ ($food['method'] ?? 'pickup') !== 'delivery' ? 'is-selected' : '' }}">
                            <input type="radio" name="food[method]" value="pickup" @checked(($food['method'] ?? 'pickup') !== 'delivery') data-food-method>
                            <span class="eb-option-thumb-icon" aria-hidden="true"><x-icon name="utensils" :size="16" /></span>
                            <span class="eb-option-tile-copy">
                                <span class="eb-option-tile-name">Pick up</span>
                                <span class="eb-help-text" style="font-size: 0.75rem;">Collect at the event counter</span>
                            </span>
                        </label>
                        <label class="eb-choice-label eb-option-tile {{ ($food['method'] ?? '') === 'delivery' ? 'is-selected' : '' }}">
                            <input type="radio" name="food[method]" value="delivery" @checked(($food['method'] ?? '') === 'delivery') data-food-method>
                            <span class="eb-option-thumb-icon" aria-hidden="true"><x-icon name="pin" :size="16" /></span>
                            <span class="eb-option-tile-copy">
                                <span class="eb-option-tile-name">Deliver to my table or area</span>
                                <span class="eb-help-text" style="font-size: 0.75rem;">Brought directly to your spot</span>
                            </span>
                        </label>
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
            <section aria-labelledby="packages-heading" class="eb-stack" style="gap: var(--space-4);">
                <div class="eb-food-section-header">
                    <div>
                        <span class="eb-food-section-kicker"><x-icon name="package" :size="13" /> Chef & Host Combinations</span>
                        <h2 id="packages-heading" class="eb-text-section-title" style="margin: 2px 0 0;">Meal packages</h2>
                        <p class="eb-text-supporting" style="margin: 2px 0 0;">Combinations put together by the host. Make your choices, swap what's allowed and add extras.</p>
                    </div>
                    <span class="eb-badge eb-badge-neutral">{{ $packages->count() }} {{ \Illuminate\Support\Str::plural('package', $packages->count()) }}</span>
                </div>
                @foreach($packages as $package)
                    @php $entries = $savedPackages->where('menu_item_id', $package->id)->values(); @endphp
                    <div class="eb-stack" style="gap: var(--space-3);" data-package-group>
                        @foreach($entries->isEmpty() ? [null] : $entries as $entry)
                            @include('checkout._package-card', ['package' => $package, 'entry' => $entry, 'index' => $packageIndex++])
                        @endforeach
                        <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm eb-package-another-btn" data-package-another style="align-self: flex-start; white-space: normal; text-align: left; max-width: 100%;">＋ Add another {{ $package->name }}, built differently</button>
                    </div>
                @endforeach
            </section>
        @endif

        @if($menu->isNotEmpty())
            <section aria-labelledby="food-heading" class="eb-stack" style="gap: var(--space-4);">
                <div class="eb-food-section-header">
                    <div>
                        <span class="eb-food-section-kicker"><x-icon name="utensils" :size="13" /> À La Carte Menu</span>
                        <h2 id="food-heading" class="eb-text-section-title" style="margin: 2px 0 0;">{{ $packages->isNotEmpty() ? 'Build your own meal' : 'Food & drinks' }}</h2>
                        <p class="eb-text-supporting" style="margin: 2px 0 0;">Choose individual items in any combination. Collect at the event with your ticket code.</p>
                    </div>
                    <span class="eb-badge eb-badge-neutral">{{ $menu->count() }} {{ \Illuminate\Support\Str::plural('dish', $menu->count()) }}</span>
                </div>

                @if($sections->count() > 1)
                    <nav class="eb-food-category-nav" aria-label="Menu categories">
                        @foreach($sections as $section => $items)
                            <a href="#menu-cat-{{ \Illuminate\Support\Str::slug($section) }}" class="eb-food-category-pill">
                                <span>{{ $section }}</span>
                                <span class="eb-food-category-count">{{ $items->count() }}</span>
                            </a>
                        @endforeach
                    </nav>
                @endif

                @foreach($sections as $section => $items)
                    <div id="menu-cat-{{ \Illuminate\Support\Str::slug($section) }}" class="eb-food-category-group eb-stack" style="gap: var(--space-3);">
                        <div class="eb-food-category-heading">
                            <h3 class="eb-text-label" style="margin: 0;">{{ $section }}</h3>
                            <span class="eb-food-category-rule" aria-hidden="true"></span>
                            <span class="eb-help-text">{{ $items->count() }} {{ \Illuminate\Support\Str::plural('item', $items->count()) }}</span>
                        </div>
                        @foreach($items as $item)
                            @php
                                $saved = $cart['items'][$item->id] ?? [];
                                $soldOut = $item->available_stock !== null && (int) $item->available_stock <= 0;
                                $cap = $item->max_per_order ? min(20, (int) $item->max_per_order) : 20;
                                $max = $item->available_stock === null ? $cap : max(0, min($cap, (int) $item->available_stock));
                                $initItemQty = (int) old('items.' . $item->id . '.quantity', $saved['quantity'] ?? 0);
                            @endphp
                            <x-food-card :item="$item" :currency="$currency" layout="row" :class="$initItemQty > 0 ? 'is-selected' : ''"
                                         data-food-line data-name="{{ $item->name }}" data-unit-minor="{{ $item->priceMinorIn($currency) }}">
                                <div class="eb-stack" style="gap: var(--space-3); width: 100%;">
                                    <x-food-item-options
                                        :item="$item"
                                        mode="item"
                                        :saved="$saved"
                                        :currency="$currency"
                                        :sold-out="$soldOut" />

                                    <div class="eb-food-action-bar">
                                        <div class="eb-food-unit-readout">
                                            @if($item->max_per_order)
                                                <span class="eb-help-text">Limit {{ $item->max_per_order }} per customer</span>
                                            @else
                                                <span class="eb-help-text">Select quantity</span>
                                            @endif
                                        </div>
                                        <x-quantity-control
                                            :name="'items[' . $item->id . '][quantity]'"
                                            :value="$initItemQty"
                                            :max="$max"
                                            :disabled="$soldOut"
                                            :label="'Quantity of ' . $item->name"
                                            data-line-qty />
                                    </div>
                                </div>
                            </x-food-card>
                        @endforeach
                    </div>
                @endforeach
            </section>
        @endif
    </div>

    <x-food-order-summary
        :event="$event"
        :currency="$currency"
        :delivery-available="$delivery"
        :method="$food['method'] ?? 'pickup'" />
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
            var countBadge = form.querySelector('[data-summary-count]');
            var methodLabel = form.querySelector('[data-summary-method-label]');

            function syncOptionTiles(scope) {
                (scope || form).querySelectorAll('.eb-option-tile').forEach(function (tile) {
                    var input = tile.querySelector('input[type=radio], input[type=checkbox]');
                    if (input) tile.classList.toggle('is-selected', input.checked);
                });
            }

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
                    if (methodLabel) methodLabel.textContent = delivery ? 'Table / area delivery' : 'Event pickup';
                }
                if (box.matches('[data-option-select]')) {
                    var pillGroup = form.querySelector('[data-option-pills="' + box.dataset.optionSelect + '"]');
                    if (pillGroup) {
                        pillGroup.querySelectorAll('[data-option-pill]').forEach(function (pill) {
                            var active = pill.dataset.value === box.value;
                            pill.classList.toggle('is-selected', active);
                            pill.setAttribute('aria-pressed', active ? 'true' : 'false');
                        });
                    }
                }
                syncOptionTiles(form);
                update();
            });
            form.addEventListener('input', update);

            // Interactive option pills and add-on steppers + "Add another, built differently"
            var nextIndex = form.querySelectorAll('[data-package-card]').length;
            form.addEventListener('click', function (event) {
                var pill = event.target.closest('[data-option-pill]');
                if (pill && !pill.disabled) {
                    var groupEl = pill.closest('[data-option-pills]');
                    var selectKey = groupEl && groupEl.dataset.optionPills;
                    var selectEl = selectKey && form.querySelector('[data-option-select="' + selectKey + '"]');
                    if (selectEl && !selectEl.disabled) {
                        selectEl.value = pill.dataset.value;
                        selectEl.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    return;
                }

                var addonBtn = event.target.closest('[data-addon-step]');
                if (addonBtn && !addonBtn.disabled) {
                    var stepper = addonBtn.closest('[data-addon-stepper]');
                    var addonInput = stepper && stepper.querySelector('input[data-addon]');
                    if (addonInput && !addonInput.disabled) {
                        var min = parseInt(addonInput.min || '0', 10);
                        var max = parseInt(addonInput.max || '10', 10);
                        var nextVal = Math.max(min, Math.min(max, parseInt(addonInput.value || '0', 10) + (addonBtn.dataset.addonStep === 'up' ? 1 : -1)));
                        addonInput.value = nextVal;
                        stepper.querySelectorAll('[data-addon-step]').forEach(function (b) {
                            b.disabled = b.dataset.addonStep === 'down' ? nextVal <= min : nextVal >= max;
                        });
                        var addonCard = stepper.closest('[data-addon-card]');
                        if (addonCard) addonCard.classList.toggle('is-selected', nextVal > 0);
                        addonInput.dispatchEvent(new Event('input', { bubbles: true }));
                        addonInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    return;
                }

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
                var qtyControl = copy.querySelector('[data-qty-control]');
                var qtyInput = copy.querySelector('[data-line-qty] input, input[data-line-qty]');
                if (qtyInput) qtyInput.value = 1;
                if (qtyControl && window.ebSyncQtyControl) window.ebSyncQtyControl(qtyControl);
                syncOptionTiles(copy);
                button.before(copy);
                update();
            });

            function update() {
                var rows = [], items = 0, totalCount = 0;
                form.querySelectorAll('[data-package-card]').forEach(function (card) {
                    var count = qty(card.querySelector('[data-line-qty] input, input[data-line-qty]'));
                    var unit = parseInt(card.dataset.baseMinor, 10);
                    var details = [];
                    card.querySelectorAll('select[data-priced] option:checked, input[data-priced]:checked').forEach(function (o) {
                        unit += parseInt(o.dataset.extraMinor || '0', 10);
                        if (o.dataset.choiceLabel) details.push(o.dataset.choiceLabel);
                        else if (o.tagName === 'OPTION' && o.value !== '') details.push(o.textContent.trim().replace(/\s*\(\+[^)]+\)$/, ''));
                    });
                    card.querySelectorAll('input[data-addon]').forEach(function (a) {
                        var aq = qty(a);
                        unit += aq * parseInt(a.dataset.priceMinor, 10);
                        if (aq > 0 && a.dataset.addonLabel) details.push('+' + aq + ' ' + a.dataset.addonLabel);
                    });
                    var out = card.querySelector('[data-package-unit]');
                    if (out) out.textContent = money(unit);
                    if (count > 0) {
                        totalCount += count;
                        rows.push({ title: count + ' × ' + card.dataset.name, amount: unit * count, detail: details.join(' · ') });
                        items += unit * count;
                    }
                });
                form.querySelectorAll('[data-food-line]').forEach(function (line) {
                    var count = qty(line.querySelector('[data-line-qty] input, input[data-line-qty]'));
                    line.classList.toggle('is-selected', count > 0);
                    if (count === 0) return;
                    totalCount += count;
                    var custom = line.querySelector('[data-custom-amount]');
                    var unit = custom ? toMinor(custom.value) : parseInt(line.dataset.unitMinor, 10);
                    var details = [];
                    line.querySelectorAll('select option:checked, input[type=checkbox]:checked').forEach(function (o) {
                        unit += parseInt(o.dataset.extraMinor || '0', 10);
                        if (o.dataset.label) details.push(o.dataset.label);
                    });
                    rows.push({ title: count + ' × ' + line.dataset.name, amount: unit * count, detail: details.join(' · ') });
                    items += unit * count;
                });
                if (countBadge) {
                    countBadge.textContent = totalCount + (totalCount === 1 ? ' item' : ' items');
                }
                lines.innerHTML = '';
                if (rows.length === 0) {
                    var empty = document.createElement('li');
                    empty.className = 'eb-food-summary-empty eb-text-supporting';
                    empty.textContent = 'Nothing chosen yet.';
                    lines.appendChild(empty);
                }
                rows.forEach(function (row) {
                    var li = document.createElement('li');
                    li.className = 'eb-food-summary-line';
                    var left = document.createElement('div');
                    left.className = 'eb-food-summary-line-info';
                    var name = document.createElement('span');
                    name.className = 'eb-food-summary-line-name';
                    name.textContent = row.title;
                    left.appendChild(name);
                    if (row.detail) {
                        var sub = document.createElement('span');
                        sub.className = 'eb-food-summary-line-detail';
                        sub.textContent = row.detail;
                        left.appendChild(sub);
                    }
                    var amount = document.createElement('span');
                    amount.className = 'eb-food-summary-line-amount';
                    amount.textContent = money(row.amount);
                    li.append(left, amount);
                    lines.appendChild(li);
                });
                var fees = 0;
                form.querySelectorAll('[data-summary-fee]').forEach(function (fee) { fees += items > 0 ? parseInt(fee.dataset.feeMinor, 10) : 0; });
                form.querySelector('[data-summary-items]').textContent = money(items);
                var totalEl = form.querySelector('[data-summary-total]');
                var nextTotalText = money(items + fees);
                if (totalEl) {
                    var prevText = totalEl.textContent;
                    totalEl.textContent = nextTotalText;
                    if (prevText && prevText !== nextTotalText && window.EventBiteAnimations) {
                        window.EventBiteAnimations.pulsePrice(totalEl);
                    }
                }
            }
            update();
        });
    });
</script>
@endonce
