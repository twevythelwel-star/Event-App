# EventBite final production-readiness scorecard

**Assessment date:** 2026-11-02  
**Scope:** Current repository and working tree, including uncommitted changes. This is not a staging or production certification.

## Overall

**Overall production readiness: 4.0 / 10 — not approved for real-money production.**

The 15 area ratings average 4.9/10. The overall deployment score is deliberately lower because this application sells tickets and food: real payment creation, settlement, webhook verification, provider refunds, backend test health, and production environment verification are release gates. A feature-rich codebase does not offset those unresolved failure modes.

## Area scores and evidence

| Area | Score | Evidence and reason |
|---|---:|---|
| Architecture | 6.0 | Laravel is the documented production application, with Blade entry points and service/controller separation. React is documented as non-production and now fails closed on API unavailability. The workspace still has a large, actively changing set of uncommitted edits, and dashboard/report paths retain large dataset reads. |
| Database integrity | 6.5 | Integer minor-unit money, relational payment/order records, unique idempotency, append-only adjustments, integrity checks, foreign keys, and additional indexes exist in migrations. Current migration/test execution is not green in the full suite and MySQL 8 execution on this working tree was not run. SQLite cannot prove row-locking behavior. |
| Authentication | 6.0 | Laravel authentication and active-account middleware are present; routes use authenticated middleware. No hosted authentication, session, account recovery, or production identity-provider flow was verified. |
| Authorization | 5.0 | Policies, tenant context, event-scoped worker assignments and server-side operation checks exist. The 278-test backend run had broad worker authorization expectation failures, and cross-tenant/direct API behavior is not currently proven green. |
| Payment security | 1.5 | Provider transaction methods fail closed and unsupported card/wallet methods should remain disabled. There is no verified Stripe intent creation, signed payment webhook, provider settlement/reconciliation, or provider refund path. The critical sales flow is therefore unavailable for real card transactions. |
| Ticket and QR security | 6.0 | Server-side token lookup, event/paid eligibility checks, row locks, and audit paths are present. Concurrent scans were not exercised on MySQL, and physical device-camera scanning was not performed. Event QR must remain informational, not admission proof. |
| Event operations | 5.5 | Host tools, event configuration, staff terminals and reports exist. Backend suite authorization failures and unverified event-day load/role behavior prevent a higher confidence rating. |
| Food ordering | 5.5 | Server-calculated totals, menu stock locks/decrements, ticket/event locks, and idempotency recheck exist. No concurrent MySQL checkout or fulfillment load test was executed; inventory guarantees under the production engine remain unverified. |
| Infrastructure | 5.0 | Runbook documents MySQL 8, persistent private storage, HTTPS, secure cookies, queues, mail, backups and recovery. No target credentials, host, durable media volume, real mail/SMS provider, or restore drill was available for verification. |
| Deployment | 4.0 | Laravel Blade is identified as the production entry point; migrations, configuration caching and worker restart guidance are documented. A fresh staging deployment and upgrade of an existing database were not performed. |
| Monitoring | 5.0 | Slow request/query logging, queue/payment/auth signals, Slack alert configuration and health route are present in code/config. No alert delivery, log retention, on-call route, or external error-monitoring service was verified. |
| Performance | 5.0 | Added event/API pagination, SQL report aggregates, market grouped counts, some indexes and slow-query fingerprints. Host dashboard still loads all scoped orders/events/cash/workers; some filtering is applied after pagination; no load test or production query plan review exists. |
| Accessibility | 4.0 | Visible focus, reduced-motion styles, some accessible discovery labels/status announcements, and React dialog focus handling were added. No complete WCAG 2.2 AA audit, screen-reader run, contrast audit, or manual end-to-end keyboard verification was completed. |
| Testing | 3.0 | Frontend tests pass, but backend run is not green (see results below). No provider sandbox, MySQL concurrency/load, restore, or staging workflow test was run. |
| Documentation | 7.0 | Detailed deployment, operations, security, cancellation, payment, and performance/readiness documents exist. Some older deployment notes are stale (for example scheduler claims differ between sections); runbooks remain unexecuted and must be reconciled to actual deployment configuration. |

## Tests executed and actual results

