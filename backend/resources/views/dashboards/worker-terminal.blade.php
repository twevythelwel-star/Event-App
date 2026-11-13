@extends('layouts.app')

@section('title', $workerRole->label() . ' Terminal — ' . ($event->title ?? 'Event Operations'))
@section('page-title', 'Staff terminal')

@php
    $may = fn (string $permission) => ($runsEvent ?? false) || $assignment->canPerform($permission);
    $supervises = ($runsEvent ?? false) || $workerRole->value === 'event_supervisor';
    $canScan = $may('scan_qr_tickets');
    $canCash = $may('record_cash_received');
    $canFood = $may('mark_quantities_collected');
    $shiftStatusLabel = $assignment->isCurrentlyActive() ? 'Active Shift' : ($assignment->isScheduled() ? 'Scheduled Shift' : 'Assigned');
@endphp

@section('content')
<div class="eb-dashboard-shell eb-stack worker-terminal">
    {{-- Unified Dashboard Header --}}
    <x-page-header
        :title="$workerRole->label() . ' Terminal'"
        badge="Staff operations"
        :description="'On-site operations desk for ' . ($event->title ?? 'Assigned Event') . ' · Station: ' . strtoupper($station)">
        <x-slot:actions>
            <div class="eb-cluster" style="gap: 0.5rem;">
                <span class="eb-badge {{ auth()->check() ? 'eb-badge-success' : 'eb-badge-neutral' }}" id="conn_badge">Online</span>
                <span class="eb-badge eb-badge-success">{{ $shiftStatusLabel }}</span>
                <span class="eb-badge eb-badge-neutral">Worker: {{ auth()->user()?->name ?? 'Staff Member' }}</span>
            </div>
        </x-slot:actions>
    </x-page-header>

    {{-- Unified Quick-Jump Section Pill Navigation --}}
    <nav class="eb-dashboard-nav-pills" aria-label="Staff terminal sections">
        <a href="#assigned-event" class="eb-dashboard-nav-pill">
            <x-icon name="events" size="sm" />
            <span>Assigned Event Info</span>
        </a>
        @if($canScan)
            <a href="#ticket-scanning" class="eb-dashboard-nav-pill">
                <x-icon name="qr" size="sm" />
                <span>Ticket Scanning</span>
            </a>
        @endif
        <a href="#operational-status" class="eb-dashboard-nav-pill">
            <x-icon name="shield" size="sm" />
            <span>Operational Status</span>
        </a>
        @if($canCash)
            <a href="#payment-verification" class="eb-dashboard-nav-pill">
                <x-icon name="payment" size="sm" />
                <span>Payment Verification</span>
            </a>
        @endif
        @if($canFood)
            <a href="#food-fulfillment" class="eb-dashboard-nav-pill">
                <x-icon name="utensils" size="sm" />
                <span>Food Fulfillment</span>
            </a>
        @endif
    </nav>

    {{-- 1. Assigned Event Information --}}
    <section id="assigned-event" class="eb-assigned-event-banner" aria-labelledby="assigned-event-heading">
        <div class="eb-assigned-event-thumb">
            <x-responsive-image :image="$event->coverImage()" alt="" sizes="72px" width="72" height="72" :eager="true"
                style="width: 100%; height: 100%; object-fit: cover;" />
        </div>
        <div style="min-width: 0;">
            <div class="eb-cluster" style="gap: 0.45rem; margin-bottom: 0.3rem;">
                <span class="eb-badge eb-badge-purple">{{ $workerRole->label() }}</span>
                <span class="eb-badge eb-badge-success">Station: {{ strtoupper($station) }}</span>
                @if($event->currency)<span class="eb-badge eb-badge-neutral">{{ $event->currency }}</span>@endif
            </div>
            <h2 id="assigned-event-heading" class="eb-text-section-title" style="margin: 0 0 0.2rem;">{{ $event->title }}</h2>
            <p class="eb-text-supporting" style="margin: 0;">
                Venue: {{ $event->venue_name ?? 'On-Site' }}{{ $event->city ? ', ' . $event->city : '' }}
                @if($event->date_str) &bull; {{ $event->date_str }}{{ $event->time_str ? ' · ' . $event->time_str : '' }} @endif
            </p>
        </div>
        <div style="text-align: right;">
            <span class="eb-text-label" style="display: block;">Session Worker</span>
            <strong style="font-size: 0.95rem; color: var(--color-text-primary);">{{ auth()->user()?->name ?? 'Staff Member' }}</strong>
            <div class="eb-help-text" style="margin-top: 0.15rem;">
                Shift: {{ $assignment->starts_at ? $assignment->starts_at->format('M j, g:i A') : 'Immediate' }}
                &mdash; {{ $assignment->expires_at ? $assignment->expires_at->format('M j, g:i A') : 'Open' }}
            </div>
        </div>
    </section>

    {{-- 2. Ticket Scanning — the primary gate action stays above status metrics and dashboards --}}
    @if ($canScan)
        @include('dashboards.partials.ticket-check-in')
    @endif

    {{-- 3. Operational Status --}}
    <section id="operational-status" class="eb-stack" aria-labelledby="operational-status-heading">
        <div class="eb-section-header">
            <div>
                <h2 id="operational-status-heading" class="eb-text-section-title" style="margin: 0;">Operational status</h2>
                <p class="eb-text-supporting" style="margin: 0;">Active terminal permissions, station assignment, and live event supervision metrics.</p>
            </div>
        </div>

        <div class="eb-grid-4">
            <x-metric-card
                label="Assigned station"
                :value="strtoupper($station)"
                :note="$workerRole->label()" />
            <x-metric-card
                label="Gate scanning"
                :value="$canScan ? 'Enabled' : 'Restricted'"
                :note="$canScan ? 'Authorized to scan admission QR passes' : 'Not assigned to this role'" />
            <x-metric-card
                label="Cash &amp; payment desk"
                :value="$canCash ? 'Enabled' : 'Restricted'"
                :note="$canCash ? 'Authorized to record cash receipts' : 'Not assigned to this role'" />
            <x-metric-card
                label="Food handover desk"
                :value="$canFood ? 'Enabled' : 'Restricted'"
                :note="$canFood ? 'Authorized for station ' . strtoupper($station) : 'Not assigned to this role'" />
        </div>

        @if ($supervises)
            <!-- EVENT SUPERVISOR: live figures for this event -->
            <div class="eb-panel card" style="margin: 0;">
                <div class="eb-card-header card-header">
                    <div>
                        <h3 class="eb-card-title card-title">Event Operations &amp; Supervision Desk</h3>
                        <p class="eb-card-subtitle card-subtitle">Live figures for this event. Reload the page to refresh.</p>
                    </div>
                    <span class="eb-badge eb-badge-info">Supervisor View</span>
                </div>

                @php
                    $admittedPct = ($stats['tickets_valid'] ?? 0) > 0 ? (int) round($stats['tickets_admitted'] / $stats['tickets_valid'] * 100) : 0;
                    $portionsLeft = max(0, ($stats['portions_ordered'] ?? 0) - ($stats['portions_collected'] ?? 0));
                @endphp
                <div class="eb-grid-3">
                    <x-metric-card
                        label="GATE ADMISSIONS"
                        :value="($stats['tickets_admitted'] ?? 0) . ' / ' . ($stats['tickets_valid'] ?? 0)"
                        :note="$admittedPct . '% of paid tickets admitted'" />
                    <x-metric-card
                        label="FOOD HANDED OVER"
                        :value="($stats['portions_collected'] ?? 0) . ' / ' . ($stats['portions_ordered'] ?? 0)"
                        :note="$portionsLeft . ' paid portions still to hand over'" />
                    <x-metric-card
                        label="STAFF ON SHIFT"
                        :value="$stats['staff_on_shift'] ?? 0"
                        note="Assignments active right now" />
                </div>
            </div>
        @endif
    </section>

    {{-- 4. Payment Verification --}}
    @if ($canCash)
        <section id="payment-verification" class="eb-panel card" aria-labelledby="payment-verification-heading">
            <div class="eb-card-header card-header">
                <div>
                    <h2 id="payment-verification-heading" class="eb-card-title card-title">Cash Collection &amp; Transfer Verification</h2>
                    <p class="eb-card-subtitle card-subtitle">Record physical cash receipts with mandatory immutable audit trail.</p>
                </div>
                <span class="eb-badge eb-badge-warning">Payment Desk</span>
            </div>

            <form id="cash_form" onsubmit="event.preventDefault(); recordPayment();" data-url="{{ route('worker.cash', $event->id) }}" class="eb-grid-2" style="gap: 1rem;">
                <div class="eb-form-group" style="margin: 0;">
                    <label class="eb-label" for="cash_order_num">Order Number</label>
                    <input type="text" id="cash_order_num" class="eb-input" placeholder="ORD-2026-XXXX" required>
                </div>

                <div class="eb-form-group" style="margin: 0;">
                    <label class="eb-label" for="cash_amount">Cash Received ({{ $event->currency ?: 'USD' }})</label>
                    <input type="number" step="0.01" min="0.01" id="cash_amount" class="eb-input" placeholder="0.00" required>
                </div>

                <div class="eb-form-group" style="grid-column: 1 / -1; margin: 0;">
                    <label class="eb-label" for="cash_notes">Receipt Notes (Optional)</label>
                    <input type="text" id="cash_notes" class="eb-input" placeholder="e.g. Paid in cash at Gate B register">
                </div>

                <div style="grid-column: 1 / -1; display: flex; justify-content: flex-end;">
                    <button type="submit" id="cash_submit" class="eb-btn eb-btn-primary">
                        <x-icon name="payment" size="sm" />
                        <span>Submit Cash Receipt (Record Audit Trail)</span>
                    </button>
                </div>
            </form>
            <p id="cash_result" role="status" aria-live="polite" class="eb-help-text" style="margin-top: 1rem; font-size: 0.9rem;"></p>
        </section>
    @endif

    {{-- 5. Food Fulfillment --}}
    @if ($canFood)
        <section id="food-fulfillment" class="eb-panel card" aria-labelledby="food-fulfillment-heading">
            <div class="eb-card-header card-header">
                <div>
                    <h2 id="food-fulfillment-heading" class="eb-card-title card-title">Food Handover</h2>
                    <p class="eb-card-subtitle card-subtitle">Scan the guest's ticket QR or type their ticket or order number to see what they are owed.</p>
                </div>
                <span class="eb-badge eb-badge-success">Station: {{ strtoupper($station) }}</span>
            </div>

            <form id="lookup_form" data-url="{{ route('worker.lookup', $event->id) }}" data-collect-url="{{ route('worker.collect', $event->id) }}"
                onsubmit="event.preventDefault(); lookupOrder();" class="eb-cluster" style="gap: 0.75rem; align-items: flex-end;">
                <div class="eb-form-group" style="flex: 1 1 16rem; margin: 0;">
                    <label class="eb-label" for="lookup_code">Ticket QR, ticket code or order number</label>
                    <input type="text" id="lookup_code" class="eb-input" autocomplete="off" spellcheck="false" required style="font-family: var(--font-mono); font-size: 1rem;">
                </div>
                <button type="submit" id="lookup_submit" class="eb-btn eb-btn-primary">
                    <x-icon name="search" size="sm" />
                    <span>Find order</span>
                </button>
            </form>

            <p id="food_message" role="status" aria-live="polite" class="eb-help-text" style="margin-top: 1rem; font-size: 0.9rem;"></p>
            <div id="food_order" hidden style="margin-top: 0.75rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 1rem; background: rgba(7, 15, 28, 0.72);">
                <div class="eb-cluster" style="justify-content: space-between; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <strong id="food_order_title" style="font-size: 1rem; color: var(--color-text-primary);"></strong>
                    <span id="food_order_badge" class="eb-badge"></span>
                </div>
                <ul id="food_items" style="list-style: none; margin: 0; padding: 0; display: grid; gap: 0.5rem;"></ul>
            </div>
        </section>
    @endif
