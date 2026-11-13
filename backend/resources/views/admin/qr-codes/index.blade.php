@extends('layouts.admin')

@section('title', 'QR Codes')
@section('breadcrumbs')<span aria-hidden="true">›</span><span aria-current="page">QR Codes</span>@endsection
@section('heading', 'QR Codes')
@section('subheading', 'Global controls for attendee ticket QR codes and promotional event QR codes. Readability rules (contrast, quiet zone, intact corner markers, error correction and a decode check) always apply and cannot be switched off here.')

@section('content')
    @php
        $controls = old() && old('form') === 'ticket' ? array_merge($ticketControls, [
            'enabled' => (bool) old('enabled'),
            'allow_custom_palette' => (bool) old('allow_custom_palette'),
            'allow_center_logo' => (bool) old('allow_center_logo'),
            'allowed_presets' => (array) old('allowed_presets', []),
            'default_preset' => old('default_preset', $ticketControls['default_preset']),
            'min_contrast' => old('min_contrast', $ticketControls['min_contrast']),
        ]) : $ticketControls;
    @endphp

    <form method="POST" action="{{ route('admin.console.qr-codes.ticket-controls') }}">
        @csrf
        <input type="hidden" name="form" value="ticket">
        <section class="ac-card" aria-labelledby="ticket-qr-heading">
            <div class="ac-card-head"><h2 id="ticket-qr-heading">Attendee ticket QR codes</h2></div>
            <div class="ac-card-body ac-form-row">
                <label class="ac-check">
                    <input type="hidden" name="enabled" value="0">
                    <input type="checkbox" name="enabled" value="1" @checked($controls['enabled'])>
                    Themed ticket QR codes (off: every ticket uses the standard black-and-white code)
                </label>
                <label class="ac-check">
                    <input type="hidden" name="allow_custom_palette" value="0">
                    <input type="checkbox" name="allow_custom_palette" value="1" @checked($controls['allow_custom_palette'])>
                    Hosts may set their own colours (Cultural Celebration design)
                </label>
                <label class="ac-check">
                    <input type="hidden" name="allow_center_logo" value="0">
                    <input type="checkbox" name="allow_center_logo" value="1" @checked($controls['allow_center_logo'])>
                    Centre marks (EventBite mark or event initials, in the area the code clears for them)
                </label>
                <div class="ac-field">
                    <label for="min_contrast">Minimum contrast (7:1 or more)</label>
                    <input class="ac-input" id="min_contrast" name="min_contrast" type="number" step="0.5" min="7" max="21" value="{{ $controls['min_contrast'] }}" @error('min_contrast') aria-invalid="true" @enderror>
                    @error('min_contrast')<span class="ac-error">{{ $message }}</span>@enderror
                </div>
                <div class="ac-field">
                    <label for="default_preset">Default design for new events</label>
                    <select class="ac-input" id="default_preset" name="default_preset">
                        <option value="auto" @selected($controls['default_preset'] === 'auto')>Match the kind of event</option>
                        @foreach($ticketPresets as $key => $spec)
                            <option value="{{ $key }}" @selected($controls['default_preset'] === $key)>{{ $spec['label'] }}</option>
                        @endforeach
                    </select>
                    @error('default_preset')<span class="ac-error">{{ $message }}</span>@enderror
                </div>
                <fieldset class="ac-field" style="border: 0; padding: 0;">
                    <legend>Designs hosts may choose</legend>
                    @error('allowed_presets')<span class="ac-error">{{ $message }}</span>@enderror
                    <div class="eb-qr-preset-grid">
                        @foreach($ticketPresets as $key => $spec)
                            <label class="eb-qr-preset" data-qr-preset="{{ $key }}">
                                <span><input type="checkbox" name="allowed_presets[]" value="{{ $key }}" @checked(in_array($key, $controls['allowed_presets'], true))> <strong>{{ $spec['label'] }}</strong></span>
                                {!! $ticketPreviews[$key]['svg'] !!}
                                <span class="ac-hint">{{ $spec['description'] }}</span>
                                @if($ticketPreviews[$key]['fallback'])
                                    <span class="ac-error">Shown as the standard code under the current controls: {{ $ticketPreviews[$key]['fallback'] }}.</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div class="ac-form-actions"><button class="ac-btn ac-btn-primary" type="submit" data-loading-text="Saving…">Save ticket QR controls</button></div>
            </div>
        </section>
    </form>

    <form method="POST" action="{{ route('admin.console.qr-codes.update') }}">
        @csrf
        <section class="ac-card" aria-labelledby="promo-qr-heading">
            <div class="ac-card-head"><h2 id="promo-qr-heading">Promotional event QR codes</h2></div>
            <div class="ac-card-body ac-form-row">
                <p class="ac-hint">The shareable code on each event's page and posters. It only opens the event page and never admits anyone.</p>
                <div class="ac-field">
                    <label for="qr_design_version">Design version</label>
                    <input class="ac-input" id="qr_design_version" name="qr_design_version" type="number" min="1" max="10" value="{{ old('qr_design_version', $settings->qr_design_version ?? 1) }}">
                </div>
                <div class="ac-field">
                    <label for="qr_default_preset">Default preset</label>
                    <select class="ac-input" id="qr_default_preset" name="qr_default_preset">
                        @foreach($presets as $key => $label)
                            <option value="{{ $key }}" @selected(old('qr_default_preset', $settings->qr_default_preset) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="ac-check">
                    <input type="checkbox" name="qr_enforce_design_rules" value="1" @checked((bool) old('qr_enforce_design_rules', $settings->qr_enforce_design_rules ?? true))>
                    Enforce design rules
                </label>
                <div class="ac-form-actions"><button class="ac-btn ac-btn-secondary" type="submit" data-loading-text="Saving…">Save promotional settings</button></div>
            </div>
        </section>
    </form>
@endsection
