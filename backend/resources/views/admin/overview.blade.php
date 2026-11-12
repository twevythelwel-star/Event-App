@extends('layouts.admin')

@section('title', 'Dashboard')
@section('breadcrumbs')<span aria-hidden="true">›</span><span aria-current="page">Dashboard</span>@endsection
@section('heading', ($isSystemAdmin ?? false) ? 'System Administrator Dashboard' : 'Administrator Dashboard')
@section('subheading')
    Platform activity you have access to. "Recent" figures cover the last {{ $periodDays }} days, up to now.
@endsection

@section('content')
<div class="eb-dashboard-shell eb-stack">
    @if(!$hasAnyCapability)
        <div class="eb-card eb-panel ac-card">
            <div class="ac-empty">
                <strong>No administration areas are switched on for your account yet</strong>
                A System Administrator assigns your permissions and organizers under Roles &amp; Permissions.
            </div>
        </div>
    @else
        {{-- Unified Quick-Jump Section Pill Navigation (strictly permission-gated) --}}
        <nav class="eb-dashboard-nav-pills" aria-label="Administration dashboard sections">
            <a href="#platform-analytics" class="eb-dashboard-nav-pill">
                <x-icon name="dashboard" size="sm" />
                <span>Platform Analytics</span>
            </a>
            @if($canSeeUsers ?? false)
                <a href="#user-management" class="eb-dashboard-nav-pill">
                    <x-icon name="users" size="sm" />
                    <span>User Management</span>
                </a>
            @endif
            @if($canSeeEvents ?? false)
                <a href="#event-oversight" class="eb-dashboard-nav-pill">
                    <x-icon name="events" size="sm" />
                    <span>Event Oversight</span>
                </a>
            @endif
            @if($canSeePayments ?? false)
                <a href="#payment-monitoring" class="eb-dashboard-nav-pill">
                    <x-icon name="payment" size="sm" />
                    <span>Payment Monitoring</span>
                </a>
            @endif
            @if($canSeeAudit ?? false)
                <a href="#security-activity" class="eb-dashboard-nav-pill">
                    <x-icon name="shield" size="sm" />
                    <span>Security Activity</span>
                </a>
            @endif
            @if(($canSeeReports ?? false) || ($canManageSettings ?? false))
                <a href="#reporting" class="eb-dashboard-nav-pill">
                    <x-icon name="reports" size="sm" />
                    <span>Reporting</span>
                </a>
            @endif
        </nav>

        {{-- 1. Platform Analytics --}}
        @if($metrics !== [])
            <section id="platform-analytics" class="eb-grid-4 ac-metrics" aria-label="Platform analytics">
                @foreach($metrics as $metric)
                    <x-metric-card
                        :label="$metric['label']"
                        :value="$metric['value']"
                        :note="$metric['note']"
                        :definition="$metric['definition']"
                        :href="$metric['href'] ?? null" />
                @endforeach
            </section>
        @endif

        {{-- 2. User Management & 3. Event Oversight --}}
        @if(($canSeeUsers ?? false) || ($canSeeEvents ?? false))
            <div class="eb-grid-2">
                @if($canSeeUsers ?? false)
                    <section id="user-management" class="eb-panel card ac-card" style="margin: 0;" aria-labelledby="user-management-heading">
                        <div class="eb-card-header card-header">
                            <div>
                                <h2 id="user-management-heading" class="eb-card-title card-title">User management</h2>
                                <p class="eb-card-subtitle card-subtitle">Newest accounts within your administration scope.</p>
                            </div>
                            <a class="eb-btn eb-btn-secondary eb-btn-sm" href="{{ route('admin.console.users.index') }}">Manage users</a>
                        </div>

                        <x-data-table
                            caption="Recent user accounts"
                            :columns="['Name & Email', 'Status', 'Joined', ['label' => 'Profile', 'numeric' => true]]"
                            :empty="collect($recentUsers ?? [])->isEmpty()"
                            empty-title="No users in scope"
                            empty-text="Registered user accounts appear here.">
                            @foreach($recentUsers as $u)
                                <tr>
                                    <td>
                                        <div style="font-weight: 700; color: var(--color-text-primary);">{{ $u->name }}</div>
                                        <div class="eb-help-text">{{ $u->email }}</div>
                                    </td>
                                    <td><x-status-indicator :status="$u->status ?? 'active'" /></td>
                                    <td class="eb-help-text">{{ $u->created_at?->format('M j, Y') }}</td>
                                    <td class="eb-table-num">
                                        <a href="{{ route('admin.console.users.show', $u) }}" class="eb-btn eb-btn-ghost eb-btn-sm">Inspect</a>
                                    </td>
                                </tr>
                            @endforeach
                        </x-data-table>
                    </section>
                @endif

                @if($canSeeEvents ?? false)
                    <section id="event-oversight" class="eb-panel card ac-card" style="margin: 0;" aria-labelledby="event-oversight-heading">
                        <div class="eb-card-header card-header">
                            <div>
                                <h2 id="event-oversight-heading" class="eb-card-title card-title">Event oversight</h2>
                                <p class="eb-card-subtitle card-subtitle">Recent events, publication status, and ordering state.</p>
                            </div>
                            <a class="eb-btn eb-btn-secondary eb-btn-sm" href="{{ route('admin.console.events.index') }}">All events</a>
                        </div>

                        <x-data-table
                            caption="Recent events"
                            :columns="['Event', 'Status', 'Ordering', ['label' => 'Review', 'numeric' => true]]"
                            :empty="collect($recentEvents ?? [])->isEmpty()"
                            empty-title="No events in scope"
                            empty-text="Events created by organizers in your scope appear here.">
                            @foreach($recentEvents as $ev)
                                <tr>
                                    <td>
                                        <div style="font-weight: 700; color: var(--color-text-primary);">{{ $ev->title }}</div>
                                        <div class="eb-help-text">{{ $ev->venue_name ?: 'Venue TBA' }}{{ $ev->city ? ', ' . $ev->city : '' }}</div>
                                    </td>
                                    <td><x-status-indicator :status="$ev->status ?? 'draft'" /></td>
                                    <td>
                                        <span class="eb-badge {{ $ev->is_ordering_open ? 'eb-badge-success' : 'eb-badge-neutral' }}">
                                            {{ $ev->is_ordering_open ? 'Open' : 'Closed' }}
                                        </span>
                                    </td>
                                    <td class="eb-table-num">
                                        <a href="{{ route('admin.console.events.show', $ev) }}" class="eb-btn eb-btn-ghost eb-btn-sm">Moderate</a>
                                    </td>
                                </tr>
                            @endforeach
                        </x-data-table>
                    </section>
                @endif
            </div>
        @endif

        {{-- 4. Payment Monitoring & 5. Security Activity --}}
        <div class="eb-grid-2">
            @if(($revenue->isNotEmpty() || ($canSeePayments ?? false)) || Gate::allows(\App\Support\AdminAuthorization::PAYMENTS_VIEW))
                <section id="payment-monitoring" class="eb-panel card ac-card" style="margin: 0;" aria-labelledby="collected-heading">
                    <div class="eb-card-header card-header">
                        <div>
                            <h2 id="collected-heading" class="eb-card-title card-title">Payment monitoring · last {{ $periodDays }} days</h2>
                            <p class="eb-card-subtitle card-subtitle">Sum of amount paid on verified orders per currency, plus transfers awaiting verification.</p>
                        </div>
                        <a class="eb-btn eb-btn-secondary eb-btn-sm" href="{{ route('admin.console.orders.index') }}">Orders &amp; Payments</a>
                    </div>

                    <div class="eb-stack" style="gap: 0.85rem;">
                        <div class="eb-cluster" style="gap: 0.75rem;">
                            @forelse($revenue as $currency => $amount)
                                <div class="eb-card" style="padding: 0.85rem 1.1rem; flex: 1 1 160px;">
                                    <span class="eb-text-label">{{ $currency }} Verified Volume</span>
                                    <div class="eb-text-price" style="font-size: 1.35rem; margin-top: 0.2rem;">
                                        {{ \App\Support\Money::formatMinor((int) $amount, $currency) }} <span class="eb-help-text">{{ $currency }}</span>
                                    </div>
                                </div>
                            @empty
                                <p class="eb-text-supporting" style="margin: 0;">No verified payments in this period.</p>
                            @endforelse
                        </div>

                        @if(collect($pendingOrders ?? [])->isNotEmpty())
                            <div>
                                <span class="eb-text-label" style="display: block; margin-bottom: 0.45rem;">Transfers awaiting verification</span>
                                <x-data-table
                                    caption="Orders awaiting verification"
                                    :columns="['Order', 'Customer', 'Method', ['label' => 'Total', 'numeric' => true], ['label' => 'Action', 'numeric' => true]]"
                                    :empty="false">
                                    @foreach($pendingOrders as $pOrder)
                                        <tr>
                                            <td style="font-family: var(--font-mono); font-weight: 700;">{{ $pOrder->order_number }}</td>
                                            <td>{{ $pOrder->customer_name }}</td>
                                            <td>{{ \App\Support\PaymentStatusPresenter::methodLabel($pOrder->method) }}</td>
                                            <td class="eb-table-num">{{ \App\Support\Money::formatMinor((int) $pOrder->total_minor, $pOrder->currency ?: 'USD') }}</td>
                                            <td class="eb-table-num">
                                                <a href="{{ route('admin.console.orders.show', $pOrder) }}" class="eb-btn eb-btn-ghost eb-btn-sm">Review</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </x-data-table>
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            @if($canSeeAudit)
                <section id="security-activity" class="eb-panel card ac-card" style="margin: 0;" aria-labelledby="activity-heading">
                    <div class="eb-card-header card-header">
                        <div>
                            <h2 id="activity-heading" class="eb-card-title card-title">Security &amp; administrative activity</h2>
                            <p class="eb-card-subtitle card-subtitle">Immutable audit trail of payment decisions, role changes, and moderation actions.</p>
                        </div>
                        <a class="eb-btn eb-btn-secondary eb-btn-sm" href="{{ route('admin.console.audit.index') }}">View audit log</a>
                    </div>

                    <x-data-table
                        caption="Recent administrative activity"
                        :columns="['When', 'Who', 'Action']"
                        :empty="$recentActivity->isEmpty()"
                        empty-title="No activity recorded yet"
                        empty-text="Actions such as payment decisions and role changes appear here.">
                        @foreach($recentActivity as $log)
                            <tr>
                                <td class="eb-help-text"><time datetime="{{ $log->created_at?->toIso8601String() }}">{{ $log->created_at?->diffForHumans() }}</time></td>
                                <td style="font-weight: 600;">{{ $log->user?->name ?? 'System' }}</td>
                                <td>
                                    <span style="font-weight: 600; color: var(--color-text-primary);">{{ str_replace('_', ' ', $log->action) }}</span>
                                    <span class="eb-help-text" style="display: block;">{{ $log->target_entity }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </x-data-table>
                </section>
            @endif
        </div>

        {{-- 6. Reporting & Platform Governance --}}
        @if(($canSeeReports ?? false) || ($canManageSettings ?? false))
            <section id="reporting" class="eb-panel card ac-card" style="margin: 0;" aria-labelledby="reporting-heading">
                <div class="eb-card-header card-header">
                    <div>
                        <h2 id="reporting-heading" class="eb-card-title card-title">Reporting &amp; platform governance</h2>
                        <p class="eb-card-subtitle card-subtitle">Export financial, order, and compliance reports or manage platform-wide configuration.</p>
                    </div>
                    <div class="eb-cluster" style="gap: 0.5rem;">
                        @if($canSeeReports ?? false)
                            <a href="{{ route('admin.console.reports.index') }}" class="eb-btn eb-btn-primary eb-btn-sm">
                                <x-icon name="reports" size="sm" />
                                <span>Open Reports</span>
                            </a>
                            <a href="{{ route('admin.console.reports.export') }}" class="eb-btn eb-btn-secondary eb-btn-sm">
                                <span>Export CSV</span>
                            </a>
                        @endif
                        @if($canManageSettings ?? false)
                            <a href="{{ route('admin.console.settings.edit') }}" class="eb-btn eb-btn-secondary eb-btn-sm">
                                <x-icon name="settings" size="sm" />
                                <span>Platform Settings</span>
                            </a>
                        @endif
                    </div>
                </div>
            </section>
        @endif
    @endif
</div>
@endsection
