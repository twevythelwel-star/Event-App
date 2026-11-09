# EventBite compliance implementation report

*Status as of 8 November 2026. This report covers the compliance, verification and policy features.*

**Policy wording:** all of it is draft. It must be reviewed by qualified US legal counsel before production release.

**The compliance database:** nothing in it has been verified yet. No food or alcohol requirement is presented as confirmed law, and none is enforced, until an administrator verifies it against an official government source and a second administrator approves it.

## Acceptance criteria

| Criterion | Status | How it is met |
|---|---|---|
| Every registering user acknowledges the required platform policies | **Met** | Terms of Service, Food Safety and Alcohol policies are each required and recorded separately, on the web form, the API and the shared registration service. |
| Every Event Host uploads an acceptable government-issued ID and passes verification before publishing | **Met** | Publishing needs an approved, unexpired ID verification. Approval needs an uploaded, scanned and encrypted document, and an administrator specifically granted identity access. Approving a host application no longer counts as identity verification (fixed in this review). |
| Every new event requires its own applicable compliance acknowledgement | **Met** | The web form requires the 15 declarations to create an event, and they apply to that event only. Events created through the API may be saved as drafts without them, but cannot be published until the event's own acknowledgement is given. Material changes require a new acknowledgement. |
| Food and alcohol requirements are assessed separately | **Met** | Food topics apply only when food is provided, and alcohol topics only when alcohol is provided. Food can be prepared, and alcohol served, at locations other than the venue, and each location is assessed in its own jurisdiction. |
| State, county and municipal requirements can be maintained independently | **Met, data pending** | Country, state, county and city records each have their own agencies, requirements and rules. County and city records are not imported yet (see below). |
| Official regulatory sources are linked and tracked | **Met** | Every record and rule needs an official source: an https .gov, .mil or .us address, or an administrator's confirmation that it is the agency's own site. Each records its source title, last-verified date and next review date. Records are flagged when a review falls due. |
| Unverified legal requirements are never presented as confirmed law | **Met** | Unresearched topics show as "Verification Required". Public pages show a requirement's details only once it is verified. Requirement rules now need a second system administrator's approval before they apply (fixed in this review). |
| Required documents can be reviewed securely | **Met** | Documents are kept in private storage. ID documents are encrypted and opened through 5-minute links bound to one administrator, and each opening is logged as personal-data access. Permit downloads are limited to the event's hosts and compliance managers whose scope covers the event, and are audited. |
| Hosts cannot bypass publication restrictions | **Met** | Every way an event is published runs the same publication checks: the web form, the publish action, the API and admin republish. Checks for identity, host acknowledgements, the event's own acknowledgement, food and alcohol answers, and suspensions cannot be overridden. Only a system administrator can override a permit requirement, with a reason, a review date and an audit entry. |
| All acknowledgement, verification and override actions are auditable | **Met** | Acknowledgements are append-only records linked to the exact policy version, and each is also written to the audit log (added in this review). ID decisions, document access, permit reviews, overrides, suspensions, rule approvals, policy publishing, access grants and exports are all audit-logged. |
| Tested and compatible with the existing Laravel architecture | **Met** | 103 compliance tests: unit, feature, authorization and integration. The code uses the app's own roles and permissions, Gates and Policies, Form Requests, Eloquent relationships with foreign keys, events and listeners, queued notifications and scheduled commands. |

## Defects fixed during this final review

1. **Approving a host application verified the applicant's identity.**
   - It also marked any uploaded ID as approved, without review by an administrator with identity access, and the publication check accepted that.
   - Application approval now grants the host role only, and identity needs its own ID review.
   - A second eligibility helper reported "allowed" for hosts with no ID at all; it now follows the same rule.
2. **A single administrator's requirement rule took effect immediately.**
   - Hosts were then shown it as "Required".
   - Rules now wait for approval by a system administrator other than their author, the same two-person check as compliance database records.
3. **Acknowledgements were recorded but not in the audit log.** Policy acknowledgements and event declarations now write audit entries too.

