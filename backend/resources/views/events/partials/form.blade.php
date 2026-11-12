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
            'category' => $tier['category'] ?? 'General Admission',
            'price' => (\App\Services\Checkout\TicketTierCatalog::priceMinorFor($tier, 'USD') ?? 0) / 100,
            'price_jmd' => (\App\Services\Checkout\TicketTierCatalog::priceMinorFor($tier, 'JMD') ?? 0) / 100,
            'early_bird_price' => $tier['earlyBirdPriceUSD'] ?? '',
            'available_stock' => $tier['availableStock'] ?? $tier['available_stock'] ?? '',
            'max_per_customer' => $tier['maxPerCustomer'] ?? $tier['max_per_customer'] ?? 1,
            'min_per_order' => $tier['minPerOrder'] ?? 1,
            'admission_count' => $tier['admissionCount'] ?? 1,
            'age_restriction' => $tier['ageRestriction'] ?? '',
            'admission_privileges' => $tier['admissionPrivileges'] ?? '',
            'included_food_and_drinks' => $tier['includedFoodAndDrinks'] ?? '',
            'benefits' => implode("\n", (array) ($tier['benefits'] ?? [])),
            'refund_eligible' => $tier['refundEligible'] ?? true,
            'visibility' => $tier['visibility'] ?? 'public',
            'design_style' => $tier['designStyle'] ?? 'premium_minimal',
            'accent_color' => $tier['accentColor'] ?? 'indigo',
            'background_style' => $tier['backgroundStyle'] ?? 'solid',
            'is_active' => $tier['isActive'] ?? true,
            'sale_start' => $toLocal($tier['saleStartIso'] ?? $tier['sale_start_iso'] ?? null),
            'sale_end' => $toLocal($tier['saleEndIso'] ?? $tier['sale_end_iso'] ?? null),
        ])->values()->all());
    $traditionCatalog = (array) ($traditions ?? config('traditions', []));
@endphp

<section class="eb-panel card studio-section-card" style="margin: 0;" id="studio-overview" data-studio-section="overview">
    <div class="eb-card-header card-header"><div>
        <h2 class="eb-card-title card-title">Basics</h2>
        <p class="eb-card-subtitle card-subtitle">What people see first on the event page and in search.</p>
    </div></div>
    @if(!$e && auth()->user()?->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN) && !\App\Services\TenantContext::id())
        {{-- System administrators create events on behalf of an organizer; the server checks the choice. --}}
        <x-form-field name="organizer_id" type="select" label="Organizer" :required="true" placeholder="Choose the organizer this event belongs to"
                      :options="\App\Models\Organizer::orderBy('name')->pluck('name', 'id')->all()" />
    @endif
    <div class="eb-grid-2">
        <x-form-field name="title" label="Event name" :value="$e?->title" :required="true" placeholder="e.g. Summer Food and Music Festival" />
        <x-form-field name="tagline" label="Tagline" :value="$e?->tagline" placeholder="One line about the event" />
    </div>
    <x-form-field name="slug" label="Web address (optional)" :value="$e?->slug" placeholder="summer-food-festival-2027"
                  help="Lower-case letters, numbers and dashes. Left empty, one is made from the name." />
    <x-form-field name="description" type="textarea" label="Description" :value="$e?->description" :rows="5"
                  help="Programme, parking, entry rules and anything guests should know." />
    <div class="eb-grid-2">
        <x-form-field name="category" id="eb-field-category" label="Event category" :value="$e?->category" type="select" :options="['Community'=>'Community','Food'=>'Food & drink','Music'=>'Music','Culture'=>'Arts & culture','Sports'=>'Sports','Fundraiser'=>'Fundraiser','Market'=>'Market','Workshop'=>'Workshop','Other'=>'Other']" placeholder="Choose a category" />
        @php $photoSubject = old('photo_subject', $e?->photo_subject); @endphp
        <div class="eb-field">
            <label class="eb-label" for="eb-field-photo-subject">What kind of event is this? <span class="eb-help-text">(optional)</span></label>
            <select id="eb-field-photo-subject" name="photo_subject" class="eb-input" aria-describedby="eb-photo-subject-help">
                <option value="">Not specified</option>
                @foreach(\App\Services\PhotoLibrary::subjectOptions() as $group => $options)
                    <optgroup label="{{ $group }}">@foreach($options as $value => $label)<option value="{{ $value }}" @selected($photoSubject === $value)>{{ $label }}</option>@endforeach</optgroup>
                @endforeach
            </select>
            <p id="eb-photo-subject-help" class="eb-help-text" style="margin:4px 0 0">Until you upload a flyer, guests may see an illustrative photo of this kind of event, labelled "Illustrative photo". Your own flyer always replaces it.</p>
        </div>
        <div class="studio-inline-note"><span>✦</span><p>A clear title and inviting description help guests understand what makes this gathering special.</p></div>
    </div>
</section>

@php
    // The host's own choice; for an event that has not chosen yet, the suggestion from its text is pre-ticked.
    $chosenTraditions = old('traditions', $e ? $e->traditionKeys() : []);
@endphp
<section class="eb-panel card studio-section-card" style="margin: 0;" id="studio-identity" data-studio-section="identity">
    <fieldset style="border: 0; margin: 0; padding: 0;">
        <legend class="eb-card-title card-title" style="padding: 0;">Traditions and cuisines</legend>
        <p class="eb-card-subtitle card-subtitle" style="margin: 0 0 var(--space-4);">
            Choose up to four that this event celebrates or serves. Guests use them to find events; leave them all unticked if none fit.
            @if($e && $e->traditions === null && $chosenTraditions)<br>Suggested from your event's description and menu; check them before saving.@endif
        </p>
        <input type="hidden" name="traditions_submitted" value="1">
        <div class="eb-grid-3" style="gap: var(--space-2);">
            @foreach($traditionCatalog as $key => $tradition)
                <label class="eb-choice-label">
                    <input type="checkbox" class="eb-checkbox" name="traditions[]" value="{{ $key }}" @checked(in_array($key, (array) $chosenTraditions, true))>
                    <span>{{ $tradition['label'] }}</span>
                </label>
            @endforeach
        </div>
        @error('traditions')<div class="eb-field-error" role="alert"><span>{{ $message }}</span></div>@enderror
        @error('traditions.*')<div class="eb-field-error" role="alert"><span>{{ $message }}</span></div>@enderror
    </fieldset>
    @include('events.partials.country-picker')
</section>

@php
    $local = fn ($instant) => $instant ? \Illuminate\Support\Carbon::parse($instant)->setTimezone($tz)->format('Y-m-d\TH:i') : '';
    $hhmm = fn ($value) => preg_match('/^\d{2}:\d{2}$/', (string) $value) ? $value : '';
    $countries = (array) config('events.market.allowed_countries', ['US']);
    $initialCountry = strtoupper(old('country_code', $e?->country_code ?? 'US'));
    $maps = app(\App\Services\Events\MapLocationService::class);
    $pinPreview = ($e?->location_verified_at && $e->latitude !== null) ? $maps->previewUrl((float) $e->latitude, (float) $e->longitude) : null;
