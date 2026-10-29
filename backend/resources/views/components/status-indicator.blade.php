@props(['status', 'label' => null])

@php
    $statusNormalized = strtolower(trim((string) $status));

    // Section 2 Rule: Payment status must always have an explicit, unambiguous textual label.
    // Never use green alone to imply that a payment has been independently verified.
    $defaultLabels = [
        'paid' => 'Paid (Verified)',
        'verified' => 'Payment Verified',
        'payment_submitted' => 'Transfer Submitted — Unverified',
        'under_verification' => 'Under Verification',
        'awaiting_payment' => 'Awaiting Payment',
        'partially_paid' => 'Partially Paid',
        'refunded' => 'Refunded',
        'rejected' => 'Payment Rejected',
        'cancelled' => 'Cancelled',
        'collected' => 'Collected',
        'ready_for_pickup' => 'Ready for Pickup',
        'preparing' => 'Preparing in Kitchen',
        'unprepared' => 'Order Queued',
        'active' => 'Active',
        'published' => 'Published',
        'draft' => 'Draft',
        'pending' => 'Pending Review',
        'suspended' => 'Suspended',
    ];

    $badgeClass = match($statusNormalized) {
        'active', 'paid', 'healthy', 'operational', 'verified', 'collected', 'published' => 'eb-badge-success badge-active',
        'suspended', 'rejected', 'failed', 'down', 'cancelled' => 'eb-badge-error badge-suspended',
        'pending', 'awaiting_payment', 'payment_submitted', 'degraded', 'under_verification', 'partially_collected', 'partially_paid', 'preparing' => 'eb-badge-warning badge-pending',
        'refunded', 'draft', 'unprepared' => 'eb-badge-purple badge-scope',
        default => 'eb-badge-info badge-info',
    };

    $displayLabel = $label ?? ($defaultLabels[$statusNormalized] ?? ucfirst(str_replace('_', ' ', $statusNormalized)));
@endphp

<span class="eb-badge badge {{ $badgeClass }}" data-status="{{ $statusNormalized }}">
    <span class="eb-badge-dot" aria-hidden="true"></span>
    <span class="eb-text-status">{{ $displayLabel }}</span>
</span>
