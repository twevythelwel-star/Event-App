@props(['status', 'label' => null])

@php
    $statusNormalized = strtolower(trim((string) $status));

    // Payment states use the single platform wording (Pending, Confirmation submitted, Verified,
    // Rejected, Refunded) from PaymentStatusPresenter, shared with the admin console. The colour
    // is never the only signal: every badge carries its text label.
    if (\App\Support\PaymentStatusPresenter::isPaymentStatus($statusNormalized)) {
        $displayLabel = $label ?? \App\Support\PaymentStatusPresenter::label($statusNormalized);
        $badgeClass = match (\App\Support\PaymentStatusPresenter::tone($statusNormalized)) {
            'success' => 'eb-badge-success badge-active',
            'warning' => 'eb-badge-warning badge-pending',
            'danger' => 'eb-badge-error badge-suspended',
            'info' => 'eb-badge-purple badge-scope',
            default => 'eb-badge-info badge-info',
        };
    } else {
        $otherLabels = [
            'verified' => 'Payment Verified',
            'collected' => 'Collected',
            'ready_for_pickup' => 'Ready for Pickup',
            'preparing' => 'Preparing in Kitchen',
            'unprepared' => 'Order Queued',
            'active' => 'Active',
            'published' => 'Published',
            'unpublished' => 'Unpublished',
            'draft' => 'Draft',
            'pending' => 'Pending Review',
            'pending_verification' => 'Pending Verification',
            'suspended' => 'Suspended',
        ];

        $badgeClass = match ($statusNormalized) {
            'active', 'healthy', 'operational', 'verified', 'collected', 'published' => 'eb-badge-success badge-active',
            'suspended', 'failed', 'down' => 'eb-badge-error badge-suspended',
            'pending', 'degraded', 'partially_collected', 'preparing', 'pending_verification', 'unpublished' => 'eb-badge-warning badge-pending',
            'draft', 'unprepared' => 'eb-badge-purple badge-scope',
            default => 'eb-badge-info badge-info',
        };

        $displayLabel = $label ?? ($otherLabels[$statusNormalized] ?? ucfirst(str_replace('_', ' ', $statusNormalized)));
    }
@endphp

{{-- Plain element (no $attributes): several pages render this with @include rather than <x-status-indicator>. --}}
<span class="eb-badge badge {{ $badgeClass }}" data-status="{{ $statusNormalized }}">
    <span class="eb-badge-dot" aria-hidden="true"></span>
    <span class="eb-text-status">{{ $displayLabel }}</span>
</span>
