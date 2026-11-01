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
| **H1** Ticket code derivable from the order number, and accepted at the gate | **Admission is by the QR token.** `/worker/events/{event}/scan` admits a `tok_…` QR token for any ticket checker. A typed ticket code or order number is refused (403, "Scan the QR code on the ticket") unless the scanner is a supervisor, or the event's host or a system administrator. Those manual admissions are written to the audit log as "manual check". New orders get a ticket code of 8 random characters (no look-alike letters or digits), unrelated to the order number. Existing orders keep their old codes, but a code alone no longer admits anyone. Supervisors now have the check-in box too (shared partial `dashboards/partials/ticket-check-in.blade.php`). | `TicketCheckInTest`: typed code and order number refused for a checker; supervisor manual admission logged; ticket-code format and uniqueness. The existing check-in tests now scan the QR token (same assertions). Browser: a typed code was refused and the QR token admitted. |
| **H2** No row lock on ticket redemption | `EventWorkerService::scanAndValidateTicket` runs the whole check and the admission in a transaction on the order row locked for update, and decides from the locked row rather than the caller's copy. A second scanner waits, then sees the check-in and refuses. (Food handover was already locked through `recordHandover`, fixed with C3.) | `TicketCheckInTest::test_admission_reads_the_stored_check_in_not_a_stale_copy`: a copy loaded before the first admission is refused. It fails without the locked re-read. A truly simultaneous test isn't possible on SQLite, which CI uses; the row lock takes effect on MySQL and PostgreSQL. |
| **H3** No payment-status transition rules | `OrderOperationsService::PAYMENT_TRANSITIONS` defines which decision may follow which state, and the check runs on the order row locked for update. A rejected payment must go back to review before it can be accepted. Paid and partly paid orders are corrected only by refund or cash reversal. Refunded, cancelled and expired orders are closed. Marking a paid order paid again changes nothing and sends no notification. Refused moves return 422 with a plain message. | `PaymentStatusAndRefundTest`: every state × decision (9 × 4) checked against the table, the key rules, and a stale copy of a since-refunded order being refused. Existing payment and admin-review tests are unchanged and pass. |
| **H4** Refunds could race and never released stock | `CashLedgerService::refundOrder` now reads the refund history and computes the refundable amount inside the locked transaction. A full refund calls the new `Checkout\StockReturnService`, which returns tickets (only if the ticket was never checked in) and food portions not yet handed over, locking the event and menu rows the same way checkout does. Partial refunds return no stock, because they are amounts, not items. The audit record states what was returned. | `PaymentStatusAndRefundTest`: refunds capped at the amount received, with nothing more after a full refund; partial refund leaves stock alone; full refund returns 2 tickets and the 2 unserved portions (1 was handed over); a checked-in ticket is not returned. |
| **H5** The event's own host was locked out of its staff terminal | `EventWorkerService::assertActiveAssignmentValid` compared `$event->organizer_id` with `$worker->organizer_id`, but users have no such column, so the host bypass never matched. It now checks for an active `organizer_users` membership in a role that runs events (platform admin, event owner, supervisor) through the new `EventWorkerService::managesOrganizer()`. The scan service's own check used to let any host account through, whichever organizer it belonged to; it now uses the same membership test. **Also fixed (was medium):** the scan response now returns the real email, payment method and total; it read columns that don't exist, so those fields were always null. | `HostTerminalAccessTest` (3): the owner opens the terminal and admits a QR ticket, and the response carries the real email, method and total; an organizer supervisor membership also works; hosts of another organizer, hosts with no organizer, and inactive memberships get 403 and admit nobody. |
| **H6** The deployment guide described infrastructure that wasn't in the code | MySQL 8 chosen as the production database. PostgreSQL would first need real UUIDs: the host API creates `evt-…` event IDs and demo data uses `evt-kingston-…`/`org-…` keys, which a PostgreSQL `uuid` column rejects. A new CI job, `backend-mysql`, runs every migration, the seeders and the full test suite on MySQL 8. `docs/production_deployment_and_operations.md` is rewritten to match the code: database queue (worker on `notifications,default`), nothing scheduled yet, private local file storage, backup and restore procedures with a restore drill, the required production settings, and a deploy script using `queue:restart`. Each item is marked in place and verified, in place but not verified, or not in place. **Also found and fixed:** the host form stored flyers on an `s3_flyers` disk that was never configured, so form uploads failed in any real environment; even if it had existed, the flyer route reads a different disk. `s3_flyers` is now a local disk on the same private root. | `FlyerStorageTest` (2): a flyer uploaded through the form is served by the public flyer route. The MySQL job's first run is the proof for the database (pending push). |
| **C2** Staff cash recording crashed | `WorkerTerminalController@recordCash` goes through `CashLedgerService::recordReceipt`, which is locked, capped at the balance, uses the order's currency, refuses refunded or rejected orders, and writes an audit record. The broken `EventWorkerService::recordCashReceipt` was removed. The terminal's cash form now posts to the server; before, it showed a fake `alert()`. | `WorkerCashReceiptTest` (4 tests). The form was not exercised in a browser, because no demo account holds a Payment Clerk assignment. |

