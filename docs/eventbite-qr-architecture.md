# EventBite — QR Architecture

Two strictly separated QR systems. They share nothing: different payloads, different storage, different lifecycles, different security boundaries.

---

## 1. System overview

```
┌─────────────────────────────┐         ┌─────────────────────────────┐
│  PUBLIC PROMOTIONAL QR      │         │  ATTENDEE TICKET QR         │
│  (marketing / discovery)    │         │  (admission / identity)     │
├─────────────────────────────┤         ├─────────────────────────────┤
│ Identity: event_qr_codes.   │         │ Identity: orders row +      │
│   public_code (10-char      │         │   secure_ticket_token_hash  │
│   opaque, CSPRNG)           │         │   (SHA-256, unique)         │
│ Payload: /e/{public_code}   │         │ Payload: tok_<64 hex>       │
│   → public event page       │         │   (256-bit CSPRNG raw)      │
│ Storage: public_code +      │         │ Storage: hash only; raw in  │
│   design JSON (separate)    │         │   delivery path only        │
│ Renderer: QrArtworkRenderer │         │ Renderer: QrCode::svg()     │
│   (chillerlan, decode-      │         │   (chillerlan, ECC Q)       │
│   verified before save)     │         │ Lifecycle: issue → verify → │
│ Lifecycle: stable forever;  │         │   admit | reissue | revoke  │
│   redesign keeps identity   │         │ Boundary: NEVER admits;     │
│ Boundary: discovery only;   │         │   server-validated only     │
│   gate rejects it outright  │         │ Boundary: admits once,      │
│                             │         │   server-side only          │
└─────────────────────────────┘         └─────────────────────────────┘
```

## 2. Event QR workflow

```
Host requests QR ──► EventQrService::identityFor(event)
                          │  creates event_qr_codes row:
                          │  public_code = 10 chars from
                          │  ABCDEFGHJKMNPQRSTUVWXYZ23456789
                          │  (random_int CSPRNG, unique index)
                          ▼
                   QrArtworkRenderer::scene()
                          │  payload = config('app.url') . '/e/' . code
                          │  design = approved preset | custom_theme
                          │  chillerlan matrix → shapes
                          ▼
                   Decode gate: PNG rendered at print & small
                   sizes + after JPEG compression MUST read
                   back the exact destination URL, else the
                   validated fallback design is used
                          ▼
        ┌─────────────────┴──────────────────┐
        ▼                                    ▼
  Host downloads                      Guest scans
  GET /events/{id}/qr/{svg|png|poster}  GET /e/{code}
  (owner/manager only)                  │
                                        ├─ published → 302 /events/{id}
                                        ├─ cancelled → status page + refund note
                                        ├─ unpublished → status page
                                        ├─ private → status page (no title leak)
                                        └─ draft → 404 (never was public)
```

Rules enforced:
- Destination is the stable public URL — survives description/flyer/content edits.
- QR contains no attendee, payment, or staff data; cannot admit, redeem food, or validate tickets.
- `redesign()` changes only `design` JSON; `public_code` and destination are untouched, so every printed copy keeps working.

## 3. Ticket QR lifecycle

```
Checkout paid ──► Order::generateSecureTicketToken()
                      tok_ + bin2hex(random_bytes(32))  [256-bit CSPRNG]
                          │
                          ▼
              saving hook: raw → 'sha256:'+hash into
              secure_ticket_token; hash into
              secure_ticket_token_hash (UNIQUE index)
              token_version = 1
                          │
        ┌─────────────────┼──────────────────────┐
        ▼                 ▼                      ▼
   DELIVERY          REISSUE                REVOKE
   QR rendered       POST /tickets/{o}/     POST /tickets/{o}/revoke
   only when         reissue (owner or      (verify-capable staff
   settled & not     manager)               only)
   revoked           │ lockForUpdate        │ sentinel hash swap
                     │ reason required      │ token_revoked_at
                     │ rotate token+hash    │ revocation_reason
                     │ version++            │ version++
                     │ audit + notify       │ audit + notify
                     ▼                      ▼
              old QR dead immediately — hash no longer matches
```

Storage guarantees:
- Raw token never persisted; `redactTokenForLog()` scrubs it from logs.
- `isTicketRevoked()` = explicit revocation OR terminal status (refunded/rejected/cancelled/expired).
- `isSettledForAdmission()` gates QR display: paid/confirmed/partially_refunded and not revoked.

## 4. Ticket verification workflow (gate scan)

