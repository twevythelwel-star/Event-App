@props(['status', 'label' => null])

@php
    $statusNormalized = strtolower((string) $status);
    $badgeClass = match($statusNormalized) {
        'active', 'paid', 'healthy', 'operational', 'verified', 'collected' => 'badge-active',
        'suspended', 'rejected', 'failed', 'down', 'cancelled' => 'badge-suspended',
        'pending', 'awaiting_payment', 'payment_submitted', 'degraded', 'under_verification', 'partially_collected' => 'badge-pending',
        default => 'badge-info',
    };
    $displayLabel = $label ?? ucfirst(str_replace('_', ' ', $statusNormalized));
@endphp

<span class="badge {{ $badgeClass }}">
    <span style="font-size: 0.55rem; line-height: 1;">●</span>
    <span>{{ $displayLabel }}</span>
</span>
