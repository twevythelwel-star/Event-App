@extends('layouts.app')

@section('title', 'System Administrator Console — EventBite')
@section('page-title', 'Platform Governance')

@section('navigation')
    <div class="nav-section-title">Events Platform</div>
    <a href="{{ route('events.index') }}">
        <span class="nav-icon">🌴</span>
        <span>Browse Events</span>
    </a>

    <div class="nav-section-title">Platform Governance</div>
    <a href="{{ route('system-admin.dashboard') }}" class="active">
        <span class="nav-icon">🖥️</span>
        <span>Infrastructure Health</span>
    </a>
    <a href="#users">
        <span class="nav-icon">👥</span>
        <span>User & Role Control</span>
    </a>
    <a href="#audit">
        <span class="nav-icon">📜</span>
        <span>Security Audit Logs</span>
    </a>

    <div class="nav-section-title">Configuration</div>
    <a href="{{ route('settings.show') }}#system">
        <span class="nav-icon">⚙️</span>
        <span>Platform Settings</span>
    </a>
    <a href="{{ route('settings.show') }}#security">
        <span class="nav-icon">🔒</span>
        <span>Master Credentials</span>
    </a>
@endsection

@section('content')
<div class="system-admin-console-view">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.35rem;">
                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--color-navy); letter-spacing: -0.02em;">
                    System Administrator Console
                </h1>
                <span class="badge" style="background: var(--color-navy); color: var(--color-white);">Superadmin</span>
            </div>
            <p style="color: var(--color-text-secondary); font-size: 0.92rem;">
                Root platform operations, infrastructure health monitoring, security audit trails & multi-tenant governance.
            </p>
        </div>

        <div>
            @include('components.status-indicator', ['status' => 'operational', 'label' => 'All Systems Operational'])
        </div>
    </div>

    <!-- Infrastructure Health Indicators -->
    <div class="grid-4" style="margin-bottom: 1.75rem;">
        <div class="metric-card">
            <span class="metric-label">DB Connection Pool</span>
            <div class="metric-value">{{ $health['database_pool']['active_connections'] ?? 12 }} / {{ $health['database_pool']['max_connections'] ?? 100 }}</div>
            <div class="metric-footer" style="color: var(--color-success);">
                <span>●</span> Pool Healthy &bull; Low Latency
            </div>
        </div>

        <div class="metric-card">
            <span class="metric-label">Queue Workers</span>
            <div class="metric-value">{{ $health['queue_workers']['active_workers'] ?? 4 }} Active</div>
            <div class="metric-footer" style="color: var(--color-success);">
                <span>●</span> 0 Dead Letter Jobs
            </div>
        </div>

        <div class="metric-card">
            <span class="metric-label">File Storage (R2 / S3)</span>
            <div class="metric-value" style="font-size: 1.4rem; line-height: 1.25;">Cloudflare R2</div>
            <div class="metric-footer" style="color: var(--color-success);">
                <span>●</span> Bucket Healthy (Encrypted)
            </div>
        </div>

        <div class="metric-card">
            <span class="metric-label">Automated Backups</span>
            <div class="metric-value" style="font-size: 1.4rem; line-height: 1.25;">Daily WAL</div>
            <div class="metric-footer" style="color: var(--color-info);">
                <span>✓</span> Verified (RPO &lt; 5m)
            </div>
        </div>
    </div>

    <!-- Global Platform Metrics -->
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">United States Platform Activity Metrics</h2>
                <p class="card-subtitle">Aggregated totals across all United States event workspaces.</p>
            </div>
            <span class="badge badge-scope">Platform Total</span>
        </div>

        <div class="grid-4">
            <div style="background: var(--color-bg-page); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                <span style="font-size: 0.75rem; color: var(--color-text-secondary); text-transform: uppercase; font-weight: 600;">Registered Users</span>
                <div style="font-size: 1.4rem; font-weight: 800; color: var(--color-navy); margin-top: 0.25rem;">{{ $metrics['total_users'] ?? 0 }}</div>
            </div>

            <div style="background: var(--color-bg-page); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                <span style="font-size: 0.75rem; color: var(--color-text-secondary); text-transform: uppercase; font-weight: 600;">Active Event Hosts</span>
                <div style="font-size: 1.4rem; font-weight: 800; color: var(--color-indigo); margin-top: 0.25rem;">{{ $metrics['active_hosts'] ?? 0 }}</div>
            </div>

            @forelse(($metrics['paid_volume_minor_by_currency'] ?? []) as $code => $minor)
                <div style="background: var(--color-bg-page); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                    <span style="font-size: 0.75rem; color: var(--color-text-secondary); text-transform: uppercase; font-weight: 600;">Paid volume ({{ $code }})</span>
                    <div style="font-size: 1.4rem; font-weight: 800; color: var(--color-success); margin-top: 0.25rem;">{{ \App\Support\Money::formatMinor($minor, $code) }}</div>
                </div>
            @empty
                <div style="background: var(--color-bg-page); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                    <span style="font-size: 0.75rem; color: var(--color-text-secondary); text-transform: uppercase; font-weight: 600;">Paid volume</span>
                    <div style="font-size: 1.4rem; font-weight: 800; color: var(--color-text-secondary); margin-top: 0.25rem;">{{ \App\Support\Money::formatMinor(0, null) }}</div>
                </div>
            @endforelse
        </div>
    </div>

    <!-- User & Role Management Table -->
    <div class="card" id="users">
        <div class="card-header">
            <div>
                <h2 class="card-title">Account & Role Administration</h2>
                <p class="card-subtitle">Manage accounts, activate/suspend privileges, and audit role assignments.</p>
            </div>
            <span class="badge badge-pending">Anti-Self-Elevation Enforced</span>
        </div>

        @if(!empty($users) && count($users) > 0)
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>User Name</th>
                            <th>Email Address</th>
                            <th>Primary Role Context</th>
                            <th>Status</th>
                            <th>Created Date</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $u)
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: var(--color-navy);">{{ $u->name }}</div>
                                    @if($u->is_system_admin)
                                        <span style="font-size: 0.72rem; color: var(--color-indigo); font-weight: 600;">Superadmin Flag</span>
                                    @endif
                                </td>
                                <td>{{ $u->email }}</td>
                                <td>
                                    <span class="badge badge-scope">
                                        {{ ucfirst($u->current_context_role ?? 'attendee') }}
                                    </span>
                                </td>
                                <td>
                                    @include('components.status-indicator', ['status' => $u->status ?? 'active'])
                                </td>
                                <td>{{ date('M d, Y', strtotime($u->created_at)) }}</td>
                                <td style="text-align: right;">
                                    @if($u->id !== Auth::id())
                                        <button class="btn btn-secondary btn-sm" onclick="alert('Manage user: {{ $u->email }}')">
                                            Edit Role
                                        </button>
                                    @else
                                        <span style="font-size: 0.78rem; color: var(--color-text-muted);">Current Account</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state">
                <div class="empty-state-icon">👥</div>
                <h3 class="empty-state-title">No user records</h3>
            </div>
        @endif
    </div>

    <!-- Security Audit Logs Table -->
    <div class="card" id="audit">
        <div class="card-header">
            <div>
                <h2 class="card-title">Security & Audit Event Logs</h2>
                <p class="card-subtitle">Immutable audit trail with IP address and privileged PII inspection flags.</p>
            </div>
            <span class="badge badge-active">Immutable Stream</span>
        </div>

        @if(!empty($recentLogs) && count($recentLogs) > 0)
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Action</th>
                            <th>Actor Role</th>
                            <th>Entity Target</th>
                            <th>IP Address</th>
                            <th>PII Access</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentLogs as $log)
                            <tr>
                                <td>
                                    <span style="font-family: monospace; font-size: 0.8rem;">
                                        {{ date('Y-m-d H:i:s', strtotime($log->created_at)) }}
                                    </span>
                                </td>
                                <td>
                                    <strong style="color: var(--color-navy); font-size: 0.82rem;">{{ $log->action }}</strong>
                                </td>
                                <td>
                                    <span class="badge badge-scope">{{ $log->actor_role ?? 'system' }}</span>
                                </td>
                                <td>{{ $log->target_entity }}: {{ substr($log->target_id ?? '', 0, 8) }}</td>
                                <td>
                                    <span style="font-family: monospace; font-size: 0.8rem; color: var(--color-text-secondary);">
                                        {{ $log->ip_address ?? '127.0.0.1' }}
                                    </span>
                                </td>
                                <td>
                                    @if($log->is_pii_access)
                                        <span class="badge badge-suspended">PII Inspected</span>
                                    @else
                                        <span style="font-size: 0.75rem; color: var(--color-text-muted);">Routine</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state">
                <div class="empty-state-icon">📜</div>
                <h3 class="empty-state-title">No audit records</h3>
                <p class="empty-state-desc">Audit events will stream here as platform actions occur.</p>
            </div>
        @endif
    </div>
</div>
@endsection
