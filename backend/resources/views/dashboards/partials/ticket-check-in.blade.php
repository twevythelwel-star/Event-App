{{-- Ticket check-in card, shared by the ticket checker and supervisor terminals. The verdict shown is the server's. --}}
<!-- TICKET CHECKER: every code is checked by the server; the result shown is the server's verdict -->
<section id="ticket-scanning" class="eb-panel card" aria-labelledby="ticket-scanning-heading" data-offline-policy="fail-closed">
    <div class="eb-card-header card-header">
        <div>
            <h2 id="ticket-scanning-heading" class="eb-card-title card-title">Ticket Check-in</h2>
            <p class="eb-card-subtitle card-subtitle">
                Scan the QR on the guest's ticket with a handheld scanner into the box below. Each ticket admits once.@if ($workerRole->value === 'event_supervisor') As a supervisor you may also type a ticket code or order number when a QR won't scan; that entry is logged as a manual check.@else A typed ticket code needs a supervisor.@endif
            </p>
        </div>
        <div class="eb-cluster" style="gap: 0.5rem;">
            <span class="eb-badge eb-badge-neutral" title="Scanner fails closed if disconnected from server">Fail-Closed Gate</span>
            <span class="eb-badge eb-badge-success">Gate Desk</span>
        </div>
    </div>

    <form id="scan_form" data-url="{{ route('worker.scan', $event->id) }}" data-offline-policy="fail-closed" onsubmit="event.preventDefault(); validateTicket();" class="eb-stack" style="gap: 1rem;">
        <div class="eb-form-group" style="margin: 0;">
            <label class="eb-label" for="ticket_input">{{ $workerRole->value === 'event_supervisor' ? 'Ticket QR, ticket code or order number' : 'Ticket QR' }}</label>
            <input type="text" id="ticket_input" class="eb-input" autocomplete="off" autocapitalize="off" spellcheck="false" autofocus required
                style="font-family: var(--font-mono); font-size: 1.125rem; min-height: 56px;">
        </div>
        <div>
            <button type="submit" id="scan_submit" class="eb-btn eb-btn-primary" style="min-height: 56px; padding: 0 1.5rem; font-size: 1rem;">
                <x-icon name="qr" size="sm" />
                <span>Check ticket</span>
            </button>
        </div>
    </form>

    <div id="scan_result" role="status" aria-live="assertive" hidden
        style="margin-top: 1rem; padding: 1rem; border-radius: var(--radius-md); border: 2px solid var(--color-border); background: rgba(7, 15, 28, 0.72);">
        <strong id="scan_verdict" style="font-size: 1.1rem; display: block;"></strong>
        <div id="scan_details" class="eb-help-text" style="margin-top: 0.35rem;"></div>
    </div>
    <p id="scanner_connection" role="status" aria-live="polite" class="eb-help-text" style="margin-top: 0.75rem;"></p>
    <p class="eb-help-text" style="margin-top: 0.35rem; font-size: 0.78rem;">
        <strong>Security policy:</strong> Public event QR flyers cannot admit guests. Each attendee ticket QR is a one-time cryptographic token verified by SHA-256 hash on the server. If a duplicate screenshot scan occurs, escalate to the Event Supervisor for purchaser identity verification.
    </p>
</section>
