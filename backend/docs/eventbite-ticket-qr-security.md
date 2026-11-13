# EventBite Ticket QR Security

**Date:** 2026-11-13
**Branch:** `claude/busy-feynman-15b28d`
**Scope:** The attendee ticket credential, what the QR code carries, where secrets live, logging, statuses and
payment eligibility, and the remaining production blockers.

Related: [design](eventbite-ticket-qr-design.md) ·
[generation workflow](eventbite-ticket-generation-workflow.md) ·
[scanner integration](eventbite-ticket-scanner-integration.md) ·
[test report](eventbite-ticket-qr-test-report.md)

---

## 1. Principle

A QR code's appearance is not a security mechanism. Security comes from the credential, authorization,
server-side validation and the ticket lifecycle. The design system only receives an opaque string to draw; it
never sees ticket IDs, people or payments, and it cannot make a code admit anyone.

## 2. Existing implementation (audit, before this work)

| Area | Found | Gap |
|---|---|---|
| Order pass `orders.secure_ticket_token` | `tok_` + 64 hex (256-bit CSPRNG). DB stores only the SHA-256 hash; the raw token is kept for display in an in-memory map and an encrypted vault file. Reissue and revoke exist with audit entries. | One credential per order, not per person. |
| Promotional event QR | Separate system, refused at the gate (`isPromotionalEventQrPayload`). | None. |
| Gate | `EventWorkerService::scanAndValidateTicket` with a row lock: event match, admission window, revocation, payment status, duplicate check. | No event publication or cancellation check; no database-level duplicate guard; no per-person admission. |
| Logging | `Order::redactTokenForLog()` existed. | Nothing called it; nothing guaranteed raw credentials stay out of logs. |
| Payment | Paid status set only by provider reconciliation, the cash ledger or host verification; customer references give `payment_submitted`. | One unreachable path: `HostDashboardController::recordCashReceipt` marks an order paid for any amount, but no route points to it. |

## 3. The attendee credential

Each attendee ticket (`attendee_tickets`, one row per person) has its own credential:

```
credential = "tkt_" + base64url( HMAC-SHA256( APP_KEY, "attendee-ticket|{id}|{token_version}|{credential_nonce}" ) )
```

| Requirement | How it is met |
|---|---|
| At least 256 bits of CSPRNG randomness | `credential_nonce` is `random_bytes(32)` per ticket, and a new one on every reissue. The HMAC output is 256 bits (43 base64url characters). |
| Store only a hash | `token_hash` = SHA-256 of the credential, unique index. The credential itself is in no column. |
| No PII or internal IDs in the QR | The payload is only `tkt_` + 43 characters. The row id goes into the HMAC input, not the output. Tests decode the QR and check it contains no IDs, names, emails, phone numbers, ticket or order numbers, amounts or payment methods. |
| No URL | The QR encodes the bare credential, not a link, so nothing leaks through browser history, link previews or referrers. |
| Not sequential | Ticket numbers (`SFF-XXXXXX-02`) are for people to read and are never accepted at the gate. Tests show both a checker and a supervisor are refused when they type one. |
| Unique | Unique index on `token_hash`; a test issues 24 credentials across six orders and finds no collision. |
| Shown again on later visits | The server recomputes the credential from the row and `APP_KEY`, so there is no raw-token vault for attendee tickets. A copy of the database alone cannot produce a credential; the nonce is useless without the key. |
| Reissue | A new nonce and `token_version + 1`. The old hash no longer matches, so every copy of the old QR (screenshots included) stops working immediately. |

**Key dependency:** rotating `APP_KEY` invalidates every outstanding attendee credential, because the server can
no longer derive them and their hashes no longer match. That is safe (nothing is admitted wrongly) but must be
planned: rotate only between events, or reissue tickets after rotation. This is listed under blockers.

## 4. Verification is server-side

The QR resolves only through `POST /worker/events/{event}/scan`, which requires an authenticated, active worker
with the `scan_qr_tickets` permission for that event. The scanner sends the decoded string; the server hashes it
and looks up `attendee_tickets.token_hash` restricted to the event in the URL. Every eligibility rule is evaluated
on the server, under row locks, at scan time (see the scanner integration document). Nothing in the QR is
trusted.

## 5. Logging

- `Order::redactTokenForLog()` now covers both `tok_` and `tkt_` credentials. Each is replaced with
  `tok_[REDACTED:sha256:<12 hex>]` (a fingerprint, so investigators can correlate without the secret).
- `AuditLogService::log()` redacts every `details` string before storing it, whoever calls it.
- `App\Support\RedactCredentialsProcessor` is pushed onto the application's Monolog logger
  (`AppServiceProvider::boot`). It redacts every log message and every string in its context, recursively.