</div>

<script>
// One scanner-session id per device/browser session: sent on every terminal call so audit
// records can be correlated to a device without storing anything reusable or permanent.
const SCANNER_SESSION_KEY = 'eventbite:scanner_session';
function scannerSessionId() {
    try {
        let id = sessionStorage.getItem(SCANNER_SESSION_KEY);
        if (!id) {
            id = crypto.randomUUID();
            sessionStorage.setItem(SCANNER_SESSION_KEY, id);
        }
        return id;
    } catch (_) { return null; }
}

function csrfHeaders() {
    const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
    };
    const sid = scannerSessionId();
    if (sid) headers['X-EventBite-Scanner-Session'] = sid;
    return headers;
}

async function postJson(url, body) {
    const response = await fetch(url, { method: 'POST', headers: csrfHeaders(), body: JSON.stringify(body) });
    const data = await response.json().catch(() => ({}));
    return { ok: response.ok, status: response.status, data };
}

// Ticket check-in: the verdict shown is the server's. Nothing is shown as admitted unless the server admitted it.
async function validateTicket() {
    const form = document.getElementById('scan_form');
    const input = document.getElementById('ticket_input');
    const button = document.getElementById('scan_submit');
    const box = document.getElementById('scan_result');
    const verdict = document.getElementById('scan_verdict');
    const details = document.getElementById('scan_details');
    const code = input.value.trim();
    if (!code) return;

    const requestKey = `eventbite:scan:{{ $event->id }}`;
    const codeHashBytes = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(code));
    const codeHash = Array.from(new Uint8Array(codeHashBytes), b => b.toString(16).padStart(2, '0')).join('');
    let scanRequestId = null;
    try {
        const saved = JSON.parse(sessionStorage.getItem(requestKey) || 'null');
        if (saved?.code_hash === codeHash && saved?.id) scanRequestId = saved.id;
    } catch (_) {}
    if (!scanRequestId) {
        scanRequestId = crypto.randomUUID();
        try { sessionStorage.setItem(requestKey, JSON.stringify({ code_hash: codeHash, id: scanRequestId })); } catch (_) {}
    }

    button.disabled = true;
    box.hidden = false;
    box.dataset.scanState = 'checking';
    box.style.borderColor = 'var(--color-border)';
    verdict.style.color = 'var(--color-text-primary)';
    verdict.textContent = 'Checking…';
    details.textContent = '';

    try {
        const { ok, status, data } = await postJson(form.dataset.url, { ticket_code: code, scan_request_id: scanRequestId });
        if (status < 500) { try { sessionStorage.removeItem(requestKey); } catch (_) {} }
        const admitted = ok && data.success === true;
        box.dataset.scanState = admitted ? 'admitted' : 'rejected';
        box.style.borderColor = admitted ? 'var(--color-success-border, #34d399)' : 'var(--color-error-border, #f87171)';
        verdict.style.color = admitted ? 'var(--color-success-text, #6ee7b7)' : 'var(--color-error-text, #fca5a5)';
        if (admitted) {
            verdict.textContent = data.replayed ? '✓ ADMIT (confirmed retry)' : '✓ ADMIT';
            const guest = data.ticket?.attendee ? `Guest ${data.ticket.attendee.sequence} of ${data.ticket.attendee.of} · ${data.ticket.attendee.ticket_number}` : null;
            details.textContent = [guest, data.ticket?.customer_name, data.ticket?.order_number].filter(Boolean).join(' • ');
        } else if (data.promotional_qr_rejected) {
            verdict.textContent = '✕ PROMOTIONAL EVENT QR — DO NOT ADMIT';
            details.textContent = data.rejection_reason || 'Public event QR codes are for event discovery only and cannot admit guests.';
        } else if (data.already_admitted) {
            verdict.textContent = '✕ ALREADY USED — DO NOT ADMIT';
            details.textContent = (data.rejection_reason || '') + ' (Screenshot/duplicate scan detected — escalate to Supervisor for purchaser identity verification if disputed.)';
        } else {
            verdict.textContent = '✕ DO NOT ADMIT';
            details.textContent = data.rejection_reason || data.error || data.message || 'The ticket could not be verified.';
        }
    } catch (e) {
        box.dataset.scanState = 'offline';
        box.style.borderColor = 'var(--color-warning-border, #fbbf24)';
        verdict.style.color = 'var(--color-warning-text, #fcd34d)';
        verdict.textContent = 'CONNECTION LOST — NOT ADMITTED';
        details.textContent = 'The scan is still in the input. Reconnect and retry this same code; do not admit based on an offline result.';
    } finally {
        button.disabled = false;
        if (navigator.onLine && verdict.textContent.startsWith('✓ ADMIT')) input.value = '';
        input.focus();
    }
}

