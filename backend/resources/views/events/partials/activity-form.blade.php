{{-- Add or edit one schedule activity. $activity is null for a new one; times are in the event's own timezone ($tz). --}}
@php
    $a = $activity;
    $key = $a?->id ?? 'new';
    $local = fn ($t) => $t?->copy()->setTimezone($tz);
    $defaultDate = $event->starts_at?->copy()->setTimezone($tz)->format('Y-m-d') ?? $event->event_date_iso;
    $picked = old('performer_ids', $a ? $a->performers->pluck('id')->all() : []);
@endphp
<form method="POST" action="{{ $a ? route('events.schedule-studio.activities.update', [$event->id, $a->id]) : route('events.schedule-studio.activities.store', $event->id) }}" class="eb-stack" style="gap: var(--space-3);">
    @csrf
    @if($a) @method('PUT') @endif
    <div class="eb-grid-2">
        <x-form-field name="title" :id="'act-title-' . $key" label="Title" :value="old('title', $a?->title)" :required="true" placeholder="e.g. Sound system clash" />
        <x-form-field name="kind" :id="'act-kind-' . $key" type="select" label="Kind" :value="old('kind', $a?->kind ?? 'performance')" :options="\App\Models\EventActivity::KINDS" :required="true" />
    </div>
    <div class="eb-grid-3">
        <x-form-field name="date" :id="'act-date-' . $key" type="date" label="Date" :value="old('date', $local($a?->starts_at)?->format('Y-m-d') ?? $defaultDate)" :required="true" />
        <x-form-field name="start_time" :id="'act-start-' . $key" type="time" label="Starts" :value="old('start_time', $local($a?->starts_at)?->format('H:i'))" :required="true" />
        <x-form-field name="end_time" :id="'act-end-' . $key" type="time" label="Ends" :value="old('end_time', $local($a?->ends_at)?->format('H:i'))" :required="true" help="An end time before the start means it runs past midnight." />
    </div>
    <x-form-field name="stage_id" :id="'act-stage-' . $key" type="select" label="Where" :value="old('stage_id', $a?->stage_id)"
                  :options="['' => 'No set place'] + $stages->pluck('name', 'id')->all()" />
    @if($performers->isNotEmpty())
        <fieldset class="eb-fieldset">
            <legend class="eb-text-label">Who is on</legend>
            <div class="eb-cluster">
                @foreach($performers as $person)
                    <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="performer_ids[]" value="{{ $person->id }}" @checked(in_array($person->id, (array) $picked, true))>
                        <span>{{ $person->name }} <span class="eb-help-text">({{ $person->roleLabel() }})</span></span></label>
                @endforeach
            </div>
        </fieldset>
    @endif
    <x-form-field name="description" :id="'act-desc-' . $key" type="textarea" label="Description (optional)" :value="old('description', $a?->description)" />
    <label class="eb-choice-label"><input type="checkbox" class="eb-checkbox" name="hidden" value="1" @checked(old('hidden', $a && !$a->is_public))>
        <span>Keep this off the public schedule (for staff, setup or private moments)</span></label>
    <div><button type="submit" class="eb-btn eb-btn-primary" data-prevent-double-submit="true">{{ $a ? 'Save activity' : 'Add to schedule' }}</button></div>
</form>
