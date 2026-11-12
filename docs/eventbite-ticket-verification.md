# EventBite — Server-Side Ticket Verification

The security core of the platform. A QR scan is only a *hint*; every admission decision is made by the server against database state.

## 1. Principles

1. **The browser never decides.** The scanner submits a token string and nothing else trusted. Event, order, ticket, payment, and admission state are all re-read from the database.
2. **Payment ≠ admission.** A valid QR proves nothing about money. `status IN (paid, confirmed)` is required, read from the orders table — never from a client field, redirect, or screenshot.
3. **Atomic redemption.** Check-and-admit happen inside one transaction under `lockForUpdate`; a second scanner physically cannot interleave.
4. **Fail closed.** Any uncertainty — offline, unknown token, mismatched event, expired assignment — results in refusal, never benefit-of-the-doubt.
5. **Structured results.** Every outcome has a stable `reason_code` so terminals and audits agree.

## 2. Endpoint & middleware chain

```
POST /worker/events/{event}/scan
  auth                        → must be signed in
  active                      → account not suspended
  worker.active               → resolves EventWorkerAssignment for {event};
                                rejects revoked / pending / declined /
                                not-started / expired assignments
  TicketVerificationRequest   → Form Request authorize(): assignment active
                                AND (role may scan OR scan_qr_tickets)
  worker.permission:scan_qr_tickets → role->canPerform / custom_permissions
  throttle:30,1               → 31st request/min → 429
```

Every layer re-checks on every request — nothing is cached across requests.

## 3. Verification sequence (in order)

| # | Check | Source of truth | Failure code |
|---|---|---|---|
| 0 | Token format | `tok_` + 64 hex pattern | `invalid_ticket` (404) |
| 1 | Token → order | `whereTicketToken()` = SHA-256 hash match, **scoped to this event** | `invalid_ticket` (404) |
| 2 | Manual-entry gate | typed codes need supervisor role | `unauthorized_manual_entry` (403) |
| 3 | Replay | same `scan_request_id` + same checker → return stored success | `replayed: true` |
| 4 | Row lock | `lockForUpdate` inside `DB::transaction` | — |
| 5 | Event match | `order.event_id === route event` | `wrong_event` |
| 6 | Validity window | status ≠ expired/closed; `doors_open_time`(event date, event tz) ≤ now ≤ `ends_at` | `ticket_expired` / `event_not_open` |
| 7 | Revocation | `token_revoked_at` null AND status ∉ {refunded, rejected, cancelled, expired} | `ticket_revoked` |
| 8 | Payment | status ∈ {paid, confirmed} | `payment_pending` |
| 9 | Prior admission | `checked_in_at` null (rechecked **after** lock) | `already_admitted` |
| — | **Admit** | set `checked_in_at`, `checked_in_by`; audit `ticket_redemption_verified` | `success` |

Ordering note: revocation (7) is checked before payment (8) and admission (9) so a revoked ticket is never reported as merely unpaid or already-used — the most severe truth wins.

## 4. Payment verification rules

| Method | How payment becomes `paid` | Who can do it |
|---|---|---|
| Online processor | Provider webhook/verification writes `Payment::SUCCEEDED`; order reconciled server-side | System only — no browser path |
| Manual (Zelle, bank, Cash App) | Authorized clerk/host verifies reference → `OrderOperationsService` payment endpoint; capability `verify` required; audited | Payment clerk, supervisor, host, admin |
| Cash | `POST /worker/events/{event}/cash` → `CashLedgerService` (permission `record_cash_received`, amount capped at balance, currency enforced, ledger + audit) | Payment clerk, supervisor |

A submitted screenshot or reference number is **never** automatic proof — it is a claim a permitted human verifies.

Admission additionally requires the event's admission window: gate opens at `doors_open_time` on the event date in the event's timezone (fallback `starts_at`), closes at `ends_at`. Scans outside the window are refused with `event_not_open` regardless of payment.

## 5. Atomic redemption detail

```
DB::transaction:
    order = Order::whereKey(id)->lockForUpdate()->first()
    if order.check_in_request_id == request.scan_request_id
       AND order.checked_in_by == worker.id:
           return stored success (replayed)          // lost-response retry
    run checks 5–9 against the LOCKED row
    if pass:
        checked_in_at = now(); checked_in_by = worker.id
        check_in_request_id = scan_request_id
        commit → audit verified
    else:
        rollback → audit rejected (reason_code + reason)
```

Concurrency guarantees:
- Two simultaneous scans serialize on the row lock; the loser rechecks `checked_in_at` **after** acquiring the lock and is refused `already_admitted`.
- `check_in_request_id` is unique — a retried request can never create a second admission.
- Rejection paths write `ticket_redemption_rejected` with the reason; they never mutate the order.

## 6. Result contract

Success:
```json
{ "success": true, "replayed": false,
  "ticket": { "order_number": "...", "ticket_code": "...",
              "customer_name": "...", "status": "paid",
              "checked_in_at": "..." } }
```

Failure (HTTP 200 for gate verdicts, 4xx for transport/authorization):
```json
{ "success": false, "already_admitted": true|false,
  "reason_code": "payment_pending",
  "rejection_reason": "human-readable reason",
  "error": "..." }
```

- Scanner responses expose only `customer_name`, `order_number`, `ticket_code`, `status`, `checked_in_at` — no payment totals, references, or contact PII (non-privileged roles get the sanitized view).
- `promotional_qr_rejected: true` distinguishes public event QRs.
- Errors are sanitized: no SQL, no stack traces, no token echo.

## 7. Manual (non-QR) admission

Typed ticket codes / order numbers are accepted **only** for `event_supervisor` (and logged with `manual: true` in audit details). Ticket checkers scanning typed codes get `unauthorized_manual_entry`. This is the authorized fallback when a QR is damaged — it is a supervised, audited path, not a bypass.

## 8. What is deliberately NOT trusted

- `event_id` in the request body (route binding + DB lookup win)
- `status`, `payment_status`, `checked_in_at` fields in the payload (ignored)
- Ticket IDs or order IDs supplied by the scanner (lookup is by token hash only)
- Signed QR payloads (none exist; server-side hash lookup is authoritative)
- `scan_request_id` is used **only** for idempotent replay, never as proof of validity