- `npm test -- --run`: **passed** — 28 test files, 206 tests.
- `npm run build`: **passed**, with a Vite warning that the main JavaScript bundle is approximately 1.07 MB minified.
- Backend feature suite: **278 tests, 3,476 assertions, 2 errors, 39 failures**. It ran through a temporary PHPUnit bootstrap workaround because the normal configured bootstrap could not be opened by PHPUnit in this Windows environment. The temporary workaround was removed afterward.
- Named failures visible in captured output include:
  - `CashLedgerApiTest::test_only_the_owner_can_reverse_and_reversals_are_bounded` returned 422 before authorization because `refund_reference` is required.
  - `CashLedgerApiTest::test_refunds_are_owner_only_bounded_and_mark_full_refunds` triggered an undefined `refund_reference` key in `OrderOperationsController`.
  - Multiple `TicketCheckInTest`, `WorkerCashReceiptTest`, and `WorkerTerminalScreensTest` expectations failed with 403/404 responses or response-shape differences.
- Two captured errors:
  - `ChargesAndTotalsTest::test_migration_rejects_unknown_historical_currency_before_schema_changes`: `Class "Tests\Feature\Schema" not found`.
  - `CustomerCheckoutFlowTest::test_processor_methods_stay_unavailable_even_when_configured_and_approved`: its `SystemPlatformSetting` fixture omitted required `supported_currencies`.
- The captured console output was truncated and did not preserve a complete, reliable list of all 39 test names. The counts and examples above are the actual captured results; no uncaptured failure names are inferred.
- Follow-up targeted resilience tests after reservation/reconciliation/check-in changes: **5 tests, 24 assertions passed** (inventory release exactly once; reservations held during provider processing; matched and mismatched provider snapshots; idempotent gate retry). `schedule:list` confirms inventory expiry every minute and Stripe reconciliation every five minutes. These tests use SQLite/HTTP fakes and do not prove MySQL contention or live Stripe behavior.
- PHP lint for the changed controllers/services/migration passed; `php artisan view:cache`, route listing, and `git diff --check` passed. These are syntax/configuration checks, not proof that changed reports or migrations work on MySQL.

## Outstanding blockers

1. Implement and verify real provider-backed card payment intents, signed webhooks, asynchronous outcomes, idempotency/replay handling, amount/currency/order checks, reconciliation, and provider refunds. Keep card and wallet payments disabled until those flows pass provider sandbox verification.
2. Fix backend test errors/failures without weakening authorization or financial controls. Rerun and retain complete output for SQLite and MySQL 8.
3. Exercise simultaneous checkout and ticket redemption against MySQL 8; prove inventory cannot oversell and exactly one concurrent ticket redemption succeeds.
4. Deploy a production-like staging environment and verify fresh install, in-place upgrade, login, discovery, food/ticket purchase, manual/cash payment, ticket issue/scan, cancellation/refund, notifications, staff and admin controls.
5. Run and record actual database/media backup restoration; configure and verify alert delivery, queues, scheduler, retention, secrets, secure cookies and HTTPS.
6. Complete keyboard, screen-reader, contrast, mobile camera, and WCAG 2.2 AA review.
7. Optimize host dashboards and order-operation authorization queries; add target-shaped load tests and fix the oversized SPA bundle if that client is ever promoted to production.

## Unverified functionality and external dependencies

- Stripe merchant account, API keys, webhook signing secret, eligible wallet configuration, and provider sandbox access.
- MySQL 8 staging database, production-like host/web server, HTTPS domain/certificate, persistent private storage, database/cache/queue workers, and secret management.
- Mail and SMS provider accounts/credentials, Slack alert destination, and an on-call/monitoring service.
- Encrypted offsite backup destination and a disposable MySQL restore target.
- Supported iOS/Android camera devices and NVDA/VoiceOver test environments.
- Current dependency advisory status was not verified; prior Composer audit was blocked by network/DNS access.

## Recommendation

**Do not deploy for real customer ticket sales or food orders that require online payment.** No real payment method should be enabled until a provider integration and its sandbox flows have been independently verified. A later restricted pilot using only authorized, reconciled manual/cash workflows still requires the backend suite to be green and staging authorization, inventory, and recovery tests to pass.
