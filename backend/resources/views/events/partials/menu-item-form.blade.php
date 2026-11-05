{{--
    Menu Builder form for one single item. $item is null when adding. $formId keeps field ids unique on the page.
    Selections (option groups) are typed one choice per line, with an optional upgrade price after a comma.
--}}
@php
    $tz = $event->timezone ?: config('events.market.default_timezone', 'America/New_York');
    $local = fn ($instant) => $instant ? \Illuminate\Support\Carbon::parse($instant)->setTimezone($tz)->format('Y-m-d\TH:i') : '';
    $id = fn (string $key) => "{$formId}-{$key}";
    $groups = collect($item?->custom_option_groups ?? [])->map(function ($g) {
        $required = (bool) ($g['required'] ?? true);
        $max = max(1, (int) ($g['max'] ?? 1));
        return [
            'title' => $g['title'] ?? '',
            'mode' => $max > 1 ? ($required ? 'multi_required' : 'multi') : ($required ? 'required' : 'optional'),
            'max' => $max,
            'choices' => collect($g['choices'] ?? [])->map(fn ($c) => ($c['label'] ?? '') . ((float) ($c['extraPriceUSD'] ?? 0) > 0 ? ', ' . number_format((float) $c['extraPriceUSD'], 2) : ''))->implode("\n"),
        ];
    })->values()->all();
    $categories = collect(\App\Models\MenuItem::CATEGORIES)->merge($item ? [$item->category] : [])->unique()->values();
@endphp
<form method="POST" enctype="multipart/form-data" class="eb-stack" style="gap: var(--space-3); margin-top: var(--space-3);"
      action="{{ $item ? route('events.menu-studio.update', [$event->id, $item->id]) : route('events.menu-studio.items.store', $event->id) }}">
    @csrf
    @if($item) @method('PUT') @endif
    <input type="hidden" name="item_type_form" value="{{ $formId }}">
    <div class="eb-grid-3">
        <x-form-field name="name" :id="$id('name')" label="Item name" :value="$item?->name" :required="true" placeholder="Jerk Chicken" />
        <div class="eb-form-group">
            <label class="eb-label" for="{{ $id('category') }}">Food category <span class="eb-label-required" aria-hidden="true">*</span></label>
            <select class="eb-select eb-input" id="{{ $id('category') }}" name="category" required>
                @foreach($categories as $category)<option @selected(($item?->category ?? 'Main Dishes') === $category)>{{ $category }}</option>@endforeach
            </select>
            <p class="eb-help-text">Also the food station that hands it over.</p>
        </div>
        <x-form-field name="cuisine" :id="$id('cuisine')" type="select" label="Cuisine or country" :value="$item?->cuisine" placeholder="Not specified"
                      :options="collect($traditions)->mapWithKeys(fn ($t, $k) => [$k => $t['label']])->all()" />
    </div>
    <x-form-field name="description" :id="$id('description')" type="textarea" :rows="2" label="Description" :value="$item?->description" placeholder="What it is and how it's served" />
    <x-form-field name="ingredients" :id="$id('ingredients')" type="textarea" :rows="2" label="Ingredients" :value="$item?->ingredients" placeholder="Chicken, scallion, thyme, scotch bonnet, allspice" />
    <div class="eb-grid-4">
        <x-form-field name="price" :id="$id('price')" type="number" label="Price ($)" :value="$item ? \App\Support\Money::ofMinor($item->priceMinorIn($currency), $currency)->toDecimalString() : ''" :min="0" :step="0.01" :required="true" />
        <x-form-field name="available_stock" :id="$id('stock')" type="number" label="Available quantity" :value="$item?->available_stock" :min="0" help="Empty for no limit." />
        <x-form-field name="max_per_order" :id="$id('max')" type="number" label="Limit per customer" :value="$item?->max_per_order" :min="1" help="Empty for no limit." />
        <x-form-field name="prep_minutes" :id="$id('prep')" type="number" label="Preparation time (minutes)" :value="$item?->prep_minutes" :min="0" />
    </div>
    <div class="eb-grid-2">
        <x-form-field name="available_from" :id="$id('from')" type="datetime-local" label="Available from" :value="$local($item?->available_from)" help="Empty: whenever food can be ordered." />
        <x-form-field name="available_until" :id="$id('until')" type="datetime-local" label="Available until" :value="$local($item?->available_until)" />
    </div>

    <fieldset class="eb-fieldset">
        <legend class="eb-text-label">Dietary classification</legend>
        <div class="eb-grid-4" style="gap: var(--space-1);">
            @foreach(\App\Models\MenuItem::DIETARY_TAGS as $key => $label)
                <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="dietary_tags[]" value="{{ $key }}" @checked(in_array($key, (array) ($item?->dietary_tags ?? []), true))> <span>{{ $label }}</span></label>
            @endforeach
        </div>
    </fieldset>
    <fieldset class="eb-fieldset">
        <legend class="eb-text-label">Contains these allergens</legend>
        <div class="eb-grid-4" style="gap: var(--space-1);">
            @foreach(\App\Models\MenuItem::ALLERGENS as $key => $label)
                <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="allergens[]" value="{{ $key }}" @checked(in_array($key, (array) ($item?->allergens ?? []), true))> <span>{{ $label }}</span></label>
            @endforeach
        </div>
    </fieldset>

    <fieldset class="eb-fieldset" data-repeat>
        <legend class="eb-text-label">Selections and extras</legend>
        <p class="eb-help-text" style="margin-top: 0;">For example "Choose your side" (required) or "Extras" (optional, several). One choice per line; add an upgrade price after a comma, like <code>Extra plantain, 2.50</code>.</p>
        <div class="eb-stack" data-repeat-rows style="gap: var(--space-3);">
            @foreach($groups as $i => $g)
                @include('events.partials.option-group-row', ['n' => $i, 'g' => $g])
            @endforeach
        </div>
        <template>@include('events.partials.option-group-row', ['n' => '__N__', 'g' => ['title' => '', 'mode' => 'required', 'max' => 1, 'choices' => '']])</template>
        <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" data-repeat-add style="align-self: flex-start;">＋ Add a selection</button>
    </fieldset>

    <div class="eb-grid-2">
        <x-form-field name="guest_instructions" :id="$id('guest-notes')" label="Note for guests (optional)" :value="$item?->guest_instructions" placeholder="e.g. Served with bones; ask for boneless" />
        <x-form-field name="spice_level" :id="$id('spice')" type="select" label="Spice level" :value="$item?->spice_level" placeholder="Not spicy / not stated" :options="['mild' => 'Mild', 'medium' => 'Medium', 'hot' => 'Hot', 'extra_hot' => 'Extra hot']" />
    </div>
    <div class="eb-cluster" style="gap: var(--space-4); flex-wrap: wrap;">
        <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="allow_special_instructions" value="1" @checked($item?->allow_special_instructions)> <span>Guests may add special instructions</span></label>
        <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="is_featured" value="1" @checked($item?->is_featured)> <span>Feature at the top of the menu</span></label>
        <input type="hidden" name="is_active" value="0">
        <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="is_active" value="1" @checked($item ? $item->is_active : true)> <span>On the menu</span></label>
    </div>

    @include('events.partials.menu-photo-field', ['subject' => $item])

    <div><button type="submit" class="eb-btn eb-btn-primary">{{ $item ? 'Save item' : 'Add item' }}</button></div>
</form>
