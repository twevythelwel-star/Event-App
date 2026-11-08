@extends('layouts.admin')

@section('title', 'Event compliance declarations')
@section('heading', 'Event Compliance Declarations')
@section('subheading', 'Each current event\'s Event Compliance acknowledgement: the jurisdiction and the 15 food, alcohol and legal declarations its host confirmed.')
@section('breadcrumbs')<span> / <a href="{{ route('admin.console.compliance-centre.dashboard') }}">Compliance dashboard</a></span><span> / Declarations</span>@endsection

@section('content')
    @include('admin.compliance-centre._tabs', ['current' => 'declarations'])

    <form method="GET" class="ac-filters" style="margin-bottom:12px">
        <div class="ac-field"><label for="d-show">Show</label>
            <select id="d-show" class="ac-select" name="show" onchange="this.form.submit()">
                @foreach(['attention' => 'Missing or out of date', 'suspended' => 'Suspended events', 'all' => 'All current events'] as $value => $label)<option value="{{ $value }}" @selected($show === $value)>{{ $label }}</option>@endforeach
            </select></div>
        <div class="ac-field"><label for="d-state">State</label>
            <select id="d-state" class="ac-select" name="state" onchange="this.form.submit()">
                <option value="">All states</option>
                @foreach($states as $s)<option value="{{ $s->code }}" @selected($stateFilter === $s->code)>{{ $s->name }}</option>@endforeach
            </select></div>
        <div class="ac-field"><label for="d-county">County contains</label><input id="d-county" class="ac-input" name="county" value="{{ $county }}"></div>
        <button class="ac-btn ac-btn-sm" type="submit">Filter</button>
    </form>

    <section class="ac-card">
        @if($rows->isEmpty())
            <div class="ac-card-body ac-empty">No events match.</div>
        @else
            <div class="ac-table-wrap"><table class="ac-table">
                <thead><tr><th>Event</th><th>Jurisdiction</th><th>Event status</th><th>Declarations</th><th>Confirmed</th><th></th></tr></thead>
                <tbody>@foreach($rows as $row)
                    @php $event = $row['event']; $ack = $row['acknowledgement']; @endphp
                    <tr>
                        <td>{{ $event->title }}<div class="ac-hint">{{ $event->date_str }}</div></td>
                        <td>{{ collect([$ack?->jurisdiction_municipality, $ack?->jurisdiction_county ?? $event->compliance_jurisdiction_county, $event->state_code])->filter()->implode(', ') ?: '—' }}</td>
                        <td>{{ ucfirst($event->status) }}@if($event->compliance_suspended_at) <span class="ac-badge danger">Suspended</span>@endif</td>
                        <td><span class="ac-badge {{ ['current' => 'success', 'outdated' => 'warning', 'missing' => 'danger'][$row['state']] }}">{{ ['current' => 'Current', 'outdated' => 'Out of date', 'missing' => 'Missing'][$row['state']] }}</span></td>
                        <td>{{ $ack?->accepted_at_utc?->format('j M Y') ?? '—' }}</td>
                        <td><a class="ac-link" href="{{ route('admin.console.compliance-centre.declaration', $event->id) }}">Review</a></td>
                    </tr>
                @endforeach</tbody>
            </table></div>
        @endif
    </section>
@endsection
