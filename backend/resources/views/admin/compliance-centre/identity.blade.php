@extends('layouts.admin')

@section('title', 'Host identity submissions')
@section('heading', 'Host Identity Submissions')
@section('subheading', 'Government ID verifications. Every document you open and every decision is recorded in the PII audit log.')
@section('breadcrumbs')<span> / <a href="{{ route('admin.console.compliance-centre.dashboard') }}">Compliance dashboard</a></span><span> / Identity</span>@endsection

@section('content')
    @include('admin.compliance-centre._tabs', ['current' => 'identity'])
    <div class="ac-alert warning" role="note"><div>You can see this because you were specifically granted access to host identity documents. Open a document only to verify it. You cannot review your own submission.</div></div>
    @if($errors->any())<div class="ac-alert error" role="alert"><div>{{ $errors->first() }}</div></div>@endif
    @if(session('status'))<div class="ac-alert success" role="status"><div>{{ session('status') }}</div></div>@endif

    <form method="GET" class="ac-filters" style="margin-bottom:12px">
        <div class="ac-field"><label for="id-status">Show</label>
            <select id="id-status" class="ac-select" name="status" onchange="this.form.submit()">
                <option value="open" @selected($status === 'open')>Waiting for a decision</option>
                <option value="all" @selected($status === 'all')>All</option>
                @foreach(\App\Enums\HostVerificationStatus::cases() as $case)<option value="{{ $case->value }}" @selected($status === $case->value)>{{ $case->label() }}</option>@endforeach
            </select></div>
        <div class="ac-field"><label for="id-q">Host name or email</label><input id="id-q" class="ac-input" name="q" value="{{ $search }}"></div>
        <button class="ac-btn ac-btn-sm" type="submit">Search</button>
    </form>

    @forelse($items as $verification)
        @php $tone = ['approved' => 'success', 'rejected' => 'danger', 'suspended' => 'danger', 'expired' => 'danger', 'additional_information_required' => 'warning'][$verification->status] ?? 'info'; @endphp
        <article class="ac-card" style="margin-top:10px">
            <div class="ac-card-head">
                <h2 style="font-size:15px">{{ $verification->user?->name ?? 'Former user' }} <span class="ac-hint">{{ $verification->user?->email }}</span></h2>
                <span class="ac-badge {{ $tone }}">{{ $verification->statusLabel() }}</span>
            </div>
            <div class="ac-card-body">
                <dl class="ac-dl">
                    <dt>Legal name on ID</dt><dd>{{ $verification->legal_full_name }}</dd>
                    <dt>Document</dt><dd>{{ $verification->documentTypeLabel() }}{{ $verification->document_last4 ? ' · ending ' . $verification->document_last4 : '' }}{{ $verification->issuing_country ? ' · ' . $verification->issuing_country : '' }}</dd>
                    <dt>Document expires</dt><dd>{{ $verification->document_expires_at?->format('j M Y') ?? 'Not given' }}@if($verification->isDocumentExpired()) <span class="ac-badge danger">Expired</span>@endif</dd>
                    <dt>Submitted</dt><dd>{{ $verification->submitted_at_utc?->format('j M Y H:i') ?? '—' }} UTC · malware scan: {{ $verification->malware_scan_status }}</dd>
                    @if($verification->reviewed_at_utc)<dt>Last decision</dt><dd>{{ $verification->reviewed_at_utc->format('j M Y') }} by {{ $verification->reviewer?->name ?? '—' }}@if($verification->rejection_or_info_reason): “{{ $verification->rejection_or_info_reason }}”@endif</dd>@endif
                </dl>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-top:12px">
                    @can('viewDocument', $verification)
                        @if($verification->hasStoredDocument())
                            <form method="POST" action="{{ route('admin.console.compliance-centre.identity.document', $verification) }}" target="_blank">@csrf
                                <button class="ac-btn ac-btn-sm" type="submit">Open document (logged)</button></form>
                        @else
                            <span class="ac-hint">No stored document (purged or never uploaded).</span>
                        @endif
                    @endcan
                    @can('review', $verification)
                        <form method="POST" action="{{ route('admin.host-verification.status', $verification->id) }}" style="display:flex;gap:6px;align-items:flex-end;flex-wrap:wrap">@csrf
                            <div class="ac-field"><label for="decision-{{ $verification->id }}">Decision</label>
                                <select id="decision-{{ $verification->id }}" class="ac-select" name="status" required>
                                    @foreach(['under_review' => 'Start review', 'approved' => 'Approve', 'additional_information_required' => 'Request additional documents', 'rejected' => 'Reject', 'reverification_required' => 'Require re-verification', 'suspended' => 'Suspend'] as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select></div>
                            <div class="ac-field"><label for="reason-{{ $verification->id }}">Reason (shown to the host; needed unless approving)</label><input id="reason-{{ $verification->id }}" class="ac-input" name="reason" maxlength="1000"></div>
                            <button class="ac-btn ac-btn-primary ac-btn-sm" type="submit">Record decision</button>
                        </form>
                    @else
                        <span class="ac-hint">This is your own submission: another administrator must review it.</span>
                    @endcan
                </div>
            </div>
        </article>
    @empty
        <p class="ac-empty">Nothing here.</p>
    @endforelse
    @include('admin.partials.pagination', ['paginator' => $items])
@endsection
