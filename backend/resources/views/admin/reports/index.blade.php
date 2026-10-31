@extends('layouts.admin')

@section('title', 'Reports')
@section('breadcrumbs')<span aria-hidden="true">›</span><span aria-current="page">Reports</span>@endsection
@section('heading', 'Reports')
@section('subheading')
    {{ $from->format('M j, Y') }} – {{ $to->format('M j, Y') }}. Each section says where its numbers come from.
@endsection
@section('actions')
    @if($canExport)
        <a class="ac-btn ac-btn-dark" href="{{ route('admin.console.reports.export', request()->only(['days', 'from', 'to', 'event'])) }}">Export per-event summary (CSV)</a>
    @endif
@endsection

@section('content')
    <div class="ac-card">
        <form class="ac-filters" method="GET" action="{{ route('admin.console.reports.index') }}">
            <div class="ac-field">
                <label for="days">Period</label>
                <select class="ac-select" id="days" name="days">
                    @foreach($presets as $preset)<option value="{{ $preset }}" @selected(empty($filters['from']) && (int) ($filters['days'] ?? 30) === $preset)>Last {{ $preset }} days</option>@endforeach
                </select>
            </div>
            <div class="ac-field" style="min-width:140px"><label for="from">Or from</label><input class="ac-input" id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>
            <div class="ac-field" style="min-width:140px"><label for="to">to</label><input class="ac-input" id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}"></div>
            <div class="ac-field grow">
                <label for="event">Event</label>
                <select class="ac-select" id="event" name="event">
                    <option value="">All events in your scope</option>
                    @foreach($events as $event)<option value="{{ $event->id }}" @selected(($filters['event'] ?? '') === (string) $event->id)>{{ $event->title }}</option>@endforeach
                </select>
            </div>
            <button class="ac-btn ac-btn-dark" type="submit" data-loading-text="Loading…">Update</button>
        </form>
        @unless($canExport)
            <p class="ac-hint" style="margin:0;padding:10px 18px">Financial figures and the CSV export need both the Reports and Payment oversight permissions.</p>
        @endunless
    </div>

    <div class="ac-grid ac-grid-2" style="margin-top:16px">
        <section class="ac-card" aria-labelledby="events-heading">
            <div class="ac-card-head"><h2 id="events-heading">Events</h2></div>
            <div class="ac-card-body">
                @forelse($eventStatus as $status => $count)
                    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--ac-line)"><span>{{ ucfirst($status) }}</span><strong>{{ number_format($count) }}</strong></div>
                @empty
                    <p style="margin:0;color:var(--ac-text-2)">No events in your scope.</p>
                @endforelse
                <p class="ac-definition">Source: events. Rule: every event in your scope by its current publication status (not limited to the period).</p>
            </div>
        </section>

        <section class="ac-card" aria-labelledby="sales-heading">
            <div class="ac-card-head"><h2 id="sales-heading">Ticket and food orders</h2></div>
            <div class="ac-card-body">
                <div class="ac-metric-value">{{ number_format($ordersTotal) }} <span style="font-size:14px;font-weight:600;color:var(--ac-text-2)">orders placed</span></div>
                <p class="ac-definition">Source: orders. Rule: orders created in the period. Each order is one ticket code, which can include passes and food.</p>
            </div>
        </section>
    </div>

    <section class="ac-card ac-section" aria-labelledby="status-heading" style="margin-top:16px">
        <div class="ac-card-head"><h2 id="status-heading">Payment status</h2></div>
        @if($ordersTotal === 0)
            <div class="ac-empty"><strong>No orders in this period</strong>Pick a longer period or another event.</div>
        @else
            <div class="ac-table-wrap"><table class="ac-table">
                <thead><tr><th scope="col">Status</th><th scope="col" class="num">Orders</th>@if($financial)<th scope="col" class="num">Order value</th><th scope="col" class="num">Amount verified</th>@endif</tr></thead>
                <tbody>
                @foreach($byGroup as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td class="num">{{ number_format($row['count']) }}</td>
                        @if($financial)
                            <td class="num">@forelse($row['amounts'] as $a){{ \App\Support\Money::formatMinor((int) $a->total, $a->currency) }} <span class="ac-cell-sub">{{ $a->currency }}</span><br>@empty — @endforelse</td>
                            <td class="num">@forelse($row['amounts'] as $a){{ \App\Support\Money::formatMinor((int) $a->paid, $a->currency) }} <span class="ac-cell-sub">{{ $a->currency }}</span><br>@empty — @endforelse</td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <p class="ac-definition" style="padding:0 18px 14px">Source: orders placed in the period, grouped by their current payment status. Order value is the order total; amount verified is what was confirmed received. Currencies are never added together.</p>
        @endif
    </section>

    <div class="ac-grid ac-grid-2" style="margin-top:16px">
        @if($financial)
            <section class="ac-card" aria-labelledby="refunds-heading">
                <div class="ac-card-head"><h2 id="refunds-heading">Refunds</h2></div>
                <div class="ac-card-body">
                    @forelse($refunds as $refund)
                        <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--ac-line)">
                            <span>{{ number_format($refund->orders) }} {{ \Illuminate\Support\Str::plural('order', $refund->orders) }}</span>
                            <strong>{{ \App\Support\Money::formatMinor((int) $refund->total, $refund->currency) }} {{ $refund->currency }}</strong>
                        </div>
                    @empty
                        <p style="margin:0;color:var(--ac-text-2)">No refunds on orders from this period.</p>
                    @endforelse
                    <p class="ac-definition">Source: orders. Rule: orders placed in the period with any money returned (fully or in part), valued at the amount refunded, per currency.</p>
                </div>
            </section>
        @endif

        <section class="ac-card" aria-labelledby="redemption-heading">
            <div class="ac-card-head"><h2 id="redemption-heading">Ticket redemption</h2></div>
            <div class="ac-card-body">
                <dl class="ac-dl">
                    <dt>Tickets scanned in</dt><dd>{{ number_format($redemptions['scans']) }}</dd>
                    <dt>Food portions handed over</dt><dd>{{ number_format($redemptions['collections']) }}</dd>
                </dl>
                <p class="ac-definition">Sources: successful gate scans recorded in the audit log (ticket redemption verified), and food handovers in the handover log. Both counted by when they happened in the period.</p>
            </div>
        </section>
    </div>

    <section class="ac-card ac-section" aria-labelledby="staff-heading" style="margin-top:16px">
        <div class="ac-card-head"><h2 id="staff-heading">Staff activity</h2></div>
        @if($staffActivity->isEmpty())
            <div class="ac-empty"><strong>No staff handovers in this period</strong></div>
        @else
            <div class="ac-table-wrap"><table class="ac-table">
                <thead><tr><th scope="col">Staff member</th><th scope="col">Station</th><th scope="col" class="num">Handovers</th><th scope="col" class="num">Portions</th></tr></thead>
                <tbody>
                @foreach($staffActivity as $row)
                    <tr><td>{{ $row->staff_name }}</td><td>{{ ucfirst($row->station) }}</td><td class="num">{{ number_format($row->handovers) }}</td><td class="num">{{ number_format($row->portions) }}</td></tr>
                @endforeach
                </tbody>
            </table></div>
            <p class="ac-definition" style="padding:0 18px 14px">Source: handover log. Rule: food handovers recorded by each staff member at each station in the period (top 20).</p>
        @endif
    </section>
@endsection
