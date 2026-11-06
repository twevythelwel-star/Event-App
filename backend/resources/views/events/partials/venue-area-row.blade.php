{{-- One area of the venue plan as form fields; the canvas reads and writes these. $i is the index, $row the values. --}}
@php
    $v = fn (string $key, $default = '') => $row[$key] ?? $default;
    $name = fn (string $key) => "areas[{$i}][{$key}]";
    $id = fn (string $key) => "area-{$i}-{$key}";
    $sold = (int) ($counts['sold'] ?? 0);
@endphp
<li class="eb-panel eb-venue-row" style="margin: 0;" data-venue-row>
    <input type="hidden" name="{{ $name('id') }}" value="{{ $v('id') }}">
    <div class="eb-venue-row-grid">
        <div class="eb-form-group"><label class="eb-label" for="{{ $id('kind') }}">Type</label>
            <select class="eb-select eb-input" id="{{ $id('kind') }}" name="{{ $name('kind') }}" data-field="kind">
                @foreach(\App\Models\VenueArea::KINDS as $key => $label)<option value="{{ $key }}" @selected($v('kind', 'general_admission') === $key)>{{ $label }}</option>@endforeach
            </select></div>
        <div class="eb-form-group"><label class="eb-label" for="{{ $id('label') }}">Name</label>
            <input class="eb-input" id="{{ $id('label') }}" name="{{ $name('label') }}" value="{{ $v('label') }}" maxlength="60" required data-field="label"></div>
        <div class="eb-form-group" data-for="general_admission vip table seat_rows"><label class="eb-label" for="{{ $id('tier') }}">Ticket type</label>
            <select class="eb-select eb-input" id="{{ $id('tier') }}" name="{{ $name('ticket_tier_id') }}">
                <option value="">No ticket type</option>
                @foreach($tiers as $tier)<option value="{{ $tier['id'] }}" @selected((string) $v('ticket_tier_id') === $tier['id'])>{{ $tier['name'] }}</option>@endforeach
            </select></div>
        <div class="eb-form-group" data-for="general_admission vip"><label class="eb-label" for="{{ $id('capacity') }}">Capacity</label>
            <input class="eb-input" type="number" min="1" max="65000" id="{{ $id('capacity') }}" name="{{ $name('capacity') }}" value="{{ $v('capacity') }}"></div>
        <div class="eb-form-group" data-for="seat_rows"><label class="eb-label" for="{{ $id('rows') }}">Rows</label>
            <input class="eb-input" type="number" min="1" max="26" id="{{ $id('rows') }}" name="{{ $name('rows') }}" value="{{ $v('rows', 4) }}"></div>
        <div class="eb-form-group" data-for="seat_rows"><label class="eb-label" for="{{ $id('spr') }}">Seats per row</label>
            <input class="eb-input" type="number" min="1" max="60" id="{{ $id('spr') }}" name="{{ $name('seats_per_row') }}" value="{{ $v('seats_per_row', 10) }}"></div>
        <div class="eb-form-group" data-for="table"><label class="eb-label" for="{{ $id('spt') }}">Seats at the table</label>
            <input class="eb-input" type="number" min="1" max="20" id="{{ $id('spt') }}" name="{{ $name('seats_per_table') }}" value="{{ $v('seats_per_table', 8) }}"></div>
    </div>
    <fieldset class="eb-venue-row-grid eb-venue-geometry">
        <legend class="eb-text-label">Position and size (plan units)</legend>
        @foreach(['x' => 'Left', 'y' => 'Top', 'width' => 'Width', 'height' => 'Height'] as $key => $label)
            <div class="eb-form-group"><label class="eb-label" for="{{ $id($key) }}">{{ $label }}</label>
                <input class="eb-input" type="number" min="0" step="10" id="{{ $id($key) }}" name="{{ $name($key) }}" value="{{ $v($key, in_array($key, ['width', 'height'], true) ? 120 : 20) }}" required data-field="{{ $key }}"></div>
        @endforeach
    </fieldset>
    <div class="eb-cluster" style="justify-content: space-between;">
        <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="{{ $name('is_accessible') }}" value="1" @checked($v('is_accessible', false))><span>Accessible (step-free)</span></label>
        @if($counts)<span class="eb-help-text">{{ $counts['total'] }} seats · {{ $sold }} sold</span>@endif
        <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm" data-remove-area data-sold="{{ $sold }}">Remove area</button>
    </div>
</li>
