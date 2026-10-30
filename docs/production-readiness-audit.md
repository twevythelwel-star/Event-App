# EventBite: design, functionality and production-readiness audit

**Second pass:** 2026-10-29, against commit `832e359` (pushed to `main`; GitHub Actions CI passed).
Scope: the Laravel (Blade) application in `backend/`, which is what the deployment runbook ships. The React SPA in `src/` was not audited.

**Verdict: not production-ready.** Overall **5.4 / 10** (first pass: 4.9).
The two critical defects from the first pass are fixed (C1 and C2). This pass found one new critical defect:

> **The staff terminal admits anyone.** Its ticket-scan screen shows "Pass Verified" for any typed code without asking the server.

Ticket forgery, redemption races, payment-state rules and the deployment stack also remain open.

## How this was checked

| Method | What it covered |
|---|---|
| **Automated tests, run today** | `php artisan test`: **169 / 169** passed (891 assertions). Standalone runner `tests/run_backend_tests.php`: **525 / 525**. `tests/lint_all.php`: 279 files, 0 syntax errors. Frontend `npm test`: 202 / 202 and `npm run build` OK (no frontend files changed since). **CI on `832e359`: success.** |
| **Live checks on `localhost:8000`** | Signed in as the seeded attendee, host and admin. Fetched 14 pages, with automated checks for labels, alt text, headings, inline styles and horizontal overflow at 375 px. Measured colour contrast on `/events` (199 text elements). Tested keyboard focus with real Tab presses. Screenshots of the attendee, host and staff screens. |
| **Reproduction on a copy of the local database** | The old staff cash path (C2), before the fix. |
| **Code review** | Services, controllers, routes, migrations, configuration, the staff terminal view and the deployment runbook. |

No production system was touched. No secrets appear in this report.

---

## Fixed since the first pass

| Item | Change | Verified by |
|---|---|---|
| **C1** Host dashboard showed other organizers' data | `HostDashboardController@index` always scopes to the user's active organizer memberships. A host with none sees nothing; only a system administrator sees all organizers. | `HostDashboardIsolationTest` (3 tests). Live: the Kingston host now sees 1 event and 7 orders, all its own (was 10 and 8). |
| **C3** Staff terminal faked scan and food handover | **Ticket check-in** now posts to `/worker/events/{event}/scan` and shows the server's verdict: ADMIT, DO NOT ADMIT with the reason, or ALREADY USED. A server error shows "NOT CHECKED". The fake camera button is gone; the code box takes typed codes or a handheld scanner. **Food station** has a new `POST /worker/events/{event}/lookup` (by QR token, ticket code or order number; returns first name, payment state and food lines, with no prices or phone number). **Hand over** goes through `OrderOperationsService::recordHandover`, the same service the host screens use: locked item row, capped at the ordered quantity, unpaid orders refused, station enforced by item category, and every handover written to `handover_audit_logs`. The broken `EventWorkerService::markFoodCollected` was removed; it wrote non-existent columns and didn't check payment. **Supervisor desk** shows live counts: tickets admitted out of paid tickets, paid portions handed over, and staff on shift. **Also fixed:** Ticket Checkers and Food Station Workers couldn't open the terminal page at all (it required `view_relevant_orders`, which neither role holds). The page now opens for any active assignment that holds one of the terminal's actions. | `WorkerTerminalScreensTest` (5 tests). Checked in the browser on the local database with a temporary assignment (database restored afterwards): unpaid → DO NOT ADMIT; paid → ADMIT; same ticket again → ALREADY USED; food lookup → Hand over → "Remaining 0" and the button disabled. |
| **C2** Staff cash recording crashed | `WorkerTerminalController@recordCash` goes through `CashLedgerService::recordReceipt`, which is locked, capped at the balance, uses the order's currency, refuses refunded or rejected orders, and writes an audit record. The broken `EventWorkerService::recordCashReceipt` was removed. The terminal's cash form now posts to the server; before, it showed a fake `alert()`. | `WorkerCashReceiptTest` (4 tests). The form was not exercised in a browser, because no demo account holds a Payment Clerk assignment. |

## Critical blockers

**None open.** C3 below was fixed after this pass (see "Fixed since the first pass"); it is kept for the record.

