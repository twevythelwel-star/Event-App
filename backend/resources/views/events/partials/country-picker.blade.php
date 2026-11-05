{{--
    Countries and cuisines the event celebrates, chosen from the platform's cultural catalog
    (categories → regions → countries). Each country is a card with its flag and cuisines; a cuisine photo shows
    only where the catalog has a real one. Regions expand in place; the chosen countries are summarised at the top.
    The checkboxes are the real form fields, so it all works without JavaScript and with a keyboard.
--}}
@php
    $categories = \App\Models\CulturalCategory::where('is_active', true)->orderBy('sort_order')
        ->with('regions.countries.cuisines')->get()->filter(fn ($c) => $c->regions->flatMap->countries->isNotEmpty());
    $chosenCountries = array_values(array_unique((array) old('country_ids', $e ? $e->countries()->pluck('countries.id')->all() : [])));
    $maxCountries = 8;
@endphp
@if($categories->isNotEmpty())
@once
    {{-- Windows does not draw flag emoji; this colour emoji font fills in, and only the flag glyphs used are downloaded. --}}
    <link href="https://fonts.googleapis.com/css2?family=Noto+Color+Emoji&display=swap" rel="stylesheet">
@endonce
<fieldset class="eb-country-picker" data-country-picker data-max="{{ $maxCountries }}">
    <legend class="eb-text-label" style="padding: 0;">Countries and cuisines</legend>
    <p class="eb-help-text" style="margin: 0 0 var(--space-2);">Choose up to {{ $maxCountries }} countries whose food or culture this event features. Guests can find your event by them.</p>
    <input type="hidden" name="country_ids_submitted" value="1">

    <div class="eb-country-summary" data-country-summary aria-live="polite">
        <span class="eb-help-text" data-country-empty @if($chosenCountries) hidden @endif>No countries chosen yet.</span>
    </div>
    <p class="eb-help-text" data-country-limit role="status" hidden>You can choose up to {{ $maxCountries }}. Remove one to add another.</p>

    <label class="sr-only" for="country-search">Find a country</label>
    <input type="search" id="country-search" class="eb-input" placeholder="Find a country" data-country-search style="max-width: 320px;">

    @foreach($categories as $category)
        @php $ids = $category->regions->flatMap->countries->pluck('id')->unique(); $picked = $ids->intersect($chosenCountries)->count(); @endphp
        <details class="eb-region" data-region @if($picked) open @endif>
            <summary>
                <span>{{ $category->name }}</span>
                <span class="eb-region-count" data-region-count>{{ $picked ? $picked . ' chosen' : $ids->count() . ' countries' }}</span>
            </summary>
            <div class="eb-region-body">
                @foreach($category->regions as $region)
                    @continue($region->countries->isEmpty())
                    <p class="eb-region-name">{{ $region->name }}</p>
                    <div class="eb-country-grid">
                        @foreach($region->countries as $country)
                            @php $cuisines = $country->cuisines->pluck('name')->take(2)->implode(', '); @endphp
                            <label class="eb-country-card" data-country-card data-name="{{ \Illuminate\Support\Str::lower($country->name) }}">
                                <input type="checkbox" class="eb-country-input" name="country_ids[]" value="{{ $country->id }}" data-country-id="{{ $country->id }}"
                                       data-label="{{ $country->flag_emoji }} {{ $country->name }}" @checked(in_array($country->id, $chosenCountries, true))>
                                <span class="eb-country-media" aria-hidden="true">
                                    @if(filled($country->cuisine_image_url))
                                        <img src="{{ $country->cuisine_image_url }}" alt="" loading="lazy" decoding="async">
                                    @endif
                                    <span class="eb-country-flag">{{ $country->flag_emoji }}</span>
                                </span>
                                <span class="eb-country-text">
                                    <span class="eb-country-name">{{ $country->name }}</span>
                                    @if($cuisines !== '')<span class="eb-country-cuisine">{{ $cuisines }}</span>@endif
                                </span>
                                <span class="eb-country-check" aria-hidden="true">✓</span>
                            </label>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </details>
    @endforeach
    @error('country_ids')<div class="eb-field-error" role="alert"><span>{{ $message }}</span></div>@enderror
</fieldset>

@once
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-country-picker]').forEach(function (picker) {
            const max = parseInt(picker.dataset.max, 10);
            const summary = picker.querySelector('[data-country-summary]');
            const empty = picker.querySelector('[data-country-empty]');
            const limit = picker.querySelector('[data-country-limit]');
            const boxes = () => [...picker.querySelectorAll('[data-country-id]')];
            const chosen = () => [...new Map(boxes().filter(b => b.checked).map(b => [b.value, b.dataset.label])).entries()];

            function render() {
                summary.querySelectorAll('[data-chip]').forEach(c => c.remove());
                chosen().forEach(function ([id, label]) {
                    const chip = document.createElement('button');
                    chip.type = 'button'; chip.className = 'eb-country-chip'; chip.dataset.chip = id;
                    chip.setAttribute('aria-label', 'Remove ' + label.replace(/^\S+\s/, ''));
                    chip.textContent = label + ' ×';
                    summary.appendChild(chip);
                });
                empty.hidden = chosen().length > 0;
                picker.querySelectorAll('[data-region]').forEach(function (region) {
                    const ids = new Set([...region.querySelectorAll('[data-country-id]')].filter(b => b.checked).map(b => b.value));
                    const total = new Set([...region.querySelectorAll('[data-country-id]')].map(b => b.value)).size;
                    region.querySelector('[data-region-count]').textContent = ids.size ? ids.size + ' chosen' : total + ' countries';
                });
            }

            picker.addEventListener('change', function (event) {
                const box = event.target.closest('[data-country-id]');
                if (!box) return;
                // A country listed in two regions is one choice: keep its copies in step.
                boxes().filter(b => b.value === box.value).forEach(b => { b.checked = box.checked; });
                if (box.checked && chosen().length > max) {
                    boxes().filter(b => b.value === box.value).forEach(b => { b.checked = false; });
                    limit.hidden = false;
                } else {
                    limit.hidden = true;
                }
                render();
            });
            summary.addEventListener('click', function (event) {
                const chip = event.target.closest('[data-chip]');
                if (!chip) return;
                boxes().filter(b => b.value === chip.dataset.chip).forEach(b => { b.checked = false; });
                limit.hidden = true;
                render();
            });
            picker.querySelector('[data-country-search]').addEventListener('input', function (event) {
                const term = event.target.value.trim().toLowerCase();
                picker.querySelectorAll('[data-country-card]').forEach(card => { card.hidden = term !== '' && !card.dataset.name.includes(term); });
                picker.querySelectorAll('[data-region]').forEach(function (region) {
                    const visible = [...region.querySelectorAll('[data-country-card]')].some(c => !c.hidden);
                    region.hidden = term !== '' && !visible;
                    if (term !== '' && visible) region.open = true;
                });
            });
            render();
        });
    });
</script>
@endonce
@endif
