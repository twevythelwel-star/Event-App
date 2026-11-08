@extends('layouts.admin')

@section('title', 'Hosts')
@section('heading', 'Host Compliance and Publishing Privileges')
@section('subheading', 'Each event host\'s identity verification, outstanding acknowledgements and publishing status.')
@section('breadcrumbs')<span> / <a href="{{ route('admin.console.compliance-centre.dashboard') }}">Compliance dashboard</a></span><span> / Hosts</span>@endsection

@section('content')
    @include('admin.compliance-centre._tabs', ['current' => 'hosts'])
    @if($errors->any())<div class="ac-alert error" role="alert"><div>{{ $errors->first() }}</div></div>@endif

    <form method="GET" class="ac-filters" style="margin-bottom:12px">
        <div class="ac-field"><label for="h-q">Name or email</label><input id="h-q" class="ac-input" name="q" value="{{ $search }}"></div>
        <div class="ac-field"><label for="h-show">Show</label>
            <select id="h-show" class="ac-select" name="show">@foreach(['all' => 'All hosts', 'suspended' => 'Publishing suspended'] as $v => $l)<option value="{{ $v }}" @selected($show === $v)>{{ $l }}</option>@endforeach</select></div>
        <button class="ac-btn ac-btn-sm" type="submit">Search</button>
    </form>

    @forelse($rows as $row)
        @php $host = $row['host']; $suspension = $row['suspension']; @endphp
        <article class="ac-card" style="margin-top:10px">
            <div class="ac-card-head">
                <h2 style="font-size:15px">{{ $host->name }} <span class="ac-hint">{{ $host->email }}</span></h2>
                <span class="ac-badge {{ $suspension ? 'danger' : 'success' }}">{{ $suspension ? 'Publishing suspended' : 'May publish' }}</span>
            </div>
            <div class="ac-card-body">
                <dl class="ac-dl">
                    <dt>Identity</dt><dd><span class="ac-badge {{ $row['identity'] ? 'success' : 'danger' }}">{{ $row['identity'] ? 'Verified' : 'Not verified' }}</span></dd>
                    <dt>Host acknowledgements</dt><dd>{{ $row['missing_policies'] === [] ? 'All accepted' : 'Not accepted: ' . implode('; ', $row['missing_policies']) }}</dd>
                    @if($suspension)
                        <dt>Suspended</dt><dd>{{ $suspension->suspended_at->format('j M Y') }} by {{ $suspension->suspender?->name ?? '—' }}: {{ $suspension->reason }}{{ $suspension->review_by ? ' · review by ' . $suspension->review_by->format('j M Y') : '' }}</dd>
                    @endif
                </dl>
                @can(\App\Support\AdminAuthorization::COMPLIANCE_SUSPEND_HOST, $host)
                    @if($suspension)
                        <form method="POST" action="{{ route('admin.console.compliance-centre.hosts.reinstate', $suspension) }}" style="display:flex;gap:6px;flex-wrap:wrap;align-items:flex-end;margin-top:12px">@csrf
                            <div class="ac-field" style="flex:1;min-width:240px"><label for="lift-{{ $host->id }}">Why publishing can be restored</label><input id="lift-{{ $host->id }}" class="ac-input" name="reason" required minlength="10" maxlength="1000"></div>
                            <button class="ac-btn ac-btn-sm" type="submit">Restore publishing</button>
                        </form>
                    @else
                        <details style="margin-top:12px"><summary>Suspend publishing privileges</summary>
                            <form method="POST" action="{{ route('admin.console.compliance-centre.hosts.suspend', $host) }}" class="ac-stack" style="display:grid;gap:8px;margin-top:8px">@csrf
                                <div class="ac-field"><label for="sus-{{ $host->id }}">Compliance reason (the host sees it; at least 20 characters)</label><textarea id="sus-{{ $host->id }}" class="ac-input" name="reason" rows="2" required minlength="20" maxlength="1000"></textarea></div>
                                <div class="ac-field"><label for="rev-{{ $host->id }}">Review by (optional)</label><input id="rev-{{ $host->id }}" class="ac-input" type="date" name="review_by"></div>
                                <label style="display:flex;gap:6px"><input type="checkbox" name="unpublish" value="1"> <span>Also unpublish their published events and close ordering</span></label>
                                <label style="display:flex;gap:6px"><input type="checkbox" name="confirm" value="1" required> <span>I confirm this host may not publish events until the suspension is lifted.</span></label>
                                <button class="ac-btn ac-btn-danger ac-btn-sm" type="submit">Suspend publishing</button>
                            </form>
                        </details>
                    @endif
                @endcan
            </div>
        </article>
    @empty
        <p class="ac-empty">No hosts match.</p>
    @endforelse
    @include('admin.partials.pagination', ['paginator' => $hosts])
@endsection