Defects fixed earlier in this work:
- Registration recorded policy acceptances nobody gave.
- Host applications recorded host acknowledgements that were never ticked.
- Any attendee could upload a government ID.
- The admin republish action skipped the publication checks.
- Hosts with no ID record could publish.
- An approved host whose ID had expired could not upload a renewal.

## Completed features

- **Registration and host onboarding:**
  - registration requires each policy, individually;
  - the host application has six individual acknowledgements;
  - ID upload is limited to hosts and applicants;
  - IDs are scanned, encrypted and given a retention schedule;
  - identity decisions (approve, reject, request more, require re-verification, suspend) need an explicit identity-access grant;
  - expired IDs can be renewed.
- **Event compliance:**
  - each event declares its jurisdiction, its food and alcohol arrangements, and 15 declarations;
  - a material change to location, food or alcohol requires a new acknowledgement;
  - a material change to the declarations' policy text makes existing acknowledgements out of date.
- **Requirements checklist:**
  - built for each event location (venue, food preparation, alcohol service) and jurisdiction level (federal, state, county, city);
  - item statuses: Required, Not Required, Submitted, Pending Verification, Verified, Rejected, Expired, Verification Required;
  - hosts submit permit and licence details and documents, and may withdraw a submission before review;
  - only administrators verify.
- **Publication checks:**
  - covered: identity, host acknowledgements, the event's acknowledgement, food and alcohol answers, mandatory permits, and event and host suspensions;
  - audited, time-limited overrides for permit requirements only.
- **US compliance database:**
  - country, all 50 states and DC, with counties and cities ready to import;
  - federal agencies (FDA, USDA FSIS, TTB);
  - state food, agriculture and alcohol agencies from the FDA and TTB directories;
  - nine requirement topics per jurisdiction;
  - two-person review workflow, review dates and official-source checks.
- **Host Compliance and verification page:** status and next steps for:
  - identity;
  - acknowledgements;
  - policy updates;
  - events needing information;
  - permits;
  - expiring documents;
  - review messages.
- **Admin Compliance Dashboard:**
  - identity, food permit, alcohol licence and declaration queues, filtered by state;
  - expiring documents and safety reports;
  - host and event suspensions;
  - policies, consent and audit records, and access grants;
  - CSV exports.
- **Version-controlled policies:**
  - each version records category, effective date, roles, jurisdictions, and whether acknowledgement is mandatory and must be repeated;
  - published versions cannot be changed;
  - material changes are acknowledged at the next visit, and restricted actions wait until then;
  - acknowledgement records are kept separately for registration, host application, event creation, material event changes and policy updates.
- **Public legal pages:** nine pages, each:
  - versioned;
  - marked as draft wording until legal review;
  - stating it is general information, not legal advice;
  - never limiting EventBite's own obligations or vouching for hosts.
- **Safety reports:** attendees file them from event pages; compliance administrators triage them.
- **Notifications (queued email):**
  - ID decisions;
  - permit reviews;
  - suspensions;
  - documents expiring in 30 or 7 days, or expired the day before.

## Outstanding legal research and review

Nothing below has been invented to fill a gap. Each item needs counsel or research against official sources.

| Item | What remains |
|---|---|
| Policy wording | All 17 policy versions are drafts awaiting review by qualified US legal counsel. Until `LEGAL_COUNSEL_REVIEWED=true` is set, every page says so. The versions are: the 3 account policies (Terms, Food Safety, Alcohol), the 6 host acknowledgements, the 3 groups of event declarations, the Host Agreement, the Host Identity and Privacy Notice, the Cancellation and Refund Policy, the Attendee Safety Notice and the Privacy Policy. |
| Identity document retention | The 90, 30 and 60-day deletion periods (after approval, rejection and abandonment) are configured defaults. Counsel should confirm them. |
| State requirements | 459 state topics (9 for each of the 50 states and DC) are "Verification Required". None has been researched or verified. |
| Federal requirements | 7 federal topics are "Verification Required". 2 have researched summaries awaiting a second administrator's approval: the FDA Food Code's status, and the federal minimum drinking age provision (23 U.S.C. 158). |
| Agencies | 127 state agency records are drafts copied from the FDA and TTB directories. 3 federal agency records await approval. None is verified yet. |
| Requirement rules | **None is configured.** At present no food permit or alcohol licence is required for any event, and hosts see "Verification Required" for every topic. Permit requirements will not block publishing until rules are researched, written with an official source, and approved by a second system administrator. |

