@extends('layouts.admin')

@section('title', $jurisdiction->name . ' · Compliance database')
@section('heading', $jurisdiction->name)
@section('subheading', $jurisdiction->typeLabel() . ($jurisdiction->type === 'country' ? ': federal authorities and requirements' : ''))
@section('breadcrumbs')
    <span> / <a href="{{ route('admin.console.compliance.index') }}">Compliance database</a></span>
    @foreach($lineage as $node)
        @if(!$loop->last)<span> / <a href="{{ route('admin.console.compliance.jurisdictions.show', $node) }}">{{ $node->name }}</a></span>@else<span> / {{ $node->name }}</span>@endif
    @endforeach
@endsection

@section('content')
    <section class="ac-card">
        <div class="ac-card-body">
            <dl class="ac-dl">
                <dt>Jurisdiction type</dt><dd>{{ $jurisdiction->typeLabel() }}</dd>
                <dt>Parent jurisdiction</dt><dd>@if($jurisdiction->parent)<a class="ac-link" href="{{ route('admin.console.compliance.jurisdictions.show', $jurisdiction->parent) }}">{{ $jurisdiction->parent->name }}</a>@else — @endif</dd>
                <dt>Code</dt><dd>{{ $jurisdiction->code }}@if(in_array($jurisdiction->type, ['county', 'municipality'], true)) <span class="ac-hint">(Census FIPS)</span>@endif</dd>
                <dt>Data source</dt><dd>{{ ['seed' => 'Initial research seed', 'census_2020' => 'U.S. Census Bureau 2020 FIPS codes', 'manual' => 'Added by an administrator'][$jurisdiction->data_source] ?? $jurisdiction->data_source }}</dd>
            </dl>
            @if($jurisdiction->type === 'country')
                <p class="ac-hint" style="margin-top:10px">Federal requirements apply in addition to each state's and locality's. They never stand in for a state's own rules.</p>
            @elseif($jurisdiction->type !== 'state')
                <p class="ac-hint" style="margin-top:10px">Local requirements are researched separately. The state's records do not apply here automatically, and neither do another locality's.</p>
            @endif
        </div>
    </section>

    <h2 class="ac-section" style="margin-top:20px">Authorities</h2>
    @forelse($jurisdiction->authorities as $authority)
        @include('admin.compliance._record', ['record' => $authority, 'type' => 'authority'])
    @empty
        <p class="ac-empty">No authorities recorded for this jurisdiction yet.</p>
    @endforelse
    @if($canEdit)
        <details class="ac-card" style="margin-top:12px;padding:14px 18px">
            <summary class="ac-link">Add an authority</summary>
            <form method="POST" action="{{ route('admin.console.compliance.authorities.store', $jurisdiction) }}" style="margin-top:10px">
                @csrf
                @include('admin.compliance._fields', ['record' => null, 'type' => 'authority', 'prefix' => 'new-authority', 'citableAuthorities' => collect()])
                <button class="ac-btn ac-btn-primary ac-btn-sm" type="submit">Add as draft</button>
            </form>
        </details>
    @endif

    <h2 class="ac-section" style="margin-top:20px">Requirements</h2>
    @if($canEdit && $requirements->count() < count(\App\Models\Compliance\ComplianceRequirement::TOPICS))
        <form method="POST" action="{{ route('admin.console.compliance.jurisdictions.research', $jurisdiction) }}" style="margin:8px 0">@csrf
            <button class="ac-btn ac-btn-sm" type="submit">Start research on every requirement topic</button></form>
    @endif
    @foreach(\App\Models\Compliance\ComplianceRequirement::TOPICS as $topic => $label)
        @if($requirements->has($topic))
            @include('admin.compliance._record', ['record' => $requirements[$topic], 'type' => 'requirement'])
        @else
            <div class="ac-card" style="margin-top:12px"><div class="ac-card-head"><h2 style="font-size:15px">{{ $label }}</h2><span class="ac-badge danger">Verification Required</span></div>
                <div class="ac-card-body ac-hint">Not researched for this jurisdiction yet.</div></div>
        @endif
    @endforeach

    <h2 class="ac-section" style="margin-top:20px" id="event-rules">Event checklist rules</h2>
    <p class="ac-hint">Rules decide what hosts see on their event's requirements checklist for this jurisdiction. Without a rule an item shows "Verification Required". An exception ("not required") wins over a requirement when both match. Every rule cites an official source.</p>
    @forelse($rules as $rule)
        <article class="ac-card" style="margin-top:10px;{{ $rule->is_active ? '' : 'opacity:.7' }}">
            <div class="ac-card-head">
                <h2 style="font-size:15px">{{ $rule->label() }} · {{ \App\Models\Compliance\ComplianceEventRule::EFFECTS[$rule->effect] }}</h2>
                <span class="ac-badge {{ $rule->is_active ? 'success' : '' }}">{{ $rule->is_active ? 'On' : 'Off' }}</span>
            </div>
            <div class="ac-card-body">
                <dl class="ac-dl">
                    <dt>Where</dt><dd>{{ \App\Models\Compliance\ComplianceEventRule::LOCATIONS[$rule->location] }}@if($rule->include_descendants), here and everywhere inside {{ $jurisdiction->name }}@endif</dd>
                    <dt>Applies to</dt><dd>{{ $rule->conditionSummary() }}</dd>
                    @if($rule->description)<dt>Shown to hosts</dt><dd>{{ $rule->description }}</dd>@endif
                    <dt>Official source</dt><dd><a class="ac-link" href="{{ $rule->source_url }}" target="_blank" rel="noopener noreferrer">{{ $rule->source_title ?: $rule->source_url }}</a></dd>
                </dl>
                @if($canEdit)
                    <form method="POST" action="{{ route('admin.console.compliance.rules.toggle', $rule) }}" style="margin-top:10px">@csrf
                        <button class="ac-btn ac-btn-sm" type="submit">{{ $rule->is_active ? 'Turn off' : 'Turn on' }}</button></form>
                @endif
            </div>
        </article>
    @empty
        <p class="ac-empty">No rules for this jurisdiction: every item here shows as Verification Required.</p>
    @endforelse
    @if($canEdit)
        <details class="ac-card" style="margin-top:12px;padding:14px 18px" @if($errors->hasAny(['topic', 'effect', 'source_url', 'title'])) open @endif>
            <summary class="ac-link">Add a requirement, an exception or a local requirement</summary>
            <form method="POST" action="{{ route('admin.console.compliance.rules.store', $jurisdiction) }}" style="margin-top:10px">
                @csrf
                <div class="ac-form-row">
                    <div class="ac-field"><label for="rule-topic">Requirement</label><select id="rule-topic" class="ac-select" name="topic">
                        @foreach(\App\Models\Compliance\ComplianceRequirement::TOPICS as $value => $label)<option value="{{ $value }}" @selected(old('topic') === $value)>{{ $label }}</option>@endforeach
                        <option value="local" @selected(old('topic') === 'local')>Additional local requirement…</option></select></div>
                    <div class="ac-field"><label for="rule-title">Local requirement name (for an additional one)</label><input id="rule-title" class="ac-input" name="title" maxlength="160" value="{{ old('title') }}"></div>
                    <div class="ac-field"><label for="rule-effect">Effect</label><select id="rule-effect" class="ac-select" name="effect">@foreach(\App\Models\Compliance\ComplianceEventRule::EFFECTS as $value => $label)<option value="{{ $value }}" @selected(old('effect') === $value)>{{ $label }}</option>@endforeach</select></div>
                    <div class="ac-field"><label for="rule-location">Location it concerns</label><select id="rule-location" class="ac-select" name="location">@foreach(\App\Models\Compliance\ComplianceEventRule::LOCATIONS as $value => $label)<option value="{{ $value }}" @selected(old('location') === $value)>{{ $label }}</option>@endforeach</select></div>
                </div>
                <fieldset style="border:0;padding:0;margin:12px 0 0"><legend class="ac-label">Only for these events (leave a group empty for any)</legend>
                    <div class="ac-form-row">
                        <div><span class="ac-label">Event type</span>@foreach($eventTypes as $type)<label class="ac-check" style="display:block"><input type="checkbox" name="event_types[]" value="{{ $type }}" @checked(in_array($type, old('event_types', []), true))> {{ $type }}</label>@endforeach</div>
                        <div><span class="ac-label">Food</span>@foreach(\App\Models\Compliance\ComplianceEventRule::FOOD_MODES as $value => $label)<label class="ac-check" style="display:block"><input type="checkbox" name="food_modes[]" value="{{ $value }}" @checked(in_array($value, old('food_modes', []), true))> {{ $label }}</label>@endforeach</div>
                        <div><span class="ac-label">Alcohol</span>@foreach(\App\Models\Compliance\ComplianceEventRule::ALCOHOL_MODES as $value => $label)<label class="ac-check" style="display:block"><input type="checkbox" name="alcohol_modes[]" value="{{ $value }}" @checked(in_array($value, old('alcohol_modes', []), true))> {{ $label }}</label>@endforeach</div>
                        <div>
                            <div class="ac-field"><label for="rule-caterer">Licensed caterer</label><select id="rule-caterer" class="ac-select" name="caterer"><option value="any">Either</option><option value="yes" @selected(old('caterer') === 'yes')>Involved</option><option value="no" @selected(old('caterer') === 'no')>Not involved</option></select></div>
                            <div class="ac-field"><label for="rule-vendor">Licensed alcohol vendor</label><select id="rule-vendor" class="ac-select" name="alcohol_vendor"><option value="any">Either</option><option value="yes" @selected(old('alcohol_vendor') === 'yes')>Involved</option><option value="no" @selected(old('alcohol_vendor') === 'no')>Not involved</option></select></div>
                        </div>
                    </div>
                </fieldset>
                <div class="ac-form-row" style="margin-top:12px">
                    <div class="ac-field grow"><label for="rule-description">What hosts are told</label><textarea id="rule-description" class="ac-textarea" name="description" rows="2" maxlength="2000">{{ old('description') }}</textarea></div>
                    <div class="ac-field"><label for="rule-requirement">Linked requirement record</label><select id="rule-requirement" class="ac-select" name="requirement_id"><option value="">—</option>@foreach($requirements as $requirement)<option value="{{ $requirement->id }}">{{ $requirement->topicLabel() }} ({{ $requirement->statusLabel() }})</option>@endforeach</select></div>
                    <div class="ac-field grow"><label for="rule-source">Official source link</label><input id="rule-source" class="ac-input" type="url" name="source_url" required maxlength="500" placeholder="https://" value="{{ old('source_url') }}"></div>
                    <div class="ac-field"><label for="rule-source-title">Source title</label><input id="rule-source-title" class="ac-input" name="source_title" maxlength="255" value="{{ old('source_title') }}"></div>
                </div>
                <label class="ac-check" style="display:block;margin-top:8px"><input type="checkbox" name="include_descendants" value="1"> Also apply to every county and city inside {{ $jurisdiction->name }}</label>
                <label class="ac-check" style="display:block"><input type="checkbox" name="source_confirmed_official" value="1"> The source is the agency's own official site (needed only when it is not on .gov, .mil or .us)</label>
                <button class="ac-btn ac-btn-primary ac-btn-sm" type="submit" style="margin-top:10px">Add rule</button>
            </form>
        </details>
    @endif

    @if($jurisdiction->type !== 'municipality')
        <section class="ac-card" style="margin-top:20px">
            <div class="ac-card-head"><h2>{{ $jurisdiction->type === 'country' ? 'States and the District of Columbia' : ($jurisdiction->type === 'state' ? 'Counties and municipalities' : 'Municipalities') }}</h2></div>
            <div class="ac-card-body">
                <form method="GET" class="ac-filters" role="search">
                    <div class="ac-field grow"><label for="child-search">Find</label><input id="child-search" class="ac-input" name="q" value="{{ $childSearch }}" maxlength="120"></div>
                    <button class="ac-btn ac-btn-sm" type="submit">Search</button>
                </form>
                @if($children->isEmpty())
                    <p class="ac-empty">@if($childSearch !== '')No match.@else None recorded yet. Counties and municipalities come from <code>php artisan compliance:import-census</code>.@endif</p>
                @else
                    <div class="ac-table-wrap"><table class="ac-table">
                        <thead><tr><th>Name</th><th>Type</th><th>Requirements verified</th></tr></thead>
                        <tbody>@foreach($children as $child)
                            <tr><td><a class="ac-link" href="{{ route('admin.console.compliance.jurisdictions.show', $child) }}">{{ $child->name }}</a></td><td>{{ $child->typeLabel() }}</td>
                                <td>{{ $child->verified_requirements_count }} / {{ count(\App\Models\Compliance\ComplianceRequirement::TOPICS) }}</td></tr>
                        @endforeach</tbody>
                    </table></div>
                    @include('admin.partials.pagination', ['paginator' => $children])
                @endif
            </div>
        </section>
    @endif

    <section class="ac-card" style="margin-top:20px">
        <div class="ac-card-head"><h2>Review history</h2></div>
        @if($history->isEmpty())
            <div class="ac-card-body ac-empty">No history yet.</div>
        @else
            <div class="ac-table-wrap"><table class="ac-table">
                <thead><tr><th>When</th><th>Record</th><th>Action</th><th>Status</th><th>By</th><th>Comment</th></tr></thead>
                <tbody>@foreach($history as $entry)
                    <tr><td>{{ $entry->created_at?->format('j M Y H:i') }}</td>
                        <td><a class="ac-link" href="#{{ $entry->record_type }}-{{ $entry->record_id }}">{{ ucfirst($entry->record_type) }} #{{ $entry->record_id }}</a></td>
                        <td>{{ str_replace('_', ' ', $entry->action) }}</td>
                        <td>{{ \App\Models\Compliance\ComplianceRequirement::STATUSES[$entry->from_status] ?? '—' }} → {{ \App\Models\Compliance\ComplianceRequirement::STATUSES[$entry->to_status] ?? '—' }}</td>
                        <td>{{ $entry->user?->name ?? 'System' }}</td><td>{{ $entry->comment }}</td></tr>
                @endforeach</tbody>
            </table></div>
        @endif
    </section>
@endsection
