@extends('layouts.admin')

@section('title', 'Policies')
@section('heading', 'Policies and Acknowledgements')
@section('subheading', 'Version-controlled policies. Published versions are never changed; a change is a new version.')
@section('breadcrumbs')<span> / <a href="{{ route('admin.console.compliance-centre.dashboard') }}">Compliance dashboard</a></span><span> / Policies</span>@endsection

@php
    use App\Models\Compliance\CompliancePolicyVersion as V;
    $rolesLabel = fn ($v) => collect($v->roles())->map(fn ($r) => V::ROLES[$r] ?? $r)->implode(', ');
    $where = fn ($v) => $v->jurisdictions() === [] ? 'All jurisdictions' : implode(', ', $v->jurisdictions());
@endphp

@section('content')
    @include('admin.compliance-centre._tabs', ['current' => 'policies'])
    @if($errors->any())<div class="ac-alert error" role="alert"><div>{{ $errors->first() }}</div></div>@endif

    <section class="ac-card">
        <div class="ac-card-head"><h2>Policies in force</h2></div>
        <div class="ac-table-wrap"><table class="ac-table">
            <thead><tr><th>Policy</th><th>Category</th><th>Applies to</th><th>Version in force</th><th>Acknowledgement</th><th>Accepted the version in force</th></tr></thead>
            <tbody>@foreach($current as $name => $v)
                @php
                    $req = $required[$name];
                    $people = optional($acceptance->get($name))->firstWhere('policy_version', $v->version)?->people ?? 0;
                @endphp
                <tr>
                    <td>{{ $v->title }}<span class="ac-cell-sub">{{ $name }}</span></td>
                    <td>{{ V::CATEGORIES[$v->category] ?? $v->category }}</td>
                    <td>{{ $rolesLabel($v) }}<span class="ac-cell-sub">{{ $where($v) }}</span></td>
                    <td>{{ $v->version }}<span class="ac-cell-sub">in force {{ ($v->effective_at ?? $v->published_at)?->format('j M Y') }}</span></td>
                    <td>{{ $v->acknowledgement_mandatory ? 'Mandatory' : 'Optional' }}<span class="ac-cell-sub">required version: {{ $req?->version }}</span></td>
                    <td>{{ number_format($people) }}</td>
                </tr>
            @endforeach</tbody>
        </table></div>
    </section>

    @if($upcoming->isNotEmpty())
        <section class="ac-card" style="margin-top:16px">
            <div class="ac-card-head"><h2>Published, not yet in force</h2></div>
            <div class="ac-table-wrap"><table class="ac-table">
                <thead><tr><th>Policy</th><th>Version</th><th>In force from</th></tr></thead>
                <tbody>@foreach($upcoming as $v)<tr><td>{{ $v->title }}</td><td>{{ $v->version }}</td><td>{{ $v->effective_at->format('j M Y') }}</td></tr>@endforeach</tbody>
            </table></div>
        </section>
    @endif

    <section class="ac-card" style="margin-top:16px">
        <div class="ac-card-head"><h2>Drafts</h2></div>
        @forelse($drafts as $v)
            <div class="ac-card-body" style="border-top:1px solid var(--ac-border, #233)">
                <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap">
                    <strong>{{ $v->title }} · version {{ $v->version }}</strong><span class="ac-badge warning">Draft</span>
                </div>
                <p class="ac-hint" style="margin:4px 0">{{ $v->policy_name }} · {{ V::CATEGORIES[$v->category] ?? $v->category }} · {{ $rolesLabel($v) }} · {{ $where($v) }}
                    · {{ $v->acknowledgement_mandatory ? 'mandatory' : 'optional' }} · {{ $v->requires_reacknowledgement ? 'material change: everyone must accept again' : 'minor change: earlier acceptance still counts' }}
                    · in force {{ $v->effective_at ? 'from ' . $v->effective_at->format('j M Y') : 'when published' }} · drafted by {{ $v->drafter?->name ?? '—' }}</p>
                <p style="margin:4px 0"><strong>What changed:</strong> {{ $v->change_summary }}</p>
                <details><summary>Full text</summary><p style="white-space:pre-line">{{ $v->body }}</p></details>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-top:8px">
                    @can('publish', $v)
                        <form method="POST" action="{{ route('admin.console.compliance-centre.policies.publish', $v) }}" style="display:flex;gap:6px;align-items:flex-end;flex-wrap:wrap">@csrf
                            <label style="display:flex;gap:6px;max-width:560px"><input type="checkbox" name="confirm" value="1" required>
                                <span>{{ $v->acknowledgement_mandatory && $v->requires_reacknowledgement
                                    ? 'I understand everyone it applies to must acknowledge it at their next visit, and cannot take restricted actions until they do.'
                                    : 'I understand this replaces the current version without asking anyone to accept it again.' }}</span></label>
                            <button class="ac-btn ac-btn-primary ac-btn-sm" type="submit">Publish</button>
                        </form>
                    @else
                        <span class="ac-hint">A system administrator publishes drafts.</span>
                    @endcan
                    @can('delete', $v)
                        <form method="POST" action="{{ route('admin.console.compliance-centre.policies.destroy', $v) }}">@csrf @method('DELETE')<button class="ac-btn ac-btn-sm" type="submit">Delete draft</button></form>
                    @endcan
                </div>
            </div>
        @empty
            <div class="ac-card-body ac-empty">No drafts.</div>
        @endforelse
    </section>

    <section class="ac-card" style="margin-top:16px">
        <div class="ac-card-head"><h2>Version history</h2></div>
        <div class="ac-card-body">
            <p class="ac-hint">Every version ever published, newest first. They are kept unchanged so each acknowledgement can be traced to the exact text accepted.</p>
            @foreach($history as $name => $versions)
                <details style="margin-top:6px"><summary>{{ $versions->first()->title }} <span class="ac-hint">({{ $name }}, {{ $versions->count() }} {{ \Illuminate\Support\Str::plural('version', $versions->count()) }})</span></summary>
                    <div class="ac-table-wrap"><table class="ac-table">
                        <thead><tr><th>Version</th><th>Status</th><th>Published</th><th>In force from</th><th>Change</th><th></th></tr></thead>
                        <tbody>@foreach($versions as $v)
                            <tr><td>{{ $v->version }}</td>
                                <td><span class="ac-badge {{ ['published' => 'success', 'draft' => 'warning'][$v->status] ?? '' }}">{{ V::STATUSES[$v->status] }}</span></td>
                                <td>{{ $v->published_at?->format('j M Y') ?? '—' }}{{ $v->publisher ? ' by ' . $v->publisher->name : ($v->source === 'built_in' ? ' (original)' : '') }}</td>
                                <td>{{ $v->effective_at?->format('j M Y') ?? '—' }}</td>
                                <td>{{ $v->requires_reacknowledgement ? 'Material' : 'Minor' }}: {{ $v->change_summary }}</td>
                                <td>@if($v->status !== 'draft')<a class="ac-link" href="{{ route('admin.console.compliance-centre.policies.affected', $v) }}">Who must acknowledge (CSV)</a>@endif</td></tr>
                        @endforeach</tbody>
                    </table></div>
                </details>
            @endforeach
        </div>
    </section>

    @can('create', \App\Models\Compliance\CompliancePolicyVersion::class)
        <section class="ac-card" style="margin-top:16px">
            <div class="ac-card-head"><h2>Draft a new version or a new policy</h2></div>
            <form method="POST" action="{{ route('admin.console.compliance-centre.policies.store') }}" class="ac-card-body" style="display:grid;gap:10px">@csrf
                <div class="ac-field"><label for="p-name">Policy</label>
                    <select id="p-name" class="ac-select" name="policy_name">
                        <option value="">A new policy</option>
                        @foreach($current as $name => $v)<option value="{{ $name }}" @selected(old('policy_name') === $name)>{{ $v->title }} (now {{ $v->version }})</option>@endforeach
                    </select></div>
                <div class="ac-field"><label for="p-id">Identifier for a new policy (optional; made from the title otherwise)</label><input id="p-id" class="ac-input" name="identifier" value="{{ old('identifier') }}" placeholder="e.g. outdoor_heat_safety" maxlength="80"></div>
                <div class="ac-field"><label for="p-version">Version number</label><input id="p-version" class="ac-input" name="version" value="{{ old('version') }}" placeholder="e.g. 2026.12-v2" required maxlength="40"></div>
                <div class="ac-field"><label for="p-title">Title</label><input id="p-title" class="ac-input" name="title" value="{{ old('title') }}" required maxlength="255"></div>
                <div class="ac-field"><label for="p-category">Category</label>
                    <select id="p-category" class="ac-select" name="category">@foreach(V::CATEGORIES as $value => $label)<option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>@endforeach</select></div>
                <fieldset class="ac-field"><legend>Applies to</legend>
                    @foreach(V::ROLES as $value => $label)<label style="margin-right:12px"><input type="checkbox" name="applicable_roles[]" value="{{ $value }}" @checked(in_array($value, old('applicable_roles', []), true))> {{ $label }}</label>@endforeach
                </fieldset>
                <div class="ac-field"><label for="p-states">Jurisdictions (leave empty for everywhere; hold Ctrl to choose several)</label>
                    <select id="p-states" class="ac-select" name="applicable_jurisdictions[]" multiple size="5">@foreach($states as $s)<option value="{{ $s->code }}" @selected(in_array($s->code, old('applicable_jurisdictions', []), true))>{{ $s->name }}</option>@endforeach</select></div>
                <div class="ac-field"><label for="p-effective">Effective date (empty: when published)</label><input id="p-effective" type="date" class="ac-input" name="effective_at" value="{{ old('effective_at') }}" min="{{ now()->toDateString() }}"></div>
                <label><input type="checkbox" name="acknowledgement_mandatory" value="1" @checked(old('acknowledgement_mandatory', '1'))> Acknowledgement is mandatory</label>
                <label><input type="checkbox" name="requires_reacknowledgement" value="1" @checked(old('requires_reacknowledgement', '1'))> Material change: everyone must acknowledge this version, even if they accepted an earlier one</label>
                <div class="ac-field"><label for="p-body">Full policy text</label><textarea id="p-body" class="ac-input" name="body" rows="8" required minlength="20">{{ old('body') }}</textarea></div>
                <div class="ac-field"><label for="p-summary">What changed</label><input id="p-summary" class="ac-input" name="change_summary" value="{{ old('change_summary') }}" required minlength="10" maxlength="2000"></div>
                <div><button class="ac-btn ac-btn-primary ac-btn-sm" type="submit">Save draft</button></div>
            </form>
        </section>
    @endcan
@endsection
