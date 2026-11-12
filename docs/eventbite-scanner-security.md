# EventBite — Scanner Security & Fraud Prevention

## 1. Device & session security

| Control | Implementation |
|---|---|
| Authentication | `auth` middleware on every worker route; guest → 401 |
| Account state | `active` middleware; suspended accounts blocked |
| Assignment | `worker.active` resolves the `EventWorkerAssignment` for the `{event}` route binding on **every** request — revoked, pending, declined, not-yet-started, and expired assignments all fail |
| Assignment expiry | `expires_at` enforced in real time (no caching); assignments are never permanent credentials |
| Revocation | Organizers/admins revoke assignments via staff management; effective immediately |
| Event binding | Lookup is `where('event_id', route event)` — a scanner physically cannot reach another event's tickets through the endpoint |
| Org-wide assignments | `event_id NULL` assignments are constrained to the assigned `organizer_id` (cross-organizer hole fixed) |
| Rate limits | Scan `throttle:30,1`; lookup/collect `throttle:60,1`; cash/codes `throttle:20,1` |
| Token-guessing | 256-bit tokens + SHA-256 hash lookup + rate limit + audit on every miss — enumeration is infeasible and noisy |
| Error surface | Safe JSON `{success, error|reason_code}`; no SQL, stack traces, or internal IDs beyond what the role needs |
| Scanner session ID | Per-browser-session UUID (`crypto.randomUUID()` in sessionStorage) sent as `X-EventBite-Scanner-Session`; ephemeral, rotates each session, never reusable or permanent |

## 2. Fraud signals recorded

Every suspicious outcome writes an append-only `security_audit_logs` row (actor, role, event, organizer, IP, user-agent, action, outcome, reason, timestamp):

| Signal | Action logged |
|---|---|
| Invalid/unknown/replaced token | `ticket_redemption_rejected` (target: event) |
| Promotional QR at gate | `ticket_redemption_rejected` + `promotional_qr` flag |
| Typed code by non-supervisor | `ticket_redemption_rejected` (`unauthorized_manual_entry`) |
| Wrong-event ticket | `ticket_redemption_rejected` (`wrong_event`) |
| Revoked/cancelled/refunded/expired ticket | `ticket_redemption_rejected` (`ticket_revoked`/`ticket_expired`) |
| Unpaid ticket | `ticket_redemption_rejected` (`payment_pending`) |
| Already-admitted re-scan | `ticket_redemption_rejected` (`already_admitted`) — **not** auto-accusation; context preserved |
| Unauthorized scanner | `ticket_redemption_rejected` (`unauthorized_scanner`) |
| Food over-collection / unpaid handover / wrong station / no capability | `food_handover_rejected` (outcome `denied`/`rejected`) |
| Token reissue / revocation / terminal-status revocation | `ticket_token_reissued` / `ticket_token_revoked` (+ holder notification) |
| Privileged PII views | `pii_record_viewed` (`is_pii_access = true`) |

**Correlation:** when the scanner sends `X-EventBite-Scanner-Session`, `AuditLogService` appends `[scanner_session:{uuid}]` to `details` — repeated suspicious scans from one device are visible without storing reusable credentials.

**Non-accusation principle:** a second scan of an admitted ticket is recorded as `already_admitted` with full context (who scanned, when, which device session, IP) — it is evidence for a human to review, not a fraud verdict.

## 3. Fraud monitoring view

`GET /admin/console/fraud-monitor` — gated by `Admin::AUDIT_VIEW`, linked in the admin drawer.

- **Rejections by staff + event + IP (24h)** — repeat offenders surface with a ≥5-rejection highlight banner.
- **Signal mix (7d)** — counts by action/outcome so spikes are visible.
- **Recent rejections (7d, latest 50)** — staff, event, reason detail for drill-down; links conceptually to the full Audit Logs view for deeper filtering.

Copy explicitly frames counts as investigation context.

`FraudPreventionController` (existing) holds platform toggles: detection enable, orders/IP/hour, max order amount, 3DS threshold — audited on change.

## 4. Audit logging guarantees

- `SecurityAuditLog` is **append-only**: Eloquent `updating`/`deleting` throw `LogicException`.
- Fields: actor user + role, action, target entity/id, IP, user-agent, details, `is_pii_access`, outcome, organizer_id, event_id, created_at.
- Indexes: `(actor_role, action)`, `(target_entity, target_id)`, `(user_id, created_at)`, `(action, created_at)` (added for the monitor), `created_at`.
- `user_id` FK is `nullOnDelete` — audit survives account deletion.
- **Never logged:** raw ticket tokens, passwords, session secrets, payment credentials. `redactTokenForLog()` + hash-only storage enforce this; tests assert it.
- Access restricted to `Admin::AUDIT_VIEW` (audit logs) and `Admin::AUDIT_VIEW` (fraud monitor) holders.

## 5. Rate limiting & abuse resistance

| Endpoint | Limit | Purpose |
|---|---|---|
| `/scan` | 30/min/user | Blocks brute-force token guessing and scanner flooding |
| `/lookup`, `/collect` | 60/min/user | Food station throughput without opening enumeration |
| Staff codes, host staff ops | 20/min | Assignment/invitation abuse |
| Reissue/revoke (customer) | 10/min | Token-rotation abuse |
| Compliance-sensitive admin ops | `throttle:compliance-sensitive` | Elevated-action throttling |

Token guessing is additionally infeasible by construction: 256-bit space, SHA-256 preimage resistance, unique-index lookup, and every miss audited.

## 6. What scanners can never do

- Admit without `scan_qr_tickets` permission on an active, in-window assignment for **that** event.
- Scan another event's tickets (event-scoped lookup + assignment check).
- Change ticket ownership (no transfer path for staff).
- Override payment status or revoke tickets without `verify` capability (staff capabilities exclude `reverse`/`refund` entirely).
- See payment totals/references or customer PII beyond their sanitized view.
- Mark food collected via an admission scan, or admit via a food lookup.
- Trust any client-supplied event/order/status/payment fields.

## 7. Verification checklist (acceptance)

- [x] Auth required; assignment + permission enforced per request
- [x] Assignment start/expiry enforced; admin revocation effective immediately
- [x] Rate limits on all scanner endpoints (429 verified at 31st scan)
- [x] Repeated invalid tokens audited without leaking token material
- [x] Already-admitted, revoked, wrong-event, unpaid, expired scans all audited with reason codes
- [x] Food over-redemption attempts audited (`food_handover_rejected`)
- [x] Scanner-session correlation into audit without reusable credentials
- [x] Append-only audit; admin-only access; no secrets/tokens logged
- [x] Fraud monitor view for authorized administrators
- [x] Offline scans impossible — honest "NOT ADMITTED" state (see offline policy)
