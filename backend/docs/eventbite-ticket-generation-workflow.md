# EventBite Ticket Generation Workflow

**Date:** 2026-11-13
**Branch:** `claude/busy-feynman-15b28d`
**Scope:** From Place Order to a ticket with one QR code per guest: transactions, idempotency, rendering, retries,
the ticket lifecycle, and the architecture and database changes behind it.

Related: [design](eventbite-ticket-qr-design.md) ·
[security](eventbite-ticket-qr-security.md) ·
[scanner integration](eventbite-ticket-scanner-integration.md) ·
[test report](eventbite-ticket-qr-test-report.md)

---

## 1. Existing implementation (audit)

- Checkout (`CheckoutController::place`) re-quotes the cart, checks the total the customer saw, and calls
  `OrderPlacementService::placeOrder()` with the cart's idempotency key.
- `placeOrder()` runs one transaction. It locks the event row (ticket stock lives in its `ticket_tiers` JSON),
  locks menu stock, creates the order and its lines, and returns the existing order for a repeated idempotency key.
  The unique index on `orders.idempotency_key` catches a race.
- After success the cart is cleared, so a second Place Order finds no cart and creates nothing.
- Each order had one admission token (`orders.secure_ticket_token`), whatever its ticket quantity.

The new work keeps this flow and adds per-guest tickets inside it.

## 2. Flow

```
Customer: Place Order
  -> CheckoutController::place (validate, re-quote, idempotency key from the cart)
  -> OrderPlacementService::placeOrder()  ---- one DB transaction --------------------------------
       lock event row; repeated idempotency key? -> return the existing order (nothing new)
       reserve ticket and food stock (failure throws -> everything rolls back)
       create order, order lines
       AttendeeTicketService::issueFor(order)      one attendee ticket + credential per ticket bought
       RenderAttendeeTicketQrCodes::dispatchFor()  queued with afterCommit (never for a rolled-back order)
       charges, discount redemption, seats, notification
     ----------------------------------------------------------------------------------- commit ----
  -> redirect to /tickets/{order}
Queue worker: RenderAttendeeTicketQrCodes -> TicketQrService::prepare(order) (draw and decode-check artwork)
Ticket page:  TicketQrService::prepare(order) again (issues anything missing, draws anything not ready)
```

## 3. Guarantees

| Requirement | Mechanism |
|---|---|
| No credential when the checkout page merely opens | Nothing is issued outside `placeOrder()`. A test walks the tickets, food and review steps and finds no order and no attendee ticket. |
| No credential when order creation fails | `issueFor()` runs inside the order's transaction; any failure rolls back the order and every credential. Tested with a sold-out item. |
| One QR per ticket | `seatsFor()` counts each ticket line's quantity; seats are numbered 1..N in a stable order. A 3-ticket order gets 3 rows, 3 credentials and 3 QR codes. |
| Double clicks and retried requests | Same idempotency key returns the same order (lookup before and after the event lock, plus the unique index). `issueFor()` locks the order row, creates only missing seats, and `unique(order_id, sequence)` stops a concurrent second issue. |
| Failed QR generation is retryable | Rendering is separate from issuing. A failure sets `qr_status = failed` with the reason. "Try again" on the ticket page (`POST /tickets/{order}/qr/retry`), the next page view, and the queued job all only re-render; none can create a ticket. Tested by making the renderer throw, then recovering. |
| Repeated job runs | `RenderAttendeeTicketQrCodes` is `ShouldBeUnique` per order, retries three times with backoff, and `prepare()` is idempotent. |
| Orders from before this change | `prepare()` on the ticket page issues their attendee tickets the first time they are viewed, idempotently. |
| Complimentary tickets | `ComplimentaryTicketService` issues credentials in the same transaction as the comp order. |

## 4. When the QR is shown

The credential exists from order creation. The ticket page shows each guest's QR once payment is settled (the
existing rule: paid or partially refunded) and while the ticket is usable. Before that the page shows the order
reference and payment instructions. The gate admits only paid or confirmed orders whatever the page shows.

Each guest card (`<x-ticket-attendee-qr>`) shows: guest N of M and ticket type, the QR code (or "admitted", or
"being prepared" with a retry), ticket holder, ticket number, payment status and admission status. The holder or
the event's payment staff can replace that one guest's QR, with a reason.

## 5. Ticket lifecycle

| State (`TicketStatuses` `ticket` / `lifecycle`) | Entered when | Effect at the gate |
|---|---|---|
| `pending_payment` (activated) | The order is created; the credential exists | Refused: payment pending |
| `valid` | An authorised payment recording or provider reconciliation marks the order paid or confirmed | Admitted once |
| `admitted` (lifecycle) | A scan records admission (`ticket_admissions` row) | Refused: already admitted |
| `cancelled` | Order cancelled, rejected or expired | Refused |
| `refunded` | Order refunded in full | Refused |
| `revoked` | `Order::revokeTicketToken()` (administrative or fraud) | Refused |
| `charged_back` | `TicketLifecycleService::recordChargeback()` | Refused (credentials revoked) |
| `event_cancelled` | The host cancels the event | Refused: event not active |
| `completed` | The event is over (`TicketLifecycle::eventIsOver`) | Refused; the ticket becomes a keepsake |

Reissue paths, each recorded in `ticket_credential_events` with reason, timestamp, actor and new version:

- one guest: `TicketLifecycleService::reissue()` via `tickets.guests.reissue`;
- the whole order: "Replace QR code" (`Order::reissueTicketToken`), which also rotates every guest credential;
- transfer: accepting a transfer rotates every guest credential; the reason names the recipient.

