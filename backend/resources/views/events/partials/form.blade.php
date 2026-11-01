{{--
    The event form, shared by Create and Edit. $event is null when creating.
    Ticket types are rows of ticket_tiers[n][...]; the controller turns them into the stored tier format.
--}}
@php
    $e = $event ?? null;
    $tz = $e?->timezone ?: config('events.market.default_timezone', 'America/New_York');
    $deadline = $e?->advance_order_deadline ? \Illuminate\Support\Carbon::parse($e->advance_order_deadline)->setTimezone($tz)->format('Y-m-d\TH:i') : '';
    $toLocal = function ($iso) use ($tz) {
        try { return $iso ? \Illuminate\Support\Carbon::parse($iso)->setTimezone($tz)->format('Y-m-d\TH:i') : ''; } catch (\Throwable) { return ''; }
    };
    $tierRows = old('ticket_tiers', collect(is_array($e?->ticket_tiers) ? $e->ticket_tiers : [])
        ->filter(fn ($tier) => is_array($tier))
        ->map(fn ($tier) => [
            'id' => $tier['id'] ?? '',
            'name' => $tier['name'] ?? '',
            'description' => $tier['description'] ?? '',
            'price' => (\App\Services\Checkout\TicketTierCatalog::priceMinorFor($tier, 'USD') ?? 0) / 100,
            'available_stock' => $tier['availableStock'] ?? $tier['available_stock'] ?? '',
            'max_per_customer' => $tier['maxPerCustomer'] ?? $tier['max_per_customer'] ?? 1,
            'sale_start' => $toLocal($tier['saleStartIso'] ?? $tier['sale_start_iso'] ?? null),
            'sale_end' => $toLocal($tier['saleEndIso'] ?? $tier['sale_end_iso'] ?? null),
        ])->values()->all());
@endphp

<section class="eb-panel card" style="margin: 0;">
    <div class="eb-card-header card-header"><div>
        <h2 class="eb-card-title card-title">Basics</h2>
        <p class="eb-card-subtitle card-subtitle">What people see first on the event page and in search.</p>
    </div></div>
    <div class="eb-grid-2">
        <x-form-field name="title" label="Event name" :value="$e?->title" :required="true" placeholder="e.g. Summer Food and Music Festival" />
        <x-form-field name="tagline" label="Tagline" :value="$e?->tagline" placeholder="One line about the event" />
    </div>
    <x-form-field name="slug" label="Web address (optional)" :value="$e?->slug" placeholder="summer-food-festival-2027"
                  help="Lower-case letters, numbers and dashes. Left empty, one is made from the name." />
    <x-form-field name="description" type="textarea" label="Description" :value="$e?->description" :rows="5"
                  help="Programme, parking, entry rules and anything guests should know." />
</section>

@php
    // The host's own choice; for an event that has not chosen yet, the suggestion from its text is pre-ticked.
    $chosenTraditions = old('traditions', $e ? $e->traditionKeys() : []);
@endphp
<section class="eb-panel card" style="margin: 0;">
    <fieldset style="border: 0; margin: 0; padding: 0;">
        <legend class="eb-card-title card-title" style="padding: 0;">Traditions and cuisines</legend>
        <p class="eb-card-subtitle card-subtitle" style="margin: 0 0 var(--space-4);">
            Choose up to four that this event celebrates or serves. Guests use them to find events; leave them all unticked if none fit.
            @if($e && $e->traditions === null && $chosenTraditions)<br>Suggested from your event's description and menu; check them before saving.@endif
        </p>
        <input type="hidden" name="traditions_submitted" value="1">
        <div class="eb-grid-3" style="gap: var(--space-2);">
            @foreach(config('traditions') as $key => $tradition)
                <label class="eb-choice-label">
                    <input type="checkbox" class="eb-checkbox" name="traditions[]" value="{{ $key }}" @checked(in_array($key, (array) $chosenTraditions, true))>
                    <span>{{ $tradition['label'] }}</span>
                </label>
            @endforeach
        </div>
        @error('traditions')<div class="eb-field-error" role="alert"><span>{{ $message }}</span></div>@enderror
        @error('traditions.*')<div class="eb-field-error" role="alert"><span>{{ $message }}</span></div>@enderror
    </fieldset>
</section>

