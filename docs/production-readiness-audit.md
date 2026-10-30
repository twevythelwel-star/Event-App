# EventBite: design, functionality and production-readiness audit

Date: 2026-10-29. Scope: the Laravel (Blade) application in `backend/`, which is what the deployment runbook ships. The React SPA in `src/` was not re-audited.
Working tree: `main` at `25177e4` plus the uncommitted Phase 2 customer-checkout work.

**Verdict: not production-ready.** Overall score **4.9 / 10**. There are two critical defects. The host dashboard shows data from other organizers, and staff cash recording crashes. There are also high-severity gaps in ticket admission and in payment-state handling.

## How this was checked

| Method | What it covered |
|---|---|
| Automated tests (run today) | `php artisan test`: **162 / 162 passed** (864 assertions). Legacy runner `tests/run_backend_tests.php`: **525 / 525**. `tests/lint_all.php`: 277 files, 0 syntax errors. Frontend `npm test`: 202 / 202, and `npm run build` OK (earlier in this session; no frontend files changed since). Fresh `migrate:fresh --seed` OK on SQLite |
| Live checks on `localhost:8000` | Signed in as the seeded attendee, host and admin. Fetched 14 pages, with automated checks for labels, alt text, headings, inline styles and horizontal overflow at 375 px. Screenshots of the attendee, host and staff screens |
| Reproduction on a **copy** of the local database | The staff cash-recording service call |
| Code review | Services, controllers, routes, migrations, configuration and the deployment runbook |

No production system was touched. No secrets are reproduced here.

---

## Remediation log

| Date | Item | Change | Verified by |
|---|---|---|---|
| 2026-10-29 | **C1 fixed** | `HostDashboardController@index` always scopes to the user's active organizer memberships. A host with none sees nothing; only a system administrator sees all organizers. | `HostDashboardIsolationTest` (3 tests). Live: the Kingston host now sees 1 event and 7 orders, all its own (was 10 and 8). |
| 2026-10-29 | **C2 fixed** | `WorkerTerminalController@recordCash` now goes through `CashLedgerService::recordReceipt`, which is locked, capped at the balance, uses the order's currency, refuses refunded or rejected orders, and is audited. It accepts an order number and an amount in currency units. The broken `EventWorkerService::recordCashReceipt` was removed. The terminal's cash form now posts to the server and shows the real result; it used to show a fake `alert()` success. | `WorkerCashReceiptTest` (4 tests). The form itself was not exercised in a browser, because no demo account holds a Payment Clerk assignment. |
| 2026-10-29 | **New finding (H7)** | The staff terminal's **ticket scan button is simulated as well**: `validateTicket()` shows "Pass Verified" for any typed code without calling `/worker/events/{event}/scan`. The supervisor statistics ("87% Admitted", "142 / 165") are hard-coded. The server endpoints are correct; the page just doesn't use them. | Code review of `dashboards/worker-terminal.blade.php` |

## Critical blockers (as found)

| # | Defect | Evidence | Impact | Fix |
|---|---|---|---|---|
| C1 | **The host dashboard shows other organizers' data** | `HostDashboardController@index` only filters by organizer when `TenantContext::id()` is set. On `/host/dashboard` there is no tenant context, so the queries run unfiltered. Live result: the Kingston host sees all 10 organizers' events and "Total Orders: 8", which includes 1 order from `org-southcoast-fishers`. It also loads cash records and worker assignments the same way. | Cross-tenant disclosure of events, order counts, revenue and cash totals. No test covers it. | Always scope to the user's own organizer memberships (refuse when there are none). Add an isolation test with two organizers. |
| C2 | **Recording cash at the staff terminal crashes** | `EventWorkerService::recordCashReceipt` writes `amount_paid_cents` and compares against `total_cents`, but the real columns are `amount_paid` and `total_amount`. Reproduced on a database copy: `NOT NULL constraint failed: cash_payments.organizer_id`. | The Payment Clerk role can't take cash (HTTP 500). If the insert succeeded, `>= null` would mark any amount as fully paid. No test covers it. | Route the terminal through `CashLedgerService::recordReceipt`, which is transactional, locked, tested and uses the real columns. Delete the broken path. |

## High-priority defects