## Critical blockers

**None open.** C3 below was fixed after this pass (see "Fixed since the first pass"); it is kept for the record.

| # | Defect | Evidence | Impact | Fix |
|---|---|---|---|---|
| **C3 (fixed)** | **The staff terminal is a prototype for admission and food handover** | In `dashboards/worker-terminal.blade.php`: `validateTicket()` shows "Pass Verified • Gate Entry Recorded" for any non-empty code and never calls `/worker/events/{event}/scan`. The food station's "Collected" button is `alert('Collected portion recorded. 1 portion remaining.')`. "Enable camera" is an alert. The supervisor figures ("87% Admitted", "142 / 165", "6 Workers") are hard-coded. | Door staff following the screen would admit unpaid, forged or already-used tickets. No food handover is recorded, so over-fulfilment controls never run. The server endpoints are correct and tested, but the screen bypasses them. | Wire scan and collect to the existing endpoints, show the server's verdict (including "already admitted"), and replace the hard-coded figures with real counts or remove them. Add a browser-level or view test asserting the form posts. |

## High-priority defects

| # | Defect | Evidence | Fix |
|---|---|---|---|
| ~~H1~~ | **Fixed** (see "Fixed since the first pass") | | |
| ~~H2~~ | **Fixed** (see "Fixed since the first pass") | | |
| ~~H3~~ | **Fixed** (see "Fixed since the first pass") | | |
| ~~H4~~ | **Fixed** (see "Fixed since the first pass") | | |
| ~~H5~~ | **Fixed** (see "Fixed since the first pass") | | |
| ~~H6~~ | **Fixed** (see "Fixed since the first pass") | | |

## Medium-priority improvements

- ~~**Wrong field names in the scan response**~~ Fixed with H5.
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
| Fees and totals; re-quote at placement | Verified: integer minor units, configured charge rules, exact total re-checked at placement (`ChargesAndTotalsTest`) |
| Duplicate orders | Verified |
| Payment-state transitions | **Missing rules (H3)** |
| Refunds | Capped at the amount received, locked, restocked on full refund (H4 fixed); each refund is an `order_adjustments` row |
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

---

## Added 2026-10-30: event QR codes

**What was built** (`App\Services\EventQr\*`, `EventQrController`, `event_qr_codes` table, `events/partials/event-qr.blade.php`):
- **One identity per event.** Every event created through the host form or the host API gets one promotional QR identity: a random 10-character public code, unique per event (enforced by a unique index on `event_id`). The code encodes `{APP_URL}/e/{code}`, which redirects to the public event page for **published events only**. Editing the event never changes it.
- **Random approved design.** The look is picked at random (with `random_int`) from approved presets: Geometric, Circular, Neon, Premium Minimal, Festival, Editorial, Custom Event Theme (colours sampled from the flyer) and Random Combination. Each preset is a whitelist of module shapes, corner-marker shapes, tested colour pairs, borders, labels and an optional centre "bite" mark. The design is stored and reused until the host asks for a new one; a redesign keeps the same code.
- **Scan rules.**
  - Dark modules on a lighter plate. Dark styles put their darkness in the frame, not the code, because light-on-dark codes aren't read by every scanner.
  - Contrast of at least 7:1, and a 4-module quiet zone.
  - Error correction Q for dot modules and H for the centre mark.
  - Anything else falls back to a classic black-on-white design.
