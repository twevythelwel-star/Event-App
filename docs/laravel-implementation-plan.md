# EventBite: Laravel design implementation plan

Status: **proposed, awaiting approval** (2026-10-29).
Scope: the "Complete Laravel design implementation" brief, applied to the repository as it is today.

---

## 1. Pre-implementation check (done)

| Item | Finding |
|---|---|
| Branch | `main`, in sync with `origin/main` at `c1cf157` |
| Uncommitted work | Only `backend/.env.example`. It holds a real-looking Twilio key ID and is deliberately left out of commits |
| Other sessions | None running; no edits from other sessions since the last commit |
| Design source | The approved EventBite board (dark navy, single lime accent, poster type; light admin canvas), `docs/design-references.md` §0 tokens, the existing Blade token sheets `backend/public/css/01–05-*.css` |
| Tests | Backend `php artisan test`: 122 passing. Legacy `php tests/run_backend_tests.php` and frontend `npm test` (27 files, 202 tests) passing |

### Two frontends exist; Blade is the production one

- **Laravel Blade** (`backend/resources/views`). This is what the deployment runbook (`docs/production_deployment_and_operations.md` §10) ships: it installs Composer dependencies and caches views, and never builds the React app.
- **React SPA** (`src/`). This is built and tested in CI, talks to `/api/v1`, and falls back to bundled demo data when the API is unreachable. The attendee restyle done earlier in this project lives here.

The brief prefers Blade/Livewire and says not to introduce another JS framework. **This plan treats Blade as the product.** The React SPA is left working but receives no new design work unless you decide otherwise (see §6).

### Screens that exist in Blade today

| Shell | Screens | State |
|---|---|---|
| Customer | `welcome`, `events/index`, `events/show` (pre-order modal), `dashboards/attendee` (tickets wallet), auth pages, `settings` | Styled with the EventBite token sheets. **Checkout is broken** (see §2) and there is no dedicated ticket-selection, checkout, confirmation or digital-ticket page |
| Host | `dashboards/host`, `events/create`, `events/edit`, `events/staff`, `host/apply` | Present; inline styles; not checked against the design yet |
| Staff | `dashboards/worker-terminal` | Present; server enforces role, event, start and expiry time |
| Admin | `admin/console/*` (8 areas), `errors/403` | **Done**: light canvas, permission-driven navigation, 37 security tests |

Reusable Blade components that already exist: `icon`, `button`, `card`, `empty-state`, `form-field`, `confirmation-dialog`, `status-indicator`, `customer-bottom-nav`, `management-sidebar`, `role-switcher`, `settings-form`; admin partials `status`, `pagination`.

---

## 2. Defects found during the audit (fix before any restyling)

| # | Severity | Where | Problem |
|---|---|---|---|
| D1 | **Blocker** | `events/show.blade.php` + `OrderController@store` | The HTML form posts to an endpoint that only returns JSON, so the customer sees raw JSON. Every dish except the first defaults to quantity 0, and the service rejects any line below 1, so most submissions fail. Currency is hard-coded to USD and a fake phone number (`+1 (305) 555-0199`) is pre-filled. |
| D2 | **High (money)** | `OrderPlacementService::placeOrder` | Ticket stock lives in the `events.ticket_tiers` JSON column, but the event row is not locked inside the transaction. Two checkouts at once can oversell tickets. |
| D3 | **High (money)** | `OrderPlacementService` (default tiers) | If an event has no configured tiers, checkout sells built-in tiers at built-in prices (e.g. General Admission J$2,250). Customers can buy tickets the host never created. |
| D4 | Medium | `OrderPlacementService` idempotency | Duplicate-submission protection searches `notes LIKE '%[idem:…]%'`. There is no unique constraint, so two simultaneous identical submissions can both succeed, and the search is a full scan. |
| D5 | Medium | `EventWorkerService::scanAndValidateTicket` | It reads `customer_email`, `payment_method` and `total_cents`, which don't exist on orders (they're always null in the response). |
| D6 | Low | `WorkerTerminalController`, `assertActiveAssignmentValid` | A host bypass checks `$worker->organizer_id`, which isn't a user column, so hosts never get the intended bypass. |

---

## 3. Phased file-level plan

Each phase is a separate commit with tests, and is usable on its own.