| # | Defect | Evidence | Fix |
|---|---|---|---|
| H1 | **The ticket code can be derived from the order number, and the scanner accepts it** | `OrderPlacementService`: `ORD-{year}-{n}` and `SFF-{n}` use the same six digits. `WorkerTerminalController@scanTicket` admits on `ticket_code` **or** `secure_ticket_token`. Order numbers appear on receipts, staff screens and notifications. | Admit by `secure_ticket_token` only, with typed codes as a supervisor-only fallback. Generate an independent random ticket code. |
| H2 | **No row lock on ticket redemption or worker food collection** | `scanAndValidateTicket` and `markFoodCollected` read, check and save without `lockForUpdate`. The host-side `recordHandover` does lock. | Two simultaneous scans can admit the same ticket twice, and two stations can over-fulfil an item. Wrap both in a transaction with a locked re-read. |
| H3 | **Payment status has no transition rules** | `OrderOperationsService::updatePaymentStatus` only blocks `refunded`. `rejected`, `cancelled` and `expired` orders can go straight to `paid`, and there is no lock. | Add an explicit transition table (for example `payment_submitted → under_verification → paid / rejected`), lock the row, and test every edge. |
| H4 | **Refunds can race, and they don't release stock** | `CashLedgerService::refundOrder` computes the refundable amount **before** the transaction and lock, so two concurrent refunds can both pass. A full refund never returns ticket or food stock. | Compute inside the locked transaction, and restock on a full refund, or record a deliberate policy that it doesn't. |
| H5 | **The event's own host can't open its staff terminal** | Live result: the host gets 403 "Access denied" on `/worker/events/evt-kingston-harbour-fry/terminal`. The cause is `assertActiveAssignmentValid` reading `$worker->organizer_id`, which isn't a user column (plan defect D6). | Check `OrganizerUser` membership instead. |
| H6 | **The deployment runbook describes infrastructure that isn't in the code** | `docs/production_deployment_and_operations.md` describes PostgreSQL 16, Redis and Horizon, `spatie/laravel-backup` and restore drills. `composer.json` has none of those packages, and CI and phpunit only ever run SQLite. | Either install and test the stack the runbook describes, or rewrite the runbook. Add a CI job that runs migrations and tests on the real production database engine. |

## Medium-priority improvements

- **Staff service uses non-existent fields (D5):** `scanAndValidateTicket` returns `customer_email`, `payment_method` and `total_cents`, which are always null.
- **Staff assignment time window is only checked in the controller:** `recordCashReceipt`, `scanAndValidateTicket` and `markFoodCollected` accept any `is_active` assignment, including one with `event_id = null`, without checking `starts_at` or `expires_at`. The controllers call `assertActiveAssignmentValid` first, so this is not exploitable today. But the services aren't safe on their own, and `EVENT_HOST` passes for **any** organizer's order.
- **No limits on cash recording:** it doesn't check order status (cash can be recorded on a refunded or cancelled order), currency or overpayment.
- **Accessibility:**
  - `/settings` has 7 inputs whose visible labels aren't linked to them (no `for`/`id`).
  - The `/events` search box and both filters have no accessible name (placeholder only).
- **Host dashboard performance:** it loads every event, order, cash record and worker with `->get()` and no pagination, then aggregates in PHP.
- **Partly paid and partly refunded tickets still admit:** a partly refunded order stays `paid`. The business decision isn't written down.
- **Four ordering paths:** `OrderController@store` (legacy form), `CatalogController::placeOrder` (API), the new Blade checkout, and the admin and host operations paths. They share `OrderPlacementService`, but each has its own validation surface.

## Low-priority polish

- **Host shell:**
  - It uses the customer bottom navigation (Home, My Tickets, Orders) and shows an "Attendee" role switcher.
  - The copy says "Overseeing US ticket sales" for a Jamaican-dollar event.
- **Inline styles in rendered pages:** `/events` 232, `/host/dashboard` 170, `/attendee/dashboard` 121, `/events/{id}` 92. The admin console has 3–6.
- **The order-lookup phone check is unsafe in principle:** `retrieveOrderSecurely` uses `str_contains`, so an empty or non-digit "phone" matches any order. It isn't reachable today: the route requires sign-in and ownership is checked first. Fix it before that route is ever opened to guests.
- **QR codes haven't been scanned on a real phone.** The encoder matches the TypeScript generator and the specification, but no device test has been done.

---

## Journey verification

| Journey | Result |
|---|---|
| **Customer:** discovery → details → tickets → food → checkout → payment instructions → confirmation → digital ticket | **Works end to end.** 13 flow tests plus 17 correctness tests. Verified live: server-side quote, price-change detection, idempotent placement, payment-reference submission, QR on the ticket page. Gaps: no customer cancellation; QR not device-scanned. |
| **Staff:** sign in → assignment check → look up ticket → action → status → audit | **Check-in works** (4 tests: admits once, refuses unpaid, refuses unassigned, keeps history). **Cash: broken (C2). Food collection: untested and unlocked (H2).** Host can't open its own terminal (H5). |
| **Host:** sign in → events → ticket types → menu → staff → orders → reporting | Event, tier and menu APIs are tested (`HostEventsApiTest`), as are staff claim codes and order operations. **Dashboard leaks across tenants (C1).** Blade host screens aren't restyled yet (planned Phase 4). |
| **Admin:** sign in → users → permissions → event oversight → reporting → audit | **Works.** Permission-driven console with 37 security tests: privilege-escalation guards, organizer scope, audit-log gating, throttled mutations. |

## Security summary