@endphp
<section class="eb-panel card studio-section-card" style="margin: 0;" id="studio-location" data-studio-section="location">
    <div class="eb-card-header card-header"><div>
        <h2 class="eb-card-title card-title">When and where</h2>
        <p class="eb-card-subtitle card-subtitle">All times are in the event's own time zone. An event can run past midnight or over several days.</p>
    </div></div>

    <fieldset class="eb-fieldset">
        <legend class="eb-text-label">Schedule</legend>
        <div class="eb-grid-4">
            <x-form-field name="event_date_iso" type="date" label="Start date" :value="$e?->event_date_iso ? substr($e->event_date_iso, 0, 10) : ''" :required="true" />
            <x-form-field name="start_time" type="time" label="Start time" :value="$hhmm($e?->start_time)" />
            <x-form-field name="end_date_iso" type="date" label="End date" :value="$e?->end_date_iso ? substr($e->end_date_iso, 0, 10) : ''"
                          help="For events over several days. Leave empty if it ends the same night, even after midnight." />
            <x-form-field name="end_time" type="time" label="End time" :value="$hhmm($e?->end_time)" />
        </div>
        <div class="eb-grid-4">
            <x-form-field name="doors_open_time" type="time" label="Doors open" :value="$e?->doors_open_time" help="On the start date, at or before the start." />
            <x-form-field name="setup_time" type="time" label="Setup begins (optional)" :value="$e?->setup_time" help="For your team and vendors; not shown to guests." />
            <x-form-field name="timezone" type="select" label="Time zone" :value="$e?->timezone" placeholder="Work it out from the city and state"
                          :options="collect($timezones)->mapWithKeys(fn ($zone) => [$zone => $zone])->all()" />
            <div></div>
        </div>
        <div class="eb-grid-3">
            <x-form-field name="ticket_sales_start_at" type="datetime-local" label="Ticket sales open" :value="$local($e?->ticket_sales_start_at)" help="Empty: on sale as soon as the event is published." />
            <x-form-field name="ticket_sales_end_at" type="datetime-local" label="Ticket sales close" :value="$local($e?->ticket_sales_end_at)" help="Empty: until the event ends." />
            <x-form-field name="early_bird_starts_at" type="datetime-local" label="Early-bird pricing starts" :value="$local($e?->early_bird_starts_at)" help="Empty: when ticket sales open." />
            <x-form-field name="early_bird_ends_at" type="datetime-local" label="Early-bird pricing ends" :value="$local($e?->early_bird_ends_at)" help="Ticket types with an early-bird price charge it until then." />
        </div>
        <div class="eb-grid-2">
            <x-form-field name="date_str" label="Date as shown (optional)" :value="$e?->date_str" placeholder="Saturday, August 15, 2027" help="Written from the dates when left empty." />
            <x-form-field name="time_str" label="Time as shown (optional)" :value="$e?->time_str" placeholder="4:00 PM – 10:00 PM" help="Written from the times when left empty." />
        </div>
    </fieldset>

    <fieldset class="eb-fieldset">
        <legend class="eb-text-label">Venue</legend>
        <div class="eb-grid-2">
            <x-form-field name="venue_name" label="Venue name" :value="$e?->venue_name" :required="true" />
            <x-form-field name="venue_type" type="select" label="Venue type" :value="$e?->venue_type" placeholder="Choose a type" :options="\App\Models\Event::VENUE_TYPES" />
        </div>
        @if(count($countries) > 1)
            <x-form-field name="country_code" type="select" label="Country" :value="$e?->country_code ?? 'US'" :options="collect($countries)->mapWithKeys(fn ($c) => [$c => $c])->all()" />
        @else
            <input type="hidden" name="country_code" value="{{ $countries[0] ?? 'US' }}">
        @endif
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
            @if(count($countries) > 1)
                <div data-location-country-fields="US" @if($initialCountry !== 'US') hidden @endif>
                    <x-form-field name="state_code" id="us-state-code" type="select" label="State or territory" :value="old('state_code', $e?->state_code)" :required="true" :disabled="$initialCountry !== 'US'" placeholder="Choose a state"
                                  :options="$states->mapWithKeys(fn ($state) => [$state->code => $state->name . ' (' . $state->code . ')'])->all()" />
                    <x-form-field name="zip_code" id="us-zip-code" label="ZIP code" :value="old('zip_code', $e?->zip_code)" :required="true" :disabled="$initialCountry !== 'US'" placeholder="12345" />
                </div>
                <div data-location-country-fields="OTHER" @if($initialCountry === 'US') hidden @endif>
                    <x-form-field name="state_code" id="other-region" label="State / province / region" :value="old('state_code', $e?->state_code)" :disabled="$initialCountry === 'US'" />
                    <x-form-field name="zip_code" id="other-postal-code" label="Postal code" :value="old('zip_code', $e?->zip_code)" :disabled="$initialCountry === 'US'" />
                </div>
            @elseif(strtoupper(old('country_code', $e?->country_code ?? 'US')) === 'US')
                <x-form-field name="state_code" type="select" label="State or territory" :value="old('state_code', $e?->state_code)" :required="true" placeholder="Choose a state"
                              :options="$states->mapWithKeys(fn ($state) => [$state->code => $state->name . ' (' . $state->code . ')'])->all()" />
                <x-form-field name="zip_code" label="ZIP code" :value="old('zip_code', $e?->zip_code)" :required="true" placeholder="12345" />
            @else
                <x-form-field name="state_code" label="State / province / region" :value="old('state_code', $e?->state_code)" />
                <x-form-field name="zip_code" label="Postal code" :value="old('zip_code', $e?->zip_code)" />
            @endif
        </div>

        <div class="eb-map-confirm" data-map-lookup data-endpoint="{{ route('events.location-lookup') }}" data-enabled="{{ $maps->enabled() ? '1' : '0' }}">
            <input type="hidden" name="location_token" value="" data-location-token>
            <div style="display: flex; gap: var(--space-3); align-items: center; flex-wrap: wrap;">
                <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" data-map-find>Find this address on the map</button>
                <span class="eb-help-text" data-map-status role="status" aria-live="polite">
                    @if(!$maps->enabled()) Map lookup isn't set up for this site, so no map pin is shown. Check the address above carefully.
                    @elseif($pinPreview) This address is pinned on the map. Look it up again after changing the address.
                    @else Look the address up and choose the right place to show a map pin. @endif
                </span>
            </div>
            <ul class="eb-map-results" data-map-results hidden></ul>
            @if($pinPreview)
                <figure class="eb-map-preview" data-map-current>
                    <img src="{{ $pinPreview }}" alt="Map of {{ $e->venue_address }}" loading="lazy">
                    <figcaption class="eb-help-text">Pinned at the place found for “{{ $e->venue_address }}”.</figcaption>
                </figure>
                <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="clear_location_pin" value="1"> <span>Remove the map pin</span></label>
            @endif
            <figure class="eb-map-preview" data-map-chosen hidden><img alt="Map of the chosen place"><figcaption class="eb-help-text"></figcaption></figure>
        </div>

        <x-form-field name="venue_instructions" type="textarea" label="Getting in" :value="$e?->venue_instructions" :rows="2" placeholder="Entrance, check-in tent, what to bring" />
        <div class="eb-grid-2">
            <x-form-field name="parking_information" type="textarea" label="Parking" :value="$e?->parking_information" :rows="2" placeholder="Where to park, cost, rideshare drop-off" />
            <x-form-field name="accessibility_information" type="textarea" label="Accessibility" :value="$e?->accessibility_information" :rows="2" placeholder="Step-free access, seating, restrooms, quiet areas" />
        </div>
    </fieldset>
</section>
<section class="eb-panel card studio-section-card" style="margin: 0;" id="studio-contact" data-studio-section="contact">
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

