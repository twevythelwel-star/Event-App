# EventBite — QR Security Audit (Final)

**Scope:** Public promotional QR, attendee ticket QR, server-side verification, atomic admission redemption, staff authorization, food fulfillment, scanner security, fraud monitoring, offline policy, QR presentation.
**Status:** All nine implementation stages executed. No unresolved critical vulnerabilities.
**Supersedes:** the Phase-1 audit (this document reflects the verified final state, not proposals).

---

## Stage gate log

| Stage | Scope | Result |
|---|---|---|
| 1 — Audit | Full QR/token/scan/food/audit inspection | Complete; baseline preserved, no destructive changes |
| 2 — Architecture approval | Two-QR separation, token lifecycle, verification sequence | Approved as-built; remediation backlog folded into stages 3–6 |
| 3 — Token security & lifecycle | 256-bit CSPRNG, SHA-256 hash-only storage, versioning, reissue, revocation, holder notification | Implemented; `TicketTokenLifecycleTest` 5/5 |
| 4 — Server-side verification & atomic redemption | Full §4.1 sequence, admission window, structured reason codes, `lockForUpdate` redemption, idempotent replay | Implemented; `TicketCheckInTest` 10/10 |
| 5 — Staff authorization & food redemption | Event-scoped assignments, permission middleware, policies, item-level locked fulfillment, station checks, handover audit | Implemented; cross-organizer org-wide hole fixed |
| 6 — Fraud monitoring & scanner security | Append-only audit, scanner-session correlation, `food_handover_rejected` signal, admin Fraud Monitor, rate limits | Implemented; `ScannerSecurityTest` 16/16 |
| 7 — Offline operation | Audited; **no offline mode shipped** — fail-closed is the policy | `docs/eventbite-offline-scanning-policy.md` |
| 8 — QR presentation | chillerlan ECC-Q ticket encoder (was hand-rolled ECC-L), validity window on ticket, decode-verified | Implemented; decode verified with real reader |
| 9 — Security & regression testing | New scanner suite + full regression batch | **98 tests executed, all passed** (see `eventbite-qr-test-report.md`) |

## Findings by requirement

### Public event QR cannot grant admission — VERIFIED
- Payload is `/e/{10-char opaque code}` → redirects to the public event page only.
- `isPromotionalEventQrPayload()` rejects it at the gate with `reason_code: promotional_qr` and the terminal displays "PROMOTIONAL EVENT QR — DO NOT ADMIT".
- Scan lookup is event-scoped and token-hash based; a promotional URL contains no ticket token.
- Cancelled/unpublished/private events render honest branded status pages (`event-qr/status`), never an unrelated page.

### Every ticket has a unique protected token — VERIFIED
- `Order::generateSecureTicketToken()` = `tok_` + `bin2hex(random_bytes(32))` → 256 bits CSPRNG.
- `orders.secure_ticket_token_hash` has a **unique index**; collisions fail loudly.
- One token per order; ticket transfers and reissues rotate it.

### Raw tokens not stored — VERIFIED
- DB holds `secure_ticket_token` as a `sha256:` sentinel + `secure_ticket_token_hash`.
- Raw token lives only in the delivery path (QR render / encrypted vault).
- `Order::redactTokenForLog()` scrubs `tok_` values; tests assert no raw token in attributes or audit details.

### Server-side status & payment validation — VERIFIED
- Scanner submits only the token; event/order/status/payment are re-read from the DB.
- Admission requires `status IN (paid, confirmed)` plus the admission window (doors open → `ends_at`, event timezone).
- Client-supplied `status`/`checked_in_at`/`event_id` fields are ignored (forgery test passes).

### Duplicate admission prevented — VERIFIED
- Atomic `DB::transaction` + `lockForUpdate` on the order row; eligibility rechecked after locking.
- `check_in_request_id` unique index; same-request retries replay the original success idempotently.
- Two-scanner test: exactly one `ticket_redemption_verified`, one `already_admitted` rejection.

### Revoked/replaced tokens dead — VERIFIED
- `revokeTicketToken()` swaps the hash for a sentinel + stamps `token_revoked_at`/`revocation_reason` + bumps `token_version` + audits + notifies holder.
- `reissueTicketToken()` rotates token+hash under lock, refuses redeemed/terminal orders, preserves payment status, audits actor+reason, notifies holder.
- Terminal statuses (refunded/rejected/cancelled/expired) count as revoked via `isTicketRevoked()`.

### Staff scoped to authorized events — VERIFIED
- `RequireActiveWorkerAssignment` resolves assignment per `{event}` on every request; start/expiry enforced in real time.
- `RequireWorkerPermission` + role `canPerform()` + `custom_permissions` gate each endpoint.
- **Fixed during audit:** org-wide assignments (`event_id NULL`) previously worked for *any* organizer's events — now constrained by `organizer_id` in both `EventWorkerService` and `EventWorkerPolicy`.

### Food redemption independent & quantity-controlled — VERIFIED
- Separate `/worker/events/{event}/collect` endpoint; admission scans never touch `collected_quantity`.
- `OrderItem` row locked in transaction; `collected_quantity <= quantity` enforced in code + DB CHECK; partial fulfillment and reasoned reversals supported.
- Station assignment enforced server-side; every handover writes `handover_audit_logs` (staff, station, qty, timestamp, action type, override reason).

### Security-sensitive actions audited — VERIFIED
- `security_audit_logs` is append-only (update/delete throw `LogicException`).
- Records actor, role, action, target, IP, user-agent, outcome, organizer/event IDs, rejection reason; scanner-session UUID correlated when present.
- No raw tokens, passwords, or payment credentials are logged.

### Scanner sessions & endpoints protected — VERIFIED
- `auth` + `worker.active` + `worker.permission:{action}` + `throttle` on every worker route.
- Per-browser-session UUID (`X-EventBite-Scanner-Session`) — ephemeral, never a reusable credential.
- Scan endpoint: `throttle:30,1` (31st request → 429, tested).

### Offline limitations explicit — VERIFIED
- No offline admission path exists; connection loss shows "CONNECTION LOST — NOT ADMITTED" and never a confirmed result.
- Policy documented in `eventbite-offline-scanning-policy.md`; controlled offline design (not implemented) in `backend/docs/eventbite-offline-scanning-security.md`.

## Database changes made during remediation

| Migration | Change |
|---|---|
| `2026_11_11_000068` | `secure_ticket_token_hash` (unique), `token_version`, `token_reissued_at`, `token_revoked_at`, `revocation_reason` on orders |
| `2026_11_12_000070` | `ticket_transfers.token_hash` unique; FKs on transfers/discount_redemptions/orders/waitlist; `security_audit_logs(action, created_at)` index |

Both additive, orphan-guarded, non-destructive, with `down()`.

## Outstanding risks (honest)

- **No true parallel-concurrency test** — PHPUnit proves the serialized invariant (lock + recheck + unique constraints), which is what the DB guarantees; a live two-device race was not reproduced.
- **`ticket_redemption_rejected` outcomes default to `success`** in `EventWorkerService` (pre-existing quirk; the action name carries the meaning). Normalize `outcome` values in a follow-up if outcome-based filtering is needed.
- **Offline mode does not exist** — venues with unreliable connectivity must keep scanners online; this is a deliberate policy, not a gap.
- **Manual device testing of printed QRs** remains a human step; automated decode checks cover generated artwork at multiple sizes/compression.

## Recommended next step

None required for acceptance. Optional follow-ups: normalize audit `outcome` values; consolidate `TicketVerificationService` vs `EventWorkerService` into one canonical verifier; add a device-compatibility checklist for printed promotional posters.