Financial history is never rewritten. A chargeback appends an `order_adjustments` row of type `chargeback` (that
table throws on update and delete), and `payments` rows are not touched. Tests compare the payment row before and
after.

## 6. Architecture

| Piece | Kind | Responsibility |
|---|---|---|
| `App\Services\Tickets\AttendeeTicketService` | Service | Issue, derive, find, rotate credentials; record history |
| `App\Services\Tickets\TicketLifecycleService` | Service | One-guest reissue, chargeback |
| `App\Services\EventQr\TicketQrService` | Service | Draw and decode-check artwork; SVG for the page |
| `App\Services\EventQr\TicketQrDesignSystem` | Service | Presets, host choice, admin controls, readability |
| `App\Support\TicketStatuses` | Support | The separate statuses and lifecycle state |
| `App\Jobs\RenderAttendeeTicketQrCodes` | Queued job | Pre-render after commit (`config/tickets.php`, `TICKET_QR_PRERENDER`) |
| `AttendeeTicket`, `TicketAdmission`, `TicketCredentialEvent` | Eloquent models | `Order::attendeeTickets()` relation |
| `UpdateTicketQrDesignRequest`, `UpdateTicketQrControlsRequest`, `ReissueAttendeeTicketRequest`, `RecordChargebackRequest` | Form Requests | Validation and authorization |
| `App\Policies\AttendeeTicketPolicy` | Policy | Who may reissue a guest |
| `RedactCredentialsProcessor` | Monolog processor | Credentials never logged |
| `<x-ticket-attendee-qr>` | Blade component | One guest's card |

QR encoding and decoding use `chillerlan/php-qrcode` ^5, already installed and maintained, compatible with PHP 8.4
and Laravel 13. No frontend framework was added.

Events and listeners: not used. Issuing must happen inside the order's transaction, so it is a direct call; the
render is a single queued job dispatched after commit. An event and listener pair would add indirection without a
second consumer.

## 7. Database changes

| Migration | Change |
|---|---|
| `2026_11_13_000072_create_attendee_tickets_table` | `attendee_tickets`: order, event, organizer, order line, tier, `sequence` (unique per order), `ticket_number` (unique), `credential_nonce`, `token_hash` (unique), `token_version`, check-in fields, QR status, design snapshot and fingerprint, attempts, failure reason. Adds `events.ticket_qr_design` (JSON) and `system_platform_settings.ticket_qr_controls` (JSON). |
| `2026_11_13_000073_create_ticket_admissions_table` | `ticket_admissions`: one row per admitted attendee ticket, unique on `attendee_ticket_id`; who, how (own code or order pass), request id, time. |
| `2026_11_13_000074_create_ticket_credential_events_table` | `ticket_credential_events`: append-only credential history (issued, reissued, revoked, chargeback) with version, reason, actor, time. |

All are additive and reversible. No existing column changes meaning; `orders.secure_ticket_token` keeps working.

## 8. Files

**Created**

- `app/Models/AttendeeTicket.php`, `app/Models/TicketAdmission.php`, `app/Models/TicketCredentialEvent.php`
- `app/Services/Tickets/AttendeeTicketService.php`, `app/Services/Tickets/TicketLifecycleService.php`
- `app/Services/EventQr/TicketQrDesignSystem.php`, `app/Services/EventQr/TicketQrService.php`
- `app/Support/TicketStatuses.php`, `app/Support/RedactCredentialsProcessor.php`
- `app/Jobs/RenderAttendeeTicketQrCodes.php`, `config/tickets.php`
- `app/Http/Requests/UpdateTicketQrDesignRequest.php`, `UpdateTicketQrControlsRequest.php`,
  `ReissueAttendeeTicketRequest.php`, `RecordChargebackRequest.php`
- `app/Policies/AttendeeTicketPolicy.php`
- `resources/views/components/ticket-attendee-qr.blade.php`, `resources/views/admin/qr-codes/index.blade.php`
- three migrations (section 7)
- tests: `AttendeeTicketQrTest`, `TicketQrDesignSystemTest`, `TicketLifecycleAndScanningTest`,
  `TicketWalletCompatibilityTest`
- these five documents

**Modified**

- `OrderPlacementService` (issue and queue inside the transaction; attendee credentials in secure retrieval)
- `ComplimentaryTicketService`, `TicketTransferService` (issue, rotate with history)
- `Order` (relation, attendee lookup in `whereTicketToken`, rotation and history on reissue and revoke, redaction of `tkt_`)
- `OrderAdjustment` (`TYPE_CHARGEBACK`), `Event` and `SystemPlatformSetting` (JSON casts)
- `EventWorkerService` (attendee admission, event status check, admission records, verdicts, statuses)
- `WorkerTerminalController` (attendee credentials at the gate)
- `CheckoutController` (prepare tickets, retry, per-guest reissue), `TicketingStudioController` (QR design tab),
  `Admin\QrCodeConfigurationController` (ticket controls, page), `Admin\OrderOversightController` (chargeback)
- `QrDesignCatalog`, `QrArtworkRenderer` (leaf finder, monogram centre mark, public quiet zone)
- `AuditLogService` (redaction), `AppServiceProvider` (log processor, policy)
- views: `checkout/ticket`, `events/ticketing-studio`, `dashboards/worker-terminal`, `layouts/admin`;
  `public/css/06-components.css`
- routes: `web.php`, `customer.php`, `admin.php`; `phpunit.xml` (`TICKET_QR_PRERENDER=false`)