function updateScannerConnection() {
    const status = document.getElementById('scanner_connection');
    const badge = document.getElementById('conn_badge');
    if (badge) {
        badge.textContent = navigator.onLine ? 'Online' : 'Offline';
        badge.className = 'eb-badge ' + (navigator.onLine ? 'eb-badge-success' : 'eb-badge-error');
    }
    if (!status) return;
    status.textContent = navigator.onLine
        ? 'Connected. Each admission is verified and recorded by the server.'
        : 'Offline. Ticket lookup may be unavailable; scans cannot admit guests until the server confirms them.';
}
window.addEventListener('online', updateScannerConnection);
window.addEventListener('offline', updateScannerConnection);
updateScannerConnection();

// Food station: look up an order, then hand over portions one line at a time.
let currentFoodOrder = null;

function foodMessage(text, isError) {
    const el = document.getElementById('food_message');
    el.style.color = isError ? 'var(--color-error-text, #fca5a5)' : 'var(--color-success-text, #6ee7b7)';
    el.textContent = text;
}

function renderFoodOrder(order) {
    currentFoodOrder = order;
    const panel = document.getElementById('food_order');
    const list = document.getElementById('food_items');
    const badge = document.getElementById('food_order_badge');
    panel.hidden = false;
    document.getElementById('food_order_title').textContent = `${order.order_number} • ${order.customer_first_name}`;
    badge.textContent = order.payment_verified ? 'Payment verified' : 'Payment not verified — do not hand over';
    badge.className = 'eb-badge ' + (order.payment_verified ? 'eb-badge-success' : 'eb-badge-error');

    list.replaceChildren();
    if (order.items.length === 0) {
        const li = document.createElement('li');
        li.textContent = 'This order has no food.';
        list.append(li);
        return;
    }

    for (const item of order.items) {
        const li = document.createElement('li');
        li.style.cssText = 'display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; flex-wrap: wrap; background: rgba(255,255,255,0.03); padding: 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border);';

        const info = document.createElement('div');
        const name = document.createElement('strong');
        name.textContent = item.name;
        const meta = document.createElement('div');
        meta.style.cssText = 'font-size: 0.8rem; color: var(--color-text-secondary);';
        const optionsStr = Array.isArray(item.options) ? item.options.join(', ') : (item.options || '');
        meta.textContent = `Ordered ${item.quantity} • Handed over ${item.collected} • Remaining ${item.remaining}`
            + (optionsStr.trim() ? ` • ${optionsStr.trim()}` : '')
            + (item.at_this_station ? '' : ` • Served at the ${item.category} station`);
        info.append(name, meta);

        const controls = document.createElement('div');
        controls.style.cssText = 'display: flex; gap: 0.5rem; align-items: center;';
        const canHandOver = order.payment_verified && item.at_this_station && item.remaining > 0;
        const qty = document.createElement('input');
        qty.type = 'number';
        qty.min = '1';
        qty.max = String(Math.max(1, item.remaining));
        qty.value = '1';
        qty.disabled = !canHandOver;
        qty.setAttribute('aria-label', `Portions of ${item.name} to hand over`);
        qty.className = 'eb-input';
        qty.style.cssText = 'width: 68px; min-height: 36px; padding: 0.35rem; text-align: center;';
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'eb-btn eb-btn-primary eb-btn-sm';
        button.textContent = item.remaining > 0 ? 'Hand over' : 'All handed over';
        button.disabled = !canHandOver;
        button.addEventListener('click', () => handOver(item, qty, button));
        controls.append(qty, button);

        li.append(info, controls);
        list.append(li);
    }
}

