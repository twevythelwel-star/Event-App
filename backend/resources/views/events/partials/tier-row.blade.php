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
            <label class="eb-label" for="{{ $id('category') }}">Ticket type</label>
            <select class="eb-input" id="{{ $id('category') }}" name="{{ $field('category') }}">
                @foreach(['General Admission', 'Early Bird', 'VIP', 'Premium VIP', 'Family Pass', 'Group Pass', 'Student Ticket', 'Child Ticket', 'Complimentary Ticket', 'Vendor Pass', 'Staff Pass', 'Custom Ticket Type', 'General', 'Group', 'Community'] as $category)
                    <option value="{{ $category }}" @selected(($row['category'] ?? 'General Admission') === $category)>{{ $category }}</option>
                @endforeach
            </select>
        </div>
        <div class="eb-form-group">
            <label class="eb-label" for="{{ $id('price') }}">Price (USD) <span class="eb-label-required" aria-hidden="true">*</span></label>
            <input class="eb-input eb-input-numeric @if($err('price')) eb-input-error @endif" id="{{ $id('price') }}" type="number" min="0" step="0.01" name="{{ $field('price') }}" value="{{ $row['price'] }}" required>
            @if($err('price'))<p class="eb-field-error">{{ $err('price') }}</p>@endif
        </div>
        <div class="eb-form-group">
            <label class="eb-label" for="{{ $id('price_jmd') }}">Price (JMD) <span class="eb-label-required" aria-hidden="true">*</span></label>
            <input class="eb-input eb-input-numeric @if($err('price_jmd')) eb-input-error @endif" id="{{ $id('price_jmd') }}" type="number" min="0" step="1" name="{{ $field('price_jmd') }}" value="{{ $row['price_jmd'] ?? '' }}" required>
            @if($err('price_jmd'))<p class="eb-field-error">{{ $err('price_jmd') }}</p>@endif
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
    <div class="eb-grid-4">
        <div class="eb-form-group">
            <label class="eb-label" for="{{ $id('description') }}">Description</label>
            <input class="eb-input" id="{{ $id('description') }}" name="{{ $field('description') }}" value="{{ $row['description'] }}" maxlength="300">
        </div>
        <div class="eb-form-group">
            <label class="eb-label" for="{{ $id('early_bird_price') }}">Early-bird price (USD)</label>
            <input class="eb-input eb-input-numeric @if($err('early_bird_price')) eb-input-error @endif" id="{{ $id('early_bird_price') }}" type="number" min="0" step="0.01" name="{{ $field('early_bird_price') }}" value="{{ $row['early_bird_price'] ?? '' }}" aria-describedby="{{ $id('early_bird_help') }}">
            <p class="eb-help-text" id="{{ $id('early_bird_help') }}">Charged until the early-bird period in "When and where" ends. Leave empty for none.</p>
            @if($err('early_bird_price'))<p class="eb-field-error">{{ $err('early_bird_price') }}</p>@endif
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
    <div class="eb-grid-4">
        <div class="eb-form-group">
            <label class="eb-label" for="{{ $id('min_per_order') }}">Minimum per order</label>
            <input class="eb-input eb-input-numeric" id="{{ $id('min_per_order') }}" type="number" min="1" max="100" step="1" name="{{ $field('min_per_order') }}" value="{{ $row['min_per_order'] ?? 1 }}" required>
        </div>
        <div class="eb-form-group">
            <label class="eb-label" for="{{ $id('admission_count') }}">People admitted</label>
            <input class="eb-input eb-input-numeric" id="{{ $id('admission_count') }}" type="number" min="1" max="100" step="1" name="{{ $field('admission_count') }}" value="{{ $row['admission_count'] ?? 1 }}" required>
        </div>
        <div class="eb-form-group">
            <label class="eb-label" for="{{ $id('age_restriction') }}">Age restriction</label>
            <input class="eb-input" id="{{ $id('age_restriction') }}" name="{{ $field('age_restriction') }}" value="{{ $row['age_restriction'] ?? '' }}" maxlength="100" placeholder="e.g. 21+">
        </div>
        <div class="eb-form-group">
            <label class="eb-label" for="{{ $id('visibility') }}">Visibility</label>
            <select class="eb-input" id="{{ $id('visibility') }}" name="{{ $field('visibility') }}">
                <option value="public" @selected(($row['visibility'] ?? 'public') === 'public')>Public</option>
                <option value="hidden" @selected(($row['visibility'] ?? 'public') === 'hidden')>Hidden</option>
            </select>
        </div>
    </div>
    <div class="eb-grid-2">
        <div class="eb-form-group">
            <label class="eb-label" for="{{ $id('admission_privileges') }}">Admission privileges</label>
            <textarea class="eb-input" id="{{ $id('admission_privileges') }}" name="{{ $field('admission_privileges') }}" maxlength="500" rows="2">{{ $row['admission_privileges'] ?? '' }}</textarea>
        </div>
        <div class="eb-form-group">
            <label class="eb-label" for="{{ $id('included_food_and_drinks') }}">Included food or drinks</label>
            <textarea class="eb-input" id="{{ $id('included_food_and_drinks') }}" name="{{ $field('included_food_and_drinks') }}" maxlength="500" rows="2">{{ $row['included_food_and_drinks'] ?? '' }}</textarea>
        </div>
    </div>
    <div class="eb-form-group">
        <label class="eb-label" for="{{ $id('benefits') }}">Ticket benefits (one per line)</label>
        <textarea class="eb-input" id="{{ $id('benefits') }}" name="{{ $field('benefits') }}" maxlength="1000" rows="3" placeholder="Priority entrance&#10;Reserved seating">{{ $row['benefits'] ?? '' }}</textarea>
    </div>
    <div class="eb-grid-4">
        <div class="eb-form-group">
            <label class="eb-label" for="{{ $id('design_style') }}">Ticket design</label>
            <select class="eb-input" id="{{ $id('design_style') }}" name="{{ $field('design_style') }}">
                @foreach(['premium_minimal' => 'Premium Minimal', 'vip_dark' => 'VIP Dark', 'festival_color' => 'Festival Color', 'modern_digital' => 'Modern Digital', 'elegant_gold' => 'Elegant Gold', 'concert_pass' => 'Concert Pass'] as $value => $label)
                    <option value="{{ $value }}" @selected(($row['design_style'] ?? 'premium_minimal') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="eb-form-group">
            <label class="eb-label" for="{{ $id('accent_color') }}">Accent color</label>
            <select class="eb-input" id="{{ $id('accent_color') }}" name="{{ $field('accent_color') }}">
                @foreach(['indigo', 'emerald', 'coral', 'cyan', 'violet', 'gold'] as $color)
                    <option value="{{ $color }}" @selected(($row['accent_color'] ?? 'indigo') === $color)>{{ ucfirst($color) }}</option>
                @endforeach
            </select>
        </div>
        <div class="eb-form-group">
            <label class="eb-label" for="{{ $id('background_style') }}">Background</label>
            <select class="eb-input" id="{{ $id('background_style') }}" name="{{ $field('background_style') }}">
                @foreach(['solid' => 'Solid', 'gradient' => 'Gradient', 'glow' => 'Soft glow'] as $value => $label)
                    <option value="{{ $value }}" @selected(($row['background_style'] ?? 'solid') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="eb-form-group" style="display: flex; flex-direction: column; justify-content: center; gap: .5rem;">
            <input type="hidden" name="{{ $field('refund_eligible') }}" value="0">
            <label class="eb-choice-label"><input class="eb-checkbox" type="checkbox" name="{{ $field('refund_eligible') }}" value="1" @checked($row['refund_eligible'] ?? true)> Refund eligible</label>
            <input type="hidden" name="{{ $field('is_active') }}" value="0">
            <label class="eb-choice-label"><input class="eb-checkbox" type="checkbox" name="{{ $field('is_active') }}" value="1" @checked($row['is_active'] ?? true)> Ticket is active</label>
        </div>
    </div>
    <div class="eb-ticket-designer" data-ticket-designer>
        <x-ticket-mockup :design="$row['design_style'] ?? 'premium_minimal'" :accent="$row['accent_color'] ?? 'indigo'" :background="$row['background_style'] ?? 'solid'"
                         :name="($row['name'] ?? '') ?: 'Ticket name'" :price="($row['price'] ?? '') !== '' ? '$' . number_format((float) $row['price'], 2) : '$0.00'" />
        <div class="eb-ticket-designer-controls">
            <span class="eb-text-label">Live preview</span>
            <div class="eb-segmented" role="group" aria-label="Preview as">
                <button type="button" data-ticket-mode="phone" aria-pressed="true">Phone</button>
                <button type="button" data-ticket-mode="print" aria-pressed="false">Printed</button>
            </div>
            <p class="eb-help-text" style="margin: 0; max-width: 18rem;">Changes to the name, price, design, colour and background show here as you make them.</p>
        </div>
    </div>
    <div style="text-align: right;">
        <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm" data-remove-tier>Remove ticket type</button>
    </div>
</fieldset>
