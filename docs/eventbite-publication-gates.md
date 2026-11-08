# Event publication gates

**What they are:** the checks every event must pass before it can be published. They live in `App\Services\Compliance\EventPublicationGates`.

**Where they run:**
- The readiness check (`EventManagementService::publicationReadiness`) treats each gate as blocking. Every way an event gets published goes through it:
  - the web form;
  - the API status endpoint;
  - publishing from the create form.
- The admin console's moderation "Republish" action now runs the gates as well. Before this change, it skipped every check.

## The gates

| # | Code | An event cannot be published while… | Can it be overridden? |
|---|---|---|---|
| 1 | `identity` | A host of the event's organizer (each active event owner, and a host publishing it themselves) has no approved, unexpired government ID verification. **No verification record at all now blocks.** Before, a host with no record was allowed through. When an administrator publishes a host's event, the host is checked, not the administrator. | No |
| 2 | `host_policies` | That host has not accepted every current host responsibility acknowledgement (`LegalComplianceService::hostResponsibilityPolicies`). | No |
| 3 | `event_ack` | The event's own Event Compliance acknowledgement is missing, or out of date because the jurisdiction or arrangements changed. | No |
| 4 | `arrangements` | The food or alcohol declarations are incomplete or contradict each other (details below). | No |
| 5 | `item:{key}` | A requirement an administrator's rule makes mandatory has **no permit submitted** ("Submit the permit or licence for …"). | Yes |
| 6 | `item:{key}` | That permit's **verification is unresolved**: submitted, pending verification, rejected (the reason is shown) or expired. | Yes |
| 7 | `item:{key}` | Any **mandatory jurisdiction requirement**, including an administrator's additional local requirement, is not verified. Gates 5 and 6 are how this shows. | Yes |

**What gate 4 checks:**
- **Both questions answered:** "Food at this event" and "Alcohol at this event" must both have an answer.
- **No contradictions:** food ordering on while the food answer is "No food", or the alcohol box ticked while the alcohol answer is "No alcohol".
- **When food is provided:**
  - the food permit status is given;
  - the temperature and sanitation, and the allergen disclosures, are confirmed.
- **When alcohol is provided:**
  - age and ID checks are confirmed;
  - when alcohol is sold, included or complimentary, a licence or permit status is needed, unless a licensed alcohol vendor serves it. BYOB needs no licence status.

## What decides "mandatory"

- **Only rules make an item mandatory.** An item is mandatory only when an administrator's rule for that jurisdiction says it is required for this kind of event. Rules are described in [the event requirements doc](eventbite-event-requirements.md).
- **Unresearched items don't block.** Items still marked "Verification Required" are not blocking, because EventBite hasn't confirmed whether they apply. The host still sees them on the checklist.
- **Food and alcohol are separate.** The checklist only includes food topics when the event provides food, and alcohol topics when it provides any alcohol. So:
  - an event with no alcohol is never blocked by an alcohol licence requirement;
  - an event with food but no alcohol is held to its food requirements only.

## Overrides

A compliance administrator can override a requirement item (gates 5 to 7) for one event. An override needs all of the following:

- **Explicit permission:** the `admin.compliance.override` ability, held by system administrators only. Delegated administrators, and every event host including the event's own, get 403.
- **A documented reason** of at least 20 characters: what was checked, and why the event may proceed.
- **An expiry date** in the future and at most 90 days away. On that date the override lapses and the requirement blocks again.
- **Confirmation** that the administrator has authority to override.
- **An audit log entry:**
  - `compliance_override_granted`, recording the requirement, the expiry date and the reason;
  - `compliance_override_revoked` if it is revoked.

**How overrides show and end:**
- An overridden item no longer blocks. On the host's review screen it appears as a note: "Overridden by …, until …, (reason): …".
- An override can be revoked at any time, with a reason, and the requirement blocks again straight away.
- Gates 1 to 4 can't be overridden. They are the host's own identity and legal statements, and no administrator can make them on the host's behalf.

**Where:** Admin console → Events → an event → "Compliance publication gates". It lists every gate with its status, an override form where one is allowed, and the history of overrides.

## Demo accounts

`DemoAccountsSeeder` gives the demo event host what an approved host would have, so it can publish locally. Both are labelled as demo data:
- an approved identity verification with no document, and the note "DEMO ACCOUNT: seeded for local development. No identity document exists.";
- host acknowledgements with context `demo_seed`.

Never run this seeder against a production database.

## Tests

`tests/Feature/EventPublicationGatesTest.php` has 7 tests. They cover:
- that a ready event publishes;
- unverified hosts, including no record at all, and missing host acknowledgements, also when an administrator publishes;
- incomplete, contradictory, BYOB, sold, and vendor-served declarations;
- required permits through each status to verified;
- an alcohol rule that never blocks a food-only event;
- the override rules: permission, reason, 90-day limit, confirmation, the gates that can't be overridden, the audit entry, lapsing and revoking;
- moderation republish being blocked.

**Test helpers in `TestCase`:**
- `eventArrangementFields()`
- `approveHostForPublishing()`
