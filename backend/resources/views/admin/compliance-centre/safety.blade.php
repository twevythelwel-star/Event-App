@extends('layouts.admin')

@section('title', 'Safety reports')
@section('heading', 'Reported Safety Issues')
@section('subheading', 'Reports from attendees and others about events: food safety, allergens, alcohol, venue safety and harassment.')
@section('breadcrumbs')<span> / <a href="{{ route('admin.console.compliance-centre.dashboard') }}">Compliance dashboard</a></span><span> / Safety reports</span>@endsection

@section('content')
    @include('admin.compliance-centre._tabs', ['current' => 'safety'])
    @if($errors->any())<div class="ac-alert error" role="alert"><div>{{ $errors->first() }}</div></div>@endif

    <form method="GET" class="ac-filters" style="margin-bottom:12px">
        <div class="ac-field"><label for="s-status">Show</label>
            <select id="s-status" class="ac-select" name="status" onchange="this.form.submit()">
                <option value="open" @selected($status === 'open')>Open and investigating</option>
                <option value="all" @selected($status === 'all')>All</option>
                @foreach(\App\Models\Compliance\ComplianceSafetyReport::STATUSES as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach
            </select></div>
        <div class="ac-field"><label for="s-state">State</label>
            <select id="s-state" class="ac-select" name="state" onchange="this.form.submit()">
                <option value="">All states</option>
                @foreach($states as $s)<option value="{{ $s->code }}" @selected($stateFilter === $s->code)>{{ $s->name }}</option>@endforeach
            </select></div>
        <noscript><button class="ac-btn ac-btn-sm" type="submit">Filter</button></noscript>
    </form>

    @forelse($items as $report)
        <article class="ac-card" style="margin-top:10px">
            <div class="ac-card-head">
                <h2 style="font-size:15px">#{{ $report->id }} · {{ \App\Models\Compliance\ComplianceSafetyReport::CATEGORIES[$report->category] ?? $report->category }}</h2>
                <span>
                    <span class="ac-badge {{ $report->priority === 'urgent' ? 'danger' : '' }}">{{ ucfirst($report->priority) }}</span>
                    <span class="ac-badge {{ $report->isOpen() ? 'warning' : 'success' }}">{{ \App\Models\Compliance\ComplianceSafetyReport::STATUSES[$report->status] }}</span>
                </span>
            </div>
            <div class="ac-card-body">
                <dl class="ac-dl">
                    <dt>Event</dt><dd>@if($report->event)<a class="ac-link" href="{{ route('admin.console.compliance-centre.declaration', $report->event_id) }}">{{ $report->event->title }}</a> · {{ $report->event->state_code }}@else — @endif</dd>
                    <dt>Reported</dt><dd>{{ $report->created_at->format('j M Y H:i') }}{{ $report->occurred_on ? ' · happened ' . $report->occurred_on->format('j M Y') : '' }}
                        · {{ $report->may_contact ? 'May be contacted: ' . ($report->reporter?->email ?? 'account removed') : 'Did not agree to be contacted' }}</dd>
                    <dt>What happened</dt><dd style="white-space:pre-line">{{ $report->description }}</dd>
                    @if($report->resolution)<dt>Resolution</dt><dd>{{ $report->resolution }} ({{ $report->resolver?->name ?? '—' }}, {{ $report->resolved_at?->format('j M Y') }})</dd>@endif
                </dl>
                @can('update', $report)
                    <form method="POST" action="{{ route('admin.console.compliance-centre.safety.update', $report) }}" style="display:flex;gap:6px;flex-wrap:wrap;align-items:flex-end;margin-top:12px">@csrf
                        <div class="ac-field"><label for="st-{{ $report->id }}">Status</label><select id="st-{{ $report->id }}" class="ac-select" name="status">@foreach(\App\Models\Compliance\ComplianceSafetyReport::STATUSES as $v => $l)<option value="{{ $v }}" @selected($report->status === $v)>{{ $l }}</option>@endforeach</select></div>
                        <div class="ac-field"><label for="pr-{{ $report->id }}">Priority</label><select id="pr-{{ $report->id }}" class="ac-select" name="priority">@foreach(\App\Models\Compliance\ComplianceSafetyReport::PRIORITIES as $v => $l)<option value="{{ $v }}" @selected($report->priority === $v)>{{ $l }}</option>@endforeach</select></div>
                        <div class="ac-field"><label for="as-{{ $report->id }}">Assigned to</label><select id="as-{{ $report->id }}" class="ac-select" name="assigned_to"><option value="">Nobody</option>@foreach($managers as $m)<option value="{{ $m->id }}" @selected($report->assigned_to === $m->id)>{{ $m->name }}</option>@endforeach</select></div>
                        <div class="ac-field" style="flex:1;min-width:220px"><label for="rs-{{ $report->id }}">Finding and action (needed to resolve or dismiss)</label><input id="rs-{{ $report->id }}" class="ac-input" name="resolution" value="{{ $report->resolution }}" maxlength="2000"></div>
                        <button class="ac-btn ac-btn-primary ac-btn-sm" type="submit">Save</button>
                    </form>
                    @if($report->event && !$report->event->compliance_suspended_at)
                        <p class="ac-hint" style="margin-top:8px">If the event is unsafe, <a class="ac-link" href="{{ route('admin.console.compliance-centre.declaration', $report->event_id) }}">suspend it</a>.</p>
                    @endif
                @endcan
            </div>
        </article>
    @empty
        <p class="ac-empty">No safety reports here.</p>
    @endforelse
    @include('admin.partials.pagination', ['paginator' => $items])
@endsection