<section class="eb-panel card studio-section-card" style="margin: 0;" id="studio-menu" data-studio-section="menu">
    <div class="eb-card-header card-header"><div>
        <h2 class="eb-card-title card-title">Food ordering</h2>
        <p class="eb-card-subtitle card-subtitle">Create the dishes and drinks guests can order. Cuisine, serving category and pricing style connect to EventBite's existing menu and checkout.</p>
    </div></div>
    <input type="hidden" name="menu_items_submitted" value="1">
    @php
        $windowRows = old('food_pickup_windows', $e?->pickupWindows() ?? []);
    @endphp
    <input type="hidden" name="supports_food_ordering" value="0">
    <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="supports_food_ordering" value="1" @checked(old('supports_food_ordering', $e?->supports_food_ordering ?? true))> <span>Guests can order food for this event</span></label>
    <div class="eb-grid-3">
        <x-form-field name="food_ordering_mode" type="select" label="When guests can order" :value="$e?->food_ordering_mode ?? 'preorder'" :options="\App\Models\Event::FOOD_ORDERING_MODES" />
        <x-form-field name="advance_order_deadline" type="datetime-local" label="Preorders close" :value="$deadline" :required="true"
                      help="In the event's time zone." />
        <x-form-field name="food_fulfillment" type="select" label="How guests get food" :value="$e?->food_fulfillment ?? 'pickup'" :options="\App\Models\Event::FOOD_FULFILLMENT" />
    </div>
    <div class="eb-grid-4">
        <x-form-field name="food_service_start_time" type="time" label="Food service starts" :value="$e?->food_service_start_time" help="Event-day orders are taken during service." />
        <x-form-field name="food_service_end_time" type="time" label="Food service ends" :value="$e?->food_service_end_time" />
        <x-form-field name="packaging_fee_usd" type="number" label="Packaging fee per order ($)" :value="$e?->packaging_fee_usd ?? '0.00'" :min="0" :step="0.25" />
        <x-form-field name="food_service_fee" type="number" label="Food service fee per order ($)" :value="old('food_service_fee', \App\Support\Money::ofMinor((int) ($e?->food_service_fee_minor ?? 0), 'USD')->toDecimalString())" :min="0" :step="0.25" />
    </div>
    <div class="eb-grid-3">
        <x-form-field name="pickup_instructions" label="Where to collect food" :value="$e?->pickup_instructions" placeholder="e.g. Show your ticket QR at the food tent" />
        <x-form-field name="food_delivery_instructions" label="Delivery to tables or areas (if offered)" :value="$e?->food_delivery_instructions" placeholder="e.g. We deliver to tables 1–40 and the VIP tent" />
        <x-form-field name="food_max_orders" type="number" label="Most food orders in total (optional)" :value="$e?->food_max_orders" :min="1" help="Food ordering closes when reached." />
    </div>
    <fieldset class="eb-fieldset">
        <legend class="eb-text-label">Pickup windows (optional)</legend>
        <p class="eb-help-text" style="margin-top: 0;">Guests choose one when they order. Each window can take a limited number of orders. Windows must fall within food service hours.</p>
        <div class="eb-stack" data-window-rows style="gap: var(--space-2);">
            @foreach($windowRows as $w => $window)
                <div class="eb-grid-4" data-window-row>
                    <x-form-field :name="'food_pickup_windows[' . $w . '][start]'" :id="'pw-' . $w . '-start'" type="time" label="From" :value="$window['start'] ?? ''" />
                    <x-form-field :name="'food_pickup_windows[' . $w . '][end]'" :id="'pw-' . $w . '-end'" type="time" label="Until" :value="$window['end'] ?? ''" />
                    <x-form-field :name="'food_pickup_windows[' . $w . '][max_orders]'" :id="'pw-' . $w . '-max'" type="number" label="Most orders" :value="$window['max_orders'] ?? ''" :min="1" />
                    <div class="eb-form-group" style="align-self: end;"><button type="button" class="eb-btn eb-btn-ghost eb-btn-sm" data-window-remove>Remove</button></div>
                </div>
            @endforeach
        </div>
        <template id="window-template">
            <div class="eb-grid-4" data-window-row>
                <div class="eb-form-group"><label class="eb-label" for="pw-__I__-start">From</label><input class="eb-input" id="pw-__I__-start" type="time" name="food_pickup_windows[__I__][start]"></div>
                <div class="eb-form-group"><label class="eb-label" for="pw-__I__-end">Until</label><input class="eb-input" id="pw-__I__-end" type="time" name="food_pickup_windows[__I__][end]"></div>
                <div class="eb-form-group"><label class="eb-label" for="pw-__I__-max">Most orders</label><input class="eb-input" id="pw-__I__-max" type="number" min="1" name="food_pickup_windows[__I__][max_orders]"></div>
                <div class="eb-form-group" style="align-self: end;"><button type="button" class="eb-btn eb-btn-ghost eb-btn-sm" data-window-remove>Remove</button></div>
            </div>
        </template>
        <input type="hidden" name="food_pickup_windows_submitted" value="1">
        <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" data-window-add>＋ Add pickup window</button>
    </fieldset>
    @if(!$e)
        <div id="studio-menu-rows"></div>
        <p class="studio-menu-empty" id="studio-menu-empty">No dishes yet. Add the food, drinks or meal combinations guests can order.</p>
        <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" id="studio-add-menu">＋ Add menu item</button>
        <template id="studio-menu-template">
            <div class="studio-menu-row" data-menu-row>
                <button type="button" class="studio-menu-remove" data-menu-remove aria-label="Remove menu item">Remove ×</button>
                <div class="eb-grid-3">
                    <div class="eb-form-group"><label class="eb-label">Item name *</label><input class="eb-input" data-menu-field="name" maxlength="150" placeholder="Jerk chicken plate" required></div>
                    <div class="eb-form-group"><label class="eb-label">Menu section *</label><select class="eb-input" data-menu-field="category" required><option>Food</option><option>Meals</option><option>Sides</option><option>Drinks</option><option>Desserts</option><option>Combos</option><option>Other</option></select></div>
                    <div class="eb-form-group"><label class="eb-label">Cuisine</label><select class="eb-input" data-menu-field="cuisine"><option value="">Choose a cuisine</option>@foreach($traditionCatalog as $key => $tradition)<option value="{{ $key }}">{{ $tradition['label'] }}</option>@endforeach</select></div>
                </div>
                <div class="eb-grid-3">
                    <div class="eb-form-group"><label class="eb-label">Price (USD) *</label><input class="eb-input" data-menu-field="price_usd" type="number" min="0" step="0.01" value="0" required></div>
                    <div class="eb-form-group"><label class="eb-label">Price (JMD)</label><input class="eb-input" data-menu-field="price_jmd" type="number" min="0" step="1" value="0"></div>
                    <div class="eb-form-group"><label class="eb-label">Pricing style</label><select class="eb-input" data-menu-field="pricing_type"><option value="fixed">Fixed price</option><option value="customizable">Customizable*</option><option value="custom_amount">Custom amount*</option></select></div>
                </div>
                <div class="eb-grid-2"><div class="eb-form-group"><label class="eb-label">Description</label><input class="eb-input" data-menu-field="description" maxlength="1000" placeholder="What is included?"></div><div class="eb-form-group"><label class="eb-label">Available portions (optional)</label><input class="eb-input" data-menu-field="available_stock" type="number" min="0" placeholder="Leave blank for unlimited"></div></div>
                <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" data-menu-field="allow_special_instructions"><span>Allow guest special instructions</span></label>
                <p class="eb-help-text">* Add customizable choices or custom amount settings from the event menu after creating the event.</p>
            </div>
        </template>
    @else
        @if($e?->menuItems)
            <div class="eb-grid-2">@foreach($e->menuItems as $item)<div class="studio-menu-row"><strong>{{ $item->name }}</strong><span class="eb-help-text">{{ ucfirst($item->category) }} · {{ $item->cuisine ? ($traditionCatalog[$item->cuisine]['label'] ?? $item->cuisine) : 'Cuisine not specified' }} · ${{ number_format((float) $item->price_usd, 2) }}</span></div>@endforeach</div>
        @endif
        <p class="eb-help-text" style="display: flex; gap: var(--space-3); align-items: center; flex-wrap: wrap;">Build the full menu, meal packages and photos in the menu studio, and preview it as guests will see it.
            <a class="eb-btn eb-btn-secondary eb-btn-sm" href="{{ route('events.menu-studio', $e->id) }}">Open menu studio</a></p>
    @endif
</section>

