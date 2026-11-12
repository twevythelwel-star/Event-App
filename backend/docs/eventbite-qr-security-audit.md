# EventBite QR-Code Security Audit & Architecture Report (Phase 1)

**Date:** 2026-11-10  
**Scope:** Comprehensive inspection and security audit of EventBite's QR-code architecture (Public Promotional Event QR, Individual Attendee Ticket QR, Authorized Gate Scanner, Food Handover & Fulfillment, Token Lifecycle, Staff Access Control, Audit Logging, and Offline Scanning Policy).  
**Phase Status:** **Phase 1 Complete (Inspection & Audit Only — Ready for Phase 2 Approval)**

---

## 1. Project Protection & Baseline State (1.1)

Before auditing or proposing remediations, the project state was inspected and preserved without destructive changes:

- **Git Branch:** `main`
- **Recent Commits:**
  - `0a59bb0` — *Prepare for MySQL 8 and Laravel Cloud; fix the CI build*
  - `7434e73` — *Make Blade the single front end; retire the React app*
  - `8908346` — *Fix the 13 failing backend tests*
  - `aa4bf37` — *Photo library, image accuracy, and acceptance-review fixes*
  - `4697b39` — *Compliance acceptance tests and consent fixes*
- **Working Tree & Uncommitted Work:** Active Blade UI, compliance, dashboard, and QR token hash changes in `backend/` were preserved intact. No database resets, ticket deletions, or destructive migrations were executed.
- **Database & Deployment Configuration:**
  - Laravel 11 native backend (`backend/`) configured for SQLite in local/test environments and MySQL 8 / Laravel Cloud in production (`backend/bootstrap/app.php` trusts proxy headers via `TRUSTED_PROXIES`).
  - Media and QR SVG artwork storage uses `App\Services\MediaStorageService::diskName()` (`local` by default, object-storage compatible for ephemeral cloud hosts).

---

## 2. Existing QR Architecture Overview (1.2)

EventBite implements **two strictly separated QR systems**:

```
┌──────────────────────────────────────────────────────────────────────────────┐
│ A. PUBLIC PROMOTIONAL EVENT QR (Discovery Only)                              │
│    Model: App\Models\EventQrCode (table: event_qr_codes)                     │
│    Payload: https://<app.url>/e/<10-char-public_code>                        │
│    Renderer: App\Services\EventQr\QrArtworkRenderer (chillerlan/php-qrcode)  │
│    Controller: EventQrController::resolve() -> 302 to /events/{id}           │
│    Security Boundary: Published events only; zero PII/payment data;          │
│                       explicitly blocked from gate admission.                │
└──────────────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────────────┐
│ B. INDIVIDUAL ATTENDEE TICKET QR (Protected Admission & Food Fulfillment)    │
│    Model: App\Models\Order (table: orders)                                   │
│    Payload: Raw opaque token (tok_<64-hex-chars>, 256-bit CSPRNG)            │
│    Server Storage: SHA-256 hash in orders.secure_ticket_token_hash           │
│    Renderer: App\Support\QrCode::svg() (inline SVG, only when $settled)      │
│    View: resources/views/checkout/ticket.blade.php (authenticated owner)     │
└──────────────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────────────┐
│ C. AUTHORIZED STAFF SCANNER & FULFILLMENT DESK                               │
│    Middleware: auth, active, worker.active, worker.permission                │
│    Controller: WorkerTerminalController (scanTicket, lookupOrder, collect)   │
│    Services: EventWorkerService, TicketVerificationService,                  │
│              OrderOperationsService, CashLedgerService                       │
│    Audit Tables: security_audit_logs, handover_audit_logs                    │
└──────────────────────────────────────────────────────────────────────────────┘
```

### Complete Inventory of Located QR & Redemption Files

