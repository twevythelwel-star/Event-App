{{-- One included item in a package, with the swaps guests may make. $n is its index (or __N__). --}}
@php $swaps = collect($component['substitutes'] ?? [])->keyBy('menu_item_id'); @endphp
<div class="eb-panel" data-repeat-row data-component-row style="margin: 0; padding: var(--space-3);">
    <div class="eb-grid-3">
        <div class="eb-form-group">
            <label class="eb-label" for="pc-{{ $formId }}-{{ $n }}-item">Item</label>
            <select class="eb-select eb-input" id="pc-{{ $formId }}-{{ $n }}-item" name="components[{{ $n }}][menu_item_id]">
                <option value="">Choose an item</option>
                @foreach($items as $menuItem)
                    <option value="{{ $menuItem->id }}" data-price-minor="{{ $menuItem->priceMinorIn($currency) }}" @selected(($component['menu_item_id'] ?? '') === $menuItem->id)>{{ $menuItem->name }} ({{ \App\Support\Money::formatMinor($menuItem->priceMinorIn($currency), $currency) }})</option>
                @endforeach
            </select>
        </div>
        <div class="eb-form-group">
            <label class="eb-label" for="pc-{{ $formId }}-{{ $n }}-qty">Quantity</label>
            <input class="eb-input" type="number" min="1" max="20" id="pc-{{ $formId }}-{{ $n }}-qty" name="components[{{ $n }}][quantity]" value="{{ (int) ($component['quantity'] ?? 1) }}" data-component-qty>
        </div>
        <div class="eb-form-group" style="align-self: end;"><button type="button" class="eb-btn eb-btn-ghost eb-btn-sm" data-repeat-remove>Remove</button></div>
    </div>
    <details @if($swaps->isNotEmpty()) open @endif>
        <summary class="eb-help-text">Allow swaps for this item{{ $swaps->isNotEmpty() ? ' (' . $swaps->count() . ')' : '' }}</summary>
        <div class="eb-stack" style="gap: var(--space-1); margin-top: var(--space-2);">
            @foreach($items as $menuItem)
                @php $swap = $swaps->get($menuItem->id); @endphp
                <div class="eb-package-option">
                    <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="components[{{ $n }}][substitutes][{{ $menuItem->id }}][on]" value="1" @checked($swap)> <span>{{ $menuItem->name }}</span></label>
                    <label class="eb-help-text">Upgrade +$ <input class="eb-input eb-input-inline" type="number" min="0" step="0.01" name="components[{{ $n }}][substitutes][{{ $menuItem->id }}][extra]" value="{{ $swap ? \App\Support\Money::ofMinor((int) $swap['extra_minor'], $currency)->toDecimalString() : '0.00' }}"></label>
                </div>
            @endforeach
        </div>
    </details>
</div>
