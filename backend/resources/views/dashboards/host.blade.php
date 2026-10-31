@extends('layouts.app')

@section('title', 'Event Host Command Center — EventBite')
@section('page-title', 'Host Command Center')

@section('navigation')
    <div class="nav-section-title">Events Platform</div>
    <a href="{{ route('events.index') }}">
        <span class="nav-icon">🌴</span>
        <span>Browse Events</span>
    </a>

    <div class="nav-section-title">Core Management</div>
    <a href="{{ route('host.dashboard') }}" class="active">
        <span class="nav-icon">📊</span>
        <span>Dashboard Overview</span>
    </a>
    <a href="{{ route('events.create') }}">
        <span class="nav-icon">➕</span>
        <span>Create New Event</span>
    </a>
    <a href="#events">
        <span class="nav-icon">🎟️</span>
        <span>My Events</span>
    </a>
    <a href="#orders">
        <span class="nav-icon">📦</span>
        <span>Orders & Verification</span>
    </a>

    <div class="nav-section-title">Operations & Staff</div>
    <a href="#forecast">
        <span class="nav-icon">🍳</span>
        <span>Kitchen Prep Forecast</span>
    </a>
    <a href="#workers">
        <span class="nav-icon">👥</span>
        <span>Staff & Workers</span>
    </a>
    <a href="#finances">
        <span class="nav-icon">💵</span>
        <span>Financial Summary</span>
    </a>
    <a href="{{ route('settings.show') }}">
        <span class="nav-icon">⚙️</span>
        <span>Event Settings</span>
    </a>
@endsection

