{{-- One "choose N" selection in a package. $n is its index (or __N__). --}}
@php $options = collect($group['options'] ?? [])->keyBy('menu_item_id'); @endphp
<div class="eb-panel" data-repeat-row style="margin: 0; padding: var(--space-3);">
    <div class="eb-grid-4">
        <div class="eb-form-group">
            <label class="eb-label" for="pg-{{ $formId }}-{{ $n }}-title">Choice name</label>
            <input class="eb-input" id="pg-{{ $formId }}-{{ $n }}-title" name="choice_groups[{{ $n }}][title]" value="{{ $group['title'] ?? '' }}" maxlength="100" placeholder="Choose two main dishes">
        </div>
        <div class="eb-form-group">
            <label class="eb-label" for="pg-{{ $formId }}-{{ $n }}-min">At least</label>
            <input class="eb-input" type="number" min="0" max="20" id="pg-{{ $formId }}-{{ $n }}-min" name="choice_groups[{{ $n }}][min]" value="{{ (int) ($group['min'] ?? 1) }}">
        </div>
        <div class="eb-form-group">
            <label class="eb-label" for="pg-{{ $formId }}-{{ $n }}-max">At most</label>
            <input class="eb-input" type="number" min="1" max="20" id="pg-{{ $formId }}-{{ $n }}-max" name="choice_groups[{{ $n }}][max]" value="{{ (int) ($group['max'] ?? 1) }}">
        </div>
        <div class="eb-form-group" style="align-self: end;"><button type="button" class="eb-btn eb-btn-ghost eb-btn-sm" data-repeat-remove>Remove</button></div>
    </div>
    <div class="eb-stack" style="gap: var(--space-1);">
        @foreach($items as $menuItem)
            @php $option = $options->get($menuItem->id); @endphp
            <div class="eb-package-option">
                <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="choice_groups[{{ $n }}][options][{{ $menuItem->id }}][on]" value="1" @checked($option)> <span>{{ $menuItem->name }}</span></label>
                <label class="eb-help-text">Upgrade +$ <input class="eb-input eb-input-inline" type="number" min="0" step="0.01" name="choice_groups[{{ $n }}][options][{{ $menuItem->id }}][extra]" value="{{ $option ? \App\Support\Money::ofMinor((int) $option['extra_minor'], $currency)->toDecimalString() : '0.00' }}"></label>
            </div>
        @endforeach
    </div>
</div>
