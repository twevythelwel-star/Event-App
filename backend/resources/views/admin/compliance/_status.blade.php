{{-- A compliance record's review status. "Verification Required" is never shown as anything softer. --}}
@php
    $tone = match ($status) {
        'verified' => 'success',
        'pending_review' => 'info',
        'review_due', 'changes_requested' => 'warning',
        'verification_required' => 'danger',
        default => '',
    };
@endphp
<span class="ac-badge {{ $tone }}">{{ \App\Models\Compliance\ComplianceRequirement::STATUSES[$status] ?? $status }}</span>
