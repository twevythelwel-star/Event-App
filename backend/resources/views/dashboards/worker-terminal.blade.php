@extends('layouts.app')

@section('title', $workerRole->label() . ' Terminal — ' . ($event->title ?? 'Event Operations'))
@section('page-title', 'Staff terminal')

@section('content')
<div class="worker-terminal" style="max-width: 900px; margin: 0 auto;">
    <!-- Terminal Header Bar -->
    <div style="background: linear-gradient(135deg, rgba(30, 41, 59, 0.95), rgba(15, 23, 42, 0.95)); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-md);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: gap; gap: 1rem;">
            <div>
                <span class="badge" style="background: rgba(99, 102, 241, 0.2); color: #818cf8; margin-bottom: 0.5rem;">
                    {{ $workerRole->label() }}
                </span>
                <h1 style="font-size: 1.5rem; font-weight: 800; margin: 0.25rem 0;">{{ $event->title }}</h1>
                <p style="color: var(--text-secondary); font-size: 0.85rem;">
                    Venue: {{ $event->venue_name ?? 'On-Site' }} &bull; Station: <strong style="color: var(--accent-emerald);">{{ strtoupper($station) }}</strong>
                </p>
            </div>
            <div style="text-align: right;">
                <span style="font-size: 0.75rem; color: var(--text-secondary); display: block;">Session Worker:</span>
                <strong style="font-size: 0.9rem;">{{ auth()->user()?->name ?? 'Staff Member' }}</strong>
            </div>
        </div>
    </div>

    <!-- Dynamic Interface Based on Worker Role -->
    @if ($workerRole->value === 'ticket_checker')
        @include('dashboards.partials.ticket-check-in')

    @elseif ($workerRole->value === 'payment_clerk')
        <!-- 💵 PAYMENT CLERK INTERFACE -->
        <div class="card">
            <div class="card-header">
                <h2 style="font-size: 1.25rem; font-weight: 700;">Cash Collection & Transfer Verification</h2>
                <p style="font-size: 0.85rem; color: var(--text-secondary);">Record physical cash receipts with mandatory immutable audit trail.</p>
            </div>

            <form id="cash_form" onsubmit="event.preventDefault(); recordPayment();" data-url="{{ route('worker.cash', $event->id) }}" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                <div>
                    <label for="cash_order_num" style="font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 0.35rem;">Order Number</label>
                    <input type="text" id="cash_order_num" placeholder="ORD-2026-XXXX" required
                        style="width: 100%; padding: 0.65rem 0.85rem; border-radius: var(--radius-md); background: rgba(0,0,0,0.25); border: 1px solid var(--border-color); color: var(--text-primary);">
                </div>

                <div>
                    <label for="cash_amount" style="font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 0.35rem;">Cash Received ({{ $event->currency ?: 'USD' }})</label>
                    <input type="number" step="0.01" min="0.01" id="cash_amount" placeholder="0.00" required
                        style="width: 100%; padding: 0.65rem 0.85rem; border-radius: var(--radius-md); background: rgba(0,0,0,0.25); border: 1px solid var(--border-color); color: var(--text-primary);">
                </div>

                <div style="grid-column: span 2;">
                    <label for="cash_notes" style="font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 0.35rem;">Receipt Notes (Optional)</label>
                    <input type="text" id="cash_notes" placeholder="e.g. Paid in cash at Gate B register"
                        style="width: 100%; padding: 0.65rem 0.85rem; border-radius: var(--radius-md); background: rgba(0,0,0,0.25); border: 1px solid var(--border-color); color: var(--text-primary);">
                </div>

                <div style="grid-column: span 2; display: flex; justify-content: flex-end;">
                    <button type="submit" id="cash_submit" class="btn btn-primary" style="padding: 0.75rem 1.5rem; font-weight: 700;">
                        Submit Cash Receipt (Record Audit Trail)
                    </button>
                </div>
            </form>
            <p id="cash_result" role="status" aria-live="polite" style="margin-top: 1rem; font-size: 0.9rem;"></p>
        </div>

    @elseif ($workerRole->value === 'food_station_worker')
        <!-- FOOD STATION: look up the order, then hand over portions; the server caps quantities and refuses unpaid orders -->
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
                <div>
                    <h2 style="font-size: 1.25rem; font-weight: 700;">Food Handover</h2>
                    <p style="font-size: 0.85rem; color: var(--text-secondary);">Scan the guest's ticket QR or type their ticket or order number to see what they are owed.</p>
                </div>
                <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: var(--accent-emerald);">
                    Station: {{ strtoupper($station) }}
                </span>
            </div>

            <form id="lookup_form" data-url="{{ route('worker.lookup', $event->id) }}" data-collect-url="{{ route('worker.collect', $event->id) }}"
                onsubmit="event.preventDefault(); lookupOrder();" style="display: flex; gap: 0.75rem; margin-top: 1rem; flex-wrap: wrap;">
                <div style="flex: 1 1 14rem;">
                    <label for="lookup_code" style="font-size: 0.8rem; color: var(--text-secondary); display: block; margin-bottom: 0.25rem;">Ticket QR, ticket code or order number</label>
                    <input type="text" id="lookup_code" autocomplete="off" spellcheck="false" autofocus required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); background: rgba(0,0,0,0.25); border: 1px solid var(--border-color); color: var(--text-primary); font-family: monospace; font-size: 1rem;">
                </div>
                <button type="submit" id="lookup_submit" class="btn btn-primary" style="align-self: flex-end; padding: 0.75rem 1.25rem; font-weight: 700;">Find order</button>
            </form>

            <p id="food_message" role="status" aria-live="polite" style="margin-top: 1rem; font-size: 0.9rem;"></p>
            <div id="food_order" hidden style="margin-top: 0.5rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1rem; background: rgba(0,0,0,0.2);">
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 0.75rem;">
                    <strong id="food_order_title" style="font-size: 1rem;"></strong>
                    <span id="food_order_badge" class="badge"></span>
                </div>
                <ul id="food_items" style="list-style: none; margin: 0; padding: 0; display: grid; gap: 0.5rem;"></ul>
            </div>
        </div>

        @else
        @include('dashboards.partials.ticket-check-in')

        <!-- EVENT SUPERVISOR: live figures for this event -->
        <div class="card">
            <div class="card-header">
                <h2 style="font-size: 1.25rem; font-weight: 700;">Event Operations & Supervision Desk</h2>
                <p style="font-size: 0.85rem; color: var(--text-secondary);">Live figures for this event. Reload the page to refresh.</p>
            </div>

            @php
                $admittedPct = ($stats['tickets_valid'] ?? 0) > 0 ? (int) round($stats['tickets_admitted'] / $stats['tickets_valid'] * 100) : 0;
                $portionsLeft = max(0, ($stats['portions_ordered'] ?? 0) - ($stats['portions_collected'] ?? 0));
            @endphp
            <div class="grid-3" style="margin-top: 1rem;">
                <div style="background: rgba(0,0,0,0.3); padding: 1rem; border-radius: var(--radius-md);">
                    <p style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 600;">GATE ADMISSIONS</p>
                    <h3 style="font-size: 1.5rem; font-weight: 700; margin: 0.25rem 0;">{{ $stats['tickets_admitted'] ?? 0 }} / {{ $stats['tickets_valid'] ?? 0 }}</h3>
                    <span style="font-size: 0.75rem; color: var(--text-secondary);">{{ $admittedPct }}% of paid tickets admitted</span>
                </div>
                <div style="background: rgba(0,0,0,0.3); padding: 1rem; border-radius: var(--radius-md);">
                    <p style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 600;">FOOD HANDED OVER</p>
                    <h3 style="font-size: 1.5rem; font-weight: 700; margin: 0.25rem 0;">{{ $stats['portions_collected'] ?? 0 }} / {{ $stats['portions_ordered'] ?? 0 }}</h3>
                    <span style="font-size: 0.75rem; color: var(--text-secondary);">{{ $portionsLeft }} paid portions still to hand over</span>
                </div>
                <div style="background: rgba(0,0,0,0.3); padding: 1rem; border-radius: var(--radius-md);">
                    <p style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 600;">STAFF ON SHIFT</p>
                    <h3 style="font-size: 1.5rem; font-weight: 700; margin: 0.25rem 0;">{{ $stats['staff_on_shift'] ?? 0 }}</h3>
                    <span style="font-size: 0.75rem; color: var(--text-secondary);">Assignments active right now</span>
                </div>
            </div>
        </div>
    @endif
