@extends('layouts.app')

@section('title', 'My Digital Tickets & Orders — EventBite')
@section('page-title', 'My Ticket Wallet')

@section('content')
<div class="attendee-wallet-view">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.35rem;">
                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--color-navy); letter-spacing: -0.02em;">
                    Digital Ticket Wallet
                </h1>
                <span class="badge badge-scope">Attendee</span>
            </div>
            <p style="color: var(--color-text-secondary); font-size: 0.92rem;">
                Official QR entry passes, advance meal collection vouchers, and payment receipts.
            </p>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; background: var(--color-white); border: 1px solid var(--color-border); padding: 0.5rem 1rem; border-radius: var(--radius-md);">
            <span style="font-size: 0.8rem; color: var(--color-text-secondary);">Preferred Currency:</span>
            <span style="font-weight: 700; color: var(--color-indigo); font-size: 0.88rem;">{{ $preferred_display_currency ?? Auth::user()->preferred_display_currency ?? 'USD' }}</span>
            <a href="{{ route('settings.show') }}#currency" style="font-size: 0.75rem; color: var(--color-text-muted); text-decoration: underline;">Change</a>
        </div>
    </div>

    <!-- Event Host Status & Privileges Card -->
    @if(Auth::user()->hasRole(\App\Enums\AccountType::EVENT_HOST))
        <div class="card" style="margin-bottom: 1.5rem; background: linear-gradient(135deg, rgba(16, 185, 129, 0.08) 0%, rgba(5, 150, 105, 0.04) 100%); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: var(--radius-lg); padding: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <span class="badge" style="background: #10b981; color: #fff; font-weight: 700; font-size: 0.75rem;">Approved Event Host</span>
                    <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--color-navy); margin-top: 0.35rem;">Host Command Center Available</h3>
                    <p style="font-size: 0.85rem; color: var(--color-text-secondary); margin-top: 0.2rem;">You hold active Event Host privileges to create events, upload flyers, and manage ticketing.</p>
                </div>
                <form action="{{ route('context.switch') }}" method="POST">
                    @csrf
                    <input type="hidden" name="target_role" value="event_host">
                    <button type="submit" class="btn btn-primary" style="background: #059669; border-color: #059669; font-weight: 700;">
                        Switch to Host Dashboard →
                    </button>
                </form>
            </div>
        </div>
    @elseif(!empty($hostApplication) && $hostApplication->isPending())
        <div class="card" style="margin-bottom: 1.5rem; background: rgba(245, 158, 11, 0.06); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: var(--radius-lg); padding: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="font-size: 1.5rem;">⏳</div>
                <div>
                    <span class="badge" style="background: #f59e0b; color: #fff; font-weight: 700; font-size: 0.75rem;">Application Under Review</span>
                    <h3 style="font-size: 1.05rem; font-weight: 700; color: #78350f; margin-top: 0.25rem;">Event Host Request for "{{ $hostApplication->organization_name }}"</h3>
                    <p style="font-size: 0.82rem; color: #92400e; margin-top: 0.15rem;">Submitted on {{ $hostApplication->created_at->format('M j, Y') }}. Our operations desk is reviewing your request.</p>
                </div>
            </div>
        </div>
    @elseif(!Auth::user()->hasRole(\App\Enums\AccountType::EVENT_HOST))
        <div class="card" style="margin-bottom: 1.5rem; background: var(--color-white); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--color-navy);">Want to organize events on EventBite?</h3>
                    <p style="font-size: 0.85rem; color: var(--color-text-secondary); margin-top: 0.2rem;">Apply for Event Host privileges to publish flyers, sell digital tickets, and accept advance food orders.</p>
                </div>
                <a href="{{ route('host.apply') }}" class="btn btn-secondary" style="font-weight: 700;">
                    Apply for Host Privileges
                </a>
            </div>
        </div>
    @endif

    <!-- Event Staff Access & One-Time Claim Code Section -->
    <div class="card" id="staff-access" style="border-left: 4px solid var(--color-indigo);">
        <div class="card-header">
            <div>
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                    <span class="badge" style="background: var(--color-indigo-soft); color: var(--color-indigo); font-weight: 700;">Event Staff Access</span>
                    <span style="font-size: 0.75rem; color: var(--color-text-muted);">Privacy-Preserving Staff Assignment</span>
                </div>
                <h2 class="card-title">Event Worker Positions & Assignment Codes</h2>
                <p class="card-subtitle">Generate a temporary, single-use 10-minute code to allow an Event Host to assign your account as an event worker.</p>
            </div>
            @if(!empty($pendingAssignments) && $pendingAssignments->count() > 0)
                <span class="badge badge-pending">{{ $pendingAssignments->count() }} Action Required</span>
            @endif
        </div>

        <!-- Pending Assignment Invitations Notification -->
        @if(!empty($pendingAssignments) && $pendingAssignments->count() > 0)
            <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1.5rem;">
                <h3 style="font-size: 0.95rem; font-weight: 700; color: #92400e; margin-bottom: 0.75rem;">
                    Pending Staff Assignment Offers
                </h3>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    @foreach($pendingAssignments as $invitation)
                        <div style="background: #ffffff; border: 1px solid #fef3c7; border-radius: var(--radius-md); padding: 1rem; display: flex; flex-direction: column; gap: 0.75rem;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.5rem;">
                                <div>
                                    <div style="font-weight: 800; font-size: 1rem; color: var(--color-navy);">{{ $invitation->event?->title ?? 'Event' }}</div>
                                    <div style="font-size: 0.82rem; color: var(--color-text-secondary); margin-top: 0.2rem;">
                                        Position: <strong style="color: var(--color-indigo);">{{ $invitation->worker_role->label() }}</strong>
                                        &bull; Station: {{ str_replace('_', ' ', $invitation->station_assignment ?? 'all') }}
                                        &bull; Host: {{ $invitation->assignedBy?->name ?? 'Event Host' }}
                                    </div>
                                    <div style="font-size: 0.78rem; color: var(--color-text-muted); margin-top: 0.15rem;">
                                        Shift: {{ $invitation->starts_at ? $invitation->starts_at->format('M j, Y g:i A') : 'Immediate' }} &mdash;
                                        {{ $invitation->expires_at ? $invitation->expires_at->format('M j, Y g:i A') : 'No Expiry' }} ({{ $invitation->timezone ?? 'UTC' }})
                                    </div>
                                </div>
                                <div style="display: flex; gap: 0.5rem;">
                                    <form action="{{ route('staff-assignments.accept', $invitation->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-primary btn-sm" style="background: #059669; border-color: #059669;">
                                            Accept Assignment
                                        </button>
                                    </form>
                                    <form action="{{ route('staff-assignments.decline', $invitation->id) }}" method="POST" onsubmit="return confirm('Decline this staff assignment offer?');">
                                        @csrf
                                        <button type="submit" class="btn btn-secondary btn-sm" style="color: #dc2626;">
                                            Decline
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Active Claim Code Box OR Generator Button -->
        @if (session('claim_code_generated') && session('plain_code'))
            <div style="background: #eff6ff; border: 2px dashed #93c5fd; border-radius: var(--radius-lg); padding: 1.5rem; text-align: center; margin-bottom: 1.5rem;">
                <span class="badge" style="background: #dbeafe; color: #1e40af; font-weight: 700; margin-bottom: 0.5rem;">Single-Use Staff Claim Code</span>
                <div style="font-size: 2rem; font-weight: 900; letter-spacing: 0.15em; font-family: monospace; color: #1e3a8a; margin: 0.5rem 0;">
                    {{ session('plain_code') }}
                </div>
                <p style="font-size: 0.85rem; color: #3b82f6; margin-bottom: 1rem;">
                    Share this code with your Event Host. Valid for <strong>10 minutes</strong> (until {{ session('code_expires_at') }}). Single-use only.
                </p>
                <div style="display: flex; justify-content: center; gap: 0.75rem; flex-wrap: wrap;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="navigator.clipboard.writeText('{{ session('plain_code') }}'); alert('Claim code copied to clipboard!');">
                        Copy Code
                    </button>
                </div>
            </div>
        @else
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; padding: 1.25rem; background: var(--color-bg-page); border-radius: var(--radius-md); border: 1px solid var(--color-border); margin-bottom: 1rem;">
                <div style="max-width: 600px;">
                    <div style="font-weight: 700; color: var(--color-navy); font-size: 0.95rem;">Working as staff at an upcoming event?</div>
                    <p style="font-size: 0.82rem; color: var(--color-text-secondary); margin-top: 0.25rem;">
                        Generate an expiring 10-minute code. The Event Host will enter it to verify your account without accessing your private profile or email directory.
                    </p>
                </div>
                <form action="{{ route('staff-codes.generate') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-primary" style="font-weight: 700;">
                        Generate Staff Claim Code
                    </button>
                </form>
            </div>
        @endif

        <!-- Active Shifts Summary -->
        @if(!empty($myStaffAssignments) && $myStaffAssignments->count() > 0)
            <div style="margin-top: 1rem;">
                <span style="font-size: 0.75rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 0.5rem;">
                    My Event Staff Assignments
                </span>
                <div class="eb-table-wrap" style="margin-bottom: 0;">
                    <table class="eb-table">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Position</th>
                                <th>Schedule</th>
                                <th>Status</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($myStaffAssignments as $assignment)
                                <tr>
                                    <td>
                                        <div style="font-weight: 700; color: var(--color-navy);">{{ $assignment->event?->title ?? 'Assigned Event' }}</div>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: var(--color-indigo-soft); color: var(--color-indigo);">
                                            {{ $assignment->worker_role->label() }}
                                        </span>
                                    </td>
                                    <td>
                                        <span style="font-size: 0.82rem; color: var(--color-text-secondary);">
                                            {{ $assignment->starts_at ? $assignment->starts_at->format('M j, g:i A') : 'Active' }} &mdash;
                                            {{ $assignment->expires_at ? $assignment->expires_at->format('M j, g:i A') : 'No Expiry' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($assignment->isCurrentlyActive())
                                            <span class="badge badge-active">Active Shift</span>
                                        @elseif ($assignment->isScheduled())
                                            <span class="badge" style="background: #e0f2fe; color: #0369a1;">Scheduled</span>
                                        @elseif ($assignment->isExpired())
                                            <span class="badge badge-suspended">Expired</span>
                                        @else
                                            <span class="badge">{{ ucfirst($assignment->status) }}</span>
                                        @endif
                                    </td>
                                    <td style="text-align: right;">
                                        @if ($assignment->isCurrentlyActive() && $assignment->event)
                                            <a href="{{ route('worker.terminal', $assignment->event->id) }}" class="btn btn-secondary btn-sm" target="_blank">
                                                Open Terminal
                                            </a>
                                        @else
                                            <span style="color: var(--color-text-muted); font-size: 0.75rem;">&mdash;</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    <!-- Active Tickets & QR Entry Section -->
    <div class="card" id="passes">
        <div class="card-header">
            <div>
                <h2 class="card-title">Active QR Event & Food Passes</h2>
                <p class="card-subtitle">Present this digital voucher to the Gate Checker and Food Station on event day.</p>
            </div>
            <span class="badge badge-active">{{ count($orders ?? []) }} Passes Active</span>
        </div>

        @if(!empty($orders) && count($orders) > 0)
            <div class="grid-2">
                @foreach($orders as $order)
                    {{-- The scannable QR code lives on the ticket page. --}}
                    <x-ticket-card :order="$order" :href="route('tickets.show', $order)" />
                @endforeach
            </div>
        @else
            <div class="empty-state">
                <div class="empty-state-icon">🎟️</div>
                <h3 class="empty-state-title">No ticket passes found</h3>
                <p class="empty-state-desc">You do not currently have any active event passes or advance meal orders under this account.</p>
            </div>
        @endif
    </div>

    <!-- Order History & Receipts Section -->
    <div class="card" id="orders">
        <div class="card-header">
            <div>
                <h2 class="card-title">Order History & Financial Receipts</h2>
                <p class="card-subtitle">Official records for ticket purchases and food pre-orders.</p>
            </div>
        </div>

        @if(!empty($orders) && count($orders) > 0)
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Order Reference</th>
                            <th>Date</th>
                            <th>Payment Status</th>
                            <th>Fulfillment</th>
                            <th>Amount (Charged)</th>
                            <th>Estimate ({{ $preferred_display_currency ?? 'USD' }})</th>
                            <th style="text-align: right;">Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: var(--color-navy);">#{{ $order->order_number }}</div>
                                    <span style="font-size: 0.75rem; color: var(--color-text-muted);">Code: {{ $order->ticket_code }}</span>
                                </td>
                                <td>
                                    <span>{{ date('M d, Y', strtotime($order->created_at)) }}</span>
                                </td>
                                <td>
                                    @include('components.status-indicator', ['status' => $order->status])
                                </td>
                                <td>
                                    <span class="badge {{ $order->all_handed_over ? 'badge-active' : 'badge-pending' }}">
                                        {{ $order->all_handed_over ? 'Collected' : 'Pending' }}
                                    </span>
                                </td>
                                <td>
                                    <strong style="color: var(--color-navy);">{{ \App\Support\Money::format($order->total_amount, $order->currency) }}</strong>
                                </td>
                                <td>
                                    <span style="color: var(--color-text-secondary); font-size: 0.84rem;">
                                        {{ $order->currency === ($preferred_display_currency ?? 'USD') ? '—' : '~' . ($ordersWithEstimates->firstWhere('id', $order->id)['display_estimate'] ?? '') }}
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="{{ route('tickets.show', $order->id) }}" class="eb-btn eb-btn-secondary eb-btn-sm">
                                        View<span class="sr-only"> order {{ $order->order_number }}</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state">
                <div class="empty-state-icon">📦</div>
                <h3 class="empty-state-title">No orders on record</h3>
                <p class="empty-state-desc">Your past orders and digital receipts will appear here once placed.</p>
            </div>
        @endif
    </div>
</div>
@endsection
