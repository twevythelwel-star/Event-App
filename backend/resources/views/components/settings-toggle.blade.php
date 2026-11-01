{{-- On/off setting that always submits a value: the hidden 0 is sent when the box is unchecked. --}}
<div class="eb-form-group">
    <input type="hidden" name="{{ $name }}" value="0">
    <label class="eb-choice-label">
        <input type="checkbox" class="eb-checkbox" name="{{ $name }}" value="1" @checked($checked)>
        <span>{{ $label }}</span>
    </label>
    @if(!empty($help))<p class="eb-help-text">{{ $help }}</p>@endif
</div>
