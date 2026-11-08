# Jurisdiction-specific event requirements

**What it does:** each event gets a requirements checklist. The checklist is worked out from where the event happens, how it provides food and alcohol, and the rules administrators keep in the [compliance database](eventbite-compliance-database.md).

**How it's built:**
- `App\Services\Compliance\JurisdictionResolver` finds the jurisdiction.
- `App\Services\Compliance\EventRequirementChecklist` builds the checklist.
- `App\Models\Compliance\ComplianceEventRule` holds the rules.
- `App\Models\EventComplianceEvidence` holds what hosts submit.

## Identifying the jurisdiction

The resolver matches each location in the database: country, then state, then county, then city or municipality. For example, United States → Florida → Miami-Dade County → City of Miami.

**Matching is loose.** "City of Miami" matches the Census place "Miami city", and "Miami-Dade" matches "Miami-Dade County".

**Missing places are labelled, not guessed.** A county or city the database doesn't have yet shows as "(not in the database yet)". Its items stay "Verification Required".

**The three locations:**
- **Event location:** the event address, using the county and municipality given in Event Compliance.
- **Food preparation location:** the event location, or another state, county and city if "Food is prepared somewhere other than the event location" is ticked.
- **Alcohol service location:** the event address, or another place if "Alcohol is served somewhere other than the event address" is ticked.

## What decides the items

| Question on the form | Values |
|---|---|
| Event type | The event category |
| Food at this event | none, sold, complimentary, sold and complimentary |
| Alcohol at this event | none, sold, included in the ticket, complimentary, BYOB |
| Licensed caterer / licensed alcohol vendor | Ticked or not |

**Topics checked at each location:**
- **Event location:** special event permit, restrictions, required documentation. If there is food, also food permit and temporary food event.
- **Food preparation location** (when there is food): food establishment, food handler training.
- **Alcohol service location** (when there is any alcohol, including BYOB): alcohol licensing, minimum drinking age.

**Levels and extra items:**
- Each topic is listed at each level of that location's chain: state, county, city.
- Federal rows appear only where an administrator has set a federal rule.
- Additional local requirements an administrator has added appear as their own items.

## Statuses

| Status | When |
|---|---|
| **Required** | An active administrator rule for that jurisdiction says so for this kind of event. |
| **Not Required** | An active administrator exception matches. An exception beats a requirement when both match. |
| **Verification Required** | No rule covers it. EventBite has not confirmed whether it applies. This is the default; nothing is assumed. |
| **Submitted** | The host submitted a permit or licence number, the issuing authority, an optional expiry date and an optional document. |
| **Pending Verification** | An administrator has started checking it. |
| **Verified** | An administrator other than the submitter checked it. |
| **Rejected** | An administrator rejected it, with a reason the host sees. |
| **Expired** | It was verified, but its expiry date has passed. |

**Ticking a box never makes a host compliant.** The declarations, and the host's own submissions, only reach "Submitted". Only an administrator sets Verified or Rejected, and never on their own submission.

## Where hosts see it

- **On the event form (step 09, Requirements):**
  - The form asks how food and alcohol are provided, and where.
  - "Requirements for this event" updates live as the form changes (`POST /events/requirements-preview`).
  - On a saved event, the list includes the evidence statuses.
- **The Requirements page (`/events/{id}/requirements`):**
  - It shows the full checklist with a form to submit evidence for each item.
  - Evidence can only be submitted for an item that is on this event's checklist.
  - Documents (PDF, JPG or PNG, up to 5 MB) are stored on the private disk. Only the event's managers and administrators can open them.
- **Changes need the declarations again:**
  - Changing how food or alcohol is provided, the caterer or vendor, or where they are prepared or served is a material change.
  - For an acknowledged event, the host must confirm the Event Compliance declarations again before the change saves.

## Where administrators configure it

**Rules (Compliance Database → a jurisdiction → "Event checklist rules"):**
- **What a rule can be:** a requirement or an exception for a standard topic, or an additional local requirement with its own name.
- **What each rule sets:**
  - the location it concerns;
  - optional conditions: event types, food modes, alcohol modes, caterer and vendor involvement;
  - whether it also covers every county and city inside the jurisdiction;
  - the text hosts see;
  - an optional link to the jurisdiction's requirement record.
- **Source:** every rule must cite an official source. That means https on `.gov`, `.mil` or `.us`, or a site the administrator confirms is the agency's own.
- **Turning off:** rules can be turned off and on. Each change goes into the review history.

**Evidence (Compliance Database → "Event evidence to verify"):**
- Start verification, verify (with a note on how it was checked) or reject (with a reason).
- Open the document.

## Tests

`tests/Feature/EventRequirementChecklistTest.php` covers:
- resolving down to the city, and reporting missing levels;
- the default of Verification Required;
- items that follow what the event provides, including BYOB and no food;
- food prepared elsewhere;
- rules, exceptions, local requirements, conditions and inactive rules;
- evidence from submission to verified to expired, plus private documents;
- rejection reasons and no self-review;
- the live preview and the form panels;
- rule source checks and permissions;
- re-acknowledgement after an arrangement change.
