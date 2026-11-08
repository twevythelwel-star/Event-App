@extends('layouts.admin')

@section('title', 'Consent and audit records')
@section('heading', 'Consent and Audit Records')
@section('subheading', 'Every policy acceptance, with when, from where and in what context, and the compliance audit trail. Records cannot be edited.')
@section('breadcrumbs')<span> / <a href="{{ route('admin.console.compliance-centre.dashboard') }}">Compliance dashboard</a></span><span> / Consent and audit</span>@endsection

@section('content')
    @include('admin.compliance-centre._tabs', ['current' => 'records'])

    <form method="GET" class="ac-filters" style="margin-bottom:12px">
        <div class="ac-field"><label for="r-q">Person (name or email)</label><input id="r-q" class="ac-input" name="q" value="{{ $search }}"></div>
        <div class="ac-field"><label for="r-policy">Policy</label>
            <select id="r-policy" class="ac-select" name="policy"><option value="">All policies</option>@foreach($policies as $name => $spec)<option value="{{ $name }}" @selected($policy === $name)>{{ $spec['title'] }}</option>@endforeach</select></div>
        <button class="ac-btn ac-btn-sm" type="submit">Search</button>
        <a class="ac-btn ac-btn-sm" href="{{ route('admin.console.compliance-centre.export', 'consent') }}">Export consent records (CSV)</a>
        <a class="ac-btn ac-btn-sm" href="{{ route('admin.console.compliance-centre.export', 'audit') }}">Export audit trail (CSV)</a>
    </form>

    <section class="ac-card">
        <div class="ac-card-head"><h2>Consent records</h2></div>
        @if($consents->isEmpty())
            <div class="ac-card-body ac-empty">None.</div>
        @else
            <div class="ac-table-wrap"><table class="ac-table">
                <thead><tr><th>Person</th><th>Policy</th><th>Version</th><th>Accepted (UTC)</th><th>Context</th><th>IP address</th></tr></thead>
                <tbody>@foreach($consents as $row)
                    <tr><td>{{ $row->user?->name ?? 'Former user' }}<div class="ac-hint">{{ $row->user?->email }}</div></td>
                        <td>{{ $policies[$row->policy_name]['title'] ?? $row->policy_name }}</td><td>{{ $row->policy_version }}</td>
                        <td>{{ $row->accepted_at_utc?->format('j M Y H:i') }}</td><td>{{ str_replace('_', ' ', (string) $row->context) }}</td><td>{{ $row->ip_address ?? '—' }}</td></tr>
                @endforeach</tbody>
            </table></div>
            @include('admin.partials.pagination', ['paginator' => $consents])
        @endif
    </section>

    <section class="ac-card" style="margin-top:16px">
        <div class="ac-card-head"><h2>Compliance audit trail</h2></div>
        @if($audit->isEmpty())
            <div class="ac-card-body ac-empty">None.</div>
        @else
            <div class="ac-table-wrap"><table class="ac-table">
                <thead><tr><th>When (UTC)</th><th>Who</th><th>Action</th><th>Details</th></tr></thead>
                <tbody>@foreach($audit as $log)
                    <tr><td>{{ $log->created_at?->format('j M Y H:i') }}</td><td>{{ $log->user?->name ?? 'System' }}<div class="ac-hint">{{ $log->actor_role }}</div></td>
                        <td>{{ str_replace('_', ' ', $log->action) }}@if($log->is_pii_access) <span class="ac-badge warning">PII</span>@endif</td><td>{{ $log->details }}</td></tr>
                @endforeach</tbody>
            </table></div>
            @include('admin.partials.pagination', ['paginator' => $audit])
        @endif
    </section>
@endsection
