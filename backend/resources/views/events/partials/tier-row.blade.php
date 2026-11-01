{{-- One ticket type in the event form. $i is the row index (or __INDEX__ in the template). --}}
@php $field = fn ($key) => "ticket_tiers[{$i}][{$key}]"; $id = fn ($key) => "tier-{$i}-{$key}"; $err = fn ($key) => $errors->first("ticket_tiers.{$i}.{$key}"); @endphp
<fieldset data-tier-row class="eb-panel" style="margin: 0; border: 1px solid var(--color-border); padding: var(--space-4);">
    <legend class="sr-only">Ticket type</legend>
    <input type="hidden" name="{{ $field('id') }}" value="{{ $row['id'] }}">
    <div class="eb-grid-4">
        <div class="eb-form-group">
            <label class="eb-label" for="{{ $id('name') }}">Name <span class="eb-label-required" aria-hidden="true">*</span></label>
            <input class="eb-input @if($err('name')) eb-input-error @endif" id="{{ $id('name') }}" name="{{ $field('name') }}" value="{{ $row['name'] }}" required maxlength="120" placeholder="General admission">
            @if($err('name'))<p class="eb-field-error">{{ $err('name') }}</p>@endif
        </div>
        <div class="eb-form-group">
            <label class="eb-label" for="{{ $id('price') }}">Price ($) <span class="eb-label-required" aria-hidden="true">*</span></label>
            <input class="eb-input eb-input-numeric @if($err('price')) eb-input-error @endif" id="{{ $id('price') }}" type="number" min="0" step="0.01" name="{{ $field('price') }}" value="{{ $row['price'] }}" required>
            @if($err('price'))<p class="eb-field-error">{{ $err('price') }}</p>@endif
        </div>
        <div class="eb-form-group">
            <label class="eb-label" for="{{ $id('available_stock') }}">Tickets available <span class="eb-label-required" aria-hidden="true">*</span></label>
            <input class="eb-input eb-input-numeric @if($err('available_stock')) eb-input-error @endif" id="{{ $id('available_stock') }}" type="number" min="1" step="1" name="{{ $field('available_stock') }}" value="{{ $row['available_stock'] }}" required>
            @if($err('available_stock'))<p class="eb-field-error">{{ $err('available_stock') }}</p>@endif
        </div>
        <div class="eb-form-group">
            <label class="eb-label" for="{{ $id('max_per_customer') }}">Limit per customer <span class="eb-label-required" aria-hidden="true">*</span></label>
            <input class="eb-input eb-input-numeric @if($err('max_per_customer')) eb-input-error @endif" id="{{ $id('max_per_customer') }}" type="number" min="1" max="100" step="1" name="{{ $field('max_per_customer') }}" value="{{ $row['max_per_customer'] }}" required>
            @if($err('max_per_customer'))<p class="eb-field-error">{{ $err('max_per_customer') }}</p>@endif
        </div>
    </div>
    <div class="eb-grid-3">
        <div class="eb-form-group">
            <label class="eb-label" for="{{ $id('description') }}">Description</label>
            <input class="eb-input" id="{{ $id('description') }}" name="{{ $field('description') }}" value="{{ $row['description'] }}" maxlength="300">
        </div>
        <div class="eb-form-group">
            <label class="eb-label" for="{{ $id('sale_start') }}">Sales start</label>
            <input class="eb-input" id="{{ $id('sale_start') }}" type="datetime-local" name="{{ $field('sale_start') }}" value="{{ $row['sale_start'] }}">
        </div>
        <div class="eb-form-group">
            <label class="eb-label" for="{{ $id('sale_end') }}">Sales end</label>
            <input class="eb-input @if($err('sale_end')) eb-input-error @endif" id="{{ $id('sale_end') }}" type="datetime-local" name="{{ $field('sale_end') }}" value="{{ $row['sale_end'] }}">
            @if($err('sale_end'))<p class="eb-field-error">{{ $err('sale_end') }}</p>@endif
        </div>
    </div>
    <div style="text-align: right;">
        <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm" data-remove-tier>Remove ticket type</button>
    </div>
</fieldset>
