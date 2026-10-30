{{--
    Inline message. Errors and warnings are announced immediately (role="alert");
    success and info politely (role="status").
    Props: type ('success' | 'error' | 'warning' | 'info'), title (optional). Message in the slot.
    Use <x-alert :flash="true" /> to render the session's success / error messages.
--}}
@props([
    'type' => 'info',
    'title' => null,
    'flash' => false,
])

@if($flash)
    @if(session('success'))<x-alert type="success">{{ session('success') }}</x-alert>@endif
    @if(session('error'))<x-alert type="error">{{ session('error') }}</x-alert>@endif
@else
    @php
        $class = match ($type) {
            'success' => 'eb-alert-success',
            'error' => 'eb-alert-error',
            'warning' => 'eb-alert-warning',
            default => 'eb-alert-info',
        };
        $icon = match ($type) {
            'success' => 'check',
            'error' => 'alert',
            'warning' => 'warning',
            default => 'alert',
        };
    @endphp
    <div {{ $attributes->merge(['class' => "eb-alert {$class}"]) }} role="{{ in_array($type, ['error', 'warning'], true) ? 'alert' : 'status' }}">
        <x-icon :name="$icon" size="sm" />
        <div>
            @if($title)<strong style="display: block;">{{ $title }}</strong>@endif
            {{ $slot }}
        </div>
    </div>
@endif
