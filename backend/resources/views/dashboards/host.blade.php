@extends('layouts.app')

@section('title', 'Host dashboard — EventBite')
@section('page-title', 'Host dashboard')

@php
    $eventTitles = collect($events ?? [])->pluck('title', 'id');
    $money = fn ($order) => \App\Support\Money::ofMinor((int) $order->total_minor, $order->currency ?: 'USD')->display();
@endphp

@section('content')
<div class="eb-stack" style="gap: var(--layout-section-gap);">
    <x-page-header
        title="Host dashboard"
        badge="Event host"
        description="Your events, orders waiting on you, food to prepare and the staff working your events.">
        <x-slot:actions>
            <x-button variant="primary" icon="plus" :href="route('events.create')">Create event</x-button>
        </x-slot:actions>
    </x-page-header>

    <section class="eb-grid-4" aria-label="Summary">
        <x-metric-card label="Events" :value="$total_events ?? 0" note="All your events, any status" href="#events" />
        <x-metric-card label="Orders" :value="$total_orders ?? 0" :note="($paid_orders_count ?? 0) . ' paid'" href="#orders" />
        <x-metric-card label="Payments to review" :value="$pending_verification_count ?? 0" note="Transfers and confirmations waiting on you" href="#review" />
        <x-metric-card label="Food portions to prepare" :value="$prep_with_buffer ?? 0" :note="'Paid portions not yet handed over, plus ' . ($buffer_percent ?? 10) . '% buffer'" href="#forecast" />
    </section>

    {{-- Events --}}
    <section id="events" class="eb-stack">
        <div class="eb-section-header">
            <div>
                <h2 class="eb-text-section-title" style="margin: 0;">Your events</h2>
                <p class="eb-text-supporting" style="margin: 0;">Edit details, tickets and menus, manage staff, or open the on-site terminal.</p>
            </div>
        </div>
        <x-data-table caption="Your events" :columns="['Event', 'Where', 'When', 'Status', 'Ordering', ['label' => 'Actions', 'numeric' => true]]"
                      :empty="collect($events ?? [])->isEmpty()" empty-title="No events yet" empty-text="Create your first event to start selling tickets and taking food orders.">
            @foreach($events ?? [] as $event)
                <tr>
                    <td>
                        <div class="eb-cluster" style="flex-wrap: nowrap;">
                            <div style="width: 44px; height: 44px; border-radius: var(--radius-sm); overflow: hidden; background: var(--color-background-secondary); border: 1px solid var(--color-border); flex-shrink: 0;">
                                @if($event->display_image_url)
                                    <img src="{{ $event->display_image_url }}" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                                @endif
                            </div>
                            <a href="{{ route('events.show', $event->id) }}" style="font-weight: 600; color: var(--color-text-primary);">{{ $event->title }}</a>
                        </div>
                    </td>
                    <td>
                        <div>{{ $event->venue_name ?: 'Venue to be announced' }}</div>
                        <div class="eb-help-text">{{ \App\Support\EventListing::location($event) }}</div>
                    </td>
                    <td>
                        <div>{{ $event->date_str ?: 'Date to be announced' }}</div>
                        <div class="eb-help-text">{{ $event->time_str }}</div>
                    </td>
                    <td><x-status-indicator :status="$event->status ?? 'draft'" /></td>
                    <td>
                        <span class="eb-badge {{ $event->is_ordering_open ? 'eb-badge-success' : '' }}">{{ $event->is_ordering_open ? 'Open' : 'Closed' }}</span>
                    </td>
                    <td class="eb-table-num" style="white-space: nowrap;">
                        <x-button variant="secondary" size="sm" :href="route('events.edit', $event->id)">Edit</x-button>
                        <x-button variant="secondary" size="sm" :href="route('host.events.staff', $event->id)">Staff</x-button>
                        <x-button variant="ghost" size="sm" :href="route('worker.terminal', $event->id)">Terminal</x-button>
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </section>

    {{-- Payments waiting for a decision --}}
    <section id="review" class="eb-stack">
        <div class="eb-section-header">
            <div>
                <h2 class="eb-text-section-title" style="margin: 0;">Payments to review</h2>
                <p class="eb-text-supporting" style="margin: 0;">Check the reference against your account before verifying. Tickets are issued only once a payment is verified.</p>
            </div>
        </div>
        <p id="review-result" role="status" aria-live="polite" class="eb-help-text" hidden></p>
        <x-data-table caption="Payments to review" :columns="['Order', 'Event', 'Customer', 'Method and reference', ['label' => 'Amount', 'numeric' => true], 'Status', ['label' => 'Decision', 'numeric' => true]]"
                      :empty="collect($review_orders ?? [])->isEmpty()" empty-title="Nothing to review" empty-text="Bank transfer and Zelle confirmations that customers submit appear here.">
            @foreach($review_orders ?? [] as $order)
                <tr>
                    <td style="font-family: var(--font-mono);">{{ $order->order_number }}</td>
                    <td>{{ $eventTitles[$order->event_id] ?? '—' }}</td>
                    <td>{{ $order->customer_name }}</td>
                    <td>
                        <div>{{ ucwords(str_replace('_', ' ', (string) $order->method)) }}</div>
                        <div class="eb-help-text">
                            {{ $order->payment_reference ? 'Ref. ' . $order->payment_reference : 'No reference given' }}{{ $order->sender_name ? ' · from ' . $order->sender_name : '' }}
                        </div>
                    </td>
                    <td class="eb-table-num">{{ $money($order) }}</td>
                    <td><x-status-indicator :status="$order->status" /></td>
                    <td class="eb-table-num" style="white-space: nowrap;">
                        <button type="button" class="eb-btn eb-btn-primary eb-btn-sm" data-payment-decision="paid" data-order="{{ $order->id }}" data-number="{{ $order->order_number }}">Verify</button>
                        <button type="button" class="eb-btn eb-btn-destructive eb-btn-sm" data-payment-decision="rejected" data-order="{{ $order->id }}" data-number="{{ $order->order_number }}">Reject</button>
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </section>

    {{-- Recent orders --}}
    <section id="orders" class="eb-stack">
        <div class="eb-section-header">
            <div>
                <h2 class="eb-text-section-title" style="margin: 0;">Recent orders</h2>
                <p class="eb-text-supporting" style="margin: 0;">The ten newest orders across your events.</p>
            </div>
        </div>
        <x-data-table caption="Recent orders" :columns="['Order', 'Event', 'Customer', 'Method', ['label' => 'Total', 'numeric' => true], 'Status', 'Placed']"
                      :empty="collect($recent_orders ?? [])->isEmpty()" empty-title="No orders yet" empty-text="Orders appear here as soon as customers check out.">
            @foreach($recent_orders ?? [] as $order)
                <tr>
                    <td style="font-family: var(--font-mono);">{{ $order->order_number }}</td>
                    <td>{{ $eventTitles[$order->event_id] ?? '—' }}</td>
                    <td>{{ $order->customer_name }}</td>
                    <td>{{ ucwords(str_replace('_', ' ', (string) $order->method)) }}</td>
                    <td class="eb-table-num">{{ $money($order) }}</td>
                    <td><x-status-indicator :status="$order->status" /></td>
                    <td class="eb-help-text">{{ optional($order->created_at)->format('M j, g:i A') }}</td>
                </tr>
            @endforeach
        </x-data-table>
    </section>

    <div class="eb-grid-2">
        {{-- Money --}}
        <section id="finances" class="eb-panel card" style="margin: 0;">
            <div class="eb-card-header card-header">
                <div>
                    <h2 class="eb-card-title card-title">Money</h2>
                    <p class="eb-card-subtitle card-subtitle">Per currency. Unverified transfers are counted as still owed, never as received.</p>
                </div>
            </div>
            @forelse(($financials ?? []) as $code => $sum)
                @if(count($financials) > 1)<h3 class="eb-text-label" style="margin: var(--space-3) 0 var(--space-2);">{{ $code }}</h3>@endif
                <dl class="eb-grid-2" style="gap: var(--space-3); margin: 0;">
                    @foreach(['received' => 'Received', 'pending' => 'Still owed', 'refunded' => 'Refunded', 'cash' => 'Cash recorded on site', 'gross' => 'All orders (incl. unpaid)', 'net' => 'Received, net of refunds'] as $key => $label)
                        <div>
                            <dt class="eb-text-label">{{ $label }}</dt>
                            <dd class="eb-text-price" style="margin: 0;">{{ $sum[$key]['formatted'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            @empty
                <x-empty-state icon="payment" title="No money recorded yet" description="Figures appear once you have orders." />
            @endforelse
        </section>

        {{-- Food preparation --}}
        <section id="forecast" class="eb-panel card" style="margin: 0;">
            <div class="eb-card-header card-header">
                <div>
                    <h2 class="eb-card-title card-title">Food to prepare</h2>
                    <p class="eb-card-subtitle card-subtitle">Portions on paid orders not yet handed over, plus your {{ $buffer_percent ?? 10 }}% buffer.</p>
                </div>
            </div>
            @if(collect($prep_items ?? [])->isEmpty())
                <x-empty-state icon="utensils" title="No paid food orders yet" description="Menu items appear here as customers pay for food." />
            @else
                <div class="eb-table-wrap">
                    <table class="eb-table">
                        <caption class="sr-only">Food to prepare</caption>
                        <thead><tr><th scope="col">Item</th><th scope="col" class="eb-table-num">Sold</th><th scope="col" class="eb-table-num">Handed over</th><th scope="col" class="eb-table-num">Prepare</th></tr></thead>
                        <tbody>
                            @foreach($prep_items as $row)
                                <tr>
                                    <td><div>{{ $row['item'] }}</div><div class="eb-help-text">{{ $row['event'] }}</div></td>
                                    <td class="eb-table-num">{{ $row['sold'] }}</td>
                                    <td class="eb-table-num">{{ $row['collected'] }}</td>
                                    <td class="eb-table-num" style="font-weight: 700;">{{ $row['prep_target'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

    {{-- Staff --}}
    <section id="workers" class="eb-stack">
        <div class="eb-section-header">
            <div>
                <h2 class="eb-text-section-title" style="margin: 0;">Staff ({{ $active_workers_count ?? 0 }} active)</h2>
                <p class="eb-text-supporting" style="margin: 0;">Assign new staff from each event's Staff page. Revoking ends access immediately.</p>
            </div>
        </div>
        <x-data-table caption="Staff on your events" :columns="['Staff member', 'Event', 'Position', 'Station', 'Status', ['label' => 'Action', 'numeric' => true]]"
                      :empty="collect($workers ?? [])->isEmpty()" empty-title="No staff assigned yet" empty-text="Open an event's Staff page to add payment clerks, ticket checkers, food station workers or a supervisor.">
            @foreach($workers ?? [] as $worker)
                <tr>
                    <td>
                        <div style="font-weight: 600;">{{ $worker->user->name ?? $worker->invite_email ?? 'Invited staff member' }}</div>
                        <div class="eb-help-text">{{ $worker->user->email ?? $worker->invite_email }}</div>
                    </td>
                    <td>{{ $eventTitles[$worker->event_id] ?? '—' }}</td>
                    <td>{{ $worker->worker_role->label() }}</td>
                    <td>{{ ($worker->station_assignment ?? 'all') === 'all' ? 'All stations' : ucfirst(str_replace('_', ' ', $worker->station_assignment)) }}</td>
                    <td>
                        @php
                            [$label, $class] = match (true) {
                                $worker->isRevoked() => ['Revoked', 'eb-badge-error'],
                                $worker->isExpired() => ['Expired', ''],
                                $worker->isScheduled() => ['Scheduled', 'eb-badge-info'],
                                $worker->isCurrentlyActive() => ['Active', 'eb-badge-success'],
                                default => [ucfirst(str_replace('_', ' ', (string) $worker->status)), 'eb-badge-warning'],
                            };
                        @endphp
                        <span class="eb-badge {{ $class }}">{{ $label }}</span>
                    </td>
                    <td class="eb-table-num">
                        @if(!$worker->isRevoked() && !$worker->isExpired())
                            <form method="POST" action="{{ route('host.events.staff.revoke', [$worker->event_id, $worker->id]) }}"
                                  onsubmit="return confirm('Revoke this person\'s access now? They lose access immediately.');" style="display: inline;">
                                @csrf
                                <input type="hidden" name="reason" value="Revoked from the host dashboard">
                                <button type="submit" class="eb-btn eb-btn-destructive eb-btn-sm">Revoke</button>
                            </form>
                        @else
                            <span class="eb-help-text">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </section>
</div>

<script>
    // Payment decisions go through the same validated service the API uses; the page reloads to show the result.
    document.querySelectorAll('[data-payment-decision]').forEach(function (button) {
        button.addEventListener('click', async function () {
            const status = button.dataset.paymentDecision;
            const number = button.dataset.number;
            let body = { status: status };
            if (status === 'rejected') {
                const reason = prompt('Why is the payment for ' + number + ' being rejected? The customer sees this reason.');
                if (reason === null) return;
                if (!reason.trim()) { alert('Please give a reason.'); return; }
                body.rejection_reason = reason.trim();
            } else if (!confirm('Verify payment for ' + number + '? Only do this once the money is in your account.')) {
                return;
            }
            const result = document.getElementById('review-result');
            button.disabled = true;
            try {
                const response = await fetch(@json(url('/api/v1/operations/orders')) + '/' + encodeURIComponent(button.dataset.order) + '/payment', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                    body: JSON.stringify(body),
                });
                const data = await response.json().catch(() => ({}));
                if (response.ok) { window.location.reload(); return; }
                result.hidden = false;
                result.textContent = data.message || data.error || 'The decision was not saved.';
                result.style.color = 'var(--color-error-text)';
            } catch (err) {
                result.hidden = false;
                result.textContent = 'Could not reach the server. Check your connection and try again.';
                result.style.color = 'var(--color-error-text)';
            } finally {
                button.disabled = false;
            }
        });
    });
</script>
@endsection