- **Decode check.** Every design is decoded with a real QR reader (the `chillerlan/php-qrcode` decoder, a ZXing port) at print size, at small size and after JPEG compression before it is saved. 42 random designs across all presets were also probed at four sizes and compression levels, with 0 failures.
- **Host controls.** Preview, then download SVG, print-size PNG (24 px per module) or a poster layout (SVG with the event name, date and a call to action outside the code). Hosts can choose a new design or retry a failed generation.
- **Failures.** A generation failure never fails event creation. The identity is kept as "failed" and can be retried; it is never duplicated.
- **Admission.** Event codes can't admit anyone. The gate only admits by each ticket's own `tok_` token (H1).
- **Tests:** `EventQrCodeTest` (11 tests) covers the 15 requirements, including decoding and a negative control showing the reader rejects unreadable artwork. CI now installs PHP `gd`.

**Not done:**
- No PDF download; there is no PDF library in the project, and SVG and PNG cover print.
- The PNG omits the text label, because GD has no reliable font here; the SVG and poster include it.
- Not yet scanned with a physical phone camera.
- The public event page doesn't show the event QR; only the host screen does.

## Added 2026-10-30: currency and pricing system

**Money model.**
- USD is the default (`MONEY_DEFAULT_CURRENCY`, `config/money.php`). Each currency's minor unit, symbol, symbol position and separators come from that file, never from code. Adding a currency is a config entry.
- `App\Support\Money` is integer minor units plus a currency. Parsing is exact text parsing, never through a float, and percentages are in basis points with half-up rounding. Mixing currencies throws.
- New `*_minor` bigint columns (migration `2026_10_30_000020`) are authoritative on orders, order items, menu items, events, cash payments and disputes.
- The old decimal columns are kept in step by `SyncsMinorUnits` for older readers (expand/contract). The migration backfills existing rows exactly and turns existing `refund_details` into `order_adjustments` rows.

**Totals.**
- `ChargeCalculator` + `OrderTotals` produce the item subtotal, discounts, fees (platform and host, kept apart) and tax separately. Total = subtotal − discount + fees + tax.
- Each charge line is frozen on the order as an `order_charges` row, so later rule changes never alter a placed order.
- Checkout shows every line before the customer confirms, and placement refuses an order whose server total differs from the shown total (`quoted_total_minor`).

**Fees and taxes are configuration only.**
- System administrators manage them at **Admin → Fees & Taxes** (`charge_rules`).
  - kinds: platform fee, host fee, tax, discount
  - methods: percent, fixed per order, fixed per item
  - scopes: platform-wide, organizer, event, US state
  - targets: tickets, food or both
- Rules are switched off, never deleted. Every change is audit-logged.
- No tax rate is built in. The old 5% fallback was removed. `STATE_SALES_TAX_RATES` is documented as unverified reference data that is never charged. Checkout states that tax is calculated from configured rates and is not tax advice.

**Reports.** Host dashboard, admin overview, admin reports and the CSV export aggregate per currency from the minor columns. The CSV now has a refunded column. Amounts in different currencies are never added together (`RevenueSummary`).

**Tests:**
- `MoneyTest` (5): parsing, rounding, formatting and currency guards.
- `ChargesAndTotalsTest` (7): exact totals with and without rules, rule scoping, the checkout breakdown and changed-total rejection, auditable refunds, per-currency reports, the backfill, and admin rule management.

**Not done:**
- The React SPA (`src/`, demo only) still does its own float maths.
- Discount codes entered by customers are not built. Discounts are rules only.
- Payment-provider tax services (e.g. Stripe Tax) are not integrated.
- `EventManagementService` still asks for custom-amount ranges in both USD and JMD.
- Rates must be confirmed by the business before any rule is switched on.

## Added 2026-10-31: sample event catalog

