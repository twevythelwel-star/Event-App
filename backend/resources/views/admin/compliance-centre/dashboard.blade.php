@extends('layouts.admin')

@section('title', 'Compliance dashboard')
@section('heading', 'Compliance Dashboard')
@section('subheading', 'Identity, permits, licences, declarations, safety reports and suspensions in one place.')
@section('breadcrumbs')<span> / Compliance dashboard</span>@endsection

@section('content')
    @include('admin.compliance-centre._tabs', ['current' => 'dashboard', 'withStateFilter' => true])

    @php
        $tiles = [
            ['Identity submissions to review', $counts['identity'], $canIdentity ? route('admin.console.compliance-centre.identity') : null, 'Only administrators granted identity document access see these.'],
            ['Food permits to review', $counts['food'], route('admin.console.compliance.evidence.index', ['kind' => 'food', 'state' => $stateFilter]), null],
            ['Alcohol licences to review', $counts['alcohol'], route('admin.console.compliance.evidence.index', ['kind' => 'alcohol', 'state' => $stateFilter]), null],
            ['Events with missing or outdated declarations', $counts['declarations'], route('admin.console.compliance-centre.declarations', ['state' => $stateFilter]), null],
            ['Documents expiring within ' . \App\Http\Controllers\Admin\ComplianceCentreController::EXPIRY_WINDOW_DAYS . ' days', $counts['expiring'], route('admin.console.compliance-centre.expiring', ['state' => $stateFilter]), null],
            ['Open safety reports', $counts['safety'] . ($counts['urgent'] ? ' (' . $counts['urgent'] . ' urgent)' : ''), route('admin.console.compliance-centre.safety', ['state' => $stateFilter]), null],
            ['Hosts with publishing suspended', $counts['host_suspensions'], route('admin.console.compliance-centre.hosts', ['show' => 'suspended']), null],
            ['Suspended events', $counts['event_suspensions'], route('admin.console.compliance-centre.declarations', ['show' => 'suspended', 'state' => $stateFilter]), null],
        ];
    @endphp
    <div class="ac-metrics">
        @foreach($tiles as [$label, $value, $url, $note])
            <div class="ac-card ac-metric">
                <div class="ac-metric-label">{{ $label }}</div>
                <div class="ac-metric-value">@if($url && $value !== null)<a class="ac-link" href="{{ $url }}">{{ $value }}</a>@else — @endif</div>
                @if($value === null && $note)<div class="ac-metric-note">{{ $note }}</div>@endif
            </div>
        @endforeach
    </div>

    <div class="ac-grid ac-grid-2" style="margin-top:16px">
        <section class="ac-card">
            <div class="ac-card-head"><h2>Expiring soonest</h2><a class="ac-link" href="{{ route('admin.console.compliance-centre.expiring', ['state' => $stateFilter]) }}">All</a></div>
            @include('admin.compliance-centre._expiring-table', ['rows' => $expiring])
        </section>
        <section class="ac-card">
            <div class="ac-card-head"><h2>Open safety reports</h2><a class="ac-link" href="{{ route('admin.console.compliance-centre.safety', ['state' => $stateFilter]) }}">All</a></div>
            @if($safety->isEmpty())
                <div class="ac-card-body ac-empty">No open safety reports.</div>
            @else
                <div class="ac-table-wrap"><table class="ac-table">
                    <thead><tr><th>Report</th><th>Event</th><th>Priority</th><th>Status</th></tr></thead>
                    <tbody>@foreach($safety as $report)
                        <tr><td>#{{ $report->id }} · {{ \App\Models\Compliance\ComplianceSafetyReport::CATEGORIES[$report->category] ?? $report->category }}</td>
                            <td>{{ $report->event?->title ?? '—' }}</td>
                            <td><span class="ac-badge {{ $report->priority === 'urgent' ? 'danger' : '' }}">{{ ucfirst($report->priority) }}</span></td>
                            <td>{{ \App\Models\Compliance\ComplianceSafetyReport::STATUSES[$report->status] }}</td></tr>
                    @endforeach</tbody>
                </table></div>
            @endif
        </section>
    </div>

    <section class="ac-card" style="margin-top:16px">
        <div class="ac-card-head"><h2>Jurisdiction data and reports</h2></div>
        <div class="ac-card-body" style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="ac-btn ac-btn-sm" href="{{ route('admin.console.compliance.index') }}">Maintain jurisdiction requirements</a>
            @foreach(\App\Http\Controllers\Admin\ComplianceExportController::REPORTS as $key => $label)
                @if(($key !== 'identity' || $canIdentity) && (!in_array($key, ['consent', 'audit'], true) || $canRecords))
                    <a class="ac-btn ac-btn-sm" href="{{ route('admin.console.compliance-centre.export', array_filter(['report' => $key, 'state' => in_array($key, ['permits', 'declarations', 'safety'], true) ? $stateFilter : null])) }}">Export {{ strtolower($label) }} (CSV)</a>
                @endif
            @endforeach
        </div>
    </section>
@endsection