async function lookupOrder() {
    const form = document.getElementById('lookup_form');
    const input = document.getElementById('lookup_code');
    const button = document.getElementById('lookup_submit');
    const code = input.value.trim();
    if (!code) return;

    button.disabled = true;
    foodMessage('Looking up…', false);
    try {
        const { ok, data } = await postJson(form.dataset.url, { code });
        if (ok && data.success) {
            foodMessage('', false);
            renderFoodOrder(data.order);
        } else {
            document.getElementById('food_order').hidden = true;
            foodMessage(data.error || data.message || 'Order not found.', true);
        }
    } catch (e) {
        foodMessage('Could not reach the server.', true);
    } finally {
        button.disabled = false;
        input.value = '';
        input.focus();
    }
}

async function handOver(item, qtyInput, button) {
    const form = document.getElementById('lookup_form');
    button.disabled = true;
    try {
        const { ok, data } = await postJson(form.dataset.collectUrl, {
            order_id: currentFoodOrder.id,
            order_item_id: item.id,
            quantity: parseInt(qtyInput.value, 10) || 1,
        });
        if (ok && data.success) {
            const updated = data.order.items.find((i) => i.id === item.id);
            foodMessage(`Handed over ${item.name}. ${updated ? updated.remaining : 0} remaining.`, false);
            renderFoodOrder(data.order);
        } else {
            foodMessage(data.error || data.message || 'The handover was not recorded.', true);
            button.disabled = false;
        }
    } catch (e) {
        foodMessage('Could not reach the server. The handover was not recorded.', true);
        button.disabled = false;
    }
}

