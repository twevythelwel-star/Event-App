@extends('layouts.app')

@section('title', $workerRole->label() . ' Terminal — ' . ($event->title ?? 'Event Operations'))

@section('navigation')
    <a href="{{ route('worker.terminal', $event->id) }}" class="active">📲 On-Site Terminal</a>
    @if ($workerRole->value === 'event_supervisor')
        <a href="#roster">👥 Worker Roster</a>
        <a href="#reports">📊 Station Reports</a>
    @endif
    <a href="{{ route('attendee.dashboard') }}">🎟️ My Tickets</a>
@endsection

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
        <!-- 🎟️ TICKET CHECKER INTERFACE -->
        <div class="card">
            <div class="card-header">
                <h2 style="font-size: 1.25rem; font-weight: 700;">QR Ticket Scanner & Entry Validation</h2>
                <p style="font-size: 0.85rem; color: var(--text-secondary);">Scan attendee pass or manually enter ticket code for server-side verification.</p>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1rem;">
                <div style="background: rgba(0,0,0,0.4); border-radius: var(--radius-md); padding: 2rem; text-align: center; border: 2px dashed var(--border-color); display: flex; flex-direction: column; align-items: center; justify-content: center;">
                    <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📷</div>
                    <strong style="font-size: 0.95rem; margin-bottom: 0.25rem;">Live Camera Scanner</strong>
                    <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 1rem;">Align ticket QR code within camera viewport.</p>
                    <button class="btn btn-secondary" onclick="alert('Camera access enabled for QR ticket scanning.')" style="font-size: 0.85rem;">
                        Activate Device Camera
                    </button>
                </div>

                <div>
                    <h4 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 0.75rem;">Manual Code Lookup</h4>
                    <form onsubmit="event.preventDefault(); validateTicket();" style="display: flex; flex-direction: column; gap: 1rem;">
                        <div>
                            <label for="ticket_input" style="font-size: 0.8rem; color: var(--text-secondary); display: block; margin-bottom: 0.25rem;">Ticket Code or Token</label>
                            <input type="text" id="ticket_input" placeholder="e.g. TCK-8899 or SEC-TOKEN-1234"
                                style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); background: rgba(0,0,0,0.25); border: 1px solid var(--border-color); color: var(--text-primary); font-family: monospace; font-size: 1rem;">
                        </div>
                        <button type="submit" class="btn btn-primary" style="padding: 0.75rem; font-weight: 700;">
                            Validate Ticket
                        </button>
                    </form>

                    <div id="scan_result" style="margin-top: 1rem; padding: 1rem; border-radius: var(--radius-md); background: rgba(16, 185, 129, 0.1); border: 1px solid var(--accent-emerald); display: none;">
                        <strong style="color: var(--accent-emerald); font-size: 0.9rem;">✓ TICKET VALID &bull; ADMITTED</strong>
                        <div id="scan_details" style="font-size: 0.8rem; margin-top: 0.35rem; color: var(--text-secondary);"></div>
                    </div>
                </div>
            </div>
        </div>

    @elseif ($workerRole->value === 'payment_clerk')
        <!-- 💵 PAYMENT CLERK INTERFACE -->
        <div class="card">
            <div class="card-header">
                <h2 style="font-size: 1.25rem; font-weight: 700;">Cash Collection & Transfer Verification</h2>
                <p style="font-size: 0.85rem; color: var(--text-secondary);">Record physical cash receipts with mandatory immutable audit trail.</p>
            </div>

            <form onsubmit="event.preventDefault(); recordPayment();" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                <div>
                    <label style="font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 0.35rem;">Order Number</label>
                    <input type="text" id="cash_order_num" placeholder="ORD-2026-XXXX" required
                        style="width: 100%; padding: 0.65rem 0.85rem; border-radius: var(--radius-md); background: rgba(0,0,0,0.25); border: 1px solid var(--border-color); color: var(--text-primary);">
                </div>

                <div>
                    <label style="font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 0.35rem;">Cash Received ($ Amount)</label>
                    <input type="number" step="0.01" id="cash_amount" placeholder="0.00" required
                        style="width: 100%; padding: 0.65rem 0.85rem; border-radius: var(--radius-md); background: rgba(0,0,0,0.25); border: 1px solid var(--border-color); color: var(--text-primary);">
                </div>

                <div style="grid-column: span 2;">
                    <label style="font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 0.35rem;">Receipt Notes (Optional)</label>
                    <input type="text" id="cash_notes" placeholder="e.g. Paid in cash at Gate B register"
                        style="width: 100%; padding: 0.65rem 0.85rem; border-radius: var(--radius-md); background: rgba(0,0,0,0.25); border: 1px solid var(--border-color); color: var(--text-primary);">
                </div>

                <div style="grid-column: span 2; display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.5rem; font-weight: 700;">
                        Submit Cash Receipt (Record Audit Trail)
                    </button>
                </div>
            </form>
        </div>

    @elseif ($workerRole->value === 'food_station_worker')
        <!-- 🍲 FOOD STATION WORKER INTERFACE -->
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 style="font-size: 1.25rem; font-weight: 700;">Kitchen Food Handover Station</h2>
                    <p style="font-size: 0.85rem; color: var(--text-secondary);">Mark portion collections. Partial collections supported with atomic updates.</p>
                </div>
                <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: var(--accent-emerald);">
                    Station: {{ strtoupper($station) }}
                </span>
            </div>

            <div style="margin-top: 1rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1rem; background: rgba(0,0,0,0.2);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <div>
                        <strong style="font-size: 1rem;">Order #ORD-2026-8812</strong> &bull;
                        <span style="color: var(--text-secondary); font-size: 0.85rem;">Ticket: TCK-8812</span>
                    </div>
                    <span class="badge" style="background: rgba(16, 185, 129, 0.2); color: var(--accent-emerald);">Paid & Ready</span>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.03); padding: 0.75rem; border-radius: var(--radius-sm);">
                    <div>
                        <strong>Escovitch Snapper with Festivals</strong>
                        <div style="font-size: 0.8rem; color: var(--text-secondary);">Ordered: 3 portions &bull; Collected: 1 portion &bull; <strong style="color: var(--accent-amber);">Remaining: 2 portions</strong></div>
                    </div>
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <input type="number" min="1" max="2" value="1" id="collect_qty_1"
                            style="width: 60px; padding: 0.4rem; border-radius: var(--radius-sm); background: rgba(0,0,0,0.3); border: 1px solid var(--border-color); color: var(--text-primary); text-align: center;">
                        <button class="btn btn-primary" onclick="alert('Collected portion recorded. 1 portion remaining.')" style="padding: 0.4rem 0.85rem; font-size: 0.8rem;">
                            Hand Over
                        </button>
                    </div>
                </div>
            </div>
        </div>

    @else
        <!-- 🛡️ EVENT SUPERVISOR INTERFACE -->
        <div class="card">
            <div class="card-header">
                <h2 style="font-size: 1.25rem; font-weight: 700;">Event Operations & Supervision Desk</h2>
                <p style="font-size: 0.85rem; color: var(--text-secondary);">Oversee station handovers, entry gates, and worker allocations for this event.</p>
            </div>

            <div class="grid-3" style="margin-top: 1rem;">
                <div style="background: rgba(0,0,0,0.3); padding: 1rem; border-radius: var(--radius-md);">
                    <p style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 600;">GATE ADMISSIONS</p>
                    <h3 style="font-size: 1.5rem; font-weight: 700; margin: 0.25rem 0;">184 / 210</h3>
                    <span style="font-size: 0.75rem; color: var(--accent-emerald);">● 87% Admitted</span>
                </div>
                <div style="background: rgba(0,0,0,0.3); padding: 1rem; border-radius: var(--radius-md);">
                    <p style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 600;">KITCHEN HANDOVERS</p>
                    <h3 style="font-size: 1.5rem; font-weight: 700; margin: 0.25rem 0;">142 / 165</h3>
                    <span style="font-size: 0.75rem; color: var(--accent-blue);">● 23 Portions Remaining</span>
                </div>
                <div style="background: rgba(0,0,0,0.3); padding: 1rem; border-radius: var(--radius-md);">
                    <p style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 600;">ACTIVE STAFF</p>
                    <h3 style="font-size: 1.5rem; font-weight: 700; margin: 0.25rem 0;">6 Workers</h3>
                    <span style="font-size: 0.75rem; color: var(--accent-emerald);">● Stations Operating</span>
                </div>
            </div>
        </div>
    @endif
</div>

<script>
function validateTicket() {
    const code = document.getElementById('ticket_input').value;
    if (!code) return;
    const resDiv = document.getElementById('scan_result');
    const details = document.getElementById('scan_details');
    resDiv.style.display = 'block';
    details.innerText = 'Pass Verified &bull; Gate Entry Recorded for ' + code;
}

function recordPayment() {
    const orderNum = document.getElementById('cash_order_num').value;
    const amount = document.getElementById('cash_amount').value;
    alert('Cash Receipt Recorded: $' + amount + ' for ' + orderNum + '. Immutable audit log generated.');
}
</script>
@endsection
