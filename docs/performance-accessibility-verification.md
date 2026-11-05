# Performance, accessibility, and verification update

Reviewed 2026-11-01 against the current working tree. This is repository-level work; no staging host, production database, provider test credentials, or mobile device was available.

## Changes implemented

- Laravel event discovery is paginated (24 by default, capped at 48), with SQL search and page links. The React catalog API now returns Laravel pagination metadata and bounds attendee order history, operations orders, cash ledger, notifications, and food fulfillment attention items.
- Event financial reporting now uses SQL aggregates instead of loading all orders and order lines. Check-in totals and food fulfillment totals/vendors are aggregated in the database; recent check-ins are bounded to 20.
- Public market catalog counts now come from one grouped query rather than two count queries per market. Homepage event sourcing is bounded to upcoming published events (maximum 500) and only eager-loads active menu items.
- Added composite indexes for organizer/user order timelines, order-item food lookup, and charge reconciliation. Existing inventory checkout locks the event row (ticket tier JSON) and each menu row before checking/decrementing stock; idempotency is rechecked after obtaining the event lock.
- Slow database queries emit a structured warning above `SLOW_QUERY_THRESHOLD_MS` (default 250 ms), with a SQL fingerprint and duration but no SQL bindings or customer values.
- Added global visible keyboard focus and reduced-motion handling; discovery filters have accessible names/status announcements; React dialogs move focus inside, trap Tab, support Escape, and restore prior focus.
- Checkout orders now carry a configurable 15-minute inventory reservation deadline. The scheduled expiry service locks the order, event and menu rows, excludes provider attempts that may still settle, restores ticket/menu stock once, expires the unpaid order, and writes an audit entry.
- Added `payments:reconcile-stripe`, which retrieves configured Stripe PaymentIntent references, validates provider ID/order metadata/amount/currency/received amount and available refund totals, applies a delayed settlement only on exact match, and records/alerts mismatches. It requires Stripe credentials and correctly linked payment rows; card checkout remains disabled because intent creation/webhook verification are not implemented.
- Worker check-in now accepts an optional idempotency request UUID and returns the original successful verdict on a retry by the same worker after the first response was lost. The Blade scanner preserves a pending code across connectivity loss and clearly reports NOT ADMITTED until the server confirms. It has no offline admission cache.
- Removed the React staff demo's fake offline queue and “0 duplicates” reconciliation success message. The screen now reflects browser connectivity and blocks scan/fulfillment/cash/override actions while offline.

## Verification results

- Frontend: `npm test` passed (28 files, 206 tests). `npm run build` passed. Vite reports the main JavaScript bundle is about 1.07 MB minified; code splitting is a follow-up optimization.
- Backend: the actual 278-test feature suite was run through a temporary PHPUnit bootstrap workaround because the configured bootstrap uses a Windows-incompatible `is_readable` check in this environment. Result: 3,476 assertions, 2 errors, 39 failures. Failures include existing payment/refund request-contract errors, checkout/config test fixture errors, broad worker authorization expectation mismatches, and other endpoint regressions; backend is not green. Temporary workaround file was removed after the run.
- PHP syntax checks passed for the changed report, discovery, provider, and event controller files. Full Laravel feature verification of the new reports/index migration remains required.
- No load test was run. The current SQLite test environment cannot validate MySQL `FOR UPDATE` contention or concurrent oversell behavior; concurrency evidence must come from MySQL 8 staging with parallel checkout, webhook, and scan requests.
- Targeted backend tests added for reservation release/idempotence, provider-attempt protection, provider reconciliation success/mismatch, and scan retry idempotence: **5 tests, 24 assertions passed** using the local PHPUnit bootstrap workaround. The full backend suite remains red from the prior run (2 errors, 39 failures); this targeted result does not replace that run.
- No physical mobile camera scan or screen-reader review was performed. Automated frontend tests/build are not a WCAG 2.2 AA conformance result.
- Stripe HTTP behavior was tested with mocked responses only. The merchant account, actual credentials, test-mode intent creation, webhook delivery, refund data and provider reconciliation were not exercised against Stripe.

## Remaining launch blockers

- The production deployment and backup restoration drill has not been executed. There is no verified staging target.
- Simultaneous purchases still require MySQL 8 contention tests; current local transaction tests do not establish behavior under concurrent production-engine transactions.
- No legitimate Stripe test credentials or provider account were available, so a provider sandbox flow was not run. Automated mocks do not establish provider readiness.
- Reconciliation cannot substitute for missing intent creation and signed webhooks; no real payment method is enabled by this work.
- Payment, staff authorization, manual refunds, and other backend failures from the full suite must be resolved, then the full suite rerun against both SQLite and MySQL 8.
- Run production-sized load tests and contention tests for event discovery, checkout/inventory, signed webhook processing, atomic QR redemption, and food fulfillment. Set request/query latency and error-rate thresholds from the target environment.
- Complete manual keyboard, NVDA/VoiceOver, mobile camera, contrast, and screen-reader validation across customer and staff workflows.
- Address the oversized frontend bundle if the SPA is retained for production; Laravel Blade remains the documented production entry point.

## Current performance risks still visible in code

- `HostDashboardController::index` currently loads every order, event, cash receipt, and worker in scope to produce aggregate financials and report tables. Replace materialized totals with grouped SQL and paginate the event, review queue, and worker lists while preserving all-time metrics.
- Some order-operation and cancellation endpoints still derive access by loading sets of orders before filtering capabilities. Move capability scopes into SQL and paginate API collections.
- Catalog radius and price filters operate over the current result page after pagination. For complete filter semantics, apply geospatial/price filtering before page selection or use indexed columns/search infrastructure.
- Leading-wildcard `LIKE` event search cannot use ordinary B-tree title indexes at scale. If catalog volume warrants it, move to MySQL full-text search with a relevance/date plan and keep matching behavior covered by tests.
- `Event::allTenants()->with('menuItems')` and other unbounded dashboard/list/report reads found in the audit should be reviewed before the application handles large real datasets.

## Readiness

Repository improvements reduce several obvious scaling and accessibility gaps, but the application is not production-ready. Payment provider integration and backend test failures are release blockers. Staging deployment, MySQL concurrency/load tests, camera and screen-reader verification, and backup restoration are still unverified. Do not use this review as authorization to accept real customer payments.