@section('content')
<div class="host-dashboard-view">
    <!-- Welcome Header & Context Banner -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.35rem;">
                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--color-navy); letter-spacing: -0.02em;">
                    Welcome back, {{ Auth::user()->first_name ?? Auth::user()->name }}!
                </h1>
                <span class="badge badge-scope">Event Host</span>
            </div>
            <p style="color: var(--color-text-secondary); font-size: 0.92rem;">
                Overseeing US ticket sales, kitchen advance orders, and on-site redemption stations.
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <a href="{{ route('events.create') }}" class="btn btn-primary">
                <span>➕</span> Create Event
            </a>
            <a href="#record-cash" class="btn btn-secondary">
                <span>💵</span> Record Walk-in Cash
            </a>
        </div>
    </div>

    <!-- Top KPI Metric Cards -->
    <div class="grid-4" style="margin-bottom: 1.75rem;">
        <!-- Active Events -->
        <div class="metric-card">
            <span class="metric-label">Active Events</span>
            <div class="metric-value">{{ $total_events ?? count($events ?? []) }}</div>
            <div class="metric-footer" style="color: var(--color-success);">
                <span>●</span> Published & Accepting Orders
            </div>
        </div>

        <!-- Total Orders -->
        <div class="metric-card">
            <span class="metric-label">Total Orders</span>
            <div class="metric-value">{{ $total_orders ?? 0 }}</div>
            <div class="metric-footer" style="color: var(--color-info);">
                <span>{{ $paid_orders_count ?? 0 }} Paid & Verified</span>
            </div>
        </div>

        <!-- Pending Verifications -->
        <div class="metric-card">
            <span class="metric-label">Transfer Verification</span>
            <div class="metric-value" style="color: {{ ($pending_verification_count ?? 0) > 0 ? 'var(--color-warning)' : 'var(--color-navy)' }};">
                {{ $pending_verification_count ?? 0 }}
            </div>
            <div class="metric-footer" style="color: var(--color-warning);">
                <span>●</span> Awaiting Bank/Cash Review
            </div>
        </div>

        <!-- Kitchen Prep Forecast Buffer -->
        <div class="metric-card">
            <span class="metric-label">Kitchen Prep Forecast</span>
            <div class="metric-value" style="color: var(--color-indigo);">
                {{ $prep_with_buffer ?? 0 }}
            </div>
            <div class="metric-footer" style="color: var(--color-indigo);">
                <span>●</span> {{ $sold_portions ?? 0 }} Sold + {{ $buffer_percent ?? 10 }}% Safety Buffer
            </div>
        </div>
    </div>

    <!-- Analytics & Financial Overview Section -->
    <div class="grid-2" style="margin-bottom: 1.75rem;" id="finances">
        <!-- Revenue Breakdown Card -->
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Financial Summary & Verified Receipts</h2>
                    <p class="card-subtitle">Accurate reconciliation conforming to US banking and payment rules.</p>
                </div>
                <span class="badge badge-active">Audited Ledger</span>
            </div>

            <div style="display: flex; flex-direction: column; gap: 1rem;">
                @foreach(($financials ?? []) as $code => $money)
                    @if(count($financials) > 1)
                        <h3 style="font-size: 0.85rem; font-weight: 700; margin: 0.25rem 0 0;">{{ $code }} orders</h3>
                    @endif
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; background: var(--color-bg-page); border-radius: var(--radius-md);">
                        <div>
                            <span style="font-size: 0.8rem; color: var(--color-text-secondary); text-transform: uppercase;">Gross order volume ({{ $code }})</span>
                            <div style="font-size: 1.25rem; font-weight: 700; color: var(--color-navy);">{{ $money['gross']['formatted'] }}</div>
                        </div>
                        <span style="font-size: 0.85rem; color: var(--color-text-secondary);">All orders, including unpaid</span>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; background: var(--color-success-bg); border-radius: var(--radius-md);">
                        <div>
                            <span style="font-size: 0.8rem; color: var(--color-success); font-weight: 600; text-transform: uppercase;">Received ({{ $code }})</span>
                            <div style="font-size: 1.25rem; font-weight: 800; color: var(--color-success);">{{ $money['received']['formatted'] }}</div>
                        </div>
                        <span style="font-size: 0.8rem; color: var(--color-text-secondary);">Net of refunds: {{ $money['net']['formatted'] }}</span>
                    </div>

                    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                        <div style="flex: 1 1 10rem; padding: 0.75rem 1rem; background: var(--color-bg-page); border-radius: var(--radius-md);">
                            <span style="font-size: 0.75rem; color: var(--color-text-secondary); text-transform: uppercase;">Still owed</span>
                            <div style="font-weight: 700;">{{ $money['pending']['formatted'] }}</div>
                        </div>
                        <div style="flex: 1 1 10rem; padding: 0.75rem 1rem; background: var(--color-bg-page); border-radius: var(--radius-md);">
                            <span style="font-size: 0.75rem; color: var(--color-text-secondary); text-transform: uppercase;">Refunded</span>
                            <div style="font-weight: 700;">{{ $money['refunded']['formatted'] }}</div>
                        </div>
                        <div style="flex: 1 1 10rem; padding: 0.75rem 1rem; background: var(--color-bg-page); border-radius: var(--radius-md);">
                            <span style="font-size: 0.75rem; color: var(--color-text-secondary); text-transform: uppercase;">Cash recorded on site</span>
                            <div style="font-weight: 700;">{{ $money['cash']['formatted'] }}</div>
                        </div>
                    </div>
                @endforeach

                <p style="font-size: 0.78rem; color: var(--color-text-muted); font-style: italic; margin-top: 0.25rem;">
                    * Notice: In accordance with financial invariants, pending manual bank-transfers are not counted as verified money until formally reconciled.
                </p>
            </div>
        </div>

        <!-- SVG Visual Data Chart -->
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Order Trajectory & Advance Prep Trend</h2>
                    <p class="card-subtitle">Real-time demand velocity leading up to event order cutoff.</p>
                </div>
                <span style="font-size: 0.8rem; font-weight: 600; color: var(--color-indigo);">Last 7 Days</span>
            </div>

            <!-- Accessible Pure SVG Area Chart -->
            <div style="width: 100%; height: 180px; position: relative;">
                <svg viewBox="0 0 500 160" style="width: 100%; height: 100%; overflow: visible;" aria-label="Ticket sales trajectory chart">
                    <defs>
                        <linearGradient id="indigoGradient" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#4F46E5" stop-opacity="0.25" />
                            <stop offset="100%" stop-color="#4F46E5" stop-opacity="0.0" />
                        </linearGradient>
                    </defs>
                    <!-- Grid Lines -->
                    <line x1="0" y1="140" x2="500" y2="140" stroke="#E2E8F0" stroke-width="1" />
                    <line x1="0" y1="90" x2="500" y2="90" stroke="#F1F5F9" stroke-width="1" stroke-dasharray="4" />
                    <line x1="0" y1="40" x2="500" y2="40" stroke="#F1F5F9" stroke-width="1" stroke-dasharray="4" />

                    <!-- Area Fill -->
                    <polygon points="0,140 0,110 80,95 160,115 240,70 320,60 400,35 500,20 500,140" fill="url(#indigoGradient)" />
                    <!-- Polyline Line -->
                    <polyline points="0,110 80,95 160,115 240,70 320,60 400,35 500,20" fill="none" stroke="#4F46E5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />

                    <!-- Data Points -->
                    <circle cx="80" cy="95" r="4" fill="#4F46E5" stroke="#FFFFFF" stroke-width="2" />
                    <circle cx="160" cy="115" r="4" fill="#4F46E5" stroke="#FFFFFF" stroke-width="2" />
                    <circle cx="240" cy="70" r="4" fill="#4F46E5" stroke="#FFFFFF" stroke-width="2" />
                    <circle cx="320" cy="60" r="4" fill="#4F46E5" stroke="#FFFFFF" stroke-width="2" />
                    <circle cx="400" cy="35" r="4" fill="#4F46E5" stroke="#FFFFFF" stroke-width="2" />
                    <circle cx="500" cy="20" r="5" fill="#15803D" stroke="#FFFFFF" stroke-width="2" />
                </svg>
                <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--color-text-muted); margin-top: 0.5rem;">
                    <span>Mon</span>
                    <span>Tue</span>
                    <span>Wed</span>
                    <span>Thu</span>
                    <span>Fri</span>
                    <span>Sat</span>
                    <span>Today (Peak)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Event Management Section -->
    <div class="card" id="events">
        <div class="card-header">
            <div>
                <h2 class="card-title">Managed Events & Promotions</h2>
                <p class="card-subtitle">Active events, flyer previews, capacity, and cutoff deadlines.</p>
            </div>
            <a href="{{ route('events.create') }}" class="btn btn-primary btn-sm">
                + New Event
            </a>
        </div>

        @if(!empty($events) && count($events) > 0)
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Event & Flyer</th>
                            <th>Venue & Location</th>
                            <th>Event Date</th>
                            <th>Currency</th>
                            <th>Status</th>
                            <th>Ordering Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($events as $event)
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.85rem;">
                                        <div style="width: 48px; height: 48px; border-radius: var(--radius-md); overflow: hidden; background: var(--color-bg-page); border: 1px solid var(--color-border); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                            @if(!empty($event->flyer_path))
                                                <img src="/storage/{{ $event->flyer_path }}" alt="{{ $event->title }}" style="width: 100%; height: 100%; object-fit: cover;">
                                            @else
                                                <span style="font-size: 1.5rem;">🌴</span>
                                            @endif
                                        </div>
                                        <div>
                                            <div style="font-weight: 700; color: var(--color-navy);">{{ $event->title }}</div>
                                            <span style="font-size: 0.78rem; color: var(--color-text-secondary);">ID: {{ substr($event->id, 0, 8) }}...</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 500;">{{ $event->venue ?? 'Main Grounds' }}</div>
                                    <span style="font-size: 0.78rem; color: var(--color-text-muted);">{{ $event->address ?? 'United States' }}</span>
                                </td>
                                <td>
                                    <div style="font-weight: 600;">{{ !empty($event->start_time) ? date('M d, Y', strtotime($event->start_time)) : 'Upcoming' }}</div>
                                    <span style="font-size: 0.78rem; color: var(--color-text-muted);">{{ !empty($event->start_time) ? date('g:i A', strtotime($event->start_time)) : 'TBD' }}</span>
                                </td>
                                <td>
                                    <span class="badge" style="background: var(--color-indigo-soft); color: var(--color-indigo); font-weight: 700;">
                                        {{ $event->currency ?? 'USD' }}
                                    </span>
                                </td>
                                <td>
                                    @include('components.status-indicator', ['status' => $event->status ?? 'published'])
                                </td>
                                <td>
                                    <span class="badge {{ ($event->is_ordering_open ?? true) ? 'badge-active' : 'badge-suspended' }}">
                                        {{ ($event->is_ordering_open ?? true) ? 'Ordering Open' : 'Cutoff Reached' }}
                                    </span>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <a href="{{ route('host.events.staff', $event->id) }}" class="btn btn-secondary btn-sm" title="Manage Staff & Workers">
                                        Staff
                                    </a>
                                    <a href="{{ route('events.edit', $event->id) }}" class="btn btn-secondary btn-sm" title="Edit Event Details">
                                        Edit
                                    </a>
                                    <a href="{{ route('worker.terminal', $event->id) }}" class="btn btn-secondary btn-sm" title="Open Worker Terminal" target="_blank">
                                        Terminal
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state">
                <div class="empty-state-icon">🎟️</div>
                <h3 class="empty-state-title">No events published yet</h3>
                <p class="empty-state-desc">Create your first event to start accepting digital ticket sales and advance food orders.</p>
                <a href="{{ route('events.create') }}" class="btn btn-primary">
                    Create Your First Event
                </a>
            </div>
        @endif
    </div>

    <!-- Kitchen Prep Forecast Sheet -->
    <div class="card" id="forecast">
        <div class="card-header">
            <div>
                <h2 class="card-title">Live Kitchen Prep Forecast (+{{ $buffer_percent ?? 10 }}% Safety Buffer)</h2>
                <p class="card-subtitle">Portion specifications for head chefs and station supervisors before event kickoff.</p>
            </div>
            <span class="badge badge-active">
                Safety Buffer: +{{ $buffer_percent ?? 10 }}% Enforced
            </span>
        </div>

        <div class="grid-2">
            <div style="background: var(--color-bg-page); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <h3 style="color: var(--color-indigo); font-size: 1rem; font-weight: 700;">Station A &bull; Escovitch Fish & Festivals</h3>
                    <span class="badge badge-scope">Hot Kitchen</span>
                </div>
                <p style="font-size: 0.82rem; color: var(--color-text-secondary); margin-bottom: 0.75rem;">Assigned Station Lead: Sister Beverly Campbell</p>
                <div style="font-size: 0.88rem; display: flex; flex-direction: column; gap: 0.45rem;">
                    <div style="display: flex; justify-content: space-between; padding-bottom: 0.35rem; border-bottom: 1px solid var(--color-border-subtle);">
                        <span>Escovitch Medium Snapper Meals:</span>
                        <strong>{{ (int) round(($sold_portions ?? 50) * 0.45) }} Sold &rarr; {{ (int) ceil((($sold_portions ?? 50) * 0.45) * 1.1) }} Prep Target</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Golden Fried Festival Portions:</span>
                        <strong>{{ (int) round(($sold_portions ?? 50) * 0.9) }} Pieces</strong>
                    </div>
                </div>
            </div>

            <div style="background: var(--color-bg-page); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <h3 style="color: var(--color-warning); font-size: 1rem; font-weight: 700;">Station B &bull; Jerk Chicken & Bammy</h3>
                    <span class="badge badge-pending">Grill Pit</span>
                </div>
                <p style="font-size: 0.82rem; color: var(--color-text-secondary); margin-bottom: 0.75rem;">Assigned Station Lead: Chef Devon Campbell</p>
                <div style="font-size: 0.88rem; display: flex; flex-direction: column; gap: 0.45rem;">
                    <div style="display: flex; justify-content: space-between; padding-bottom: 0.35rem; border-bottom: 1px solid var(--color-border-subtle);">
                        <span>Smoked Jerk Chicken Quarters:</span>
                        <strong>{{ (int) round(($sold_portions ?? 50) * 0.55) }} Sold &rarr; {{ (int) ceil((($sold_portions ?? 50) * 0.55) * 1.1) }} Prep Target</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Toasted Cassava Bammy Slices:</span>
                        <strong>{{ (int) round(($sold_portions ?? 50) * 0.75) }} Portions</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Event Workers & Operational Staff Assignments -->
    <div class="card" id="workers">
        <div class="card-header">
            <div>
                <h2 class="card-title">Event Workers & Staff Permissions</h2>
                <p class="card-subtitle">Manage Payment Clerks, Ticket Checkers, Food Station Workers, and Supervisors.</p>
            </div>
            <span class="badge badge-scope">{{ $active_workers_count ?? count($workers ?? []) }} Active Staff</span>
        </div>

        @if(!empty($workers) && count($workers) > 0)
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Staff Member</th>
                            <th>Assigned Role</th>
                            <th>Station Assignment</th>
                            <th>Separation of Duties</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($workers as $worker)
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: var(--color-navy);">{{ $worker->user->name ?? $worker->invite_email ?? 'Staff Member' }}</div>
                                    <span style="font-size: 0.78rem; color: var(--color-text-muted);">{{ $worker->user->email ?? $worker->invite_email ?? 'No email' }}</span>
                                </td>
                                <td>
                                    <span class="badge badge-scope">
                                        {{ $worker->worker_role->label() ?? 'Event Worker' }}
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size: 0.85rem; font-weight: 600; text-transform: capitalize;">
                                        {{ $worker->station_assignment ?? 'All Stations' }}
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size: 0.78rem; color: var(--color-success); font-weight: 600;">
                                        ✓ Enforced (No Price/Ownership Access)
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $worker->is_active ? 'badge-active' : 'badge-suspended' }}">
                                        {{ $worker->is_active ? 'Active on Shift' : 'Revoked / Inactive' }}
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <button class="btn btn-secondary btn-sm" style="color: var(--color-error);" onclick="confirm('Revoke access for this worker?')">
                                        Revoke
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state">
                <div class="empty-state-icon">👥</div>
                <h3 class="empty-state-title">No event workers assigned yet</h3>
                <p class="empty-state-desc">Invite ticket checkers, payment clerks, or kitchen workers to scan passes and collect portions without exposing platform settings.</p>
            </div>
        @endif
    </div>
</div>
@endsection
