@extends('layouts.admin')

@section('title', 'Declarations: ' . $event->title)
@section('heading', $event->title)
@section('subheading', 'Event Compliance declarations, publication gates and compliance suspension.')
@section('breadcrumbs')<span> / <a href="{{ route('admin.console.compliance-centre.declarations') }}">Declarations</a></span><span> / {{ $event->title }}</span>@endsection

@section('content')
    @include('admin.compliance-centre._tabs', ['current' => 'declarations'])
    @if($errors->any())<div class="ac-alert error" role="alert"><div>{{ $errors->first() }}</div></div>@endif

    @php $ack = $history->firstWhere('superseded_at_utc', null); @endphp
    <div class="ac-grid ac-grid-2">
        <section class="ac-card">
            <div class="ac-card-head"><h2>Current declarations</h2>
                <span class="ac-badge {{ ['current' => 'success', 'outdated' => 'warning', 'missing' => 'danger'][$status] }}">{{ ['current' => 'Current', 'outdated' => 'Out of date: arrangements changed since', 'missing' => 'Missing'][$status] }}</span></div>
            <div class="ac-card-body">
                @if($ack)
                    <dl class="ac-dl">
                        <dt>Jurisdiction</dt><dd>{{ collect([$ack->jurisdiction_municipality, $ack->jurisdiction_county, $ack->jurisdiction_state, $ack->jurisdiction_country])->filter()->implode(', ') }}</dd>
                        <dt>Confirmed</dt><dd>{{ $ack->accepted_at_utc?->format('j M Y H:i') }} UTC by {{ $ack->user?->name ?? 'former user' }} ({{ str_replace('_', ' ', $ack->acknowledgement_context) }})</dd>
                        <dt>Food</dt><dd>{{ $event->food_service_mode ? ucfirst($event->food_service_mode) : 'Not answered' }}{{ $ack->food_permit_status ? ' · permit: ' . str_replace('_', ' ', $ack->food_permit_status) : '' }}</dd>
                        <dt>Alcohol</dt><dd>{{ $event->alcohol_service_mode ? ucfirst($event->alcohol_service_mode) : 'Not answered' }}{{ $ack->alcohol_permit_status ? ' · licence: ' . str_replace('_', ' ', $ack->alcohol_permit_status) : '' }}</dd>
                    </dl>
                    @foreach($declarations as $group => $spec)
                        @php $given = (array) ($ack->{$group . '_declarations'} ?? []); @endphp
                        <h3 style="font-size:13px;margin:12px 0 4px">{{ $spec['title'] }}</h3>
                        <ul style="margin:0;padding-left:18px">
                            @foreach($spec['items'] as $field => $text)
                                @php $entry = $given[$field] ?? null; $ok = is_array($entry) ? !empty($entry['accepted']) : (bool) $entry; @endphp
                                <li>{{ $ok ? '✓' : '✗ Not confirmed:' }} {{ $text }}</li>
                            @endforeach
                        </ul>
                    @endforeach
                @else
                    <p class="ac-empty">The host has not confirmed the Event Compliance declarations for this event.</p>
                @endif
            </div>
        </section>

        <section class="ac-card">
            <div class="ac-card-head"><h2>Publication gates</h2></div>
            <div class="ac-card-body">
                @php $blocking = collect($gates)->where('override', null); @endphp
                @if($blocking->isEmpty())
                    <p>Nothing blocks publication.</p>
                @else
                    <ul style="margin:0;padding-left:18px">@foreach($blocking as $gate)<li>{{ $gate['message'] }}</li>@endforeach</ul>
                @endif
                <p style="margin-top:10px"><a class="ac-link" href="{{ route('admin.console.events.show', $event->id) }}">Event page (overrides and moderation)</a></p>
            </div>
            <div class="ac-card-head"><h2>Compliance suspension</h2></div>
            <div class="ac-card-body">
                @if($event->compliance_suspended_at)
                    <p><span class="ac-badge danger">Suspended</span> since {{ $event->compliance_suspended_at->format('j M Y H:i') }}: {{ $event->compliance_suspension_reason }}</p>
                    <form method="POST" action="{{ route('admin.console.compliance-centre.events.reinstate', $event->id) }}" class="ac-stack" style="display:grid;gap:8px">@csrf
                        <div class="ac-field"><label for="reinstate-reason">Why it can be reinstated</label><input id="reinstate-reason" class="ac-input" name="reason" required minlength="10" maxlength="1000"></div>
                        <p class="ac-hint">Reinstating does not republish the event. The host publishes it again, and every compliance gate runs.</p>
                        <button class="ac-btn ac-btn-sm" type="submit">Reinstate event</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.console.compliance-centre.events.suspend', $event->id) }}" class="ac-stack" style="display:grid;gap:8px">@csrf
                        <div class="ac-field"><label for="suspend-reason">Compliance reason (the host sees it; at least 20 characters)</label><textarea id="suspend-reason" class="ac-input" name="reason" required minlength="20" maxlength="1000" rows="3">{{ old('reason') }}</textarea></div>
                        <label style="display:flex;gap:6px;align-items:flex-start"><input type="checkbox" name="confirm" value="1" required> <span>Unpublish this event, close ordering and stop it being published until it is reinstated.</span></label>
                        <button class="ac-btn ac-btn-danger ac-btn-sm" type="submit">Suspend event</button>
                    </form>
                @endif
            </div>
        </section>
    </div>

    <section class="ac-card" style="margin-top:16px">
        <div class="ac-card-head"><h2>Acknowledgement history</h2></div>
        @if($history->isEmpty())
            <div class="ac-card-body ac-empty">None.</div>
        @else
            <div class="ac-table-wrap"><table class="ac-table">
                <thead><tr><th>Confirmed (UTC)</th><th>By</th><th>Context</th><th>Jurisdiction</th><th>Superseded</th></tr></thead>
                <tbody>@foreach($history as $row)
                    <tr><td>{{ $row->accepted_at_utc?->format('j M Y H:i') }}</td><td>{{ $row->user?->name ?? '—' }}</td><td>{{ str_replace('_', ' ', $row->acknowledgement_context) }}</td>
                        <td>{{ collect([$row->jurisdiction_county, $row->jurisdiction_state])->filter()->implode(', ') }}</td>
                        <td>{{ $row->superseded_at_utc ? $row->superseded_at_utc->format('j M Y') . ': ' . $row->superseded_reason : 'Current' }}</td></tr>
                @endforeach</tbody>
            </table></div>
        @endif
    </section>
@endsection