## Unresolved jurisdiction requirements

- **Counties and cities:** none are loaded. Run `php artisan compliance:import-census --download` to import them from the Census Bureau's official code files. This downloads two public files, so it needs approval first. Until then, county and city levels show as "not in the database yet", and their requirements as "Verification Required".
- **Every state:** all nine topics are unresearched: food permits, food handler training, food establishments, temporary food events, alcohol licensing, special event permits, minimum drinking age, restrictions and required documentation.
- **Territories:** US territories other than DC are not in the jurisdiction list, although the agency directories include them.
- **Local rules:** county and city requirements differ widely and are not researched. Hosts are told to check with their county health department and city clerk.

## Security controls

- **Access control:**
  - Laravel Gates and Policies throughout;
  - compliance management is a specific grant for delegated administrators;
  - identity documents need a separate explicit grant, even for system administrators, and nobody grants it to themselves;
  - delegated administrators are limited to their organizers.
- **Separation of duties:**
  - no one reviews their own ID or permit, approves their own compliance record or rule, or suspends themselves;
  - only system administrators publish policies or grant overrides.
- **Documents:**
  - private storage only;
  - ID documents are encrypted, checked by hash on read and malware-scanned;
  - ID links are short-lived and bound to one administrator;
  - permit downloads are authorized by policy;
  - ID files are deleted on a retention schedule;
  - emergency breach lockdown is available.
- **Audit:**
  - append-only audit log, read through `ComplianceAuditLog`;
  - audit events fire only after the database change commits, so failed actions leave no trace;
  - acknowledgement records, published policy versions and audit entries cannot be changed or deleted.
- **Rate limits:** per person, on ID uploads, decisions and document access (10 per minute), and on compliance documents and reviews (30 per minute).
- **Integrity:**
  - multi-step operations run in database transactions;
  - foreign keys restrict deleting a person or policy version that has acknowledgement records;
  - CSV exports neutralise spreadsheet formulas and never include ID documents, document numbers or hashes.

## Test results

**Full suite:** 533 tests, 520 pass and 13 fail. All 13 failures were failing before this work began, and none involves compliance:
- event preview;
- the promotional and event QR codes;
- the notifications API;
- a page-header audit;
- worker cash receipts (3);
- worker terminal screens (3);
- a currency migration check;
- card methods at checkout.

**103 compliance tests, all passing:**
- **Unit tests (9):** requirement facts, rule matching, place names, document expiry, ID validity, policy applicability, legal text, CSV safety.
- **Feature and authorization tests (93):**
  - the 20 acceptance scenarios;
  - the permission matrix;
  - publication gates, overrides, suspensions, policy versioning, legal pages, the host and admin dashboards, the compliance database, and declarations;
  - the fixes from this review.
- **Integration test (1):** registration through to publication, a policy change, a suspension and an export, then a check that every decision on the way is audit-logged.

**Where to find them:** each scenario's test is listed in `docs/eventbite-compliance-test-plan.md`.

## Before production

1. Have qualified US legal counsel review every policy, then set `LEGAL_COUNSEL_REVIEWED=true`.
2. Research each state's requirements from official sources, approve them (two people), and write and approve the requirement rules.
3. Import counties and cities.
4. Keep the demo seeders out of production. The demo host's approved ID is labelled demo data and has no document.
5. Run a queue worker for the `notifications` queue, and the scheduler: expiry reminders, review flags and ID retention purges.
6. Configure mail.
7. Add the six host acknowledgements to the React host application card (`src/components/HostApplicationCard.tsx`). Today, hosts who apply there accept them later on their compliance page.
