# EventBite Ticket QR Test Report

**Date:** 2026-11-13
**Branch:** `claude/busy-feynman-15b28d`
**Environment:** PHP 8.4 (Herd, GD enabled), Laravel 13, PHPUnit 12, SQLite in memory (`phpunit.xml`),
`chillerlan/php-qrcode` 5 for encoding and decoding.

Related: [design](eventbite-ticket-qr-design.md) ·
[security](eventbite-ticket-qr-security.md) ·
[generation workflow](eventbite-ticket-generation-workflow.md) ·
[scanner integration](eventbite-ticket-scanner-integration.md)

---

## 1. Results

### Full suite (final run, all changes in place)

| Tests | Passed | Failed | Skipped | Assertions | Time |
|---|---|---|---|---|---|
| 675 | 673 | 1 | 1 | 10,438 | 18m 40s |

The one failure was `ProfileNavigationCrawlTest::test_every_profile_only_sees_links_that_work`
("admin: /admin/console/users/4: HTTP 403"). Rerun on its own it passed. This test has failed once in a full run
before and then passed on a rerun, so it is the known intermittent failure, not a regression. The crawl visits
every admin console link, including the new QR Codes page, and found them all working on the rerun. The skipped
test is pre-existing.

Earlier full runs on this branch: 645/647 (an `AppleWalletTest` intermittent failure, passed on rerun), 652/653,
and 658/659 with no failures. No existing test was deleted or weakened.

### New tests: 52, all passing

| File | Tests | Focus |
|---|---|---|
| `tests/Feature/AttendeeTicketQrTest.php` | 25 | Issue on Place Order, idempotency, retries, ticket page, gate, food, credential privacy, statuses |
| `tests/Feature/TicketQrDesignSystemTest.php` | 11 | Readability of every preset, host choice, admin controls |
| `tests/Feature/TicketLifecycleAndScanningTest.php` | 13 | Lifecycle, reissue history, chargeback, queue, concurrency, staff, cross-event, phone and print |
| `tests/Feature/TicketWalletCompatibilityTest.php` | 3 | Apple Wallet pass and guest codes together |

## 2. Coverage against the required list