</div>

<script>
function csrfHeaders() {
    return {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
    };
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

    button.disabled = true;
    box.hidden = false;
    box.style.borderColor = 'var(--border-color)';
    verdict.style.color = 'var(--text-primary)';
    verdict.textContent = 'Checking…';
    details.textContent = '';

    try {
        const { ok, data } = await postJson(form.dataset.url, { ticket_code: code });
        const admitted = ok && data.success === true;
        box.style.borderColor = admitted ? 'var(--accent-emerald)' : 'var(--accent-rose, #f87171)';
        verdict.style.color = admitted ? 'var(--accent-emerald)' : 'var(--accent-rose, #f87171)';
        if (admitted) {
            verdict.textContent = '✓ ADMIT';
            details.textContent = [data.ticket?.customer_name, data.ticket?.order_number].filter(Boolean).join(' • ');
        } else if (data.already_admitted) {
            verdict.textContent = '✕ ALREADY USED — DO NOT ADMIT';
            details.textContent = data.rejection_reason || '';
        } else {
            verdict.textContent = '✕ DO NOT ADMIT';
            details.textContent = data.rejection_reason || data.error || data.message || 'The ticket could not be verified.';
        }
    } catch (e) {
        box.style.borderColor = 'var(--accent-rose, #f87171)';
        verdict.style.color = 'var(--accent-rose, #f87171)';
        verdict.textContent = '✕ NOT CHECKED';
        details.textContent = 'Could not reach the server. Do not admit until the ticket is checked.';
    } finally {
        button.disabled = false;
        input.value = '';
        input.focus();
    }
}