| # | Defect | Evidence | Impact | Fix |
|---|---|---|---|---|
| **C3 (fixed)** | **The staff terminal is a prototype for admission and food handover** | In `dashboards/worker-terminal.blade.php`: `validateTicket()` shows "Pass Verified • Gate Entry Recorded" for any non-empty code and never calls `/worker/events/{event}/scan`. The food station's "Collected" button is `alert('Collected portion recorded. 1 portion remaining.')`. "Enable camera" is an alert. The supervisor figures ("87% Admitted", "142 / 165", "6 Workers") are hard-coded. | Door staff following the screen would admit unpaid, forged or already-used tickets. No food handover is recorded, so over-fulfilment controls never run. The server endpoints are correct and tested, but the screen bypasses them. | Wire scan and collect to the existing endpoints, show the server's verdict (including "already admitted"), and replace the hard-coded figures with real counts or remove them. Add a browser-level or view test asserting the form posts. |

## High-priority defects

| # | Defect | Evidence | Fix |
|---|---|---|---|
| H1 | **Ticket code can be derived from the order number, and the scanner accepts it** | `ORD-{year}-{n}` and `SFF-{n}` share the same six digits (`OrderPlacementService`). The scan endpoint admits on `ticket_code` **or** `secure_ticket_token`. | Admit by `secure_ticket_token` only, with typed codes as a supervisor-only fallback. Generate an independent random ticket code. |
| H2 | **No row lock on ticket redemption** | `scanAndValidateTicket` reads, checks and saves with no `lockForUpdate`. Worker food collection now goes through the locked `recordHandover` (fixed with C3). | Use a transaction with a locked re-read. Add a parallel-request test. |
| H3 | **No payment-status transition rules** | `OrderOperationsService::updatePaymentStatus` blocks only `refunded`. `rejected`, `cancelled` and `expired` orders can be set to `paid`. No lock. | Add a transition table and a locked update, with a test per edge. |
| H4 | **Refunds can race, and don't release stock** | `CashLedgerService::refundOrder` computes the refundable amount **before** the locked transaction. A full refund never returns ticket or food stock. | Compute inside the lock. Restock on a full refund, or document a policy that it doesn't. |
| H5 | **The event's own host can't open its staff terminal** | Live: 403 on `/worker/events/evt-kingston-harbour-fry/terminal`. `assertActiveAssignmentValid` reads `$worker->organizer_id`, which isn't a user column. | Check `OrganizerUser` membership instead. |
| H6 | **The deployment runbook describes infrastructure that isn't in the code** | The runbook specifies PostgreSQL 16, Redis and Horizon, and `spatie/laravel-backup` with restore drills. `composer.json` contains none of these, and CI and phpunit run SQLite only. | Install and test the stack the runbook describes, or rewrite the runbook. Add a CI job on the production database engine. |

## Medium-priority improvements