- `TicketCredentialEvent::record()` redacts reasons too.
- Tests scan valid, refused and unknown credentials, then assert that no audit row contains a raw credential. They
  also pass credentials directly to the audit logger and the log processor and check they come out redacted.

## 6. Statuses kept separate

`App\Support\TicketStatuses::for($order, $ticket)` reports each status separately:

| Key | Values |
|---|---|
| `order` | placed, cancelled, expired, rejected |
| `payment` | unpaid, submitted, partially_paid, paid, partially_refunded, refunded, charged_back |
| `ticket` | pending_payment, valid, cancelled, refunded, charged_back, revoked, event_cancelled, completed |
| `lifecycle` | `ticket`, or `admitted` once admission is recorded on a valid or completed ticket |
| `admission` | not_admitted, admitted (with `admitted_at`) |
| `food` | the order's fulfilment status, handled by the food stations |

The gate never relies on these labels; they are for people (ticket page, scanner response).

## 7. Payment eligibility

A credential is created when the order is created, so it exists while payment is pending (`pending_payment`). The
gate admits only orders whose payment status is `paid` or `confirmed`. Those come only from:

- payment provider reconciliation (`StripePaymentReconciliationService`, provider-confirmed amounts);
- an authorised manual recording: the cash ledger (`CashLedgerService`), or host or admin verification
  (`ManualPaymentGateway::verifyPayment`, `OrderOperationsService::updatePaymentStatus`).

A ticket is never marked paid because a customer clicked a pay button, a browser reached a success page, a
customer uploaded a screenshot or entered a bank reference (that is `payment_submitted`), or a QR code was
generated. Tests walk this path: pending, refused at the gate; reference submitted, still refused; host verifies,
admitted.

## 8. Security review of the new surfaces

| Surface | Control |
|---|---|
| Ticket page `GET /tickets/{order}` | Existing ownership check (`isUserAuthorizedForOrder`, 404 for others). The page draws the QR as SVG modules; the credential is never in the HTML as text (tested). |
| Per-guest reissue `POST /tickets/{order}/guests/{attendeeTicket}/reissue` | `ReissueAttendeeTicketRequest` plus `AttendeeTicketPolicy::reissue`: only the order's holder or staff who may verify its payments. A reason is required. The guest must belong to the order in the URL (404 otherwise). Refused once admitted or revoked. Throttled. |
| QR retry `POST /tickets/{order}/qr/retry` | Ownership check; only re-renders artwork, never creates tickets or credentials. |
| Host design `POST /events/{id}/ticketing/qr-design` | Only events the host manages (`findEvent`, 404 otherwise); validated by `UpdateTicketQrDesignRequest`; refused when unreadable. Stored values are whitelisted keys and validated hex colours only. |
| Admin controls `POST /admin/console/qr-codes/ticket-controls` | `UpdateTicketQrControlsRequest` authorizes `admin.settings.manage`; contrast floor cannot go below 7:1; audited. |
| Chargeback `POST /admin/console/orders/{order}/chargeback` | `admin.payments.review` gate for that order (system admins, event owners, payment verifiers in scope). Explicit confirmation required. Append-only adjustment; payments untouched; recorded once. |
| SVG output | Only numbers, validated `#RRGGBB` colours and XML-escaped text (labels, initials restricted to `[A-Z0-9&]{1,3}`). No markup from users reaches the SVG. |

## 9. Remaining production blockers

1. **`APP_KEY` rotation plan.** Rotating the key invalidates all attendee credentials (section 3). Document a
   procedure: rotate between events, or rotate and then reissue upcoming tickets.
2. **Chargeback intake is manual.** There is no payment-provider webhook in the codebase. An administrator records
   chargebacks in the console. Connect Stripe `charge.dispute.created` to `TicketLifecycleService::recordChargeback`
   once webhooks exist.
3. **Apple Wallet carries the order pass, not per-guest codes.** It admits the whole party, and the gate keeps the
   two consistent (tested). Per-guest wallet passes belong with the wallet work on
   `claude/intelligent-tereshkova-a6d30e`.
4. **Unreachable cash endpoint.** `HostDashboardController::recordCashReceipt` marks any amount as fully paid. It has
   no route today; delete it or route it through `CashLedgerService` before anyone wires it up.
5. **Queue worker required.** Pre-rendering runs on the queue (`QUEUE_CONNECTION=database` in `.env`). Without a
   worker, the ticket page still draws codes on first view, only slower.
6. **`TicketVerificationService::verifyTicket`** (no route uses it) does not understand attendee credentials or the
   new event-status check. Remove it, or route it through `EventWorkerService`, before it is used.