### Phase 0: checkout correctness (blockers D1–D4): **done 2026-10-29**
Also found while testing: every ticket purchase crashed with a foreign-key error, because ticket lines were stored in `order_items.menu_item_id`. Fixed with the new `ticket_tier_id` column. An unpriced configured tier was also being sold at an invented J$2,250; it's now rejected. Covered by `tests/Feature/CheckoutCorrectnessTest.php` (14 tests).
- `app/Services/OrderPlacementService.php`: lock the event row (`lockForUpdate`) before reading or decrementing `ticket_tiers`. Remove the built-in default tiers: an event with no tiers sells food only, and an unknown tier is rejected. Skip zero-quantity lines instead of failing the whole order.
- Migration `add_idempotency_key_to_orders`: a nullable `idempotency_key` with a unique index per organizer. The service writes it, and a duplicate returns the original order. This is additive and safe under the expand/contract rule (doc §9).
- `app/Http/Controllers/OrderController.php`: browser form posts redirect to a confirmation page with flashed validation errors. The JSON path stays unchanged for the API.
- `resources/views/events/show.blade.php`: use the event's currency, no pre-filled fake phone, a server-generated idempotency key per form, and a disabled submit button while submitting.
- Tests: oversell race (two sequential locked placements on the last ticket), unknown tier rejected, zero-quantity lines ignored, duplicate key returns the same order, browser post redirects.

### Phase 1: shared component library (Blade)
New in `resources/views/components/`, styled from the existing token sheets: `event-card`, `food-card`, `ticket-card`, `payment-method`, `status-badge` (wrapping the existing order-state presenter), `price-summary`, `quantity-control`, `search-field`, `metric-card`, `data-table`, `pagination`, `modal`, `loading`, `alert`.
Reused: `icon`, `button`, `card`, `empty-state`, `form-field`, `confirmation-dialog`, `customer-bottom-nav`, `management-sidebar`.
The admin console partials move onto the shared `status-badge` and `pagination` components.

### Phase 2: customer shell and workflow (Blade): **done 2026-10-30**
Built: `CheckoutController` and `routes/customer.php`; `checkout/{tickets,food,review,ticket}` pages; services `Checkout\{CheckoutCart, OrderQuoteService, TicketTierCatalog, PaymentOptions, CustomerPaymentService}`; `App\Support\QrCode` (PHP port of `src/lib/qrCodeMatrix.ts`, versions 1–5, plus the second format-information copy).
Also fixed:
- **Event page:** the hard-coded "$15 / $45" ticket tiers and their JavaScript totals are replaced with the host's real ticket types and a server-side "From" price.
- **Wallet:** the unscannable "QR Code Simulated" drawing is replaced with ticket cards linking to the real QR. The query now also matches by account (`user_id`) and loads events and items up front.
- **Checkout pricing:** the event currency is enforced, invalid option choices are rejected, and the donation minimum is enforced.
- **App header:** no longer causes sideways scrolling on phones.

Not done: customer-initiated cancellation (no existing business rule to follow); scanning the QR with a physical device (the encoding was checked against the TS generator and the specification only).
`layouts/customer.blade.php` (mobile-first, bottom nav) plus pages:
discovery (`events/index`) → event detail (`events/show`) → **new** `orders/tickets` (ticket selection) → **new** `orders/food` → **new** `orders/checkout` (bank transfer or cash, from the host's settings) → **new** `orders/confirmation` (payment instructions, Pending status) → **new** `tickets/show` (QR from the existing `secure_ticket_token`, with what-now status).
Cart state is kept in the session per event and re-priced on the server at every step. It handles changed prices, sold-out items, expired sessions, double submissions, leaving checkout, and cancelled orders.

### Phase 3: staff portal shell
`layouts/staff.blade.php`; restyle `worker-terminal` into separate task views: scan or look up a ticket, payment desk, food station. The authorization path is unchanged (assignment, start and expiry checks already enforced). Fix D5 and D6.

### Phase 4: host dashboard shell
`layouts/host.blade.php` using the management sidebar; restyle `dashboards/host`, `events/create|edit|staff` with the shared components. No new business logic.

### Phase 5: performance, accessibility and the report
N+1 checks with query counting in tests, image sizing, pagination on host lists, an accessibility pass (labels, focus, contrast), responsive checks at 375/768/1280, and the implementation report the brief asks for (§13).

---

## 4. Rules that apply to every phase
- No database resets; migrations are additive only; no edits to existing migrations.
- No business rules in Blade: pricing, stock and authorization stay in services and policies.
- Every protected route is checked on the server, never by hiding navigation alone.
- Existing tests stay; no test is weakened to make a phase pass.

## 5. Rough size
Phase 0 is about one working session. Phases 1–2 are the bulk, several sessions. Phases 3–5 are about one session each.

## 6. Decisions needed
1. **Frontend:** confirm Blade is the product. Options for the React SPA: keep it as is (dev/demo), or retire it once the Blade customer flow is complete.
2. **Order of work:** start with Phase 0 (money and checkout blockers), then 1 → 5?