<section class="eb-panel card studio-section-card" style="margin: 0;" id="studio-policy" data-studio-section="policy">
    <div class="eb-card-header card-header"><div>
        <h2 class="eb-card-title card-title">Cancellations and refunds</h2>
        <p class="eb-card-subtitle card-subtitle">Set this event's policy. Leave either field blank to inherit your organizer settings.</p>
    </div></div>
    <div class="eb-grid-2">
        <x-form-field name="refund_policy_deadline_hours" type="number" label="Refund requests close (hours before event)"
                      :value="old('refund_policy_deadline_hours', $e?->refund_policy_deadline_hours)" :min="0" :max="720"
                      help="Use 0 to allow requests until the event starts." />
        <x-form-field name="refund_policy_notes" type="textarea" label="Event cancellation and refund terms" :rows="3"
                      :value="old('refund_policy_notes', $e?->refund_policy_notes)" />
    </div>
</section>

<section class="eb-panel card studio-section-card" style="margin: 0;" id="studio-compliance" data-studio-section="compliance">
    <div class="eb-card-header card-header"><div>
        <h2 class="eb-card-title card-title">Food safety, alcohol service and legal compliance</h2>
        <p class="eb-card-subtitle card-subtitle">Confirm safe food handling, allergen disclosures, alcohol licensing, and municipal permit compliance for this event.</p>
    </div></div>

    <fieldset class="eb-fieldset" style="margin-bottom: var(--space-4);">
        <legend class="eb-text-label">Food Safety, Allergen &amp; Permit Disclosures</legend>
        <div class="eb-grid-2">
            <x-form-field name="food_permit_status" type="select" label="Food-service permit &amp; inspection status"
                          :value="old('food_permit_status', $e?->food_permit_status ?? 'licensed_caterer_or_venue')"
                          :options="\App\Models\Event::FOOD_PERMIT_STATUSES" />
            <x-form-field name="food_permit_number" label="Food-service / health permit # (if applicable)"
                          :value="old('food_permit_number', $e?->food_permit_number)"
                          placeholder="e.g. FL-DBPR-2026-8841" />
        </div>
        <div class="eb-stack" style="gap: var(--space-2); margin-top: var(--space-3);">
            <input type="hidden" name="temperature_and_sanitation_confirmed" value="0">
            <label class="eb-choice-label">
                <input type="checkbox" class="eb-checkbox" name="temperature_and_sanitation_confirmed" value="1"
                       @checked(old('temperature_and_sanitation_confirmed', $e?->temperature_and_sanitation_confirmed ?? false))>
                <span>I confirm compliance with safe food preparation, proper cooking and holding temperatures, refrigeration, handwashing, sanitation, food transport, foodborne illness prevention, and safe disposal of food and waste.</span>
            </label>
            <input type="hidden" name="allergen_disclosure_confirmed" value="0">
            <label class="eb-choice-label">
                <input type="checkbox" class="eb-checkbox" name="allergen_disclosure_confirmed" value="1"
                       @checked(old('allergen_disclosure_confirmed', $e?->allergen_disclosure_confirmed ?? false))>
                <span>I confirm major food allergens and dietary disclosures are identified on menu items and cross-contamination prevention controls are enforced across all food stations.</span>
            </label>
        </div>
    </fieldset>

    <fieldset class="eb-fieldset">
        <legend class="eb-text-label">Alcohol Service, Age Verification &amp; Licensing</legend>
        <div class="eb-stack" style="gap: var(--space-2); margin-bottom: var(--space-3);">
            <input type="hidden" name="serves_alcohol" value="0">
            <label class="eb-choice-label">
                <input type="checkbox" class="eb-checkbox" name="serves_alcohol" value="1"
                       @checked(old('serves_alcohol', $e?->serves_alcohol ?? false))>
                <span>Alcohol will be sold, supplied, or served at this event (only when legally authorized under state, county, and municipal law).</span>
            </label>
            <input type="hidden" name="alcohol_age_verification_required" value="0">
            <label class="eb-choice-label">
                <input type="checkbox" class="eb-checkbox" name="alcohol_age_verification_required" value="1"
                       @checked(old('alcohol_age_verification_required', $e?->alcohol_age_verification_required ?? false))>
                <span>Mandatory legal drinking age and valid government ID verification will be enforced on-site; alcohol will not be served to minors or prohibited persons.</span>
            </label>
        </div>
        <div class="eb-grid-2">
            <x-form-field name="alcohol_permit_status" type="select" label="Alcohol licence / permit status"
                          :value="old('alcohol_permit_status', $e?->alcohol_permit_status ?? 'no_alcohol')"
                          :options="\App\Models\Event::ALCOHOL_PERMIT_STATUSES" />
            <x-form-field name="alcohol_permit_number" label="Alcohol / special-event permit # (if applicable)"
                          :value="old('alcohol_permit_number', $e?->alcohol_permit_number)"
                          placeholder="e.g. ABT-TEMP-4410" />
        </div>
        <p class="eb-help-text" style="margin-top: var(--space-2);">
            <strong>Legal notice:</strong> Purchasing an event ticket does not automatically authorize alcohol consumption and EventBite does not grant an alcohol licence. Hosts are independently responsible for applicable alcohol-related insurance, licences, and legal obligations.
        </p>
    </fieldset>
</section>

@php
    $complianceService = app(\App\Services\Compliance\EventComplianceService::class);
    $complianceStatus = $e ? $complianceService->status($e) : null;
    $complianceAck = $complianceStatus['acknowledgement'] ?? null;
    $complianceStateCode = old('state_code', $e?->state_code);
    $complianceStateName = $complianceStateCode ? (collect($states ?? [])->firstWhere('code', $complianceStateCode)?->name ?? $complianceStateCode) : null;
