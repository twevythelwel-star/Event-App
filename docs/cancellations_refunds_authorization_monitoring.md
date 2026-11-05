# Cancellations, refunds, authorization, and monitoring

## Cancellation policy and workflow

Each event can set `refund_policy_deadline_hours` (0–720 hours before its start) and `refund_policy_notes` through the host event API. Blank event values inherit that organizer's `HostPaymentPolicy`; the fallback deadline is 48 hours. Event start is interpreted in the event timezone. Customers see their policy text, deadline, eligibility, request status, order status, and refund status in the attendee dashboard and order API.

Customers submit `POST /api/v1/orders/{orderId}/cancellation-requests` (or the authenticated attendee dashboard form). Ownership is checked server-side. Hosts and authorized system administrators list requests at `GET /api/v1/operations/cancellation-requests` and review with `POST /api/v1/operations/cancellation-requests/{id}/review`, supplying `decision=approved|rejected` and a reason. The service locks the order and request, prevents duplicate open requests, applies the policy deadline, audits the action, and returns unused stock for an approved unpaid order. Paid approvals remain open for refund completion; they do not themselves mark an order refunded.

Manual refunds require a reason and completed receipt/transfer reference. A successful provider payment is explicitly rejected by the local manual refund path; it cannot mark an order refunded until a verified provider refund adapter exists. The payment provider integration is currently disabled, so provider-backed paid orders cannot be refunded from this deployment. Partial and full manual refunds are append-only order adjustments and update the order only inside a locked database transaction. Cancellation requests track pending, approved, rejected, partial-refund, and refunded outcomes.

## Authorization checks

- Event Host order operations are limited by active organizer membership and event/order organizer identity. Event staff assignments are checked for active status and matching event; staff permissions do not include refunds.
- Attendees can request cancellation only for `orders.user_id` matching their signed-in account. Order lookup paths must use owner-scoped queries; the attendee ticket retrieval flow independently checks ownership.
- Delegated administrators now require active account status and organizer scope in event/order policy paths. Refund mutations remain unavailable to delegated administrators because there is no explicit refund permission flag; they cannot inherit refund authority from payment visibility.
- System administrators retain platform-wide scope. Payment verification and refund flows remain distinct. Worker cashier/ticket/food permissions do not grant refunds or payment-state edits.

## Monitoring and operations

Production logging uses retained daily files plus a separate Slack alert channel: configure `LOG_CHANNEL=daily`, a secret HTTPS `LOG_SLACK_WEBHOOK_URL`, `LOG_LEVEL=info`, `LOG_SLACK_LEVEL=error`, and `LOG_DAILY_DAYS=14`. The production boot guard refuses to start without the alert channel. Detailed exception traces remain in retained application logs; sanitized exception class/path/request ID, queue failure metadata, and payment failure identifiers are sent to Slack. The app also logs payment state transitions, slow requests (path/status/duration only), failed sign-ins (hashed email and IP only), and duplicate ticket redemption conflicts. Daily scheduled `queue:prune-failed --hours=336` retains two weeks of failed-job inspection data; operators use `queue:failed`, `queue:retry {id}`, and `queue:forget {id}`. Worker retry/backoff and `failed_jobs` procedures are in the deployment runbook.

Sentry is the preferred next error-monitoring integration for exception grouping, releases, and performance traces; it is not installed. Slack alerts are an implemented low-complexity critical-error path, but webhook delivery and alert routing require staging verification. Monitor the `failed_jobs` count and backup status externally; do not expose customer details, payment references, or webhook secrets in alert payloads.

## Verification limits

PHPUnit cannot start in this workspace because its configured `backend/vendor/autoload.php` bootstrap is rejected even though direct PHP can load that file. The new cancellation and authorization feature tests were added but could not be executed here. No staging environment was available to exercise the cancellation screens, Slack webhook, database contention, or provider refund behavior. Provider-backed refunds remain intentionally closed until the actual provider integration is implemented and verified.
