# Legal disclaimers and policy pages

**Where they are:** public pages at `/legal`, linked from the footer of every page in the main layout, from registration and from the host application.

| Page | URL | Policy identifier |
|---|---|---|
| EventBite Terms of Service | `/legal/terms` | `platform_terms_and_conditions` |
| Food Safety Policy | `/legal/food-safety` | `food_safety_disclaimer` |
| Alcohol Service and Responsible Consumption Policy | `/legal/alcohol` | `alcohol_policy` |
| Event Host Agreement | `/legal/host-agreement` | `event_host_agreement`. Also lists the host acknowledgements and the event declarations in force. |
| Host Identity Verification and Privacy Notice | `/legal/host-identity-privacy` | `host_identity_verification_privacy_notice` |
| Event Cancellation and Refund Policy | `/legal/cancellations-refunds` | `event_cancellation_refund_policy` |
| Attendee Safety Notice | `/legal/attendee-safety` | `attendee_safety_notice` |
| Privacy Policy | `/legal/privacy` | `privacy_policy` |
| Jurisdiction-Specific Compliance Information | `/legal/jurisdictions`, `/legal/jurisdictions/{state}` | Read from the compliance database |

## Wording is versioned

**Where the text comes from:** each page shows the version of its policy in force (see [policy versioning](eventbite-policy-versioning.md)), with its version number and its effective and publication dates.

**Earlier versions:** these stay readable at `?version=`. A version published with a future effective date is announced on the page.

**Changing the wording:** draft and publish a new version on Compliance Dashboard → Policies. Pages are never edited directly.

**Where the original wording lives:** the five new policies' original wording is in `App\Support\LegalPages::originals()`, stored as version 1 by migration `000063`. They are notices, so acknowledging them is not mandatory. To require acknowledgement, publish a new version marked mandatory.

## Legal review before production

The wording is a **draft. It must be reviewed by qualified US legal counsel before production release.** Until `LEGAL_COUNSEL_REVIEWED=true` is set (config `compliance.legal.counsel_reviewed`), every page shows a "Draft wording" notice saying so. The optional `LEGAL_COUNSEL_REVIEWED_ON` records the review date.

## Required statements

Every page carries:
- **Not legal advice** (`LegalPages::NOT_LEGAL_ADVICE`): the information is general information. It is not legal advice and does not replace federal, state, county or city requirements, or professional advice. Confirm requirements with the responsible agency.
- **No limit and no guarantee** (`LegalPages::NO_GUARANTEE`): nothing on the page limits any legal obligation EventBite has. EventBite does not guarantee that any host, vendor or event complies with the law, and any review is limited to what the page describes.

**What the wording never does:**
- claim to remove EventBite's obligations;
- waive or limit liability;
- require indemnity;
- say a host or event is compliant.

A test checks every page for such wording.

## What the pages describe

**Policies:** the pages describe EventBite as it works today:
- **Refunds:** the host chooses the event's refund mode (full only, full or partial, or none) and a deadline. Attendees request cancellations from their orders, and the host reviews them.
- **Identity documents:** stored encrypted, opened only by specifically authorized administrators through short-lived, logged links. Document files are deleted 90, 30 or 60 days after approval, rejection or abandonment, the configured defaults.
- **Consent records:** these keep the version, time, IP address and browser.
- **Safety reports:** these go to EventBite's compliance team, not to the host.

**If any of this changes,** publish a new version of the policy.

**Jurisdiction pages:** these list each state's agencies from official directories, with their record status. A requirement's details show only once it is verified; every other topic says EventBite has not verified it and names the agency to check with.

## Tests

`tests/Feature/LegalPagesTest.php` has 6 tests.