**Catalog.**
- `database/seeders/data/us_cultural_events.json` holds 20 fictional sample events, one per target market. It covers the ten required concepts (Lauderhill, Miramar, Brooklyn, Atlanta, Hartford, Philadelphia, Boston, Baltimore, Orlando, East Orange) and ten more (Miami Gardens, Sunrise, Tamarac, Tampa, Jacksonville, Queens, Mount Vernon, Hempstead, Newark, Bridgeport).
- Each event is in USD, linked to its market, and has an obviously fictional street address. Each description starts with "SAMPLE EVENT", saying it is not a real booking, partnership, endorsement or payment.
- Menus are specific to each event's cuisines, with dietary notes where supplied.
- The Jamaican Independence Celebration is held the Saturday after August 6.
- Orlando is listed as one day, because events support a single session.
- East Orange demonstrates cash plus bank and Zelle transfers, using placeholder instructions only.

**Updating an existing database** (`FishFryEventsSeeder`, never in production):
- The ten older events are rewritten in place under their existing ids, so orders, tickets and payments stay linked.
- Orders are never changed: older JMD orders keep their currency, prices and item names.
- On events with orders, a tier sold again keeps its remaining stock and takes everything else from the new definition. Tiers no longer offered go off sale.
- Menu items outside the catalog are deactivated, never deleted.
- Each event with orders gets an `event_review_required` entry in Admin → Audit Logs, naming its orders' currencies and any items taken off sale.
- `DemoOrdersSeeder` places each demo order only once.

**Discovery and event pages.** Hard-coded claims were removed:
- a named real artist as a "performer highlight";
- "Verified host" and "Verified event producer";
- "Genuine verified kitchen";
- "Trending";
- a fixed "5 verified event photos" gallery.

Pages now show the event's own category, tagline, flyer and dish images, labeled as illustrative. Discovery lists events soonest first. Flyers use `Event::display_image_url` everywhere, which fixes broken images for sample flyers (`/storage//images/...`).

**Tests:** `SampleCatalogSeederTest` (4). Updated expectations: 20 sample events, Atlanta ZIP 30303, and "publishing requires something to sell" now also clears tiers. Old Jamaican test input strings were renamed.

**Not done:**
- The React demo app (`src/data`) still uses its older US catalog (Miami, Los Angeles, Houston and others), not these 20 markets.
- Event ids and menu ids keep their old names (for example `evt-kingston-harbour-fry`), because renaming primary keys would break order history; they show in URLs.
- Sample flyers reuse the three existing stock images.

## New findings (2026-10-30)

| # | Severity | Finding | Evidence | Fix |
|---|---|---|---|---|
| C4 | ~~Critical~~ **Fixed 2026-10-30** | **Event management by ID with no ownership check** | Was: `EventController@edit`, `update`, `publish`, `unpublish`, `uploadFlyer`, `removeFlyer`, `toggleOrdering` and `cancel` used `Event::findOrFail($id)`, so any signed-in user (an attendee included) could edit, publish, unpublish or change the flyer of any organizer's event. Also found: creating an event from the host form stored the user's id as the organizer, so it failed with a foreign-key error (500) for every host. | All of these actions now load the event through `EventManagementService::findEvent`, so anyone who doesn't manage the event gets 404 and nothing changes. New events go to the active organizer when the user manages it, otherwise to the user's only manageable organizer; when that isn't clear, the user gets 403. Covered by `EventManagementAuthorizationTest`: 7 actions × 3 kinds of outsider, the owner path, and creation. Checked live: attendee gets 404 on edit and unpublish; host gets 200. |
| — | Fixed here | Draft events were publicly viewable at `/events/{id}` | `EventController@show` loaded any event | Non-published events now return 404 unless the viewer manages the event (tested). |
| M | Medium | `symfony/mime` advisory CVE-2026-45070 (email header injection) | `composer audit` | Update `symfony/mime` to a patched release (7.4.22+ or 8.1.10+). |
| L | Low | The host event edit page is unstyled | `events/edit.blade.php` uses Tailwind classes the layout doesn't load. Flash messages set as `status` are never displayed. | Restyle with the shared components (Phase 4). The new QR section already uses them. |
