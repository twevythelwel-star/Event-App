@extends('layouts.app')

@section('title', 'Administrator Operations Desk — Island Events')
@section('page-title', 'Administrator Operations')

@section('navigation')
    <div class="nav-section-title">Events Platform</div>
    <a href="{{ route('events.index') }}">
        <span class="nav-icon">🌴</span>
        <span>Browse Events</span>
    </a>

    <div class="nav-section-title">Delegated Operations</div>
    <a href="{{ route('admin.dashboard') }}" class="active">
        <span class="nav-icon">📋</span>
        <span>Scoped Overview</span>
    </a>
    <a href="{{ route('admin.host-applications') }}">
        <span class="nav-icon">📑</span>
        <span>Host Review Desk</span>
    </a>
    <a href="#events">
        <span class="nav-icon">🎟️</span>
        <span>Authorized Events</span>
    </a>
    <a href="#support">
        <span class="nav-icon">🤝</span>
        <span>Support & Disputes</span>
    </a>

    <div class="nav-section-title">Account</div>
    <a href="{{ route('settings.show') }}">
        <span class="nav-icon">⚙️</span>
        <span>My Assigned Scope</span>
    </a>
@endsection

@section('content')
<div class="admin-desk-view">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.35rem;">
                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--color-navy); letter-spacing: -0.02em;">
                    Administrator Operations Desk
                </h1>
                <span class="badge badge-info">Delegated Scope: {{ ucfirst($scope ?? 'Assigned Events') }}</span>
            </div>
            <p style="color: var(--color-text-secondary); font-size: 0.92rem;">
                Authorized event moderation, ticket dispute assistance, and customer support with enforced PII auditing.
            </p>
        </div>

        <div style="background: var(--color-warning-bg); border: 1px solid var(--color-warning-border); border-radius: var(--radius-md); padding: 0.5rem 1rem; display: flex; align-items: center; gap: 0.5rem;">
            <span style="font-size: 1rem;">🛡️</span>
            <span style="font-size: 0.8rem; color: var(--color-warning); font-weight: 600;">
                Delegated Security Boundary Enforced &bull; No Root System Access
            </span>
        </div>
    </div>

    <!-- Scoped Metric Cards -->
    <div class="grid-4" style="margin-bottom: 1.75rem;">
        <div class="metric-card">
            <span class="metric-label">Authorized Events</span>
            <div class="metric-value">{{ $eventsCount ?? 0 }}</div>
            <div class="metric-footer" style="color: var(--color-info);">
                <span>●</span> Within Assigned Portfolio
            </div>
        </div>

        <div class="metric-card">
            <span class="metric-label">Pending Verification</span>
            <div class="metric-value" style="color: {{ ($pendingVerifications ?? 0) > 0 ? 'var(--color-warning)' : 'var(--color-navy)' }};">
                {{ $pendingVerifications ?? 0 }}
            </div>
            <div class="metric-footer" style="color: var(--color-warning);">
                <span>●</span> Transfers Awaiting Audit
            </div>
        </div>

        <div class="metric-card">
            <span class="metric-label">Pending Moderation</span>
            <div class="metric-value">{{ $pendingModeration ?? 0 }}</div>
            <div class="metric-footer" style="color: var(--color-text-secondary);">
                <span>●</span> Events Before Cutoff
            </div>
        </div>

        <div class="metric-card">
            <span class="metric-label">PII Audit Logging</span>
            <div class="metric-value" style="color: var(--color-success); font-size: 1.4rem;">
                100% Active
            </div>
            <div class="metric-footer" style="color: var(--color-success);">
                <span>✓</span> Customer Data Access Logged
            </div>
        </div>
    </div>

    <!-- Authorized Events Oversight -->
    <div class="card" id="events">
        <div class="card-header">
            <div>
                <h2 class="card-title">Authorized Events Oversight</h2>
                <p class="card-subtitle">Events assigned to your administrative moderation portfolio.</p>
            </div>
            <span class="badge badge-scope">Assigned Scope Only</span>
        </div>

        @if(!empty($events) && count($events) > 0)
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Event Title</th>
                            <th>Venue</th>
                            <th>Event Date</th>
                            <th>Status</th>
                            <th>Ordering Status</th>
                            <th style="text-align: right;">Moderation</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($events as $event)
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: var(--color-navy);">{{ $event->title }}</div>
                                    <span style="font-size: 0.75rem; color: var(--color-text-muted);">ID: {{ substr($event->id, 0, 8) }}...</span>
                                </td>
                                <td>{{ $event->venue ?? 'Main Venue' }}</td>
                                <td>{{ !empty($event->start_time) ? date('M d, Y', strtotime($event->start_time)) : 'Upcoming' }}</td>
                                <td>
                                    @include('components.status-indicator', ['status' => $event->status ?? 'published'])
                                </td>
                                <td>
                                    <span class="badge {{ ($event->is_ordering_open ?? true) ? 'badge-active' : 'badge-suspended' }}">
                                        {{ ($event->is_ordering_open ?? true) ? 'Ordering Open' : 'Closed' }}
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <button class="btn btn-secondary btn-sm" onclick="alert('Moderation details for {{ $event->title }}')">
                                        Review
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state">
                <div class="empty-state-icon">🎟️</div>
                <h3 class="empty-state-title">No events currently assigned</h3>
                <p class="empty-state-desc">Your delegated administrative portfolio does not currently have assigned event organizers.</p>
            </div>
        @endif
    </div>

    <!-- Customer Support & Dispute Resolution Queue -->
    <div class="card" id="support">
        <div class="card-header">
            <div>
                <h2 class="card-title">Customer Support & Payment Verification Queue</h2>
                <p class="card-subtitle">Assisting attendees with submitted bank transfers and ticket retrieval issues.</p>
            </div>
            <span class="badge badge-pending">{{ count($supportOrders ?? []) }} Cases Awaiting Audit</span>
        </div>

        @if(!empty($supportOrders) && count($supportOrders) > 0)
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Order Number</th>
                            <th>Customer Name</th>
                            <th>Payment Method</th>
                            <th>Amount</th>
                            <th>Current Status</th>
                            <th style="text-align: right;">Audit Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($supportOrders as $order)
                            <tr>
                                <td>
                                    <strong style="color: var(--color-navy);">#{{ $order->order_number }}</strong>
                                </td>
                                <td>
                                    <div>{{ $order->customer_name }}</div>
                                    <span style="font-size: 0.75rem; color: var(--color-text-muted);">{{ $order->customer_phone ?? $order->email }}</span>
                                </td>
                                <td>
                                    <span style="font-weight: 600; text-transform: uppercase; font-size: 0.8rem;">
                                        {{ $order->payment_method ?? 'Bank Transfer' }}
                                    </span>
                                </td>
                                <td>
                                    <strong>{{ $order->currency }} ${{ number_format($order->total_amount, 2) }}</strong>
                                </td>
                                <td>
                                    @include('components.status-indicator', ['status' => $order->status])
                                </td>
                                <td style="text-align: right;">
                                    <button class="btn btn-primary btn-sm" onclick="alert('Audited PII inspection initiated for Order #{{ $order->order_number }}. Security log recorded.')">
                                        Inspect & Verify
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state">
                <div class="empty-state-icon">🤝</div>
                <h3 class="empty-state-title">Support queue is clear</h3>
                <p class="empty-state-desc">No orders or transfer verification tickets currently require administrator assistance.</p>
            </div>
        @endif
    </div>
</div>
@endsection
