@extends('layouts.admin')

@section('title', 'Event evidence to verify')
@section('heading', 'Event Evidence to Verify')
@section('subheading', 'Permits and licences hosts submitted for their events\' requirements checklists.')
@section('breadcrumbs')<span> / <a href="{{ route('admin.console.compliance.index') }}">Compliance database</a></span><span> / Evidence</span>@endsection

@section('content')
    <div class="ac-alert warning" role="note"><div>Verify only what you have checked against the issuing authority's own records or the document. A host's submission alone never verifies an item, and you cannot review your own submission.</div></div>

    <form method="GET" class="ac-filters" style="margin-bottom:12px">
        <div class="ac-field"><label for="evidence-status">Show</label>
            <select id="evidence-status" class="ac-select" name="status" onchange="this.form.submit()">
                <option value="open" @selected($status === 'open')>Waiting for review</option>
                @foreach(\App\Models\EventComplianceEvidence::STATUSES as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach
            </select></div>
        <noscript><button class="ac-btn ac-btn-sm" type="submit">Show</button></noscript>
    </form>

    @forelse($items as $item)
        <article class="ac-card" style="margin-top:10px">
            <div class="ac-card-head">
                <h2 style="font-size:15px">{{ $item->item_title }}</h2>
                <span class="ac-badge {{ ['verified' => 'success', 'rejected' => 'danger', 'pending_verification' => 'info'][$item->status] ?? 'warning' }}">{{ $item->isExpired() && $item->status === 'verified' ? 'Expired' : \App\Models\EventComplianceEvidence::STATUSES[$item->status] }}</span>
            </div>
            <div class="ac-card-body">
                <dl class="ac-dl">
                    <dt>Event</dt><dd>@if($item->event)<a class="ac-link" href="{{ route('admin.console.events.show', $item->event_id) }}">{{ $item->event->title }}</a>@else {{ $item->event_id }} @endif</dd>
                    <dt>Permit or licence</dt><dd>{{ $item->reference_number }}</dd>
                    <dt>Issued by</dt><dd>{{ $item->issuing_authority }}</dd>
                    <dt>Expires</dt><dd>{{ $item->expires_on?->format('j M Y') ?? '—' }}</dd>
                    <dt>Submitted</dt><dd>{{ $item->submitted_at?->format('j M Y H:i') }} by {{ $item->submitter?->name ?? 'a former user' }}</dd>
                    @if($item->note)<dt>Host's note</dt><dd>{{ $item->note }}</dd>@endif
                    <dt>Document</dt><dd>@if($item->document_path)<a class="ac-link" href="{{ route('events.requirements.document', $item) }}">{{ $item->document_name }}</a>@else None attached @endif</dd>
                    @if($item->reviewed_at)<dt>Reviewed</dt><dd>{{ $item->reviewed_at->format('j M Y') }} by {{ $item->reviewer?->name ?? '—' }}@if($item->review_comment): “{{ $item->review_comment }}”@endif</dd>@endif
                </dl>
                @if(in_array($item->status, ['submitted', 'pending_verification'], true))
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-top:12px">
                        @if($item->status === 'submitted')
                            <form method="POST" action="{{ route('admin.console.compliance.evidence.review', [$item, 'start']) }}">@csrf<button class="ac-btn ac-btn-sm" type="submit">Start verification</button></form>
                        @endif
                        <form method="POST" action="{{ route('admin.console.compliance.evidence.review', [$item, 'verify']) }}" style="display:flex;gap:6px;align-items:flex-end">@csrf
                            <div class="ac-field"><label for="verify-{{ $item->id }}">How you checked it (optional)</label><input id="verify-{{ $item->id }}" class="ac-input" name="comment" maxlength="2000"></div>
                            <button class="ac-btn ac-btn-primary ac-btn-sm" type="submit">Verify</button></form>
                        <form method="POST" action="{{ route('admin.console.compliance.evidence.review', [$item, 'reject']) }}" style="display:flex;gap:6px;align-items:flex-end">@csrf
                            <div class="ac-field"><label for="reject-{{ $item->id }}">Reason (shown to the host)</label><input id="reject-{{ $item->id }}" class="ac-input" name="comment" required maxlength="2000"></div>
                            <button class="ac-btn ac-btn-danger ac-btn-sm" type="submit">Reject</button></form>
                    </div>
                @endif
            </div>
        </article>
    @empty
        <p class="ac-empty">Nothing here.</p>
    @endforelse
    @include('admin.partials.pagination', ['paginator' => $items])
@endsection
