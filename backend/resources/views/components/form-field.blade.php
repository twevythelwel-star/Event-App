@props([
    'name',
    'id' => null,
    'label',
    'type' => 'text',
    'value' => '',
    'required' => false,
    'disabled' => false,
    'placeholder' => null,
    'help' => null,
    'error' => null,
    'options' => [],
    'checked' => false,
    'accept' => null,
    'min' => null,
    'max' => null,
    'step' => null,
    'rows' => 4,
])

@php
    $fieldId = $id ?? 'eb-field-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
    $errorMsg = $error ?? ($errors->has($name) ? $errors->first($name) : null);
    $hasError = !empty($errorMsg);
    $helpId = $help ? $fieldId . '-help' : null;
    $errorId = $hasError ? $fieldId . '-error' : null;
    $describedBy = implode(' ', array_filter([$helpId, $errorId]));
@endphp

<div class="eb-form-group">
    @if(!in_array($type, ['checkbox', 'radio', 'toggle'], true))
        <label for="{{ $fieldId }}" class="eb-label">
            <span>{{ $label }}</span>
            @if($required)
                <span class="eb-label-required" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    @switch($type)
        @case('textarea')
            <textarea
                name="{{ $name }}"
                id="{{ $fieldId }}"
                rows="{{ $rows }}"
                class="eb-textarea {{ $hasError ? 'eb-input-error' : '' }}"
                @if($placeholder) placeholder="{{ $placeholder }}" @endif
                @if($required) required aria-required="true" @endif
                @if($disabled) disabled @endif
                @if($hasError) aria-invalid="true" @endif
                @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
            >{{ old($name, $value) }}</textarea>
            @break

        @case('select')
            <select
                name="{{ $name }}"
                id="{{ $fieldId }}"
                class="eb-select {{ $hasError ? 'eb-input-error' : '' }}"
                @if($required) required aria-required="true" @endif
                @if($disabled) disabled @endif
                @if($hasError) aria-invalid="true" @endif
                @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
            >
                @if($placeholder)
                    <option value="">{{ $placeholder }}</option>
                @endif
                @foreach($options as $optValue => $optLabel)
                    <option value="{{ $optValue }}" {{ (string) old($name, $value) === (string) $optValue ? 'selected' : '' }}>
                        {{ $optLabel }}
                    </option>
                @endforeach
                {{ $slot }}
            </select>
            @break

        @case('search')
            <div class="eb-search-wrap">
                <span class="eb-search-icon">
                    <x-icon name="search" size="sm" />
                </span>
                <input
                    type="search"
                    name="{{ $name }}"
                    id="{{ $fieldId }}"
                    value="{{ old($name, $value) }}"
                    class="eb-input {{ $hasError ? 'eb-input-error' : '' }}"
                    @if($placeholder) placeholder="{{ $placeholder }}" @endif
                    @if($required) required aria-required="true" @endif
                    @if($disabled) disabled @endif
                    @if($hasError) aria-invalid="true" @endif
                    @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
                />
            </div>
            @break

        @case('checkbox')
        @case('radio')
            <label for="{{ $fieldId }}" class="eb-choice-label">
                <input
                    type="{{ $type }}"
                    name="{{ $name }}"
                    id="{{ $fieldId }}"
                    value="{{ $value ?: '1' }}"
                    class="{{ $type === 'checkbox' ? 'eb-checkbox' : 'eb-radio' }}"
                    @if(old($name, $checked)) checked @endif
                    @if($required) required aria-required="true" @endif
                    @if($disabled) disabled @endif
                    @if($hasError) aria-invalid="true" @endif
                    @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
                />
                <span>
                    {{ $label }}
                    @if($required)
                        <span class="eb-label-required" aria-hidden="true">*</span>
                    @endif
                </span>
            </label>
            @break

        @case('toggle')
            <label for="{{ $fieldId }}" class="eb-toggle">
                <input
                    type="checkbox"
                    role="switch"
                    name="{{ $name }}"
                    id="{{ $fieldId }}"
                    value="{{ $value ?: '1' }}"
                    class="eb-toggle-input"
                    @if(old($name, $checked)) checked @endif
                    @if($disabled) disabled @endif
                    @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
                />
                <span class="eb-toggle-track" aria-hidden="true">
                    <span class="eb-toggle-thumb"></span>
                </span>
                <span class="eb-label">{{ $label }}</span>
            </label>
            @break

        @case('file')
            <label for="{{ $fieldId }}" class="eb-file-upload">
                <x-icon name="plus" size="md" />
                <span class="eb-text-label">{{ $placeholder ?? 'Choose an image file to upload' }}</span>
                <input
                    type="file"
                    name="{{ $name }}"
                    id="{{ $fieldId }}"
                    class="eb-input"
                    style="max-width: 280px;"
                    @if($accept) accept="{{ $accept }}" @endif
                    @if($required) required aria-required="true" @endif
                    @if($disabled) disabled @endif
                    @if($hasError) aria-invalid="true" @endif
                    @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
                />
            </label>
            @break

        @default
            <input
                type="{{ $type }}"
                name="{{ $name }}"
                id="{{ $fieldId }}"
                value="{{ old($name, $value) }}"
                class="eb-input {{ $type === 'number' ? 'eb-input-numeric' : '' }} {{ $hasError ? 'eb-input-error' : '' }}"
                @if($placeholder) placeholder="{{ $placeholder }}" @endif
                @if($required) required aria-required="true" @endif
                @if($disabled) disabled @endif
                @if($min !== null) min="{{ $min }}" @endif
                @if($max !== null) max="{{ $max }}" @endif
                @if($step !== null) step="{{ $step }}" @endif
                @if($hasError) aria-invalid="true" @endif
                @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
            />
    @endswitch

    @if($help)
        <span id="{{ $helpId }}" class="eb-help-text">{{ $help }}</span>
    @endif

    @if($hasError)
        <div id="{{ $errorId }}" class="eb-field-error" role="alert">
            <x-icon name="alert" size="sm" />
            <span>{{ $errorMsg }}</span>
        </div>
    @endif
</div>