| Layer | File Path | Responsibility |
| :--- | :--- | :--- |
| **Models** | [`backend/app/Models/EventQrCode.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Models/EventQrCode.php) | 1:1 promotional QR identity per `Event` (`public_code`, `preset`, `design`, `status`, `asset_path`). |
| | [`backend/app/Models/Order.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Models/Order.php) | Holds `ticket_code`, `order_number`, `secure_ticket_token`, `secure_ticket_token_hash`, `token_version`, `token_reissued_at`, `token_revoked_at`, `checked_in_at`, `checked_in_by`, `check_in_request_id`. |
| | [`backend/app/Models/OrderItem.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Models/OrderItem.php) | Ticket (`line_type = 'ticket'`), Food (`item`), Package (`package`), and Component (`component`) lines with `quantity`, `collected_quantity`, `handover_status`. |
| | [`backend/app/Models/TicketTransfer.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Models/TicketTransfer.php) | Tracks pending/accepted ticket transfers using SHA-256 `token_hash`. |
| | [`backend/app/Models/EventWorkerAssignment.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Models/EventWorkerAssignment.php) | Event-scoped staff role, shift window (`starts_at`, `expires_at`), station assignment, and `sanitizeOrder()` filter. |
| | [`backend/app/Models/StaffClaimCode.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Models/StaffClaimCode.php) | Single-use expiring claim codes for staff onboarding. |
| | [`backend/app/Models/HandoverAuditLog.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Models/HandoverAuditLog.php) | Immutable audit log of food portion collections, reversals, and supervisor overrides. |
| | [`backend/app/Models/SecurityAuditLog.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Models/SecurityAuditLog.php) | Immutable security log of admissions, rejections, token reissues/revocations, and worker assignments. |
| **Services & Support** | [`backend/app/Services/EventQr/EventQrService.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Services/EventQr/EventQrService.php) | Generates, decodes/verifies (at 8px, 4px, and JPEG q=60), and stores promotional event QR SVGs; detects promotional QR payloads. |
| | [`backend/app/Services/EventQr/QrArtworkRenderer.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Services/EventQr/QrArtworkRenderer.php) | Renders SVG, high-res PNG, and printable poster for promotional event QRs. |
| | [`backend/app/Services/EventQr/QrDesignCatalog.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Services/EventQr/QrDesignCatalog.php) | Enforces contrast ratios (≥ 4.5:1), dark-on-light polarity, ECC level rules, and safe fallback presets. |
| | [`backend/app/Support/QrCode.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Support/QrCode.php) | Pure-PHP Reed-Solomon QR matrix & inline SVG encoder (versions 1–5, ECC Level L, 4-module quiet zone) for attendee tickets. |
| | [`backend/app/Services/TicketVerificationService.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Services/TicketVerificationService.php) | Centralized ticket verification service (token hash lookup, row lock, status/revocation/restriction checks, audit logging). |
| | [`backend/app/Services/EventWorkerService.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Services/EventWorkerService.php) | Staff assignment lifecycle, shift window validation, separation-of-duties checks, and `scanAndValidateTicket()`. |
| | [`backend/app/Services/OrderPlacementService.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Services/OrderPlacementService.php) | Atomic order creation, stock locking, `ticket_code` (`SFF-...`) and `secure_ticket_token` generation, and `retrieveOrderSecurely()`. |
| | [`backend/app/Services/OrderOperationsService.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Services/OrderOperationsService.php) | Payment status transitions, fulfillment status updates, and locked food handover (`recordHandover()`, `closeOrder()`). |
| | [`backend/app/Services/Checkout/TicketTransferService.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Services/Checkout/TicketTransferService.php) | Secure ticket transfer workflow with SHA-256 transfer token hash and automatic QR token rotation on acceptance. |
| | [`backend/app/Services/Checkout/ComplimentaryTicketService.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Services/Checkout/ComplimentaryTicketService.php) | Host-issued complimentary tickets with stock locking, audit logging, and 256-bit QR token generation. |
| **Controllers** | [`backend/app/Http/Controllers/EventQrController.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Http/Controllers/EventQrController.php) | Public short-link resolver (`/e/{code}`), public SVG endpoint (`/e/{code}/qr.svg`), and host QR download/redesign/retry actions. |
| | [`backend/app/Http/Controllers/WorkerTerminalController.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Http/Controllers/WorkerTerminalController.php) | Staff terminal UI (`index`), gate scan endpoint (`scanTicket`), cash collection (`recordCash`), food lookup (`lookupOrder`), and food handover (`collectFood`). |
| | [`backend/app/Http/Controllers/CheckoutController.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Http/Controllers/CheckoutController.php) | Renders `checkout/ticket.blade.php` (`ticket`), handles payment reference submission, controlled QR reissue (`reissueTicket`), and revocation (`revokeTicket`). |
| | [`backend/app/Http/Controllers/OrderController.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Http/Controllers/OrderController.php) | Legacy/JSON order creation (`store`) and retrieval (`retrieve`). |
| | [`backend/app/Http/Controllers/Api/OrderOperationsController.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Http/Controllers/Api/OrderOperationsController.php) | JSON operations endpoints (`/api/v1/operations/orders`, `/lookup`, `/payment`, `/fulfillment`, `/handover`, `/close`, `/refund`). |
| **Middleware, Policies & Requests** | [`backend/app/Http/Middleware/RequireActiveWorkerAssignment.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Http/Middleware/RequireActiveWorkerAssignment.php) | Enforces active shift window (`starts_at` / `expires_at`), non-revoked status, and binds `worker_assignment` to request attributes. |
| | [`backend/app/Http/Middleware/RequireWorkerPermission.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Http/Middleware/RequireWorkerPermission.php) | Enforces granular worker permission (`scan_qr_tickets`, `record_cash_received`, `view_assigned_food_items`, `mark_quantities_collected`). |
| | [`backend/app/Http/Requests/TicketVerificationRequest.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Http/Requests/TicketVerificationRequest.php) | Validates `ticket_code` (string, max 80) and `scan_request_id` (nullable UUID) and checks worker scanning authorization. |
| | [`backend/app/Http/Requests/UpdateFulfillmentRequest.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Http/Requests/UpdateFulfillmentRequest.php) | Validates food handover payload (`order_id`, `order_item_id`, `quantity`). |
| | [`backend/app/Policies/OrderPolicy.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Policies/OrderPolicy.php) | Authorization policy for viewing orders, recording cash, verifying payments, issuing refunds, and modifying items. |
| **Blade Views & Components** | [`backend/resources/views/checkout/ticket.blade.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/resources/views/checkout/ticket.blade.php) | Attendee digital pass page: renders QR code only when `$settled`, hides QR when unpaid or `$void`/`$revoked`, provides QR reissue CTA. |
| | [`backend/resources/views/components/ticket-card.blade.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/resources/views/components/ticket-card.blade.php) | Attendee dashboard wallet summary card: displays `ticket_code`, payment status, admission status, and link to full pass (never exposes `secure_ticket_token`). |
| | [`backend/resources/views/events/partials/event-qr.blade.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/resources/views/events/partials/event-qr.blade.php) | Public promotional QR card on `events/show.blade.php`, explicitly labeled *"Promotional Share Code · Not an Admission Ticket"*. |
| | [`backend/resources/views/dashboards/worker-terminal.blade.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/resources/views/dashboards/worker-terminal.blade.php) & [`ticket-check-in.blade.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/resources/views/dashboards/partials/ticket-check-in.blade.php) | Staff scanner terminal with SHA-256 `scan_request_id` idempotency, fail-closed offline handling, and food station handover UI. |

---

## 3. Existing QR Generation, Token Format & Database Schema (1.2 & 1.3)

### 3.1 Promotional Event QR Format & Generation
- **Identifier:** `event_qr_codes.public_code` — 10 characters drawn via `random_int()` from `ABCDEFGHJKMNPQRSTUVWXYZ23456789` (31-char unambiguous alphabet, ~49.5 bits of entropy), unique per event.
- **Encoded Payload:** `https://<app.url>/e/<public_code>`.
- **Verification on Generation:** `EventQrService::firstReadable()` renders every candidate preset with `chillerlan/php-qrcode` and decodes it back using `QRCode::readFromBlob()` at 8px/module, 4px/module, and after JPEG quality-60 compression before persisting. If a preset fails to decode, it automatically falls back to the high-contrast `#000000` on `#FFFFFF` `minimal` preset.

### 3.2 Individual Attendee Ticket QR Format & Generation
- **Human-Readable Reference (`orders.ticket_code`):** `SFF-` + 8 characters from `ABCDEFGHJKMNPQRSTUVWXYZ23456789` (printed on receipts and dashboard cards; cannot admit a guest at a regular Ticket Checker terminal—requires an `EVENT_SUPERVISOR` manual check).
- **Cryptographic Admission Token (`orders.secure_ticket_token`):** `tok_` + 64 hexadecimal characters generated from `bin2hex(random_bytes(32))` (**256 bits of CSPRNG entropy**, total length 68 chars).
- **Server-Side Verification Hash (`orders.secure_ticket_token_hash`):** 64-character SHA-256 hex digest (`hash('sha256', $token)`) with a unique index.
- **QR Matrix Rendering:** `App\Support\QrCode::svg()` encodes the token in Byte Mode, Error Correction Level L, with a 4-module white quiet zone (`#FFFFFF` plate, `#07111F` modules) inside `.eb-ticket-qr-well` (which strips all pseudo-elements and decorative overlays in `06-components.css`).

### 3.3 Existing Database Schema & Model Relationships
- **`events` (1) ──< (1) `event_qr_codes`:** Unique `event_id` and unique `public_code`.
- **`events` (1) ──< (N) `orders`:** Each `Order` belongs to one `Event` (`event_id`), one `Organizer` (`organizer_id`), and optionally one `User` (`user_id`).
- **`orders` (1) ──< (N) `order_items`:** An `Order` can contain multiple `OrderItem` rows:
  - `line_type = 'ticket'` (`category = 'Tickets'`, `ticket_tier_id != null`, `quantity >= 1`)
  - `line_type = 'item'` (à la carte food/drink item, `menu_item_id != null`, `quantity`, `collected_quantity`)
  - `line_type = 'package'` (meal package header holding price)
  - `line_type = 'component'` (zero-priced food items included in a meal package or bundled with a ticket tier)
- **`orders` (1) ──< (N) `ticket_transfers`:** Stores `from_user_id`, `to_email`, `to_user_id`, `token_hash` (SHA-256), `status` (`pending`, `accepted`, `cancelled`), `expires_at`.
- **`orders` (1) ──< (N) `handover_audit_logs`:** Tracks every food collection/reversal per `order_id` and `item_name`.
- **`events` (1) ──< (N) `event_worker_assignments`:** Scopes staff (`user_id`) to a specific `event_id`, `worker_role`, `station_assignment`, and shift window (`starts_at`, `expires_at`, `status`, `is_active`).

---

## 4. Existing Scanner, Admission, Food Fulfillment & Offline Workflows (1.3 & 1.4)

### 4.1 Gate Admission Workflow
1. Staff member opens `/worker/events/{event}/terminal` (guarded by `auth`, `active`, `worker.active`).
2. Scanner submits `POST /worker/events/{event}/scan` (guarded by `throttle:30,1` and `worker.permission:scan_qr_tickets`) with `ticket_code` and client-generated `scan_request_id` (UUID derived per scanned code hash in `sessionStorage`).
3. `WorkerTerminalController::scanTicket()` checks:
   - Whether the input is a promotional event QR (`EventQrService::isPromotionalEventQrPayload()`), rejecting with HTTP 422 if so.
   - Whether the input starts with `tok_`; if not, only `EVENT_SUPERVISOR` is permitted to perform a manual code lookup (HTTP 403 for regular `TICKET_CHECKER`).
   - Resolves the `Order` for `$event->id` using `whereTicketToken($code)` (matching `secure_ticket_token_hash`).
4. `EventWorkerService::scanAndValidateTicket()` opens a `DB::transaction()`, locks the `Order` row via `lockForUpdate()`, checks idempotency (`check_in_request_id`), verifies `status` (`paid` or `confirmed`) and revocation (`token_revoked_at === null`), verifies `checked_in_at === null`, records `checked_in_at = now()` and `checked_in_by = $worker->id`, writes to `security_audit_logs`, and returns role-sanitized ticket data.

### 4.2 Food Fulfillment Workflow
1. Food station worker submits `POST /worker/events/{event}/lookup` with a QR token, `ticket_code`, or `order_number`.
2. `WorkerTerminalController::lookupOrder()` returns `foodView()`: order number, guest first name, `payment_verified` (`$order->isEligibleForHandover()`), and food lines (`isHandoverItem()`), excluding prices and payment references.
3. Worker submits `POST /worker/events/{event}/collect` with `order_id`, `order_item_id`, and `quantity`.
4. `OrderOperationsService::recordHandover()` locks the `OrderItem` row (`lockForUpdate()`), verifies `$order->isEligibleForHandover()` (unless `supervisor_override` with reason), caps `collected_quantity` at `quantity`, updates `handover_status`, writes `HandoverAuditLog`, and updates `orders.all_handed_over` and `orders.closed_at`.

### 4.3 Existing Offline Behavior
- `worker-terminal.blade.php` listens to browser `online`/`offline` events and displays connection status in `#scanner_connection`.
- When a `fetch()` fails due to network loss, `#scan_result` displays `CONNECTION LOST — NOT ADMITTED`, preserves the code in `#ticket_input`, and retains the deterministic `scan_request_id` in `sessionStorage` so retrying after reconnection is idempotent.

---

## 5. Working Security Controls Confirmed During Audit (1.4)

1. **Promotional vs. Admission QR Separation:** Promotional event QR codes (`/e/{code}`) and attendee ticket QR codes (`tok_...`) use completely separate tables, formats, controllers, and visual treatments. Promotional QR codes are rejected at the gate.
2. **256-Bit CSPRNG Token Generation:** `Order::generateSecureTicketToken()` uses `bin2hex(random_bytes(32))` (256 bits of cryptographic randomness), and `ticket_code` (`SFF-...`) is drawn independently of `order_number` (`ORD-...`).
3. **Database Row Locking on Redemption:** Both ticket admission (`Order::lockForUpdate()`) and food item collection (`OrderItem::lockForUpdate()`) execute inside `DB::transaction()` blocks, preventing race-condition double redemptions.
4. **Network Retry Idempotency:** `orders.check_in_request_id` prevents a dropped HTTP response from causing a false "Already Used" error when the same scanner retries the same scan.
5. **No PII or Payment Details in QR Payloads:** Neither promotional QR codes nor attendee ticket QR codes embed names, emails, phone numbers, or payment methods in the QR payload.
6. **Ticket Transfer Token Rotation:** `TicketTransferService::accept()` hashes transfer links with SHA-256 (`ticket_transfers.token_hash`) and rotates the order's `secure_ticket_token` and `secure_ticket_token_hash` upon acceptance so the sender's old QR stops working.
7. **Unobstructed QR Quiet Zone:** `App\Support\QrCode::svg()` enforces a 4-module white quiet zone and `06-components.css` strips decorative overlays over `.eb-ticket-qr-well`.

---

## 6. Identified Security Vulnerabilities & Architectural Gaps (1.4 & 1.5)

### Finding 1 — Unpaid Cash Orders Marked `authorized_unpaid = true` at Placement Allow Food Handover & `TicketVerificationService` Admission Before Payment
- **Priority:** **Critical**
- **Location:**
  - [`backend/app/Services/OrderPlacementService.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Services/OrderPlacementService.php#L246-L249)
  - [`backend/app/Models/Order.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Models/Order.php#L266-L273) (`isEligibleForHandover()`)
  - [`backend/app/Services/TicketVerificationService.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Services/TicketVerificationService.php#L157-L161)
- **Vulnerability Details:**
  - When a customer selects `method = 'cash'` at checkout, `OrderPlacementService::placeOrder()` sets `status = 'awaiting_payment'` and `authorized_unpaid = true` immediately upon order submission—before any cash is paid at the event desk.
  - `Order::isEligibleForHandover()` returns `$this->status === 'paid' || $this->authorized_unpaid === true`, and `TicketVerificationService::verifyLockedTicket()` checks `in_array($order->status, ['paid', 'confirmed'], true) || $order->authorized_unpaid === true`.
  - Consequently, an attendee who places a cash order and never pays the Payment Clerk has `payment_verified = true` at the Food Station and can collect all food items without paying, or be admitted if verified via `TicketVerificationService`!
- **Required Remediation:**
  - Do not set `authorized_unpaid = true` automatically on unpaid cash orders at checkout (`authorized_unpaid` should default to `false` unless explicitly authorized by an organizer/host override, or food handover/admission must require settled payment `in_array($status, ['paid', 'confirmed', 'partially_refunded'], true)` unless an explicit supervisor override is recorded).
  - Also update `Order::isEligibleForHandover()` to reject revoked/cancelled/expired orders (`if ($this->isTicketRevoked()) return false;`).

---

### Finding 2 — Raw `secure_ticket_token` Exposed in API Responses (`OrderResource`, `OrderController`) Before Payment Verification and to Staff Roles
- **Priority:** **Critical**
- **Location:**
  - [`backend/app/Http/Resources/OrderResource.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Http/Resources/OrderResource.php#L76)
  - [`backend/app/Http/Controllers/Api/OrderOperationsController.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Http/Controllers/Api/OrderOperationsController.php#L28)
  - [`backend/app/Http/Controllers/OrderController.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Http/Controllers/OrderController.php#L62-L64)
- **Vulnerability Details:**
  1. `OrderResource::toArray()` always serializes `'secureTicketToken' => $this->secure_ticket_token`. When staff or hosts call `GET /api/v1/operations/orders` or `POST /api/v1/operations/lookup`, `OrderOperationsController::present()` strips `PAYMENT_FIELDS` for non-payment staff, but leaves `secureTicketToken` exposed in the JSON payload for every order! A staff member or compromised staff session can harvest raw admission QR tokens for all orders at the event.
  2. `OrderController::store()` and `OrderController::retrieve()` return `secure_ticket_token` even when the order status is `awaiting_payment` or `payment_submitted` (unpaid), allowing an unpaid buyer to extract `secure_ticket_token` from the JSON response and render their own QR code before payment is settled.
- **Required Remediation:**
  - Never expose `secureTicketToken` in `OrderResource` for staff/operations endpoints (`OrderOperationsController::present()`), and never expose `secure_ticket_token` to attendees on unpaid or revoked orders (`!$order->isSettledForAdmission()`).
  - Add `'secure_ticket_token'` and `'secure_ticket_token_hash'` to `$hidden` on `App\Models\Order` so raw Eloquent JSON serialization never leaks tokens or hashes by default.

---

### Finding 3 — Duplicate & Divergent Verification Logic Between `EventWorkerService::scanAndValidateTicket()` and `TicketVerificationService::verifyTicket()`
- **Priority:** **High**
- **Location:**
  - [`backend/app/Services/EventWorkerService.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Services/EventWorkerService.php#L112-L278)
  - [`backend/app/Services/TicketVerificationService.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Services/TicketVerificationService.php#L37-L220)
- **Vulnerability Details:**
  - Two separate classes implement ticket admission verification with inconsistent rules:
    1. **Partially Refunded Orders:** `checkout/ticket.blade.php` treats `partially_refunded` (e.g., a food item was refunded, but the ticket item remains valid) as `$settled = true` and displays the admission QR code. However, neither `EventWorkerService::admitLockedTicket()` nor `TicketVerificationService::verifyLockedTicket()` includes `'partially_refunded'` (when ticket lines remain active) in the valid admission status list!
    2. **Food-Only Orders at the Admission Gate:** An order that contains *only* advance food pre-order items (`OrderItem` with `category != 'Tickets'` and no `ticket_tier_id`) still receives a `secure_ticket_token` for food pickup. However, neither `EventWorkerService` nor `TicketVerificationService` checks whether the order actually contains an admission ticket (`OrderItem` with `line_type = 'ticket'` or `category = 'Tickets'`) when scanning at the **Admission Gate** (`WorkerTerminalController::scanTicket`) when the event has ticket tiers!
    3. **Ticket Restrictions & Event Status:** `TicketVerificationService` evaluates `$order->ticket_restrictions` (`time_window`, `max_uses`, `age_restriction`) and checks `$event->status === 'published'`, whereas `EventWorkerService::admitLockedTicket()` (which `WorkerTerminalController::scanTicket()` calls) skips `ticket_restrictions` and does not check if the event was cancelled!
    4. **Closed Food Order Check:** `EventWorkerService::admitLockedTicket()` line 192 rejects admission if `($order->closed_at && $order->all_handed_over)`—meaning if a guest collects their food first (`all_handed_over = true`, which sets `closed_at = now()`), their admission ticket is falsely rejected as "expired" at the gate!
- **Required Remediation:**
  - Unify the admission rules between `EventWorkerService::scanAndValidateTicket()` and `TicketVerificationService::verifyTicket()` so both enforce:
    - Event is active (`status === 'published'`, not `cancelled`).
    - Ticket is not revoked (`!$order->isTicketRevoked()`).
    - Payment is settled (`in_array($order->status, ['paid', 'confirmed', 'partially_refunded'], true)`).
    - Completing food handover (`all_handed_over = true`) does **not** invalidate gate admission (`closed_at` on `orders` means food handover is complete, not that the ticket is expired!).
    - Ticket restrictions (`time_window`, etc.) are enforced consistently.

---

### Finding 4 — `OrderPolicy` Compares Non-Existent `$user->organizer_id` Instead of `organizer_users` Membership
- **Priority:** **High**
- **Location:**
  - [`backend/app/Policies/OrderPolicy.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Policies/OrderPolicy.php#L31-L34)
- **Vulnerability / Bug Details:**
  - In [`backend/app/Policies/OrderPolicy.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Policies/OrderPolicy.php#L31-L34) (`view()`, `issueRefund()`, `modifyItems()`), the policy checks `$order->event->organizer_id === $user->organizer_id`. As documented in `EventWorkerService.php:712`, `users` has no `organizer_id` column (organizer membership is stored in `organizer_users`), so `OrderPolicy::view()`, `issueRefund()`, and `modifyItems()` always evaluate `null === $order->event->organizer_id` (`false`) for Event Hosts!
- **Required Remediation:**
  - Update `OrderPolicy::view()`, `OrderPolicy::issueRefund()`, and `OrderPolicy::modifyItems()` to use `$this->runsOrdersEvent($user, $order)` (which checks `EventWorkerService::actsWithoutAssignment($user, $event)` via `organizer_users`).

---

### Finding 5 — `TicketVerificationRequest` `max:80` Rule Can Truncate or Reject Full Promotional URLs Scanned at the Gate Before Promotional QR Detection Runs
- **Priority:** **Medium**
- **Location:**
  - [`backend/app/Http/Requests/TicketVerificationRequest.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Http/Requests/TicketVerificationRequest.php#L31-L37)
  - [`backend/app/Http/Controllers/WorkerTerminalController.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Http/Controllers/WorkerTerminalController.php#L166)
- **Vulnerability Details:**
  - `TicketVerificationRequest` validates `'ticket_code' => ['required', 'string', 'max:80']`. If a guest presents a promotional event QR encoding a longer URL (e.g., `https://staging-events.example.com/events/annual-caribbean-seafood-festival-2026?ref=poster` > 80 chars), Laravel FormRequest validation fails with a generic 422 validation error before `WorkerTerminalController::scanTicket()` can log the promotional QR scan attempt or return the explicit `promotional_qr_rejected` warning.
- **Required Remediation:**
  - Increase `ticket_code` / `code` max length in `TicketVerificationRequest` and `WorkerTerminalController::lookupOrder()` to `max:255`.

---

### Finding 6 — Multi-Quantity Ticket Orders Share a Single `checked_in_at` Timestamp Without Party-Size Visibility in the Gate Scanner
- **Priority:** **Medium**
- **Location:**
  - [`backend/app/Models/Order.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Models/Order.php)
  - [`backend/app/Services/EventWorkerService.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/app/Services/EventWorkerService.php#L214-L224)
- **Vulnerability / Operational Details:**
  - An order can have multiple ticket quantities (e.g., `2 × General Admission` on one order). Currently, `Order` has `checked_in_at` and `checked_in_by` for the entire order, and `OrderItem` has `quantity` and `collected_quantity` (used for food items, while ticket `OrderItem` rows leave `collected_quantity = 0`).
  - When the order's QR code is scanned once at the gate, `checked_in_at` is set on the `Order`, admitting the order as a single group check-in, and the gate response does not tell the gate staff **how many guests (`ticket_quantity`)** are covered by that pass!
- **Required Remediation:**
  - Include `ticket_quantity` and `ticket_summary` (e.g., `2 × VIP Cabana Pass`) in the sanitized gate scan response (`ticket` payload in `EventWorkerService::scanAndValidateTicket()` and `TicketVerificationService::verifyTicket()`) and display the party size on `#scan_details` in `worker-terminal.blade.php` so gate staff know how many guests that single pass admits.

---

### Finding 7 — Rate Limiting & Brute-Force / Fraud Anomaly Detection on Scanner & Order Retrieval Endpoints
- **Priority:** **Medium**
- **Location:**
  - [`backend/routes/web.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/routes/web.php#L101) (`GET /orders/retrieve`, `POST /attendee/retrieve-ticket`)
  - [`backend/routes/web.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/routes/web.php#L255-L266) (`/worker/events/{event}/scan`)
- **Vulnerability Details:**
  1. `GET /orders/retrieve` (`orders.retrieve`) and `POST /attendee/retrieve-ticket` (`attendee.retrieve-ticket`) have **no `throttle` middleware**, allowing high-rate brute-forcing of `ticket_code` (`SFF-...`) + phone number combinations.
  2. Conversely, `POST /worker/events/{event}/scan` is throttled at `throttle:30,1` (30 scans per minute = 1 scan every 2 seconds), which can bottleneck a busy gate scanner during peak event entry (where a fast handheld scanner processes 60–120 guests/minute), while repeated *invalid* scan attempts from a single user/IP are not tracked for fraud anomaly alerting.
- **Required Remediation:**
  - Add strict rate limiting (`throttle:10,1`) to `orders.retrieve` and `attendee.retrieve-ticket`.
  - Increase authorized gate scanner throughput limit on `worker.scan` to `throttle:120,1` for valid staff sessions while logging repeated rapid scan rejections (`ticket_redemption_rejected`) as a fraud/enumeration warning in `SecurityAuditLog`.

---

## 7. Summary of Priority Classification & Remediation Scope (1.5)

| ID | Finding | Priority | Files Requiring Modification |
| :---: | :--- | :---: | :--- |
| **F1** | Unpaid cash orders set `authorized_unpaid = true` at checkout, allowing unpaid food collection & `TicketVerificationService` admission; `isEligibleForHandover()` ignores revoked status | **Critical** | `backend/app/Services/OrderPlacementService.php`, `backend/app/Models/Order.php`, `backend/app/Services/TicketVerificationService.php` |
| **F2** | `OrderResource` and `OrderController` expose raw `secure_ticket_token` in JSON API responses to staff and on unpaid orders | **Critical** | `backend/app/Http/Resources/OrderResource.php`, `backend/app/Http/Controllers/Api/OrderOperationsController.php`, `backend/app/Http/Controllers/OrderController.php`, `backend/app/Models/Order.php` |
| **F3** | Divergent validation between `EventWorkerService` and `TicketVerificationService` (`all_handed_over` blocking gate entry, `partially_refunded` handling, `ticket_restrictions`) | **High** | `backend/app/Services/EventWorkerService.php`, `backend/app/Services/TicketVerificationService.php` |
| **F4** | `OrderPolicy` compares non-existent `$user->organizer_id` instead of `organizer_users` membership (`runsOrdersEvent`) | **High** | `backend/app/Policies/OrderPolicy.php` |
| **F5** | `TicketVerificationRequest` `max:80` rejects long promotional URLs before promotional QR detection & audit logging can run | **Medium** | `backend/app/Http/Requests/TicketVerificationRequest.php`, `backend/app/Http/Controllers/WorkerTerminalController.php` |
| **F6** | Gate scan response omits `ticket_quantity` / tier summary for multi-ticket orders | **Medium** | `backend/app/Services/EventWorkerService.php`, `backend/app/Services/TicketVerificationService.php`, `backend/resources/views/dashboards/worker-terminal.blade.php` |
| **F7** | Missing rate limits on `orders.retrieve` and `attendee.retrieve-ticket`; gate scan throttle tuning (`120,1`) | **Medium** | `backend/routes/web.php` |

### Database Changes Required
- Migration [`2026_11_11_000068_add_qr_security_architecture_to_orders_table.php`](file:///c:/Users/Trevaughn.Thelwell/Downloads/Project1/Events%20App/backend/database/migrations/2026_11_11_000068_add_qr_security_architecture_to_orders_table.php) (`secure_ticket_token_hash`, `token_version`, `token_reissued_at`, `token_revoked_at`, `revocation_reason`) is in place and non-destructive. No destructive schema changes are required.

### Tests Required for Phase 2 Verification
1. **Promotional vs. Admission QR Isolation Test:** Verify `/e/{code}` and long promotional URLs (>80 chars) are rejected at the gate with `promotional_qr_rejected = true` and logged in `security_audit_logs`.
2. **256-Bit Token & Hash Lookup Test:** Verify `Order::generateSecureTicketToken()` produces 256-bit tokens, `secure_ticket_token_hash` is persisted, and raw tokens are not leaked in `OrderResource` or unpaid `OrderController` responses.
3. **Unpaid Cash Order & Food Handover Protection Test:** Verify an unpaid `cash` order (`awaiting_payment`) cannot collect food or pass gate admission until payment is recorded or an explicit supervisor override is logged.
4. **Food Handover Before Gate Entry Test:** Verify a paid guest who collects all food items first (`all_handed_over = true`, `closed_at != null`) can still check in at the admission gate.
5. **Controlled Reissue, Transfer & Immediate Revocation Test:** Verify reissuing or transferring a ticket rotates the token, increments `token_version`, and blocks the old QR token; verify `revokeTicketToken()` blocks admission and food handover immediately.
6. **Concurrent Scan & Idempotent Retry Test:** Verify row locking blocks duplicate scans while `scan_request_id` replays lost responses idempotently.

