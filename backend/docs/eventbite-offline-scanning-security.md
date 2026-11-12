# EventBite — Offline Scanning Security Audit & Offline Mode Design

Status: **Audit complete. No offline validation exists. Current behavior is fail-closed and compliant with the required default.**
Offline mode below is a **design proposal only — not implemented, not approved, and must not ship without separate security review.**

---

## 1. Current-state audit

### What exists today

| Control | Status | Evidence |
|---|---|---|
| Online server verification required for admission | ✅ Enforced | `POST /worker/events/{event}/scan` → `EventWorkerService::scanAndValidateTicket()` under `auth` + `worker.active` + `worker.permission` + `throttle:30,1` |
| Offline admission possible | ❌ Impossible by design | No service worker, no local ticket cache, no offline manifest — nothing in `public/` or `app/` stores ticket data client-side |
| Confirmed result shown without server confirmation | ❌ Never | `validateTicket()` only renders `✓ ADMIT` after `response.ok && data.success === true` from the server |
| Offline behavior | ✅ Honest | `catch` branch renders `CONNECTION LOST — NOT ADMITTED` (amber), keeps the scanned code in the input, instructs the operator to reconnect and retry the **same** code, and clears the input only on a server-confirmed admit |
| Duplicate-scan protection | ✅ Server-side | Atomic `lockForUpdate` transaction + unique admission semantics; retry reuses `scan_request_id` for idempotent replay |
| Scanner session correlation | ✅ Added | `X-EventBite-Scanner-Session` per-session UUID appended to audit `details` |

### Explicit non-claims

- There is **no** offline validation, cached manifest, or local admission path today.
- Nothing in the current system represents an unverified scan as admitted.
- Because no offline mode exists, there are **no** revocation or duplicate-prevention guarantees to disclaim — the absence is itself the protection.

### Conclusion

The required default behavior is already the implemented behavior: **online server verification is mandatory; the UI states plainly that verification cannot complete while disconnected and never displays a confirmed admission offline.**

---

## 2. Offline mode — design proposal (requires separate review before any implementation)

Offline event-day operation is a legitimate operational need (venue Wi-Fi failure, cellular dead zones). If EventBite decides to support it, the following design is the minimum acceptable shape. **Every item marked ⚠️ is a limitation that must be disclosed to supervisors in-product.**

### 2.1 Authorization & device binding

- Scanner devices must be **pre-authorized per event** by an Event Supervisor or admin before the event day. A device credential is issued only to a user holding an active assignment for that event.
- Device-bound credential: a device keypair generated on-device; the server stores only the public key plus a device label. The private key never leaves the device (WebCrypto, non-extractable where supported).
- Credentials are **short-lived**: manifest validity window ≤ event duration + 4h grace, hard expiry enforced at sync time.

### 2.2 Signed event manifest

- Server issues a **signed manifest** per event containing the *minimum* ticket data needed at the gate:
  - Ticket token **hash** (SHA-256) — never the raw token.
  - Event ID, order/ticket reference (opaque), admission window, seat/section if applicable.
  - Manifest signature (Ed25519 or HMAC-SHA256 with a per-event key), `issued_at`, `expires_at`, manifest version.
- Manifest contains **no** PII beyond what a ticket checker already sees, no payment data, no customer contact details.
- ⚠️ **A signed manifest guarantees integrity and freshness bounds only. It does NOT guarantee immediate revocation or global duplicate prevention while devices are disconnected.** A ticket revoked after manifest issuance remains scannable on offline devices until the manifest expires or the device reconnects.

### 2.3 Local storage & scanning

- Manifest + scan results stored in **protected local storage** (IndexedDB with device-bound encryption where supported; at minimum OS-protected storage, never `localStorage` plaintext).
- Local scan = hash the presented token → match against manifest hashes → **provisional admit** only. A local match is never displayed as a confirmed admission.
- **Local duplicate detection**: first provisional admit for a hash is recorded locally; subsequent scans of the same hash return `PROVISIONAL DUPLICATE — escalate`. This catches same-device replays only — ⚠️ it cannot prevent two different offline devices from both provisionally admitting the same ticket.

### 2.4 Provisional classification & UX

- Every offline result is labeled **PROVISIONAL — PENDING SERVER CONFIRMATION**, visually distinct from `✓ ADMIT`.
- The terminal must show a persistent offline-mode banner to all staff: *"Offline mode: admissions are provisional. Revocations and cross-device duplicates cannot be detected until sync. Escalate disputes to a supervisor."*
- Event supervisors receive an explicit operational warning at assignment time and on the terminal: offline mode trades fraud protection for availability; supervisors own the reconciliation decision.

### 2.5 Sync, reconciliation & conflicts

- On reconnect, the device uploads a **signed sync batch**: device ID, manifest version, provisional admit records (token hash, timestamp, local scan ID — never raw tokens).
- Server reconciliation per record:
  - Ticket still valid + not already admitted server-side → promote to confirmed admission, write audit record linking provisional → confirmed.
  - Ticket revoked/cancelled/refunded after manifest issuance → record `offline_revocation_conflict`, flag for supervisor review; the guest may need to be escorted out or handled per event policy.
  - Ticket already admitted by another device/server → record `offline_duplicate_conflict`; both scan events preserved in audit.
  - Payment status changed → re-validate against server records.
- Conflicts are **never auto-resolved silently**: they surface in the fraud monitor and to the event supervisor.
- Idempotent sync: records carry client-generated UUIDs; replays are deduplicated.

### 2.6 Revocation limitations (must be documented to staff)

- While disconnected, a device **cannot know** about revocations issued after its manifest was signed.
- Mitigations: short manifest windows, frequent re-sync when connectivity flickers, supervisor authority to invalidate a device credential remotely (effective on next sync), and post-event audit of all provisional admits.
- If a revocation must be enforced *immediately*, the only correct answer is: the device must be brought back online — offline mode cannot provide this guarantee.

### 2.7 What offline mode must never do

- Never display `✓ ADMIT` for an unverified scan.
- Never write a confirmed admission locally.
- Never include raw ticket tokens, payment data, or PII beyond the minimum in the manifest.
- Never treat local duplicate detection as global duplicate prevention.
- Never bypass the server-side checks in §4.1 on sync — reconciliation re-runs the full eligibility sequence.

---

## 3. Recommendation

Ship **no offline mode** for launch. The current fail-closed design is the correct default: a gate that cannot verify should stop admitting, not guess. If venue Wi-Fi reliability becomes a demonstrated operational problem, implement §2 as a separately reviewed feature with the provisional/conflict UX above — and treat every offline admit as an audit-flagged event requiring reconciliation, never as equivalent to a live verification.
