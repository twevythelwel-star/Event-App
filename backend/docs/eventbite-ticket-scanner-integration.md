# EventBite Ticket Scanner Integration

**Date:** 2026-11-13
**Branch:** `claude/busy-feynman-15b28d`
**Scope:** What happens when staff scan a ticket QR at the gate, how duplicate admission is prevented, what the
scanner is told, and how food redemption stays separate.

Related: [design](eventbite-ticket-qr-design.md) ·
[security](eventbite-ticket-qr-security.md) ·
[generation workflow](eventbite-ticket-generation-workflow.md) ·
[test report](eventbite-ticket-qr-test-report.md)

---

## 1. Endpoint

`POST /worker/events/{event}/scan` (`worker.scan`), body `{ticket_code, scan_request_id?}`.
Middleware: `auth`, `worker.active`, `throttle:30,1`, `worker.permission:scan_qr_tickets`.
Controller: `WorkerTerminalController::scanTicket` → `EventWorkerService::scanAndValidateTicket`.

Accepted codes:

| Code | Meaning | Admits |
|---|---|---|
| `tkt_…` (43 chars) | One guest's own ticket | That guest |
| `tok_…` | The order's pass (Apple Wallet carries this) | Every guest on the order not yet admitted |
| Ticket code or order number, typed | Supervisor manual check (existing) | As the order pass |
| Promotional event QR | Refused: never admits | No one |

## 2. The ten steps

| # | Step | Where |
|---|---|---|
| 1 | Authenticate the scanner | `auth` middleware |
| 2 | Verify the staff member's assignment and permission for this event | `worker.active`, `worker.permission:scan_qr_tickets`, then `EventWorkerService` re-checks the assignment for the order's organizer and event (or system admin or event manager) |
| 3 | Resolve the QR credential on the server | `AttendeeTicketService::findByCredential` (SHA-256 of the code against `attendee_tickets.token_hash`) or `Order::whereTicketToken` |
| 4 | Credential belongs to this event | Lookup restricted to the URL's event (unknown elsewhere: 404), then `orderChecks()` compares the order's event (`wrong_event`) |
| 5 | Event publication and cancellation | `orderChecks()`: the event must exist with status `published`; cancelled, draft or missing gives `event_not_active` |
| 6 | Payment eligibility | Order status `paid` or `confirmed` only (`payment_pending` otherwise) |
| 7 | Cancellation, refund, revocation, expiry | Revoked token, or order refunded, rejected, cancelled or expired (`ticket_revoked`); admission window and event over (`event_not_open`) |
| 8 | Already admitted? | Attendee: its own `checked_in_at` or the order's party admission. Order pass: the order's `checked_in_at` or any guest already admitted individually (`already_admitted`) |
| 9 | Atomically record admission | One transaction: the order row and the attendee row locked `FOR UPDATE`; a `ticket_admissions` row inserted under its unique key (in a savepoint); then the ticket, and the order once every guest is in, marked |
| 10 | Clear result to the scanner | `result` (`admit` or `reject`), `headline`, `reason_code`, `rejection_reason`, the guest (`ticket_number`, `sequence`, `of`), and the separate statuses |

Every acceptance and refusal is audit-logged (`ticket_redemption_verified` or `ticket_redemption_rejected`) with
credentials redacted.

## 3. Duplicate admission: three layers

1. **Transactions and row locks.** The order row and the attendee row are read `FOR UPDATE`. A second gate scanning
   the same ticket waits, then sees the admission.
2. **Database constraint.** `ticket_admissions.attendee_ticket_id` is unique. Even if two admissions got past the
   locks, the second insert fails, the scan is refused as already admitted, and nothing else is written for it.
3. **Cross-checks between the two kinds of code.** The order pass refuses once any guest came in on their own code;
   a guest's code refuses once the order pass admitted the party. The order pass writes one admission record per
   guest, so the unique key covers it too.

Retries: a scanner that lost a response resends with the same `scan_request_id`. If the same checker already
admitted that ticket with that id, the answer is `replayed: true` and nothing is recorded again.

## 4. Response

```json
{
  "success": true,
  "result": "admit",
  "headline": "ADMIT · Guest 2 of 3",
  "valid": true,
  "already_admitted": false,
  "replayed": false,
  "reason_code": null,
  "rejection_reason": null,
  "ticket": {
    "order_number": "ORD-2026-123456",
    "customer_name": "…",
    "status": "paid",
    "attendee": {"ticket_number": "SFF-7KQ2MX-02", "sequence": 2, "of": 3, "checked_in_at": "2026-11-28 18:04:11"}
  },
  "statuses": {"order": "placed", "payment": "paid", "ticket": "valid", "lifecycle": "admitted", "admission": "admitted", "admitted_at": "…", "food": "unprepared"}
}
```

The `ticket` block is sanitised per worker role by the existing `EventWorkerAssignment::sanitizeOrder` (ticket
checkers do not receive payment details or contact information).

Refusal reason codes: `wrong_event`, `event_not_active`, `event_not_open`, `ticket_revoked`, `payment_pending`,
`ticket_expired`, `already_admitted`, `invalid_ticket`; and, from the controller, `promotional_qr`,
`unauthorized_manual_entry`, `unauthorized_scanner`. The scanner screen (`dashboards/worker-terminal`) shows
✓ ADMIT with the guest ("Guest 2 of 3 · SFF-…-02"), or ✕ DO NOT ADMIT with the reason.

## 5. Food redemption stays separate

- Food stations use `POST /worker/events/{event}/lookup` and `/collect`, with their own permissions
  (`view_assigned_food_items`, `mark_quantities_collected`). A guest's QR or the order pass finds the order there,
  but lookup never records admission.
- Each food item is handed over through `OrderOperationsService::recordHandover`: its own row lock, quantity cap,
  payment check, station check and handover audit log.
- Admission never marks food collected, and collecting food never records admission. A food worker cannot call
  the gate endpoint (403). Tested.

## 6. Existing implementation (audit) and what changed

| Before | After |
|---|---|
| One admission per order | One admission per guest, with the order pass kept for Wallet and supervisors |
| No event status check at the gate | Cancelled, draft or missing events refused |
| Duplicates stopped by locks only | Locks plus the `ticket_admissions` unique key |
| Result as `success` and a reason | Plus `result`, `headline`, the guest and the separate statuses |
| `tok_` the only scannable credential | `tkt_` too, at the gate and in food and staff lookups (`Order::whereTicketToken`) |

`TicketVerificationService::verifyTicket` is not routed anywhere and was left as it was. See the blockers in the
security document.
