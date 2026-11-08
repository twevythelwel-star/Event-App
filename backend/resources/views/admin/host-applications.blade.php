@extends('layouts.app')

@section('title', 'Host applications — EventBite')
@section('page-title', 'Host applications')

@section('content')
<div class="eb-stack" style="gap: var(--layout-section-gap);">
    <x-page-header title="Host applications"
                   :badge="$pendingApplications->count() . ' waiting'"
                   description="Approving gives the applicant the host role and an organizer for their events. Every decision is recorded in the audit log."
                   :breadcrumbs="['Admin console' => route('admin.console.overview'), 'Host applications' => null]" />

    @if($pendingApplications->isEmpty())
        <x-empty-state icon="users" title="No applications waiting" description="New host applications appear here for review." />
    @else
        @foreach($pendingApplications as $app)
            @php
                $site = (string) $app->website_or_social;
                // Only real web addresses become links; anything else (including javascript: URLs) is shown as text.
                $siteIsLink = preg_match('#^https?://#i', $site) === 1;
                $idVerification = $app->identityVerification;
                $idStatusLabel = $idVerification?->statusEnum()->label() ?? ucfirst(str_replace('_', ' ', $app->identity_verification_status ?? 'pending_verification'));
            @endphp
            <x-card :title="$app->organization_name" :subtitle="'Submitted ' . $app->created_at->format('F j, Y g:i A T') . ' · ' . ($app->country === 'US' ? 'United States' : $app->country)">
                <dl class="eb-grid-3" style="margin: 0 0 var(--space-4);">
                    <div><dt class="eb-text-label">Applicant</dt><dd style="margin: 0;">{{ $app->user?->name ?? 'Account removed' }}</dd></div>
                    <div><dt class="eb-text-label">Email</dt><dd style="margin: 0;">{{ $app->user?->email ?? '—' }}</dd></div>
                    <div><dt class="eb-text-label">Phone</dt><dd style="margin: 0;">{{ $app->contact_phone }}</dd></div>
                    <div><dt class="eb-text-label">Legal accountable name</dt><dd style="margin: 0;">{{ $app->legal_full_name ?: ($app->user?->name ?? '—') }} ({{ strtoupper($app->business_entity_type ?: 'individual') }})</dd></div>
                    <div><dt class="eb-text-label">Identity &amp; compliance</dt><dd style="margin: 0;">{{ $idStatusLabel }}{{ $app->compliance_acknowledged_at ? ' · 6/6 policies accepted' : '' }}</dd></div>
                    <div><dt class="eb-text-label">Government ID</dt><dd style="margin: 0;">
                        @if($idVerification)
                            @can('viewAny', \App\Models\HostIdentityVerification::class)
                                {{ $idVerification->documentTypeLabel() }}{{ $idVerification->document_last4 ? ' (••' . $idVerification->document_last4 . ')' : '' }} · Scan: {{ strtoupper($idVerification->malware_scan_status) }}
                            @else
                                Submitted. Details are visible only to administrators granted identity document access.
                            @endcan
                        @else
                            {{ $app->government_id_type ? ucfirst(str_replace('_', ' ', $app->government_id_type)) : 'Not uploaded yet' }}
                        @endif
                    </dd></div>
                    <div><dt class="eb-text-label">Permits disclosed</dt><dd style="margin: 0;">Food: {{ $app->food_handler_permit_number ?: 'None listed' }} · Alcohol: {{ $app->alcohol_license_number ?: 'None listed' }}</dd></div>
                    @if($site !== '')
                        <div><dt class="eb-text-label">Website or social</dt><dd style="margin: 0; overflow-wrap: anywhere;">
                            @if($siteIsLink)<a href="{{ $site }}" target="_blank" rel="noopener noreferrer nofollow">{{ $site }}</a>@else{{ $site }}@endif
                        </dd></div>
                    @endif
                </dl>
                @if($app->description)
                    <p class="eb-text-body" style="margin: 0 0 var(--space-4);">{{ $app->description }}</p>
                @endif
                <div class="eb-cluster" style="justify-content: flex-end;">
                    <form action="{{ route('admin.host-applications.reject', $app->id) }}" method="POST" class="eb-cluster" data-reject-form>
                        @csrf
                        <label class="sr-only" for="reason-{{ $app->id }}">Reason for rejecting</label>
                        <input id="reason-{{ $app->id }}" name="reason" class="eb-input" style="min-width: 16rem;" required minlength="5" maxlength="1000"
                               placeholder="Reason (shown to the applicant)">
                        <button type="submit" class="eb-btn eb-btn-destructive eb-btn-sm">Reject</button>
                    </form>
                    <form action="{{ route('admin.host-applications.approve', $app->id) }}" method="POST"
                          onsubmit="return confirm('Approve this applicant as an event host?');">
                        @csrf
                        <button type="submit" class="eb-btn eb-btn-primary eb-btn-sm">Approve</button>
                    </form>
                </div>
            </x-card>
        @endforeach
    @endif

    @if(isset($identityVerifications) && $identityVerifications->isNotEmpty())
        <x-card title="Host Government-Issued ID Verification Desk"
                subtitle="Private encrypted identity submissions. Every document view or status transition is recorded in the PII security audit log.">
            <div class="eb-stack" style="gap: var(--space-4);">
                @foreach($identityVerifications as $verification)
                    <div class="eb-card" style="padding: 1rem; border: 1px solid var(--border-default, #cbd5e1); border-radius: 10px;">
                        <dl class="eb-grid-3" style="margin: 0 0 0.75rem;">
                            <div><dt class="eb-text-label">Host / Applicant</dt><dd style="margin: 0; font-weight: 700;">{{ $verification->legal_full_name }} ({{ $verification->user?->email ?? '—' }})</dd></div>
                            <div><dt class="eb-text-label">Status</dt><dd style="margin: 0;">{{ $verification->statusEnum()->label() }}</dd></div>
                            <div><dt class="eb-text-label">Document</dt><dd style="margin: 0;">{{ $verification->documentTypeLabel() }}{{ $verification->document_last4 ? ' · Ending ' . $verification->document_last4 : '' }}{{ $verification->document_expires_at ? ' · Exp ' . $verification->document_expires_at->format('Y-m-d') : '' }}</dd></div>
                            <div><dt class="eb-text-label">Security &amp; Storage</dt><dd style="margin: 0;">{{ $verification->hasStoredDocument() ? 'Encrypted at rest (private disk)' : 'Purged per retention policy' }} · Scan: {{ strtoupper($verification->malware_scan_status) }}</dd></div>
                            <div><dt class="eb-text-label">Submitted (UTC)</dt><dd style="margin: 0;">{{ $verification->submitted_at_utc?->format('Y-m-d H:i') ?? '—' }}</dd></div>
                            <div><dt class="eb-text-label">Retention until (UTC)</dt><dd style="margin: 0;">{{ $verification->retention_expires_at_utc?->format('Y-m-d') ?? '—' }}</dd></div>
                        </dl>

                        <div class="eb-cluster" style="justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                            @if($verification->hasStoredDocument())
                                @php
                                    $signedDocUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                                        'admin.host-verification.document',
                                        now()->addMinutes(10),
                                        ['verification' => $verification->id, 'admin' => Auth::id()]
                                    );
                                @endphp
                                <a href="{{ $signedDocUrl }}" target="_blank" rel="noopener noreferrer" class="eb-btn eb-btn-secondary eb-btn-sm">
                                    Open temporary signed ID link (10m TTL · PII logged)
                                </a>
                            @else
                                <span class="eb-help-text">Raw ID file purged ({{ $verification->purge_reason ?? 'retention schedule' }}); verification evidence retained.</span>
                            @endif

                            <form action="{{ route('admin.host-verification.status', $verification->id) }}" method="POST" class="eb-cluster" style="gap: 0.5rem; flex-wrap: wrap;">
                                @csrf
                                <select name="status" class="eb-input" style="width: auto;" required>
                                    @foreach(\App\Enums\HostVerificationStatus::cases() as $case)
                                        @if($case !== \App\Enums\HostVerificationStatus::NOT_SUBMITTED)
                                            <option value="{{ $case->value }}" @selected($verification->status === $case->value)>{{ $case->label() }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                <input type="text" name="reason" class="eb-input" style="min-width: 14rem;" placeholder="Reason / compliance notes">
                                <button type="submit" class="eb-btn eb-btn-primary eb-btn-sm">Update ID Status</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif
</div>
@endsection