- **Wrong field names in the scan response:** `scanAndValidateTicket` returns `customer_email`, `payment_method` and `total_cents`. Those columns don't exist, so the values are always null.
- **Staff services don't check the assignment time window themselves:** they accept any `is_active` assignment, including `event_id = null`, and let any `EVENT_HOST` through. The controllers check the time window first, so this isn't reachable today.
- **Accessibility:**
  - `/settings`: 7 inputs whose visible labels aren't linked (no `for`/`id`).
  - `/events`: the search box and both filters have no accessible name.
  - `/events`: the date filter has `outline: none` on keyboard focus (measured; a border-colour change on focus wasn't checked).
- **Host dashboard has no pagination:** it loads every event, order, cash record and worker for the host's organizers.
- **Partly paid and partly refunded orders still admit:** the business rule isn't written down.
- **Four ordering paths:** legacy form, API, Blade checkout and operations. They share `OrderPlacementService`.
- **No `orders(event_id, status)` index:** the scan, dashboard and check-in queries would use one. PostgreSQL doesn't index foreign keys automatically.

## Low-priority polish

- **Host shell:** uses the customer bottom navigation and an "Attendee" switcher. Its copy says "US ticket sales" for a Jamaican-dollar event.
- **Inline styles in rendered pages:** `/events` 232, `/host/dashboard` 170, `/attendee/dashboard` 121, `/events/{id}` 92. The admin console has 3–6.
- **Contrast:** 1 failure out of 199 text elements on `/events` (grey address text, 3.4:1 against 4.5:1 required).
- **Order lookup:** `retrieveOrderSecurely` matches an empty "phone" in principle. It isn't reachable, because the route requires sign-in and checks ownership first.
- **QR:** not yet scanned on a real phone. The encoder matches the TypeScript generator and the specification.
- **`backend/.env.example`:** holds a real-looking Twilio account ID. It's kept out of commits, but should become a placeholder.

---

## Journey verification

| Journey | Result |
|---|---|
| **Customer:** discovery → details → tickets → food → checkout → payment instructions → confirmation → digital ticket | **Works end to end** (13 flow tests and 17 correctness tests; checked live). A customer-submitted transfer reference only moves the order to `payment_submitted`; it is never treated as payment. Gaps: no customer cancellation; QR not device-scanned. |
| **Staff:** sign in → assignment check → look up → action → status → audit | **Server side works and is tested:** check-in (4 tests) and cash (4 tests). **Screen: only cash is real.** Scan and food collection are simulated (C3). The host is locked out (H5). |
| **Host:** sign in → events → ticket types → menu → staff → orders → reporting | Event, tier and menu APIs, staff claim codes, order operations and the cash ledger are tested. The dashboard is now tenant-scoped (C1 fixed). Host Blade screens aren't restyled and the dashboard isn't paginated. |
| **Admin:** sign in → users → permissions → event oversight → reporting → audit | **Works.** 37 security tests cover privilege escalation, organizer scope, audit-log gating and throttled mutations. |

## Security summary

| Area | Status |
|---|---|
| Authentication | Login, registration, reset and verification are throttled. `active` middleware runs on signed-in routes. |
| CSRF | Laravel default with no exemptions. The new terminal cash call sends the token. |
| Authorization / tenant scoping | Admin console, checkout and host dashboard: verified (C1 fixed). Staff services: weak (H1, H5, assignment window). |
| File uploads | `getimagesize` format check; SVG and GIF excluded; files served through controlled routes. |
| Session | `http_only` on and `same_site=lax`. **`SESSION_SECURE_COOKIE` must be set in production.** |
| Payment verification | Only a host or admin with the verify capability can mark `paid`. Transition rules are missing (H3). |
| Duplicate submissions | Unique `(event_id, idempotency_key)`; tested. |
| Ticket forgery / duplicate redemption | Server logic is correct for a single scanner. Codes are derivable (H1), there's no lock (H2), and **the screen skips the server entirely (C3).** |
| Food over-fulfilment | Host path is locked; worker path is unlocked (H2); **the screen is simulated (C3).** |
| Privilege escalation | Covered by admin console tests (`UserPolicy` sysadmin invariants). |
| Audit logging | Cash, refunds, payment-status changes, check-ins and admin actions are logged. |

## Financial and inventory integrity

| Check | Status |
|---|---|
| Server-side pricing, currency, option and donation validation | Verified (tests) |
| Ticket stock (event-row lock) and food stock (item lock) | Verified with sequential lock tests; no real parallel test |
| Fees and totals; re-quote at placement | Verified |
| Duplicate orders | Verified |
| Payment-state transitions | **Missing rules (H3)** |
| Refunds | Capped at the amount received; **race and no restock (H4)** |
| Cash recording | **Verified on both host and staff paths** (C2 fixed) |
| Redemption eligibility | Paid, once, matching event (server); **H1, H2, C3** |
| Fulfilment quantities | Capped at the ordered quantity (server); **worker path unlocked, screen simulated** |

## Database and deployment

- **Migrations** are additive and run cleanly on a fresh SQLite database in CI.
- **Constraints:** foreign keys with cascade or null-on-delete; unique `order_number`; unique `(event_id, idempotency_key)`; indexes on `(organizer_id, status)` and `(organizer_id, fulfillment_status)`.
- **PHP versions:** `composer.json` requires PHP ^8.3, and CI uses 8.4.
- **Laravel version:** `laravel/framework` ^13.17.
- **Production database engine:** **never tested.**
- **Configuration:** the example environment file is development-oriented (`APP_DEBUG=true`, SQLite, database queue, cache and sessions, local disk). Production values are left to the operator; the runbook expects Redis, Horizon and PostgreSQL that the code doesn't include (H6).
- **Backups:** the runbook describes PITR, spatie backups and weekly restore drills, but **none of it is installed or verifiable from this repository.**

---

## Scores (0–10)

| # | Category | Score | Change | Evidence |
|---|---|---|---|---|
| 1 | Visual fidelity | 6 | – | Customer and admin screens follow the EventBite direction. Host and staff screens aren't restyled. |
| 2 | UI consistency | 5 | – | 14 shared components, but 90–230 inline styles per customer page and two frontends. |
| 3 | Responsive design | 6 | – | No horizontal scroll at 375 px on pages checked. Tablet not checked. |
| 4 | Accessibility | 4 | – | Good contrast on `/events` (198 of 199 elements pass) and visible focus on buttons and cards. But: unlabeled inputs on settings and discovery, select focus outline removed, no screen-reader pass. |
| 5 | Customer functionality | 7 | – | Full flow built and tested. No cancellation; QR not device-tested. |
| 6 | Host functionality | 6 | +1 | Tenant leak fixed. Still locked out of its own terminal; dashboard not paginated; screens not restyled. |
| 7 | Staff functionality | 3 | – | Server side is now sound for check-in and cash, but **the screen fakes scan and collection (C3).** |
| 8 | Administrative functionality | 7 | – | Console complete with 37 security tests. |
| 9 | Authentication and authorization | 6 | +1 | C1 fixed. H1 and H5 remain. |
| 10 | Financial integrity | 6 | +1 | C2 fixed; checkout is solid. H3 and H4 remain. |
| 11 | Testing | 6 | – | 169 + 525 + 202 passing; CI green; 7 new tests. No parallel tests; no view-level tests of the terminal; SQLite only. |
| 12 | Performance | 5 | – | Checkout eager-loads. Dashboard unbounded. No query-count tests. Image sizing not reviewed. |
| 13 | Maintainability | 5 | – | Broken cash path removed, but four ordering paths and simulated UI scripts remain. |
| 14 | Deployment readiness | 3 | – | CI is green, but the runbook doesn't match the code and the production database is untested. |
| | **Overall (mean)** | **5.4** | +0.5 | |

## Failed tests

None. All 169 Laravel tests, 525 standalone runner tests and 202 frontend tests pass, and CI passed on `832e359`. The passing suites don't cover C3, H1–H4 or parallel requests.

## Implementation status

**A. Fully implemented and verified**
- Customer checkout: tickets, food, review, placement, idempotency, payment reference and ticket page.
- Admin console and permission model.
- Host dashboard tenant scoping.
- Cash ledger on both host and staff endpoints.
- Server-side check-in for a single scanner.
- Event, tier and menu APIs.
- Login and rate limits.

**B. Implemented but insufficiently tested**
- QR scanning on devices.
- Concurrent purchase, redemption and refund.
- Upload limits under load.
- Production database compatibility.
- The terminal cash form in a browser.

**C. Partially implemented**
- Payment-state machine.
- Refund restocking.
- Staff services' own authorization checks.
- Host access to its own terminal.

**D. Visual prototype only**
- **Staff terminal scan, food collection, camera and supervisor statistics.**
- Host Blade shell styling.

**E. Not implemented**
- Customer cancellation.
- The runbook's backup, queue and monitoring stack.
- Query-count and performance tests.
- Accessibility pass.

## Recommended remediation order

1. **C3:** wire the terminal's scan and collect to the server, and remove the fake statistics.
2. **H1 and H2:** admit by secure token only with a random ticket code; lock redemption and collection; add parallel tests.
3. **H5:** host access to its own terminal, plus the scan-response field cleanup.
4. **H3 and H4:** payment transition table; locked refund with a restock policy.
5. **H6:** choose the production stack, add CI on that database, and correct the runbook.
6. Accessibility fixes (labels, select focus), host and staff shells (Phases 3–4), then pagination, query-count tests and the full accessibility pass (Phase 5).