<section class="eb-panel card" style="margin: 0;">
    <div class="eb-card-header card-header"><div>
        <h2 class="eb-card-title card-title">When and where</h2>
        <p class="eb-card-subtitle card-subtitle">The event date controls when it is listed as upcoming. Times are in the event's own time zone.</p>
    </div></div>
    <div class="eb-grid-3">
        <x-form-field name="event_date_iso" type="date" label="Event date" :value="$e?->event_date_iso ? substr($e->event_date_iso, 0, 10) : ''" :required="true" />
        <x-form-field name="date_str" label="Date as shown" :value="$e?->date_str" :required="true" placeholder="Saturday, August 15, 2027"
                      help="Filled in from the event date if you leave it empty." />
        <x-form-field name="time_str" label="Time as shown" :value="$e?->time_str" :required="true" placeholder="4:00 PM – 10:00 PM" />
    </div>
    <div class="eb-grid-2">
        <x-form-field name="venue_name" label="Venue" :value="$e?->venue_name" :required="true" />
        <x-form-field name="timezone" type="select" label="Time zone" :value="$e?->timezone" placeholder="Work it out from the city and state"
                      :options="collect($timezones)->mapWithKeys(fn ($zone) => [$zone => $zone])->all()" />
    </div>
    <input type="hidden" name="country_code" value="US">
    <div class="eb-grid-2">
        <x-form-field name="street_address" label="Street address" :value="$e?->street_address" :required="true" />
        <x-form-field name="venue_building_name" label="Building or room (optional)" :value="$e?->venue_building_name" />
    </div>
    <div class="eb-grid-3">
        <div class="eb-form-group">
            <label for="city" class="eb-label"><span>City or town</span> <span class="eb-label-required" aria-hidden="true">*</span></label>
            <input type="text" id="city" name="city" value="{{ old('city', $e?->city) }}" required maxlength="120" list="event-city-options"
                   class="eb-input @error('city') eb-input-error @enderror">
            <datalist id="event-city-options">@foreach($markets as $market)<option value="{{ $market->city }}"></option>@endforeach</datalist>
            @error('city')<div class="eb-field-error" role="alert"><span>{{ $message }}</span></div>@enderror
        </div>
        <x-form-field name="state_code" type="select" label="State or territory" :value="$e?->state_code" :required="true" placeholder="Choose a state"
                      :options="$states->mapWithKeys(fn ($state) => [$state->code => $state->name . ' (' . $state->code . ')'])->all()" />
        <x-form-field name="zip_code" label="ZIP code" :value="$e?->zip_code" :required="true" placeholder="12345" />
    </div>
</section>

<section class="eb-panel card" style="margin: 0;">
    <div class="eb-card-header card-header"><div>
        <h2 class="eb-card-title card-title">Contact</h2>
        <p class="eb-card-subtitle card-subtitle">Printed on tickets and receipts so guests can reach you.</p>
    </div></div>
    <div class="eb-grid-2">
        <x-form-field name="organizer_name" label="Host or organization name" :value="$e?->organizer_name ?? auth()->user()?->name" :required="true" />
        <x-form-field name="organizer_phone" type="tel" label="Phone" :value="$e?->organizer_phone ?? auth()->user()?->phone" :required="true" />
        <x-form-field name="organizer_whatsapp" type="tel" label="WhatsApp (optional)" :value="$e?->organizer_whatsapp" />
        <x-form-field name="organizer_email" type="email" label="Support email (optional)" :value="$e?->organizer_email ?? auth()->user()?->email" />
    </div>
</section>

<section class="eb-panel card" style="margin: 0;">
    <div class="eb-card-header card-header"><div>
        <h2 class="eb-card-title card-title">Food ordering</h2>
        <p class="eb-card-subtitle card-subtitle">Prices are in US dollars. Menu items are managed from the event's menu once it is created.</p>
    </div></div>
    <input type="hidden" name="currency" value="USD">
    <div class="eb-grid-3">
        <x-form-field name="advance_order_deadline" type="datetime-local" label="Food orders close" :value="$deadline" :required="true"
                      help="In the event's time zone." />
        <x-form-field name="packaging_fee_usd" type="number" label="Packaging fee per order ($)" :value="$e?->packaging_fee_usd ?? '0.00'" :min="0" :step="0.25" />
        <x-form-field name="pickup_instructions" label="Where to collect food" :value="$e?->pickup_instructions" placeholder="e.g. Show your ticket QR at the food tent" />
    </div>
</section>