```
Scanner ──POST /worker/events/{event}/scan {ticket_code, scan_request_id}──►
        │ middleware: auth → worker.active → TicketVerificationRequest::authorize
        │             → worker.permission:scan_qr_tickets → throttle:30,1
        ▼
WorkerTerminalController::scanTicket
        │ isToken? → whereTicketToken(hash) scoped to THIS event
        │ not found → 404 invalid_ticket + audit(ticket_redemption_rejected)
        ▼
EventWorkerService::scanAndValidateTicket          [DB::transaction]
        │ lockForUpdate on order row
        │ replay check: same scan_request_id + same checker → return original
        ▼
admitLockedTicket — every value read from DB, never from client:
        1. ticket_code & order_number exist          → invalid_ticket
        2. order.event_id == gate event              → wrong_event
        3. status != expired & not closed            → ticket_expired
        4. admission window: doors_open_time(event   → event_not_open
           date, event tz) .. ends_at
        5. token_revoked_at null & status not in     → ticket_revoked
           [refunded, rejected, cancelled, expired]
        6. status IN [paid, confirmed]               → payment_pending
        7. checked_in_at null                        → already_admitted
        ▼ all pass
   checked_in_at = now, checked_in_by = worker->id
   audit(ticket_redemption_verified)
        ▼
Structured JSON: success | already_admitted | reason_code | ticket{...}
```

Reason codes: `promotional_qr`, `invalid_ticket`, `unauthorized_manual_entry`, `unauthorized_scanner`, `wrong_event`, `ticket_expired`, `event_not_open`, `ticket_revoked`, `payment_pending`, `already_admitted`.

## 5. Food fulfillment workflow

```
Ticket QR ──► lookupOrder (read-only foodView: first name,
              payment flag, item lines — NO prices/PII)
                  │
                  ▼ staff picks order + item + qty
POST /worker/events/{event}/collect {order_id, order_item_id, quantity}
        │ middleware: auth → worker.active → UpdateFulfillmentRequest
        │             (assignment + station + mark_quantities_collected)
        ▼
OrderOperationsService::recordHandover          [DB::transaction]
        │ assertCapability(order organizer, 'collect')
        │ item->lockForUpdate; package lines refused
        │ isEligibleForHandover? else supervisor_override + reason
        │ collected + delta within [0, quantity] else reject
        ▼
order_items.collected_quantity / handed_over_* updated
handover_audit_logs row: staff, station, qty, remaining,
                       timestamp, action_type, override reason
        ▼
Failures (over-qty, unpaid, wrong station, no capability)
        → food_handover_rejected security audit entry
```

Admission scans never touch `collected_quantity`; food never touches `checked_in_at`.

## 6. Staff authorization model

```
User account (must exist, status active)
      │
      ▼ EventWorkerAssignment (event_id | NULL=org-wide, organizer_id,
      │                        worker_role | custom_permissions,
      │                        starts_at/expires_at, status, station_assignment)
      │
      ├─ RequireActiveWorkerAssignment  → resolves per {event} route binding,
      │                                   rejects revoked/pending/expired/not-started
      ├─ RequireWorkerPermission:{act}  → role->canPerform(act) or custom_permissions
      ├─ EventWorkerPolicy              → per-action authorization (scan/pay/food/reports)
      │                                   org-wide assignments constrained to organizer_id
      └─ OrderOperationsService::capabilitiesFor → verify/collect/scan/cash/reverse/refund
                                            staff NEVER get reverse/refund
```

Roles: `payment_clerk`, `ticket_checker`, `food_station_worker`, `event_supervisor`, `custom_restricted_worker` (explicit permission list only).

## 7. Offline policy

**No offline admission exists.** Scanners are online-only; a lost connection renders "CONNECTION LOST — NOT ADMITTED" and the operator retries the same code (idempotent `scan_request_id`). A controlled offline mode (signed manifests, provisional admits, reconciliation) is designed but **not implemented** — see `eventbite-offline-scanning-policy.md` and `backend/docs/eventbite-offline-scanning-security.md`.

## 8. Component map

| Concern | Component |
|---|---|
| Promo QR identity/design/lifecycle | `App\Services\EventQr\EventQrService`, `QrArtworkRenderer`, `QrDesignCatalog`, `App\Models\EventQrCode` |
| Promo QR routes | `EventQrController` (`/e/{code}`, `/e/{code}/qr.svg`, host download/preview/design/retry) |
| Ticket token | `Order::generateSecureTicketToken/hashTicketToken/whereTicketToken/reissueTicketToken/revokeTicketToken` |
| Gate scan | `WorkerTerminalController::scanTicket` → `EventWorkerService::scanAndValidateTicket` |
| Food fulfillment | `WorkerTerminalController::lookupOrder/collectFood` → `OrderOperationsService::recordHandover` |
| Authorization | `RequireActiveWorkerAssignment`, `RequireWorkerPermission`, `TicketVerificationRequest`, `UpdateFulfillmentRequest`, `EventWorkerPolicy`, `EventWorkerRole`/`Permission` enums |
| Audit | `SecurityAuditLog` (immutable), `AuditLogService`, `HandoverAuditLog` |
| Fraud monitoring | `Admin\FraudMonitorController` (`/admin/console/fraud-monitor`), `Admin\FraudPreventionController` (settings) |
| QR rendering | `App\Support\QrCode` (ticket, chillerlan ECC-Q), `QrArtworkRenderer` (promo) |
