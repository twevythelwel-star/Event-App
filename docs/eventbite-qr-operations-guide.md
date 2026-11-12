# EventBite — QR Operations Guide

Runbook for event-day staff, hosts, and administrators. Security rules are enforced server-side; this guide covers the human workflows around them.

## 1. Before the event

### For hosts
- **Generate the promotional QR** on the event page (owner/manager only). One stable code per event — reuse it on flyers, posters, social.
- **Customize the design** from approved presets or `custom_theme` (flyer-derived palette). Every design is decode-tested before it is stored; a design that won't scan is replaced by the safe fallback automatically.
- **Download** SVG (vector), PNG, or print-ready poster. Re-download anytime — the code never changes when you redesign.
- **Print guidance:** keep the quiet zone clear, avoid stretching/distorting, use the PNG/poster at the sizes provided. Physical-device spot-checks are still recommended for large-format prints.

### For organizers/supervisors
- **Assign staff** before doors open: payment clerks, ticket checkers, food station workers (with station), supervisors, or custom restricted workers. Assignments have start/expiry — set them to cover the event window plus buffer.
- **Verify assignments** in staff management; revoke anyone who shouldn't have access. Revocation is effective on the next request.

### For every scanner operator
- Open the worker terminal on a charged device with reliable connectivity.
- Confirm the **Online** badge before doors open.
- Each device gets its own ephemeral scanner session automatically — nothing to configure.

## 2. At the gate

### Ticket checkers
1. Scan the ticket QR with a handheld scanner into the scan box (or use camera scanning where available).
2. Read the verdict — it is the **server's** answer:
   - `✓ ADMIT` — guest may enter. Admit once.
   - `✕ ALREADY USED — DO NOT ADMIT` — possible duplicate/screenshot; **do not accuse the guest**. Escalate to a supervisor for identity verification.
   - `✕ DO NOT ADMIT` — read the reason (payment pending, revoked, wrong event, event not open, expired).
   - `CONNECTION LOST — NOT ADMITTED` — **stop; reconnect and retry the same code.** Never admit on an offline result.
3. Typed ticket codes/order numbers require a **supervisor** — they are logged as manual checks.

### Supervisors
- You may type a ticket code or order number when a QR is damaged; the entry is audited as manual.
- You see the Supervisor Desk with live counts and can perform elevated actions — all audited.
- Own the call on `already_admitted` disputes: check the audit trail before deciding.

### Payment clerks
- Verify manual payments (Zelle/bank/Cash App) against references; record cash receipts through the cash endpoint (amount capped at balance, currency enforced, ledger + audit written).
- You cannot scan tickets or see food items — permissions are enforced server-side.

### Food station workers
1. Look up the order by ticket QR/token or order number — you see only first name, payment flag, and item lines (no prices/PII).
2. Hand over items one line at a time, up to the purchased quantity. Partial collection is supported.
3. Unpaid orders cannot be fulfilled without a supervisor override **with a written reason**.
4. Over-quantity attempts are refused and audited.

## 3. Ticket holder self-service

- **View ticket:** event artwork, date/venue, pass holder, payment + admission status, ticket code, single-use QR with validity window.
- **Replace QR** (lost/compromised screenshot): owner or authorized manager only; rotates the token, kills the old QR instantly, preserves payment, notifies the holder, audits actor + reason.
- **Revocation** is staff/admin only — immediate server-side effect.

## 4. When something goes wrong

| Situation | Action |
|---|---|
| Guest's QR won't scan | Supervisor types the ticket code (manual, audited) |
| `ALREADY USED` on a legitimate guest | Check audit logs / fraud monitor for the scan history before deciding; possible transferred or screenshotted ticket |
| Suspected fake/replaced QR | Do not admit; note the code; supervisor verifies against the order |
| Scanner keeps dropping | Move to better connectivity; **do not** admit offline — the terminal will not confirm anyway |
| Guest claims payment but status is pending | Payment clerk verifies the reference; ticket activates only when status is `paid` |
| Need to kill a ticket NOW (fraud/refund) | Staff with `verify` capability revokes via ticket management — effective immediately, old QR dead, holder notified |
| Repeated suspicious scans from one device | Check **Admin → Fraud Monitor**: rejections by staff/event/IP, signal mix, recent rejection detail with scanner-session correlation |

## 5. Admin & audit

- **Audit Logs** (`/admin/console/audit-logs`): permanent, immutable record — filter by action, actor, outcome, date, PII views.
- **Fraud Monitor** (`/admin/console/fraud-monitor`): scanner/handover rejection signals — counts are investigation context, not accusations.
- **Fraud settings** (`FraudPreventionController`): platform thresholds for order/IP limits, max order amount, 3DS — changes are audited.
- Audit records include: event, ticket reference, staff member, scanner session/device ID (when sent), action, result, timestamp, rejection reason, and request correlation — never raw tokens or secrets.

## 6. QR artwork quick reference

| Artifact | Format | Use |
|---|---|---|
| Event promo QR | SVG / PNG / poster | Flyers, posters, social, print |
| Ticket admission QR | Inline SVG on ticket page | Gate scanning, print ticket, screenshot delivery |

Both systems are intentionally separate: the promo QR opens discovery only and is rejected at the gate; the ticket QR admits once and is meaningless as promotion.
