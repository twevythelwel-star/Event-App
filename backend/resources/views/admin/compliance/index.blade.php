@extends('layouts.admin')

@section('title', 'Compliance database')
@section('heading', 'Compliance Database')
@section('subheading', 'Food and alcohol authorities and requirements for the United States, every state, the District of Columbia, counties and municipalities.')
@section('breadcrumbs')<span> / Compliance database</span>@endsection
@section('actions')
    @can(\App\Support\AdminAuthorization::COMPLIANCE_EDIT)
        <a class="ac-btn ac-btn-sm" href="{{ route('admin.console.compliance.evidence.index') }}">Event evidence to verify ({{ \App\Models\EventComplianceEvidence::whereIn('status', ['submitted', 'pending_verification'])->count() }})</a>
    @endcan
@endsection

@section('content')
    <div class="ac-alert warning" role="note">
        <div>
            <strong>Only verified records may be relied on.</strong>
            Every record starts as "Verification Required". A record becomes verified only after an administrator confirms it against
            the agency's own official source and a system administrator other than the submitter approves it. Researched entries copied from
            federal directories (FDA, TTB) are drafts until then.
        </div>
    </div>

    <div class="ac-metrics">
        @foreach(['country' => 'Country', 'state' => 'States and D.C.', 'county' => 'Counties', 'municipality' => 'Cities and municipalities'] as $type => $label)
            <div class="ac-card ac-metric">
                <div class="ac-metric-label">{{ $label }}</div>
                <div class="ac-metric-value">{{ number_format((int) ($totals[$type] ?? 0)) }}</div>
                @if(in_array($type, ['county', 'municipality'], true) && empty($totals[$type]))
                    <div class="ac-metric-note">Not imported yet: <code>php artisan compliance:import-census</code></div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="ac-grid ac-grid-2">
        @foreach(['Requirement records' => $requirementCounts, 'Authority records' => $authorityCounts] as $label => $counts)
            <section class="ac-card">
                <div class="ac-card-head"><h2>{{ $label }}</h2></div>
                <div class="ac-card-body">
                    <dl class="ac-dl">
                        @foreach(\App\Models\Compliance\ComplianceRequirement::STATUSES as $status => $statusLabel)
                            <dt>@include('admin.compliance._status', ['status' => $status])</dt><dd>{{ number_format((int) ($counts[$status] ?? 0)) }}</dd>
                        @endforeach
                    </dl>
                </div>
            </section>
        @endforeach
    </div>

    @foreach(['Pending review' => $pending, 'Due for review again' => $due] as $title => $queue)
        <section class="ac-card" style="margin-top:16px">
            <div class="ac-card-head"><h2>{{ $title }} ({{ $queue->count() }})</h2></div>
            @if($queue->isEmpty())
                <div class="ac-card-body ac-empty">Nothing here.</div>
            @else
                <div class="ac-table-wrap"><table class="ac-table">
                    <thead><tr><th>Record</th><th>Jurisdiction</th><th>Status</th><th>Updated</th></tr></thead>
                    <tbody>
                    @foreach($queue as $record)
                        <tr>
                            <td>
                                <div class="ac-cell-main">{{ $record instanceof \App\Models\Compliance\ComplianceAuthority ? $record->name : $record->topicLabel() }}</div>
                                <div class="ac-cell-sub">{{ $record instanceof \App\Models\Compliance\ComplianceAuthority ? 'Authority' : 'Requirement' }}</div>
                            </td>
                            <td><a class="ac-link" href="{{ route('admin.console.compliance.jurisdictions.show', $record->jurisdiction_id) }}#{{ $record::RECORD_TYPE }}-{{ $record->id }}">{{ $record->jurisdiction->name }}</a></td>
                            <td>@include('admin.compliance._status', ['status' => $record->status])</td>
                            <td>{{ $record->updated_at?->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </section>
    @endforeach

    <section class="ac-card" style="margin-top:16px">
        <div class="ac-card-head"><h2>Find a jurisdiction</h2></div>
        <div class="ac-card-body">
            <form method="GET" class="ac-filters" role="search">
                <div class="ac-field grow"><label for="jurisdiction-search">State, county or municipality</label>
                    <input id="jurisdiction-search" class="ac-input" name="q" value="{{ $search }}" maxlength="120" placeholder="e.g. Miami-Dade"></div>
                <button class="ac-btn ac-btn-primary" type="submit">Search</button>
            </form>
            @if($search !== '')
                @if($results->isEmpty())
                    <p class="ac-empty">No jurisdiction matches "{{ $search }}".</p>
                @else
                    <ul>
                        @foreach($results as $result)
                            <li><a class="ac-link" href="{{ route('admin.console.compliance.jurisdictions.show', $result) }}">{{ $result->name }}</a>
                                <span class="ac-hint">{{ $result->typeLabel() }}@if($result->parent) · in {{ $result->parent->name }}@endif</span></li>
                        @endforeach
                    </ul>
                @endif
            @endif
        </div>
    </section>

    <section class="ac-card" style="margin-top:16px">
        <div class="ac-card-head"><h2>States and the District of Columbia</h2>
            @if($country)<a class="ac-btn ac-btn-sm" href="{{ route('admin.console.compliance.jurisdictions.show', $country) }}">Federal records</a>@endif
        </div>
        @if($states->isEmpty())
            <div class="ac-card-body ac-empty">No jurisdictions yet. Run <code>php artisan db:seed --class=ComplianceDatabaseSeeder</code>.</div>
        @else
            <div class="ac-table-wrap"><table class="ac-table">
                <thead><tr><th>State</th><th>Authorities</th><th>Requirements verified</th><th>Still to verify</th><th>Counties</th></tr></thead>
                <tbody>
                @foreach($states as $state)
                    <tr>
                        <td><a class="ac-link" href="{{ route('admin.console.compliance.jurisdictions.show', $state) }}">{{ $state->name }}</a> <span class="ac-hint">{{ $state->code }}</span></td>
                        <td>{{ $state->authorities_count }}</td>
                        <td>{{ $state->verified_requirements_count }} / {{ count(\App\Models\Compliance\ComplianceRequirement::TOPICS) }}</td>
                        <td>@if($state->open_requirements_count)<span class="ac-badge danger">{{ $state->open_requirements_count }} Verification Required</span>@else — @endif</td>
                        <td>{{ number_format($state->counties_count) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </section>
@endsection
