@extends('layouts.admin')

@section('title', 'Audit Logs')
@section('breadcrumbs')<span aria-hidden="true">›</span><span aria-current="page">Audit Logs</span>@endsection
@section('heading', 'Audit Logs')
@section('subheading', 'A permanent record of sensitive actions. Entries cannot be edited or deleted from the application.')

@section('content')
    <div class="ac-card">
        <form class="ac-filters" method="GET" action="{{ route('admin.console.audit.index') }}" role="search">
            <div class="ac-field">
                <label for="action">Action</label>
                <select class="ac-select" id="action" name="action">
                    <option value="">All actions</option>
                    @foreach($actions as $action)<option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ str_replace('_', ' ', $action) }}</option>@endforeach
                </select>
            </div>
            <div class="ac-field grow">
                <label for="actor">Actor</label>
                <input class="ac-input" id="actor" name="actor" type="search" value="{{ $filters['actor'] ?? '' }}" placeholder="Name">
            </div>
            <div class="ac-field">
                <label for="outcome">Outcome</label>
                <select class="ac-select" id="outcome" name="outcome">
                    <option value="">Any</option>
                    @foreach(['success', 'denied', 'failed'] as $outcome)<option value="{{ $outcome }}" @selected(($filters['outcome'] ?? '') === $outcome)>{{ ucfirst($outcome) }}</option>@endforeach
                </select>
            </div>
            <div class="ac-field" style="min-width:140px"><label for="from">From</label><input class="ac-input" id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>
            <div class="ac-field" style="min-width:140px"><label for="to">To</label><input class="ac-input" id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}"></div>
            <label class="ac-check" style="align-self:center"><input type="checkbox" name="pii" value="1" @checked(!empty($filters['pii']))> Personal data views only</label>
            <button class="ac-btn ac-btn-dark" type="submit" data-loading-text="Loading…">Apply</button>
            @if(array_filter($filters))<a class="ac-btn" href="{{ route('admin.console.audit.index') }}">Clear</a>@endif
        </form>

        @if($logs->isEmpty())
            <div class="ac-empty">
                @if(array_filter($filters))<strong>No entries match these filters</strong>@else<strong>No audit entries in your scope yet</strong>@endif
            </div>
        @else
            <div class="ac-table-wrap"><table class="ac-table">
                <caption class="sr-only">Audit log entries</caption>
                <thead><tr>
                    <th scope="col">When</th><th scope="col">Actor</th><th scope="col">Action</th><th scope="col">Target</th><th scope="col">Scope</th>
                    <th scope="col">Outcome</th><th scope="col">Details</th>
                </tr></thead>
                <tbody>
                @foreach($logs as $log)
                    <tr>
                        <td style="white-space:nowrap"><time datetime="{{ $log->created_at?->toIso8601String() }}">{{ $log->created_at?->format('M j, Y g:i:s A') }}</time></td>
                        <td>{{ $log->user?->name ?? 'System' }}<span class="ac-cell-sub">{{ \App\Enums\AccountType::tryFrom((string) $log->actor_role)?->label() ?? $log->actor_role }}</span></td>
                        <td>{{ str_replace('_', ' ', $log->action) }}@if($log->is_pii_access)<span class="ac-cell-sub"><span class="ac-badge warning">Personal data</span></span>@endif</td>
                        <td>{{ $log->target_entity }}<span class="ac-cell-sub">{{ $log->target_id ? \Illuminate\Support\Str::limit($log->target_id, 13) : '' }}</span></td>
                        <td style="min-width:160px">
                            @if($log->event_id)
                                {{ $eventTitles[$log->event_id] ?? 'Deleted event' }}
                                <span class="ac-cell-sub">{{ $organizerNames[$log->organizer_id] ?? '' }}</span>
                            @elseif($log->organizer_id)
                                {{ $organizerNames[$log->organizer_id] ?? 'Unknown organizer' }}
                            @else
                                <span class="ac-cell-sub" style="display:inline">Platform</span>
                            @endif
                        </td>
                        <td><span class="ac-badge {{ ($log->outcome ?? 'success') === 'success' ? 'success' : 'danger' }}">{{ ucfirst($log->outcome ?? 'success') }}</span></td>
                        <td style="min-width:260px">{{ \Illuminate\Support\Str::limit($redact($log->details), 220) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            @include('admin.partials.pagination', ['paginator' => $logs])
        @endif
    </div>
@endsection