| Area | Finding |
|---|---|
| Authentication | Login throttled (`throttle:login`); registration, password reset and verification throttled; `active` middleware on signed-in routes. |
| CSRF | Laravel default, with no exemptions in `bootstrap/app.php`. |
| Authorization | Strong in the admin console and the new checkout. **Weak in the host dashboard (C1) and the staff services (H1, H5, assignment window).** |
| Uploads | `MediaStorageService` checks the format with `getimagesize`, excludes SVG and GIF, and serves files through controlled routes. |
| Session | `SESSION_SECURE_COOKIE` must be set in production (the default is unset); `http_only` and `same_site=lax` are on. |
| Payments | A customer-submitted reference only moves the order to `payment_submitted`. Only a host or admin with the verify capability can set `paid`. The transition rules are missing (H3). |
| Duplicate submissions | Unique `(event_id, idempotency_key)` plus a lookup that returns the existing order; tested. |
| Redemption / over-fulfilment | Logic is correct for single-threaded use. No locks on the staff path (H2). |
| Secrets | `backend/.env.example` holds a real-looking Twilio account ID and is deliberately kept out of commits. Replace it with a placeholder. |

## Financial and inventory integrity

| Check | Status |
|---|---|
| Server-side pricing, event currency, option and donation validation | Verified (tests) |
| Ticket stock with event-row lock; food stock with item lock | Verified (sequential lock tests; no real parallel test) |
| Fees and totals | Verified (quote tests; re-quote at placement) |
| Duplicate orders | Verified |
| Payment-state transitions | **Missing rules (H3)** |
| Refunds | Capped at the amount received; **race and no restock (H4)** |
| Cash recording | Host and API path (`CashLedgerService`): verified. **Staff terminal path: broken (C2)** |
| Redemption eligibility | Paid only, once, matching event; **no lock (H2)**; **derivable code accepted (H1)** |

---

## Scores (0–10)

| # | Category | Score | Evidence |
|---|---|---|---|
| 1 | Visual fidelity | 6 | Customer and admin screens follow the EventBite direction. Host and staff screens aren't restyled; the host uses customer navigation. |
| 2 | UI consistency | 5 | 14 shared components exist, but customer pages still carry 90–230 inline styles each, and there are two frontends. |
| 3 | Responsive design | 6 | No horizontal scroll at 375 px on the pages checked; the checkout bar sits above the bottom nav. Tablet not checked. |
| 4 | Accessibility | 4 | Unlabeled inputs on settings and discovery. Reduced-motion rules exist. No keyboard, screen-reader or contrast pass done. |
| 5 | Customer functionality | 7 | Full flow is built and tested. No cancellation; QR not device-tested. |
| 6 | Host functionality | 5 | APIs are tested, but the dashboard leaks across tenants and the host is locked out of its own terminal. |
| 7 | Staff functionality | 3 | Check-in works. Cash crashes. Collection is untested. |
| 8 | Administrative functionality | 7 | Console complete, with 37 security tests. |
| 9 | Authentication and authorization | 5 | Good gates and throttles, offset by C1, H1 and H5. |
| 10 | Financial integrity | 5 | Checkout is solid. Payment transitions, refunds and staff cash are not. |
| 11 | Testing | 6 | 162 + 525 + 202 passing. No coverage of the defects above. No real parallel tests. CI runs SQLite only. |
| 12 | Performance | 5 | Checkout eager-loads. The host dashboard is unbounded. No query-count tests. Image sizing not reviewed. |
| 13 | Maintainability | 5 | Clear services, but four ordering paths, a static staff service using wrong columns, and mixed tier-key spellings (now accepted in both spellings). |
| 14 | Deployment readiness | 3 | The runbook doesn't match the code (H6). Never tested on the production database engine. |
| | **Overall (mean)** | **4.9** | |

## Implementation status

**A. Fully implemented and verified**
- Customer checkout: tickets, food, review, placement, idempotency, payment reference and ticket page.
- Admin console and its permission model.
- Ticket check-in (single scanner).
- Host and API cash ledger and refund caps.
- Event, tier and menu management APIs.
- Login and rate limits.

**B. Implemented but insufficiently tested**
- QR scanning on real devices.
- Concurrent purchase, redemption and refund.
- Image upload limits under load.
- Production database engine compatibility.

**C. Partially implemented**
- Staff terminal: cash is broken; collection is unlocked and untested.
- Host dashboard: tenant scoping.
- Payment-state machine.
- Refund restocking.

**D. Visual prototype only**
- Host Blade shell, which still uses customer navigation and inline styles.
- Staff terminal visuals (planned Phase 3).

**E. Not implemented**
- Customer-initiated cancellation.
- The backup, queue and monitoring stack described in the runbook (Horizon, spatie backup, restore drills).
- Query-count and performance tests.
- Accessibility pass.

## Recommended remediation order

1. **C1:** scope the host dashboard to the user's organizers, with an isolation test.
2. **C2:** route the staff terminal's cash recording through `CashLedgerService`, with a test.
3. **H1 and H2:** admit by secure token only, with a random ticket code, and lock redemption and collection, with tests.
4. **H3 and H4:** payment transition table, and a locked refund with a restock policy.
5. **H5:** host access to its own terminal, plus the D5 field cleanup.
6. **H6:** choose the production stack, add a CI job on that database, and correct the runbook.
7. Accessibility labels, host and staff shells (Phases 3–4), then performance and the full accessibility pass (Phase 5).
