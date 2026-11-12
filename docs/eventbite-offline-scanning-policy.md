# EventBite — Offline Scanning Policy

**Status: ONLINE-ONLY. No offline admission mode is implemented or permitted.**

## Policy statement

EventBite gate admission requires live server verification. There is no offline validation, no cached ticket manifest, no local admission path, and no service worker that could admit a guest without the server.

When the server is unreachable, the scanner terminal displays **"CONNECTION LOST — NOT ADMITTED"** (amber, distinct from admit green and reject red), keeps the scanned code in the input, and instructs the operator to reconnect and retry the **same** code — preserving `scan_request_id` idempotency so the retry replays correctly.

A confirmed admission (`✓ ADMIT`) is rendered **only** after the server responds `success: true`. Nothing unverified is ever displayed as admitted.

## Rationale

Offline admission would require trusting client-side state for decisions that must be authoritative: payment status, revocation, duplicate admission across devices, and event eligibility. A disconnected device cannot know about revocations issued after its data was loaded, and cannot prevent two devices from admitting the same ticket. The fraud exposure outweighs the availability benefit for a security-critical gate.

## If offline event-day operation is ever required

A controlled offline mode is **designed but not implemented** and must not ship without separate security review. The design (in `backend/docs/eventbite-offline-scanning-security.md`) specifies:

- Pre-authorized, device-bound credentials (public key on server; private key non-extractable on device)
- Short-lived **signed manifests** containing only token *hashes* + minimal ticket references
- **Provisional** classification for every offline result — never displayed as confirmed admission
- Local duplicate detection scoped honestly to same-device replays only
- Signed sync batches on reconnect; server re-runs the full verification sequence; conflicts (`offline_revocation_conflict`, `offline_duplicate_conflict`) surface to supervisors — never auto-resolved
- Persistent supervisor warning that offline mode trades fraud protection for availability

**Explicit limitation that must always be disclosed:** a signed manifest guarantees integrity and freshness bounds only — it does **not** guarantee immediate revocation or global duplicate prevention while devices are disconnected.

## Operational guidance for event day

1. Verify scanner connectivity **before** doors open (the terminal shows a live Online/Offline badge).
2. If connectivity drops, **stop admitting** — do not wave guests through on an offline result.
3. Retry the same scanned code after reconnecting; the idempotency key prevents double-admission.
4. Escalate repeated connection failures to the event supervisor; consider moving scanners to a different network rather than adopting any offline workaround.
5. Any proposal to enable offline mode requires: security review, supervisor sign-off, provisional-admit UX, reconciliation reporting, and updated audit events — none of which exist today.