async function recordPayment() {
    const form = document.getElementById('cash_form');
    const button = document.getElementById('cash_submit');
    const result = document.getElementById('cash_result');
    button.disabled = true;
    result.textContent = 'Recording…';
    try {
        const response = await fetch(form.dataset.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({
                order_number: document.getElementById('cash_order_num').value.trim(),
                amount: document.getElementById('cash_amount').value,
                notes: document.getElementById('cash_notes').value,
            }),
        });
        const data = await response.json().catch(() => ({}));
        if (response.ok && data.success) {
            result.style.color = 'var(--color-success-text, #6ee7b7)';
            result.textContent = `Receipt ${data.receipt_number} recorded for ${data.order_number}. `
                + `Paid ${data.currency} ${data.amount_paid.toFixed(2)}; balance ${data.currency} ${data.balance.toFixed(2)} (${data.status.replace('_', ' ')}).`;
            form.reset();
        } else {
            result.style.color = 'var(--color-error-text, #fca5a5)';
            result.textContent = data.error || data.message || 'The cash receipt was not recorded.';
        }
    } catch (e) {
        result.style.color = 'var(--color-error-text, #fca5a5)';
        result.textContent = 'Could not reach the server. The cash receipt was not recorded.';
    } finally {
        button.disabled = false;
    }
}
</script>
@endsection
