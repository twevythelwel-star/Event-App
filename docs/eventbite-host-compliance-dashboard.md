# Host compliance and verification area

**Where:** `/host/compliance` (route `host.compliance`), and a summary card on the host dashboard that links to it. JSON: `GET /api/v1/host/compliance`.

**Code:**
- `App\Services\Compliance\HostComplianceOverview` builds everything shown.
- `App\Http\Controllers\HostComplianceController` serves the page and records policy acceptances.
- The view is `resources/views/host/compliance.blade.php`.

**Who can see it:** event hosts only. Other signed-in users are sent to the host application.

**Where the statuses come from:** the overview reads the same sources as the publication gates (`EventPublicationGates`). So what it reports as blocking is exactly what blocks publishing. It never marks anything compliant by itself. An ID, permit or licence counts only once an EventBite administrator has verified it.

**How statuses are shown:**
- Every status has a text label and an icon as well as a colour, so colour is never the only signal.
- Wherever the host has something to do, the action is written out in plain words, with a link to where to do it.

## What it shows

| Area | Content |
|---|---|
| Host account compliance | One overall status, and a numbered "What you need to do" list. The statuses are: **Action required** (cannot publish), **Under review**, **Attention needed** and **In good standing**. |
| Identity verification | The latest government ID verification and what it means. An upload form appears when a new or renewed ID is allowed. |
| Outstanding acknowledgements | Host responsibility acknowledgements not accepted at the current version, and events whose Event Compliance declarations are missing or out of date. |
| Policy updates requiring acceptance | Account policies and host acknowledgements: **Not accepted**, or **Updated: accept again** when only an older version was accepted. Each has its own checkbox. |
| Events requiring additional information | Events with something only the host can supply, each issue linked to the right place. |
| Event compliance status | Every current event (not cancelled, not ended). The statuses are: **Ready to publish** or **Compliant**, **Information required**, **Published: information missing**, **Waiting for EventBite review** and **Blocked by your account**. Temporary waivers are listed too. |
| Uploaded licences and permits | Permits and licences submitted from events' requirements checklists, with their reference, expiry and review status. |
| Upcoming document expirations | Verified documents (the ID, permits and licences) that have expired or expire within 60 days. |
| Compliance review messages | Newest first: ID review notes, host application decisions, permit and licence reviews, and requirement waivers granted or ended. |

## Accepting policies

`POST /host/compliance/policies`:
- **Ticking:** the host must tick each policy separately. Unknown policy names are refused.
- **Recording:** each ticked policy is recorded as its own `PolicyAcceptance` row with:
  - the current version;
  - the UTC time, IP address and browser;
  - context `policy_update`.

## Renewing an expired ID

Before this change, an approved ID whose document had expired could not be replaced. The host was blocked from publishing and had no way to fix it. `HostIdentityVerificationService::submitOrReplaceDocument` now accepts a new document in that case. The new document goes to **Pending Review** like any other.

## Tests

`tests/Feature/HostComplianceDashboardTest.php` has 7 tests.