@endphp
<section class="eb-panel card studio-section-card" style="margin: 0;" id="studio-event-compliance" data-studio-section="event-compliance" data-event-compliance aria-labelledby="event-compliance-heading">
    <div class="eb-card-header card-header"><div>
        <h2 class="eb-card-title card-title" id="event-compliance-heading">Event Compliance</h2>
        <p class="eb-card-subtitle card-subtitle">Confirm these for this event. They are separate from the policies you accepted when you registered: every new event needs them, and they are recorded for this event only.</p>
    </div></div>

    @error('compliance')<x-alert type="error" title="Event Compliance">{{ $message }}</x-alert>@enderror
    @if($complianceStatus && $complianceStatus['state'] === 'current')
        <x-alert type="success" title="Confirmed for this event">
            Confirmed on {{ $complianceAck->accepted_at_utc->setTimezone($e->timezone ?: config('app.timezone'))->format('j M Y, g:i A') }}
            for {{ $complianceAck->jurisdictionLabel() }}. Changing the event's jurisdiction, food-service or alcohol-service arrangements will ask you to confirm them again.
        </x-alert>
    @elseif($complianceStatus && $complianceStatus['state'] === 'outdated')
        <x-alert type="warning" title="Confirm these again">
            The event's jurisdiction, food-service or alcohol-service arrangements changed after these were confirmed on
            {{ $complianceAck->accepted_at_utc->setTimezone($e->timezone ?: config('app.timezone'))->format('j M Y') }}. The event cannot be published until you confirm them for what it does now.
        </x-alert>
    @elseif($complianceStatus)
        <x-alert type="info" title="Not confirmed yet">
            @if($e->status === 'published')
                This event has no Event Compliance acknowledgement yet. Give its jurisdiction and confirm every declaration below.
            @else
                This event cannot be published until its jurisdiction is given and every declaration is confirmed.
            @endif
        </x-alert>
    @endif

    <fieldset class="eb-fieldset" style="margin-bottom: var(--space-4);">
        <legend class="eb-text-label">Applicable jurisdiction <span class="eb-label-required" aria-hidden="true">*</span></legend>
        <p class="eb-help-text" id="compliance-jurisdiction-help">Where the event's laws, permits and inspections come from. Give the county, the city or municipality, or both.</p>
        <p class="eb-help-text">Country: <strong>United States</strong> · State: <strong data-compliance-state>{{ $complianceStateName ?? 'choose it under When and where' }}</strong></p>
        <div class="eb-grid-2">
            <x-form-field name="compliance_jurisdiction_county" label="County" :value="old('compliance_jurisdiction_county', $e?->compliance_jurisdiction_county)"
                          placeholder="e.g. Miami-Dade County" maxlength="120" />
            <x-form-field name="compliance_jurisdiction_municipality" label="City or municipality" :value="old('compliance_jurisdiction_municipality', $e?->compliance_jurisdiction_municipality)"
                          placeholder="e.g. City of Miami" maxlength="120" />
        </div>
    </fieldset>

    @php
        $stateOptions = collect($states ?? [])->mapWithKeys(fn ($s) => [$s->code => $s->name])->all();
        $flagValue = fn (string $name) => old($name, $e?->{$name}) ? '1' : '0';
    @endphp
    <fieldset class="eb-fieldset" style="margin-bottom: var(--space-4);" data-arrangements>
        <legend class="eb-text-label">How food and alcohol are provided</legend>
        <p class="eb-help-text">These decide which requirements apply to this event, and where.</p>
        <div class="eb-grid-2">
            <x-form-field name="food_service_mode" type="select" label="Food at this event" :value="old('food_service_mode', $e?->food_service_mode)"
                          :options="\App\Models\Compliance\ComplianceEventRule::FOOD_MODES" placeholder="Choose one" />
            <x-form-field name="alcohol_service_mode" type="select" label="Alcohol at this event" :value="old('alcohol_service_mode', $e?->alcohol_service_mode)"
                          :options="\App\Models\Compliance\ComplianceEventRule::ALCOHOL_MODES" placeholder="Choose one" />
        </div>
        <div class="eb-stack" style="gap: var(--space-2); margin-top: var(--space-2);">
            @foreach(['uses_licensed_caterer' => 'A licensed caterer prepares or serves the food.', 'uses_licensed_alcohol_vendor' => 'A licensed alcohol vendor sells or serves the alcohol.',
                      'food_prepared_elsewhere' => 'Food is prepared somewhere other than the event location.', 'alcohol_served_elsewhere' => 'Alcohol is served somewhere other than the event address (for example a separate licensed area).'] as $flag => $text)
                <input type="hidden" name="{{ $flag }}" value="0">
                <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="{{ $flag }}" value="1" @checked($flagValue($flag) === '1') data-arrangement-toggle="{{ $flag }}"><span>{{ $text }}</span></label>
            @endforeach
        </div>
        @foreach(['food_prepared_elsewhere' => ['food_prep', 'Where the food is prepared'], 'alcohol_served_elsewhere' => ['alcohol_service', 'Where the alcohol is served']] as $flag => [$prefix, $title])
            <div class="eb-grid-3" style="margin-top: var(--space-2);" data-arrangement-location="{{ $flag }}" @if($flagValue($flag) !== '1') hidden @endif>
                <x-form-field :name="$prefix . '_state_code'" type="select" :label="$title . ': state'" :value="old($prefix . '_state_code', $e?->{$prefix . '_state_code'})" :options="$stateOptions" placeholder="Same state" />
                <x-form-field :name="$prefix . '_county'" label="County" :value="old($prefix . '_county', $e?->{$prefix . '_county'})" maxlength="120" />
                <x-form-field :name="$prefix . '_city'" label="City or municipality" :value="old($prefix . '_city', $e?->{$prefix . '_city'})" maxlength="120" />
            </div>
        @endforeach
    </fieldset>

    <p class="eb-help-text" data-compliance-locked role="status">Give the state and the county or city above before confirming the declarations.</p>
    @foreach(\App\Services\Compliance\EventComplianceService::declarations() as $group)
        <fieldset class="eb-fieldset" style="margin-bottom: var(--space-3);">
            <legend class="eb-text-label">{{ $group['title'] }}</legend>
            <div class="eb-stack" style="gap: var(--space-2);">
                @foreach($group['items'] as $field => $statement)
                    <label class="eb-choice-label">
                        <input type="checkbox" class="eb-checkbox" name="{{ $field }}" value="1" data-compliance-declaration @checked(old($field))>
                        <span>{{ $statement }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    @endforeach
    <p class="eb-help-text">Every declaration is required. When you save, they are stored with the jurisdiction and the food and alcohol arrangements they cover.</p>
</section>

<section class="eb-panel card studio-section-card" style="margin: 0;" id="studio-requirements" data-studio-section="requirements" aria-labelledby="requirements-heading">
    <div class="eb-card-header card-header"><div>
        <h2 class="eb-card-title card-title" id="requirements-heading">Requirements for this event</h2>
        <p class="eb-card-subtitle card-subtitle">Identified from the event's location and the details above, and updated as you change them.
            @if($e)<a href="{{ route('events.requirements', $e->id) }}">Submit permits and licences for verification</a>@endif</p>
    </div></div>
    <div data-requirements-preview data-preview-url="{{ route('events.requirements.preview') }}" data-event-id="{{ $e?->id }}" aria-live="polite">
        @if($e)
            @include('events.partials.requirements-checklist', [
                'checklist' => app(\App\Services\Compliance\EventRequirementChecklist::class)->build(\App\Services\Compliance\EventRequirementChecklist::factsFromEvent($e), $e),
                'event' => $e, 'interactive' => false,
            ])
        @else
            <p class="eb-help-text">Choose the event's state and city to see its requirements.</p>
        @endif
    </div>
</section>
<script>
(function () {
    // Arrangement locations open with their checkbox; the requirements list follows the form as it changes.
    const panel = document.querySelector('[data-requirements-preview]');
    if (!panel) { return; }
    const form = panel.closest('form') || document;
    document.querySelectorAll('[data-arrangement-toggle]').forEach((box) => {
        const target = document.querySelector('[data-arrangement-location="' + box.dataset.arrangementToggle + '"]');
        if (target) { box.addEventListener('change', () => { target.hidden = !box.checked; }); }
    });
    const keys = ['category', 'state_code', 'city', 'compliance_jurisdiction_county', 'compliance_jurisdiction_municipality', 'supports_food_ordering', 'serves_alcohol',
        'food_service_mode', 'alcohol_service_mode', 'uses_licensed_caterer', 'uses_licensed_alcohol_vendor', 'food_prepared_elsewhere', 'food_prep_state_code',
        'food_prep_county', 'food_prep_city', 'alcohol_served_elsewhere', 'alcohol_service_state_code', 'alcohol_service_county', 'alcohol_service_city'];
    const value = (name) => {
        const fields = Array.from(form.querySelectorAll('[name="' + name + '"]')).filter((el) => !el.disabled);
        const box = fields.find((el) => el.type === 'checkbox');
        if (box) { return box.checked ? '1' : '0'; }
        return (fields.find((el) => el.type !== 'hidden') || fields[0])?.value || '';
    };
    let timer = null;
    let last = '';
    function refresh() {
        const body = new FormData();
        keys.forEach((key) => body.append(key, value(key)));
        if (panel.dataset.eventId) { body.append('event_id', panel.dataset.eventId); }
        const signature = new URLSearchParams(body).toString();
        if (signature === last || !value('state_code')) { return; }
        last = signature;
        body.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
        fetch(panel.dataset.previewUrl, { method: 'POST', body, headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then((response) => response.ok ? response.text() : Promise.reject(response.status))
            .then((html) => { panel.innerHTML = html; })
            .catch(() => {});
    }
    const schedule = () => { clearTimeout(timer); timer = setTimeout(refresh, 450); };
    form.addEventListener('input', schedule);
    form.addEventListener('change', schedule);
    if (!panel.dataset.eventId) { schedule(); }
})();
</script>
<script>
(function () {
    // The declarations open once the jurisdiction is given; the server checks the same rule.
    const section = document.querySelector('[data-event-compliance]');
    if (!section) { return; }
    const form = section.closest('form') || document;
    const boxes = section.querySelectorAll('[data-compliance-declaration]');
    const locked = section.querySelector('[data-compliance-locked]');
    const stateLabel = section.querySelector('[data-compliance-state]');
    const field = (name) => Array.from(form.querySelectorAll('[name="' + name + '"]')).find((el) => !el.disabled);
    function refresh() {
        const state = field('state_code');
        const stateText = state ? (state.tagName === 'SELECT' && state.value ? state.options[state.selectedIndex].text : state.value.trim()) : '';
        stateLabel.textContent = stateText || 'choose it under When and where';
        const local = ['compliance_jurisdiction_county', 'compliance_jurisdiction_municipality'].some((name) => (field(name)?.value || '').trim() !== '');
        const ready = stateText !== '' && local;
        boxes.forEach((box) => { box.disabled = !ready; });
        locked.hidden = ready;
    }
    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    refresh();
})();
</script>

<section class="eb-panel card studio-section-card" style="margin: 0;" id="studio-tickets" data-studio-section="tickets">
    <div class="eb-card-header card-header"><div>
        <h2 class="eb-card-title card-title">Tickets</h2>
        <p class="eb-card-subtitle card-subtitle">One row per ticket type. "Tickets available" is how many are left to sell. Leave the sale dates empty to sell until the event.</p>
    </div></div>
    <x-form-field name="currency" type="select" label="Event currency" :value="old('currency', $e?->currency ?? 'USD')"
                  :options="collect(\App\Support\Currency::supportedCodes())->mapWithKeys(fn ($code) => [$code => $code])->all()"
                  help="Ticket and checkout totals use this event-wide currency. Configure both USD and JMD ticket prices below." />
    <input type="hidden" name="ticket_tiers_submitted" value="1">
    @error('ticket_tiers')<x-alert type="error">{{ $message }}</x-alert>@enderror
    <div id="tier-rows" class="eb-stack">
        @foreach($tierRows as $i => $row)
            @include('events.partials.tier-row', ['i' => $i, 'row' => $row])
        @endforeach
    </div>
    <template id="tier-template">
        @include('events.partials.tier-row', ['i' => '__INDEX__', 'row' => ['id' => '', 'name' => '', 'description' => '', 'price' => '', 'price_jmd' => '', 'available_stock' => '', 'max_per_customer' => 4, 'sale_start' => '', 'sale_end' => '']])
    </template>
    @if(!$e)
        <template id="studio-tier-template">
            @include('events.partials.tier-row', ['i' => '__STUDIO_INDEX__', 'row' => ['id' => '', 'name' => '', 'description' => '', 'price' => '', 'price_jmd' => '', 'available_stock' => '', 'max_per_customer' => 4, 'sale_start' => '', 'sale_end' => '']])
        </template>
    @endif
    <p id="tier-empty" class="eb-help-text" @if($tierRows) hidden @endif>No ticket types yet. Free events still need one, at $0.</p>
    <div style="margin-top: var(--space-4);">
        <button type="button" id="add-tier" class="eb-btn eb-btn-secondary eb-btn-sm"><x-icon name="plus" size="sm" /> Add ticket type</button>
    </div>
</section>

<section class="eb-panel card studio-section-card" style="margin: 0;" id="studio-branding" data-studio-section="branding">
    <div class="eb-card-header card-header"><div>
        <h2 class="eb-card-title card-title">Event Branding</h2>
        <p class="eb-card-subtitle card-subtitle">Upload flyers, cover images, gallery photos, and logos.</p>
        <span id="branding-status" class="section-status incomplete" style="margin-left: auto;">In progress</span>
    </div></div>

    <!-- Main Flyer -->
    <fieldset class="eb-fieldset">
        <legend class="eb-text-label">Main Event Flyer</legend>
        <p class="eb-help-text">JPG, PNG or WebP, up to 5 MB, between 300 × 200 and 4500 × 4500 pixels.</p>
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
        <div class="eb-grid-2">
            <x-form-field name="flyer_alt_text" label="Alt text (accessibility)" :value="$e?->flyer_alt_text" placeholder="Describe the flyer for screen readers" />
            <div class="eb-form-group">
                <label class="eb-label">Position adjustment (for display)</label>
                <div class="eb-grid-2">
                    <input type="range" name="flyer_position_x" id="flyer-position-x" aria-label="Flyer focus, left to right (percent)" min="0" max="100" value="{{ $e?->flyer_position_x ?? 50 }}" style="width: 100%;">
                    <input type="range" name="flyer_position_y" id="flyer-position-y" aria-label="Flyer focus, top to bottom (percent)" min="0" max="100" value="{{ $e?->flyer_position_y ?? 50 }}" style="width: 100%;">
                </div>
                <p class="eb-help-text">Horizontal: {{ $e?->flyer_position_x ?? 50 }}%, Vertical: {{ $e?->flyer_position_y ?? 50 }}%</p>
            </div>
        </div>
        {{-- Focal point and crop: what each place on the site will show. The original upload is always kept whole. --}}
        <div class="eb-focal-tools" data-focal-tools @unless($e?->flyer_path) hidden @endunless>
            <p class="eb-help-text" style="margin: 0;">Click the flyer above to choose its focal point (or use the sliders). Every place the flyer is cropped keeps that point in view:</p>
            <div class="eb-focal-previews">
                <figure><div class="eb-focal-frame" style="aspect-ratio: 4 / 3;"><img data-focal-preview alt="" src="{{ $e?->flyer_path ? $e->display_image_url : '' }}"></div><figcaption>Event card</figcaption></figure>
                <figure><div class="eb-focal-frame" style="aspect-ratio: 16 / 9;"><img data-focal-preview alt="" src="{{ $e?->flyer_path ? $e->display_image_url : '' }}"></div><figcaption>Event page banner</figcaption></figure>
                <figure><div class="eb-focal-frame" style="aspect-ratio: 1 / 1;"><img data-focal-preview alt="" src="{{ $e?->flyer_path ? $e->display_image_url : '' }}"></div><figcaption>Ticket and lists</figcaption></figure>
            </div>
            <div class="eb-form-group" style="max-width: 360px;">
                <label for="flyer-crop" class="eb-label">Crop a new upload (optional)</label>
                <select id="flyer-crop" class="eb-select eb-input" disabled>
                    <option value="">Keep the whole image</option>
                    <option value="1.7778">Banner, 16:9</option>
                    <option value="1.3333">Card, 4:3</option>
                    <option value="1">Square, 1:1</option>
                </select>
                <input type="hidden" name="flyer_crop_x" id="flyer-crop-x"><input type="hidden" name="flyer_crop_y" id="flyer-crop-y">
                <input type="hidden" name="flyer_crop_width" id="flyer-crop-width"><input type="hidden" name="flyer_crop_height" id="flyer-crop-height">
                <p class="eb-help-text">Choose a new flyer first. The crop is centred on the focal point and shapes only the sizes shown on the site; your original file is kept unchanged.</p>
            </div>
        </div>
    </fieldset>

    <!-- Recommended Dimensions -->
    <div class="eb-inline-note" style="margin-top: var(--space-4);">
        <span>✦</span>
        <div>
            <p><strong>Recommended dimensions for different contexts:</strong></p>
            <ul style="margin: var(--space-2) 0; padding-left: var(--space-4);">
                <li><strong>Event discovery cards:</strong> 800 × 600 (4:3) or 1200 × 630 (1.91:1 for social)</li>
                <li><strong>Event detail page:</strong> 1600 × 900 (16:9) or 1200 × 800 (3:2)</li>
                <li><strong>Social sharing:</strong> 1200 × 630 (1.91:1) for Facebook, 1080 × 1080 (1:1) for Instagram</li>
                <li><strong>Mobile event display:</strong> 800 × 1200 (2:3) or 600 × 900 (3:4)</li>
            </ul>
        </div>
    </div>

    <!-- Device Preview -->
    <div style="margin-top: var(--space-4); padding: var(--space-4); background: var(--color-background-secondary); border-radius: var(--radius-md);">
        <h3 class="eb-text-label" style="margin-bottom: var(--space-3);">Event Page Preview</h3>
        <div style="display: flex; gap: var(--space-3); margin-bottom: var(--space-3);">
            <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" data-preview-device="desktop">Desktop</button>
            <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" data-preview-device="tablet">Tablet</button>
            <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" data-preview-device="mobile">Mobile</button>
        </div>
        <div id="event-preview-container" style="border: 1px solid var(--color-border-strong); border-radius: var(--radius-md); overflow: hidden; background: var(--color-surface); background-image: var(--gradient-surface-3d); box-shadow: var(--shadow-card); transition: width 0.3s ease, height 0.3s ease;">
            <div id="event-preview-desktop" style="width: 100%; padding: var(--space-4);">
                <img id="preview-flyer-desktop" src="{{ $e?->flyer_path ? $e->display_image_url : '/images/event-placeholder.svg' }}" alt="Desktop preview" style="width: 100%; max-height: 400px; object-fit: contain; border-radius: var(--radius-md);">
                <h2 style="margin-top: var(--space-3);">{{ $e?->title ?? 'Event Title' }}</h2>
                <p style="color: var(--color-text-secondary);">{{ $e?->date_str ?? 'Saturday, August 15, 2027' }} · {{ $e?->time_str ?? '4:00 PM – 10:00 PM' }}</p>
            </div>
            <div id="event-preview-tablet" style="width: 100%; padding: var(--space-3); display: none;">
                <img id="preview-flyer-tablet" src="{{ $e?->flyer_path ? $e->display_image_url : '/images/event-placeholder.svg' }}" alt="Tablet preview" style="width: 100%; max-height: 300px; object-fit: contain; border-radius: var(--radius-md);">
                <h2 style="margin-top: var(--space-2); font-size: 1.5rem;">{{ $e?->title ?? 'Event Title' }}</h2>
                <p style="color: var(--color-text-secondary);">{{ $e?->date_str ?? 'Saturday, August 15, 2027' }} · {{ $e?->time_str ?? '4:00 PM – 10:00 PM' }}</p>
            </div>
            <div id="event-preview-mobile" style="width: 100%; padding: var(--space-2); display: none;">
                <img id="preview-flyer-mobile" src="{{ $e?->flyer_path ? $e->display_image_url : '/images/event-placeholder.svg' }}" alt="Mobile preview" style="width: 100%; max-height: 200px; object-fit: contain; border-radius: var(--radius-md);">
                <h2 style="margin-top: var(--space-2); font-size: 1.25rem;">{{ $e?->title ?? 'Event Title' }}</h2>
                <p style="color: var(--color-text-secondary); font-size: 0.875rem;">{{ $e?->date_str ?? 'Saturday, August 15, 2027' }} · {{ $e?->time_str ?? '4:00 PM – 10:00 PM' }}</p>
            </div>
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

        const menuRows = document.getElementById('studio-menu-rows');
        if (menuRows) {
            const menuTemplate = document.getElementById('studio-menu-template');
            const emptyMenu = document.getElementById('studio-menu-empty');
            let menuIndex = 0;
            document.getElementById('studio-add-menu').addEventListener('click', function () {
                const fragment = menuTemplate.content.cloneNode(true);
                const row = fragment.querySelector('[data-menu-row]');
                row.querySelectorAll('[data-menu-field]').forEach(function (field) {
                    const key = field.dataset.menuField;
                    field.name = 'menu_items[' + menuIndex + '][' + key + ']';
                });
                menuIndex++;
                menuRows.appendChild(fragment);
                emptyMenu.hidden = true;
                row.querySelector('[data-menu-field="name"]').focus();
            });
            menuRows.addEventListener('click', function (event) {
                const remove = event.target.closest('[data-menu-remove]');
                if (!remove) return;
                remove.closest('[data-menu-row]').remove();
                emptyMenu.hidden = menuRows.children.length > 0;
            });
        }

        // Ticket designer: each ticket type's mockup follows its fields and the event's name and date.
        const ticketRows = document.getElementById('tier-rows');
        if (ticketRows) {
            const money = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(parseFloat(v || '0') || 0);
            const fieldValue = (name) => (document.querySelector('[name="' + name + '"]') || {}).value || '';
            const when = function () {
                const date = fieldValue('event_date_iso');
                const start = fieldValue('start_time');
                if (!date) return 'Date to be announced';
                const d = new Date(date + 'T' + (start || '12:00'));
                return d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' }) + (start ? ' · ' + d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }) : '');
            };
            const paint = function (row) {
                const skin = row.querySelector('.eb-ticket-skin');
                if (!skin) return;
                const pick = (suffix) => (row.querySelector('[name$="[' + suffix + ']"]') || {}).value;
                const early = pick('early_bird_price');
                skin.dataset.design = pick('design_style') || 'premium_minimal';
                skin.dataset.accent = pick('accent_color') || 'indigo';
                skin.dataset.bg = pick('background_style') || 'solid';
                skin.querySelector('[data-mock-name]').textContent = pick('name') || 'Ticket name';
                skin.querySelector('[data-mock-price]').textContent = early ? money(early) + ' early bird' : money(pick('price'));
                skin.querySelector('[data-mock-event]').textContent = fieldValue('title') || 'Your event';
                skin.querySelector('[data-mock-when]').textContent = when();
                skin.classList.add('is-updating');
                setTimeout(() => skin.classList.remove('is-updating'), 160);
            };
            ticketRows.querySelectorAll('[data-tier-row]').forEach(paint);
            ticketRows.addEventListener('input', (e) => { const row = e.target.closest('[data-tier-row]'); if (row) paint(row); });
            ticketRows.addEventListener('change', (e) => { const row = e.target.closest('[data-tier-row]'); if (row) paint(row); });
            ticketRows.addEventListener('click', function (e) {
                const toggle = e.target.closest('[data-ticket-mode]');
                if (!toggle) return;
                const designer = toggle.closest('[data-ticket-designer]');
                designer.querySelector('.eb-ticket-skin').dataset.mode = toggle.dataset.ticketMode;
                designer.querySelectorAll('[data-ticket-mode]').forEach(b => b.setAttribute('aria-pressed', b === toggle ? 'true' : 'false'));
            });
            ['title', 'event_date_iso', 'start_time'].forEach(function (name) {
                document.querySelector('[name="' + name + '"]')?.addEventListener('input', () => ticketRows.querySelectorAll('[data-tier-row]').forEach(paint));
            });
            new MutationObserver(() => ticketRows.querySelectorAll('[data-tier-row]').forEach(paint)).observe(ticketRows, { childList: true });
        }

        // Pickup windows
        const windowRows = document.querySelector('[data-window-rows]');
        if (windowRows) {
            let windowIndex = windowRows.children.length;
            document.querySelector('[data-window-add]').addEventListener('click', function () {
                windowRows.insertAdjacentHTML('beforeend', document.getElementById('window-template').innerHTML.replaceAll('__I__', String(windowIndex++)));
                windowRows.lastElementChild.querySelector('input').focus();
            });
            windowRows.addEventListener('click', function (event) {
                const remove = event.target.closest('[data-window-remove]');
                if (remove) remove.closest('[data-window-row]').remove();
            });
        }

        // Map lookup: the host chooses one of the places the mapping service returns; only that choice is pinned.
        const lookup = document.querySelector('[data-map-lookup]');
        const countryField = document.querySelector('[name="country_code"]');
        const countryLocationFields = document.querySelectorAll('[data-location-country-fields]');
        const updateCountryFields = () => {
            const country = (countryField?.value || 'US').toUpperCase();
            countryLocationFields.forEach(function (group) {
                const active = group.dataset.locationCountryFields === (country === 'US' ? 'US' : 'OTHER');
                group.hidden = !active;
                group.querySelectorAll('input, select, textarea').forEach(function (field) { field.disabled = !active; });
            });
        };
        if (countryField) {
            countryField.addEventListener('change', updateCountryFields);
            updateCountryFields();
        }
        if (lookup) {
            const status = lookup.querySelector('[data-map-status]');
            const list = lookup.querySelector('[data-map-results]');
            const token = lookup.querySelector('[data-location-token]');
            const chosen = lookup.querySelector('[data-map-chosen]');
            const current = lookup.querySelector('[data-map-current]');
            const value = (name) => (document.querySelector('[name="' + name + '"]:not(:disabled)') || {}).value || '';
            lookup.querySelector('[data-map-find]').addEventListener('click', async function () {
                if (lookup.dataset.enabled !== '1') { status.textContent = 'Map lookup is not set up for this site. Check the address carefully; no map pin will be shown.'; return; }
                const address = [value('street_address'), value('venue_building_name'), value('city'), value('state_code'), value('zip_code')].filter(Boolean).join(', ');
                status.textContent = 'Looking up…';
                list.hidden = true; list.innerHTML = '';
                try {
                    const response = await fetch(lookup.dataset.endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]').value },
                        body: JSON.stringify({ address: address, country_code: value('country_code') || 'US' }) });
                    const data = await response.json();
                    if (!response.ok) throw new Error(data.message || 'Lookup failed');
                    status.textContent = data.message || 'Choose the place that matches your venue.';
                    (data.results || []).forEach(function (result, index) {
                        const li = document.createElement('li');
                        const label = document.createElement('label'); label.className = 'eb-choice-label';
                        const radio = document.createElement('input'); radio.type = 'radio'; radio.name = 'map_result'; radio.value = String(index);
                        radio.addEventListener('change', function () {
                            token.value = result.token;
                            if (current) current.hidden = true;
                            chosen.hidden = !result.preview;
                            if (result.preview) { chosen.querySelector('img').src = result.preview; chosen.querySelector('figcaption').textContent = result.label; }
                            status.textContent = 'This place will be pinned when you save.';
                        });
                        const text = document.createElement('span'); text.textContent = result.label;
                        label.append(radio, text); li.appendChild(label); list.appendChild(li);
                    });
                    list.hidden = (data.results || []).length === 0;
                } catch (error) {
                    status.textContent = 'The map lookup did not work just now. You can save without a pin and try again later.';
                }
            });
            ['street_address', 'venue_building_name', 'city', 'state_code', 'zip_code', 'country_code'].forEach(function (name) {
                document.querySelectorAll('[name="' + name + '"]').forEach(function (field) {
                    field.addEventListener('change', function () {
                        token.value = '';
                        chosen.hidden = true;
                        if (current) current.hidden = true;
                    });
                });
            });
        }

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
            // The pixel-size check, the focal previews and the crop choice need the new image's own dimensions.
            preview.onload = function () {
                const w = preview.naturalWidth, h = preview.naturalHeight;
                if (w < 300 || h < 200 || w > 4500 || h > 4500) {
                    check.textContent = 'This image is ' + w + ' × ' + h + ' pixels; it must be between 300 × 200 and 4500 × 4500.';
                }
                if (focalTools) focalTools.hidden = false;
                focalImages.forEach(img => { img.src = preview.src; });
                if (cropSelect) { cropSelect.disabled = false; applyCrop(); }
            };

            // Update all preview images
            const previewImages = ['preview-flyer-desktop', 'preview-flyer-tablet', 'preview-flyer-mobile'];
            previewImages.forEach(id => {
                const img = document.getElementById(id);
                if (img) img.src = URL.createObjectURL(file);
            });
        });

        // Focal point: the sliders (keyboard) or a click on the flyer set it; the card, banner and square previews
        // show live what each place on the site will keep in view.
        const posX = document.getElementById('flyer-position-x');
        const posY = document.getElementById('flyer-position-y');
        const focalTools = document.querySelector('[data-focal-tools]');
        const focalImages = document.querySelectorAll('[data-focal-preview]');
        const cropSelect = document.getElementById('flyer-crop');
        function applyCrop() {
            const ids = ['flyer-crop-x', 'flyer-crop-y', 'flyer-crop-width', 'flyer-crop-height'].map(id => document.getElementById(id));
            const ratio = parseFloat(cropSelect && cropSelect.value);
            const w = preview.naturalWidth, h = preview.naturalHeight;
            if (!ratio || !w || !h || !input.files.length) { ids.forEach(f => { f.value = ''; }); return; }
            // The largest area of that shape, centred on the focal point and kept inside the image (in percent).
            let cw = 100, ch = 100;
            if (w / h > ratio) { cw = Math.round((h * ratio) / w * 100); } else { ch = Math.round((w / ratio) / h * 100); }
            const cx = Math.max(0, Math.min(100 - cw, Math.round(Number(posX.value) - cw / 2)));
            const cy = Math.max(0, Math.min(100 - ch, Math.round(Number(posY.value) - ch / 2)));
            [cx, cy, cw, ch].forEach((value, i) => { ids[i].value = String(value); });
        }
        if (posX && posY) {
            const updatePositionDisplay = () => {
                const helpText = posX.parentElement.nextElementSibling;
                if (helpText) {
                    helpText.textContent = 'Horizontal: ' + posX.value + '%, Vertical: ' + posY.value + '%';
                }
                focalImages.forEach(img => { img.style.objectPosition = posX.value + '% ' + posY.value + '%'; });
                preview.style.setProperty('--focal-x', posX.value + '%');
                preview.style.setProperty('--focal-y', posY.value + '%');
                applyCrop();
            };
            posX.addEventListener('input', updatePositionDisplay);
            posY.addEventListener('input', updatePositionDisplay);
            preview.addEventListener('click', function (event) {
                // The preview shows the whole image (contain): map the click to the picture inside its letterbox.
                const rect = preview.getBoundingClientRect();
                const scale = Math.min(rect.width / preview.naturalWidth, rect.height / preview.naturalHeight);
                const shownW = preview.naturalWidth * scale, shownH = preview.naturalHeight * scale;
                const left = rect.left + (rect.width - shownW) / 2, top = rect.top + (rect.height - shownH) / 2;
                posX.value = String(Math.round(Math.max(0, Math.min(1, (event.clientX - left) / shownW)) * 100));
                posY.value = String(Math.round(Math.max(0, Math.min(1, (event.clientY - top) / shownH)) * 100));
                updatePositionDisplay();
            });
            if (cropSelect) cropSelect.addEventListener('change', applyCrop);
            updatePositionDisplay();
        }

        // Device preview switching with smooth transitions
        const previewContainer = document.getElementById('event-preview-container');
        const previewDesktop = document.getElementById('event-preview-desktop');
        const previewTablet = document.getElementById('event-preview-tablet');
        const previewMobile = document.getElementById('event-preview-mobile');
        const deviceButtons = document.querySelectorAll('[data-preview-device]');

        if (previewContainer && previewDesktop && previewTablet && previewMobile) {
            deviceButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const device = this.dataset.previewDevice;

                    // Set container width for smooth transition
                    previewContainer.style.transition = 'width 0.3s ease, height 0.3s ease';
                    switch (device) {
                        case 'desktop':
                            previewContainer.style.width = '100%';
                            break;
                        case 'tablet':
                            previewContainer.style.width = '768px';
                            break;
                        case 'mobile':
                            previewContainer.style.width = '375px';
                            break;
                    }

                    // Hide all previews first
                    previewDesktop.style.display = 'none';
                    previewTablet.style.display = 'none';
                    previewMobile.style.display = 'none';

                    // Show selected preview after transition
                    setTimeout(() => {
                        switch (device) {
                            case 'desktop':
                                previewDesktop.style.display = 'block';
                                break;
                            case 'tablet':
                                previewTablet.style.display = 'block';
                                break;
                            case 'mobile':
                                previewMobile.style.display = 'block';
                                break;
                        }
                    }, 100);

                    // Update button states
                    deviceButtons.forEach(btn => btn.classList.remove('eb-btn-primary'));
                    deviceButtons.forEach(btn => btn.classList.add('eb-btn-secondary'));
                    this.classList.remove('eb-btn-secondary');
                    this.classList.add('eb-btn-primary');
                });
            });

            // Set initial state
            if (deviceButtons.length > 0) {
                deviceButtons[0].click();
            }
        }
    });
</script>
