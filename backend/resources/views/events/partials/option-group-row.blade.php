{{-- One selection (option group) in the item form. $n is its index (or __N__ in the template). --}}
<div class="eb-panel" data-repeat-row style="margin: 0; padding: var(--space-3);">
    <div class="eb-grid-3">
        <div class="eb-form-group">
            <label class="eb-label" for="og-{{ $formId }}-{{ $n }}-title">Selection name</label>
            <input class="eb-input" id="og-{{ $formId }}-{{ $n }}-title" name="option_groups[{{ $n }}][title]" value="{{ $g['title'] }}" maxlength="100" placeholder="Choose your side">
        </div>
        <div class="eb-form-group">
            <label class="eb-label" for="og-{{ $formId }}-{{ $n }}-mode">Guests must</label>
            <select class="eb-select eb-input" id="og-{{ $formId }}-{{ $n }}-mode" name="option_groups[{{ $n }}][mode]">
                <option value="required" @selected($g['mode'] === 'required')>Choose exactly one (required)</option>
                <option value="optional" @selected($g['mode'] === 'optional')>Choose one or none (optional)</option>
                <option value="multi_required" @selected($g['mode'] === 'multi_required')>Choose at least one, up to a limit</option>
                <option value="multi" @selected($g['mode'] === 'multi')>Choose any, up to a limit (optional)</option>
            </select>
        </div>
        <div class="eb-form-group">
            <label class="eb-label" for="og-{{ $formId }}-{{ $n }}-max">Most choices</label>
            <input class="eb-input" type="number" min="1" max="20" id="og-{{ $formId }}-{{ $n }}-max" name="option_groups[{{ $n }}][max]" value="{{ $g['max'] }}">
        </div>
    </div>
    <div class="eb-form-group">
        <label class="eb-label" for="og-{{ $formId }}-{{ $n }}-choices">Choices, one per line</label>
        <textarea class="eb-input" rows="3" id="og-{{ $formId }}-{{ $n }}-choices" name="option_groups[{{ $n }}][choices]" placeholder="Rice and peas&#10;White rice&#10;Extra festival, 1.50">{{ $g['choices'] }}</textarea>
    </div>
    <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm" data-repeat-remove>Remove selection</button>
</div>
