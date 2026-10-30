{{--
    Labelled search input with the search icon. Place inside a GET form.
    Props: name, value, label (visible unless hideLabel), placeholder, hideLabel.
--}}
@props([
    'name' => 'q',
    'value' => null,
    'label' => 'Search',
    'placeholder' => null,
    'hideLabel' => false,
    'id' => null,
])

@php $inputId = $id ?? 'search-' . $name; @endphp

<div {{ $attributes->merge(['class' => 'eb-form-group']) }}>
    <label for="{{ $inputId }}" class="{{ $hideLabel ? 'sr-only' : 'eb-label' }}">{{ $label }}</label>
    <div class="eb-search-wrap">
        <span class="eb-search-icon" aria-hidden="true"><x-icon name="search" size="sm" /></span>
        <input id="{{ $inputId }}" type="search" name="{{ $name }}" value="{{ $value }}" class="eb-input"
               placeholder="{{ $placeholder }}" autocomplete="off">
    </div>
</div>