// Food station: look up an order, then hand over portions one line at a time.
let currentFoodOrder = null;

function foodMessage(text, isError) {
    const el = document.getElementById('food_message');
    el.style.color = isError ? 'var(--accent-rose, #f87171)' : 'var(--accent-emerald)';
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
    badge.style.background = order.payment_verified ? 'rgba(16, 185, 129, 0.2)' : 'rgba(248, 113, 113, 0.2)';
    badge.style.color = order.payment_verified ? 'var(--accent-emerald)' : 'var(--accent-rose, #f87171)';

    list.replaceChildren();
    if (order.items.length === 0) {
        const li = document.createElement('li');
        li.textContent = 'This order has no food.';
        list.append(li);
        return;
    }

    for (const item of order.items) {
        const li = document.createElement('li');
        li.style.cssText = 'display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; flex-wrap: wrap; background: rgba(255,255,255,0.03); padding: 0.75rem; border-radius: var(--radius-sm);';

        const info = document.createElement('div');
        const name = document.createElement('strong');
        name.textContent = item.name;
        const meta = document.createElement('div');
        meta.style.cssText = 'font-size: 0.8rem; color: var(--text-secondary);';
        meta.textContent = `Ordered ${item.quantity} • Handed over ${item.collected} • Remaining ${item.remaining}`
            + (item.options && item.options.trim() ? ` • ${item.options.trim()}` : '')
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
        qty.style.cssText = 'width: 64px; padding: 0.4rem; border-radius: var(--radius-sm); background: rgba(0,0,0,0.3); border: 1px solid var(--border-color); color: var(--text-primary); text-align: center;';
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-primary';
        button.style.cssText = 'padding: 0.4rem 0.85rem; font-size: 0.8rem;';
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

// Posts to the server; the result shown is what the cash ledger actually recorded.
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
            result.style.color = 'var(--accent-emerald)';
            result.textContent = `Receipt ${data.receipt_number} recorded for ${data.order_number}. `
                + `Paid ${data.currency} ${data.amount_paid.toFixed(2)}; balance ${data.currency} ${data.balance.toFixed(2)} (${data.status.replace('_', ' ')}).`;
            form.reset();
        } else {
            result.style.color = 'var(--accent-rose, #f87171)';
            result.textContent = data.error || data.message || 'The cash receipt was not recorded.';
        }
    } catch (e) {
        result.style.color = 'var(--accent-rose, #f87171)';
        result.textContent = 'Could not reach the server. The cash receipt was not recorded.';
    } finally {
        button.disabled = false;
    }
}
</script>
@endsection
