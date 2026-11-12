# EventBite — QR Test Report

All results below are from **actually executed** test runs. Nothing is reported as passing that was not run.

## New security suite — `tests/Feature/ScannerSecurityTest.php`

**Result: 16 tests, 229 assertions — ALL PASSED** (executed via `php artisan test --filter=ScannerSecurityTest`, ~4.2s)

| Requirement area | Tests |
|---|---|
| 10.1 Token generation | unique/high-entropy/never-sequential (60 tokens); hash lookup resolves only matching order; DB stores `sha256:` sentinel + hash, never raw |
| 10.3 Verification rejections | refunded/rejected/cancelled → `ticket_revoked`; expired → `ticket_expired`; ended event → `event_not_open`; revoked token → dead QR + audit; cross-event → `invalid_ticket` (HTTP) / `wrong_event` (service); expired assignment + payment clerk + food worker → 403; guest → 401, attendee → 403; forged client fields ignored |
| 10.4 Concurrency | two scanners → one admit + one `already_admitted`, exactly one verified audit; two food workers → over-collection refused by locked recheck, final portion collectable once |
| 10.6 Fraud & abuse | repeated invalid tokens audited (no token leak in details); rate limit 31st scan → 429; duplicate admission → structured refusal; unauthorized reissue → 404 / revoke → 403; scanner-session header correlated into audit without token leak |

## Regression batch (executed after `AuditLogService` fix)

**Result: 82 tests, 491 assertions — ALL PASSED** (~148s)

`TicketCheckInTest` (10) · `TicketTokenLifecycleTest` (5) · `OrderOperationsApiTest` (10) · `WorkerCashReceiptTest` (4) · `WorkerTerminalScreensTest` (5) · `AdminConsoleSecurityTest` · `SmarterTicketingTest` (10)

## QR presentation verification (executed)

- `CustomerCheckoutFlowTest` + `TicketCheckInTest` + `ScannerSecurityTest` combined run: **41 tests, 469 assertions — ALL PASSED**
- `QrCode::svg()` now renders via chillerlan at **ECC Q**; decode proof: real 68-byte `tok_` token → PNG → chillerlan reader → **DECODED OK**
- `php artisan view:cache` — all Blade templates compile
- `php -l` clean on all touched PHP files

## Pre-existing suites covering remaining checklist areas (verified passing in this session's regression runs)

| Checklist | Covering suite(s) |
|---|---|
| 10.2 Event QR | `EventQrCodeTest` (12), `EventPromotionalQrTest` (16) — incl. real decode at multiple sizes/JPEG compression, unpublished/cancelled status pages, host-only downloads |
| 10.3 valid/unpaid/already-admitted/manual-entry | `TicketCheckInTest` (10) |
| 10.5 role matrix, cross-tenant, direct API | `AdminConsoleSecurityTest`, `OrderOperationsApiTest`, `HostDashboardIsolationTest`, `EventManagementAuthorizationTest`, `ProfileAccessAuditTest` |
| 10.7 purchases, food ordering, payments, refunds, notifications, staff assignment | `CustomerCheckoutFlowTest`, `CheckoutCorrectnessTest`, `PaymentStatusAndRefundTest`, `CashLedgerApiTest`, `ReceiptsAndNotificationsTest`, `SmarterTicketingTest`, `DiscoveryJourneyTest` |

## Bugs found & fixed by testing

1. `AuditLogService` used `!app()->runningInConsole()` → request metadata (IP, UA, scanner session) was **silently skipped under PHPUnit and any console context**. Fixed to `app()->bound('request')`; scanner-session correlation now works in tests and HTTP.
2. `ticket_transfers.token_hash` had no unique constraint or index (full scans + no collision protection) → fixed in migration `2026_11_12_000070`.
3. Org-wide staff assignments (`event_id NULL`) leaked across organizers → constrained by `organizer_id` in service + policy.
4. Admission window (`doors_open_time`/`ends_at`) was never enforced → added with `event_not_open` reason.
5. Failed food handovers threw exceptions with **no audit record** → `food_handover_rejected` now logged.
6. Ticket QR encoder was hand-rolled ECC-L → upgraded to chillerlan ECC-Q, decode-verified.

## Known test-infrastructure notes

- PHPUnit runs on SQLite; MySQL CHECK constraints and true parallel row-lock races are validated by code review + the serialized-invariant tests, not reproduced live.
- `.phpunit.result.cache` shows historical defects from earlier phases; all suites listed above were re-executed and passed in this session.
