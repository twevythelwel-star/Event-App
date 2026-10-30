{{-- Ticket check-in card, shared by the ticket checker and supervisor terminals. The verdict shown is the server's. --}}
<!-- TICKET CHECKER: every code is checked by the server; the result shown is the server's verdict -->
<div class="card">
    <div class="card-header">
        <h2 style="font-size: 1.25rem; font-weight: 700;">Ticket Check-in</h2>
        <p style="font-size: 0.85rem; color: var(--text-secondary);">Scan the QR on the guest's ticket with a handheld scanner into the box below. Each ticket admits once.@if ($workerRole->value === 'event_supervisor') As a supervisor you may also type a ticket code or order number when a QR won't scan; that entry is logged as a manual check.@else A typed ticket code needs a supervisor.@endif</p>
    </div>

    <form id="scan_form" data-url="{{ route('worker.scan', $event->id) }}" onsubmit="event.preventDefault(); validateTicket();" style="display: flex; flex-direction: column; gap: 1rem; margin-top: 1rem;">
        <div>
            <label for="ticket_input" style="font-size: 0.8rem; color: var(--text-secondary); display: block; margin-bottom: 0.25rem;">{{ $workerRole->value === 'event_supervisor' ? 'Ticket QR, ticket code or order number' : 'Ticket QR' }}</label>
            <input type="text" id="ticket_input" autocomplete="off" autocapitalize="off" spellcheck="false" autofocus required
                style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); background: rgba(0,0,0,0.25); border: 1px solid var(--border-color); color: var(--text-primary); font-family: monospace; font-size: 1rem;">
        </div>
        <button type="submit" id="scan_submit" class="btn btn-primary" style="padding: 0.75rem; font-weight: 700;">Check ticket</button>
    </form>

    <div id="scan_result" role="status" aria-live="assertive" hidden
        style="margin-top: 1rem; padding: 1rem; border-radius: var(--radius-md); border: 2px solid var(--border-color);">
        <strong id="scan_verdict" style="font-size: 1.1rem; display: block;"></strong>
        <div id="scan_details" style="font-size: 0.85rem; margin-top: 0.35rem; color: var(--text-secondary);"></div>
    </div>
</div>
