{{--
    One authority or requirement record: what it says, where it comes from, its review state, the edit form and
    the workflow actions. Expects $record, $type ('authority'|'requirement'), $canEdit, $canApprove, $citableAuthorities.
--}}
@php
    $isAuthority = $type === 'authority';
    $title = $isAuthority ? $record->name : $record->topicLabel();
    $errorKey = "record-{$type}-{$record->id}";
    $official = fn ($url) => \App\Services\Compliance\ComplianceReviewWorkflow::isOfficialUrl($url);
@endphp
<article class="ac-card" id="{{ $type }}-{{ $record->id }}" style="margin-top:12px">
    <div class="ac-card-head">
        <h2 style="font-size:15px">{{ $title }}</h2>
        @include('admin.compliance._status', ['status' => $record->status])
    </div>
    <div class="ac-card-body">
        @error($errorKey)<div class="ac-alert error" role="alert">{{ $message }}</div>@enderror

        <dl class="ac-dl">
            @if($isAuthority)
                <dt>Area</dt><dd>{{ \App\Models\Compliance\ComplianceAuthority::AREAS[$record->area] ?? $record->area }}</dd>
                <dt>Role</dt><dd>{{ $record->role }}</dd>
                <dt>Agency website</dt><dd>@if($record->website_url)<a class="ac-link" href="{{ $record->website_url }}" target="_blank" rel="noopener noreferrer">{{ $record->website_url }}</a>@if(!$official($record->website_url)) <span class="ac-badge warning">Not a .gov/.us https link</span>@endif @else <span class="ac-badge danger">Verification Required</span>@endif</dd>
                <dt>Legislation or regulations</dt><dd>@if($record->legislation_url)<a class="ac-link" href="{{ $record->legislation_url }}" target="_blank" rel="noopener noreferrer">{{ $record->legislation_title ?: $record->legislation_url }}</a>@else <span class="ac-badge danger">Verification Required</span>@endif</dd>
            @else
                <dt>Requirement</dt><dd>@if($record->summary && $record->status !== 'verification_required'){{ $record->summary }}@if(!$record->isVerified()) <span class="ac-hint">(not verified yet)</span>@endif @else <span class="ac-badge danger">Verification Required</span>@endif</dd>
                @if($record->value)<dt>Value</dt><dd>{{ $record->value }}</dd>@endif
                @if($record->conditions)<dt>Restrictions and conditions</dt><dd>{{ $record->conditions }}</dd>@endif
                @if($record->required_documents)<dt>Required documentation</dt><dd>{{ $record->required_documents }}</dd>@endif
                <dt>Authority</dt><dd>{{ $record->authority?->name ?? '—' }}</dd>
                <dt>Legislation or regulation</dt><dd>{{ $record->legislation_citation ?: '—' }}</dd>
            @endif
            <dt>Official source</dt><dd>@if($record->source_url)<a class="ac-link" href="{{ $record->source_url }}" target="_blank" rel="noopener noreferrer">{{ $record->source_title ?: $record->source_url }}</a>@else — @endif</dd>
            <dt>Effective date</dt><dd>{{ $record->effective_date?->format('j M Y') ?? '—' }}</dd>
            <dt>Last verified</dt><dd>{{ $record->last_verified_date?->format('j M Y') ?? '—' }}</dd>
            <dt>Next review</dt><dd>{{ $record->next_review_date?->format('j M Y') ?? '—' }}</dd>
            @if($record->approved_at)<dt>Approved</dt><dd>{{ $record->approved_at->format('j M Y') }} by {{ $record->approver?->name ?? 'a former administrator' }}</dd>@endif
            @if($record->research_notes)<dt>Research notes</dt><dd>{{ $record->research_notes }}</dd>@endif
            @if($record->admin_notes)<dt>Administrator notes</dt><dd>{{ $record->admin_notes }}</dd>@endif
        </dl>

        @if($canEdit || $canApprove)
            <div class="ac-form-actions" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
                @if($canEdit && in_array($record->status, ['draft', 'changes_requested', 'review_due'], true))
                    <form method="POST" action="{{ route('admin.console.compliance.records.transition', [$type, $record->id, 'submit']) }}">@csrf
                        <button class="ac-btn ac-btn-primary ac-btn-sm" type="submit">Submit for review</button></form>
                @endif
                @if($canApprove && $record->status === 'pending_review')
                    <form method="POST" action="{{ route('admin.console.compliance.records.transition', [$type, $record->id, 'approve']) }}">@csrf
                        <button class="ac-btn ac-btn-primary ac-btn-sm" type="submit">Approve as verified</button></form>
                    <form method="POST" action="{{ route('admin.console.compliance.records.transition', [$type, $record->id, 'request-changes']) }}" style="display:flex;gap:6px;align-items:flex-end">@csrf
                        <div class="ac-field"><label for="changes-{{ $type }}-{{ $record->id }}">What needs to change</label>
                            <input id="changes-{{ $type }}-{{ $record->id }}" class="ac-input" name="comment" required maxlength="2000"></div>
                        <button class="ac-btn ac-btn-sm" type="submit">Send back</button></form>
                @endif
                @if($canEdit && in_array($record->status, ['verified', 'pending_review', 'review_due'], true))
                    <form method="POST" action="{{ route('admin.console.compliance.records.transition', [$type, $record->id, 'reset']) }}" style="display:flex;gap:6px;align-items:flex-end">@csrf
                        <div class="ac-field"><label for="reset-{{ $type }}-{{ $record->id }}">Reason</label>
                            <input id="reset-{{ $type }}-{{ $record->id }}" class="ac-input" name="comment" required maxlength="2000"></div>
                        <button class="ac-btn ac-btn-danger ac-btn-sm" type="submit">Mark Verification Required</button></form>
                @endif
            </div>
        @endif

        @if($canEdit)
            <details style="margin-top:12px">
                <summary class="ac-link">Edit this record</summary>
                <form method="POST" action="{{ route('admin.console.compliance.records.update', [$type, $record->id]) }}" style="margin-top:10px">
                    @csrf @method('PATCH')
                    @include('admin.compliance._fields', ['record' => $record, 'type' => $type, 'prefix' => "{$type}-{$record->id}", 'citableAuthorities' => $citableAuthorities])
                    <p class="ac-hint">Changing what the record says, or its source or effective date, returns it to draft: it must be submitted and approved again.</p>
                    <button class="ac-btn ac-btn-primary ac-btn-sm" type="submit">Save</button>
                </form>
            </details>
        @endif
    </div>
</article>
