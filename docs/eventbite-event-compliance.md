# Event Compliance acknowledgement

**What it is:** before each event can go live, the host confirms that event's food safety, alcohol and general legal declarations. This is separate from the policies accepted at registration and when applying to host.

**Where hosts see it:** the **Event Compliance** section of the event form (`resources/views/events/partials/form.blade.php`, `#studio-event-compliance`). It appears as step 08, "Compliance", on the create page and also on the edit page.

**How it's built:** `App\Services\Compliance\EventComplianceService`. The statement text lives in `LegalComplianceService::eventComplianceDeclarations()`.

## The declarations

There are 15 checkboxes. Each one is unticked until the host ticks it, and every one is required for every event, including the alcohol ones.

| Group | Declarations |
|---|---|
| Food safety | 5: laws, permits and inspections, handler training, ingredients and allergens, cross-contamination |
| Alcohol | 6: regulated, only when authorized, licences, minimum age and ID, responsible service, event-specific restrictions |
| General legal responsibility | 4: accurate information, federal/state/county/municipal requirements, insurance and permits, EventBite may suspend or remove |

## Jurisdiction comes first

- **What the host gives:** the event's applicable jurisdiction. That is the state from the event's address (US only), plus a county, a city or municipality, or both.
- **In the form:** the checkboxes stay locked until a state and a county or city are given.
- **On the server:** the same rule is checked, and the declarations are refused without a jurisdiction.

## When it is required

| Moment | Rule |
|---|---|
| Creating an event from the form (`POST /events`) | All 15 declarations plus the jurisdiction are required. Otherwise no event is created. |
| A draft saved before reaching them (autosave or API) | It saves, but **cannot be published** until they are confirmed for that event. This is a critical readiness issue in the "Event Compliance" section. |
| Editing an acknowledged event | A material change to the jurisdiction, food-service arrangements or alcohol-service arrangements is refused unless all declarations are confirmed again in the same save. Nothing is saved when it is refused. This applies on the web form and on the API (`PATCH /api/v1/host/events/{id}`). |
| A change made some other way (for example a data fix) | The acknowledgement becomes **outdated**. The event cannot be published until the host confirms the declarations again, and the edit page shows "Confirm these again". |

### What counts as a material change

The comparison uses the fields in `LegalComplianceService::extractComplianceState`:

- **Jurisdiction:** country, state, county, municipality. Without a named municipality, the event's city is used.
- **Food service:** food ordering on or off, ordering mode, fulfilment, food permit status and number.
- **Alcohol service:** alcohol on or off, ID checks, alcohol permit status and number.

## What is stored

One row in `event_compliance_acknowledgements` for every acknowledgement of every event. Each row holds:

- **Who gave it:** the event and the host, with the policy version and the context (`event_creation`, `event_editing` or `event_material_change_reacknowledgement`).
- **Where:** the jurisdiction (country, state, county, municipality).
- **The arrangements it covered:** food ordering, permits, alcohol and ID checks, plus a SHA-256 fingerprint of those arrangements.
- **What was confirmed:** each declaration's exact statement, marked accepted.
- **When and from where:** the time in UTC, the IP address and the browser.

A newer acknowledgement does not delete the earlier one. The earlier row is kept and marked superseded, with a reason such as "Replaced after a change to the alcohol-service arrangements."

**On the event row:**
- `compliance_acknowledgement_id` points to the current acknowledgement;
- `compliance_arrangement_fingerprint` is what it covered.

**In the host's policy history (`policy_acceptances`):** three rows per acknowledgement, one each for food safety, alcohol and general legal. Each row is linked to the event.

**A record is never made unless the host ticked every declaration in that request.** The earlier draft code did otherwise; it has been removed:
- publishing used to record acceptances without asking;
- the two older food-safety checkboxes counted as accepting all 15 declarations.

## Tests

`tests/Feature/EventComplianceAcknowledgementTest.php` covers:
- the section and its 15 unticked declarations;
- refusal without a declaration, or without a jurisdiction;
- a separate record for each new event;
- a draft that can't be published until acknowledged;
- a re-acknowledgement required for alcohol, jurisdiction and food changes, on the web and through the service;
- an outdated acknowledgement blocking publishing.

`TestCase::eventComplianceFields()` supplies a complete acknowledgement for tests that create events.