| Required | Test(s) |
|---|---|
| QR generation after successful Place Order | `test_placing_an_order_issues_one_unique_secure_credential_per_ticket`, `test_place_order_over_http_then_ticket_page_then_double_submit` |
| No credential after failed order creation | `test_a_failed_order_creates_no_credential`, `test_opening_checkout_creates_no_ticket_or_credential`, `test_a_successful_order_queues_its_qr_render_and_a_failed_one_does_not` |
| One unique QR per ticket | `test_placing_an_order_issues_one_unique_secure_credential_per_ticket`, `test_credentials_are_unique_across_many_tickets` (24 credentials) |
| Multiple tickets in one order | 3-ticket orders throughout; `test_ticket_page_shows_one_scannable_qr_per_attendee_after_payment` |
| Duplicate order submission | `test_retried_requests_with_the_same_key_create_nothing_extra`, `test_place_order_over_http_then_ticket_page_then_double_submit`, `test_issuing_again_never_duplicates_and_fills_in_missing_seats` |
| Failed QR generation retryable | `test_a_failed_qr_render_is_retried_without_new_tickets` |
| Payment pending | `test_a_qr_issued_while_payment_is_pending_never_admits_until_payment_is_verified`, `test_lifecycle_runs_from_pending_payment_to_valid_to_admitted` |
| Confirmed payment | Same two tests: host verification via `ManualPaymentGateway`, then admitted |
| Unpaid ticket scanning | `test_unpaid_and_wrong_event_attendee_tickets_are_refused`, the pending-payment test above |
| Refunded and cancelled tickets | `test_refunded_cancelled_and_revoked_tickets_report_their_own_status_and_never_admit` (refunded, cancelled, expired) |
| Revoked and reissued tickets | Same test (revoked); `test_replacing_the_qr_code_retires_every_attendee_credential`, `test_reissuing_one_guest_records_reason_time_and_actor_and_kills_the_old_code`, `test_order_wide_replacement_and_revocation_are_recorded_per_guest` |
| Chargebacks | `test_a_chargeback_revokes_every_ticket_and_appends_history_without_touching_payments` |
| Event cancellation and completion | `test_event_cancellation_and_completion_change_the_ticket_state`, `test_cancelled_or_unpublished_events_admit_nobody` |
| Wrong-event scanning | `test_unpaid_and_wrong_event_attendee_tickets_are_refused`, `test_cross_event_attempts_are_refused` |
| Repeated scans | `test_each_attendee_is_admitted_once_on_their_own_qr`, `test_repeated_scans_and_retried_requests` |
| Concurrent scans | `test_concurrent_scans_of_one_ticket_admit_it_once` (two gates holding stale copies), `test_a_database_unique_key_blocks_a_second_admission_even_past_the_locks` |
| Unauthorized event staff | `test_staff_without_a_live_scanning_assignment_for_this_event_cannot_scan` (other event's checker, inactive assignment, payment clerk, food worker, no assignment, signed out); `test_only_the_holder_or_payment_staff_may_reissue_a_guest`; `test_only_administrators_change_global_controls` |
| Cross-event access attempts | `test_cross_event_attempts_are_refused` (another organizer's event, a sibling event with the same checker, and the service called directly) |
| QR readability across supported designs | `test_every_preset_and_centre_mark_passes_the_readability_check_with_a_real_decode` (7 presets × every allowed centre mark), `test_the_quiet_zone_is_plate_coloured_and_finder_patterns_are_intact` |
| Mobile screen scanning | `test_every_design_scans_from_a_phone_screen` (4 px per module, upscaled, blurred, brightened, lower contrast, JPEG 45) |
| Printed ticket scanning | `test_every_design_scans_from_a_black_and_white_print` (greyscale, ink-spread blur, paper contrast, JPEG 70; with and without a centre mark) |
| Wallet ticket compatibility | `TicketWalletCompatibilityTest` (3 tests, a real signed `.pkpass` validated against the test CA) |
| Credentials never logged | `test_raw_credentials_are_never_logged` |
| No PII, IDs or URL in the QR | `test_the_qr_carries_only_an_opaque_random_credential` |
| Sequential numbers never admit | `test_sequential_ticket_numbers_never_admit` |
| Food separate from admission | `test_food_redemption_stays_separate_from_admission` |

## 3. Readability evidence

Every preset was checked with a real decoder at three sizes, after JPEG compression, under simulated phone-screen
capture, and in greyscale print simulation. A standalone sweep of all 21 preset and centre-mark combinations plus
12 custom shape combinations passed every check, taking 0.6 to 1.0 s per design including three decodes.

| Check | Result for all presets |
|---|---|
| Polarity | Dark on light |
| Module contrast | 10:1 or better (custom palettes must reach 7:1) |
| Corner-marker contrast | 7:1 or better (Wedding is lowest at about 8.7:1) |
| Quiet zone | 4 modules, pixel-sampled as plate colour all round |
| Error correction | Q; H with a centre mark |
| Centre mark | Under 6% of the symbol, inside the encoder's cleared area |
| Resolution | At least 3 px per module at 260 px |
| Decode | Exact credential at 8 and 4 px per module, JPEG 60, phone-screen and print simulations |

## 4. Not covered by automated tests

- A real phone camera and a real hardware scanner. The simulations above approximate them; a short field check
  with the scanner devices you'll actually use is recommended before the first event.
- A real Apple Wallet device. The pass is validated against Apple's format with test certificates; device testing
  needs the production Pass Type ID certificate.
- True parallel database sessions. SQLite in memory runs one connection, so concurrency is tested with stale copies
  and the unique key. MySQL 8's `FOR UPDATE` and unique indexes give the same guarantees in production.

## 5. How to run

```bash
rm -f bootstrap/cache/config.php   # a cached config once made tests touch the real database
php artisan test tests/Feature/AttendeeTicketQrTest.php tests/Feature/TicketQrDesignSystemTest.php tests/Feature/TicketLifecycleAndScanningTest.php tests/Feature/TicketWalletCompatibilityTest.php
php artisan test   # full suite, about 20 minutes
```