<section class="eb-panel card" style="margin: 0;" id="tickets">
    <div class="eb-card-header card-header"><div>
        <h2 class="eb-card-title card-title">Tickets</h2>
        <p class="eb-card-subtitle card-subtitle">One row per ticket type. "Tickets available" is how many are left to sell. Leave the sale dates empty to sell until the event.</p>
    </div></div>
    <input type="hidden" name="ticket_tiers_submitted" value="1">
    @error('ticket_tiers')<x-alert type="error">{{ $message }}</x-alert>@enderror
    <div id="tier-rows" class="eb-stack">
        @foreach($tierRows as $i => $row)
            @include('events.partials.tier-row', ['i' => $i, 'row' => $row])
        @endforeach
    </div>
    <template id="tier-template">
        @include('events.partials.tier-row', ['i' => '__INDEX__', 'row' => ['id' => '', 'name' => '', 'description' => '', 'price' => '', 'available_stock' => '', 'max_per_customer' => 4, 'sale_start' => '', 'sale_end' => '']])
    </template>
    <p id="tier-empty" class="eb-help-text" @if($tierRows) hidden @endif>No ticket types yet. Free events still need one, at $0.</p>
    <div style="margin-top: var(--space-4);">
        <button type="button" id="add-tier" class="eb-btn eb-btn-secondary eb-btn-sm"><x-icon name="plus" size="sm" /> Add ticket type</button>
    </div>
</section>

<section class="eb-panel card" style="margin: 0;">
    <div class="eb-card-header card-header"><div>
        <h2 class="eb-card-title card-title">Flyer</h2>
        <p class="eb-card-subtitle card-subtitle">JPG, PNG or WebP, up to 5 MB, between 300 × 200 and 4500 × 4500 pixels.</p>
    </div></div>
    <div class="eb-grid-2" style="align-items: start;">
        <div class="eb-form-group">
            <label for="flyer-input" class="eb-file-upload">
                <x-icon name="plus" size="md" />
                <span class="eb-text-label">{{ $e?->flyer_path ? 'Choose a new flyer to replace the current one' : 'Choose a flyer image' }}</span>
                <input type="file" id="flyer-input" name="flyer" accept="image/jpeg,image/png,image/webp" class="eb-input" style="max-width: 280px;">
            </label>
            @error('flyer')<div class="eb-field-error" role="alert"><span>{{ $message }}</span></div>@enderror
            <p id="flyer-check" class="eb-help-text" role="status" aria-live="polite"></p>
        </div>
        <div>
            <img id="flyer-preview" alt="Flyer preview" src="{{ $e?->flyer_path ? $e->display_image_url : '' }}"
                 style="width: 100%; max-height: 260px; object-fit: contain; border-radius: var(--radius-md); border: 1px solid var(--color-border); background: var(--color-background-secondary);" @unless($e?->flyer_path) hidden @endunless>
        </div>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Ticket rows
        const rows = document.getElementById('tier-rows');
        const template = document.getElementById('tier-template');
        const empty = document.getElementById('tier-empty');
        let next = rows.children.length;
        const sync = () => { empty.hidden = rows.children.length > 0; };
        document.getElementById('add-tier').addEventListener('click', function () {
            rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(next++)));
            sync();
            rows.lastElementChild.querySelector('input').focus();
        });
        rows.addEventListener('click', function (event) {
            const button = event.target.closest('[data-remove-tier]');
            if (!button) return;
            if (!confirm('Remove this ticket type? Tickets already sold are not affected, but no more will be sold.')) return;
            button.closest('[data-tier-row]').remove();
            sync();
        });

        // Fill the displayed date from the date picker when it is empty
        const date = document.getElementById('eb-field-event_date_iso');
        const shown = document.getElementById('eb-field-date_str');
        if (date && shown) {
            date.addEventListener('change', function () {
                if (shown.value.trim() !== '' || !date.value) return;
                const d = new Date(date.value + 'T12:00:00');
                shown.value = d.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            });
        }

        // Flyer preview and size check (the server checks again)
        const input = document.getElementById('flyer-input');
        const preview = document.getElementById('flyer-preview');
        const check = document.getElementById('flyer-check');
        input.addEventListener('change', function () {
            const file = input.files && input.files[0];
            if (!file) return;
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                check.textContent = 'That file is not a JPG, PNG or WebP image.';
                input.value = '';
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                check.textContent = 'That image is larger than 5 MB.';
                input.value = '';
                return;
            }
            check.textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB) will be uploaded when you save.';
            preview.src = URL.createObjectURL(file);
            preview.hidden = false;
        });
    });
</script>
