# Compliance acceptance tests

Each acceptance scenario and the test that proves it. Run them with `php artisan test --filter=Compliance` from `backend/`; the Unit suite runs with `--testsuite=Unit`.

| # | Scenario | Test |
|---|---|---|
| 1 | A user cannot register without each mandatory policy acknowledgement | `Feature/Compliance/ComplianceScenariosTest::test_01_…` |
| 2 | An ordinary attendee cannot upload or approve their own host verification | `ComplianceAuthorizationTest::test_02_…` |
| 3 | An unverified host cannot publish an event | `ComplianceScenariosTest::test_03_…` |
| 4 | An approved host can create an event (and others cannot) | `ComplianceScenariosTest::test_04_…` |
| 5 | A host acknowledges event policies for each new event | `ComplianceScenariosTest::test_05_…` |
| 6 | A missing required food permit blocks publishing | `ComplianceScenariosTest::test_06_…` |
| 7 | An event without food is not blocked by food requirements | `ComplianceScenariosTest::test_07_…` |
| 8 | An event without alcohol is not blocked by alcohol requirements | `ComplianceScenariosTest::test_08_…` |
| 9 | An alcohol event cannot pass an unresolved mandatory licence (submitted, pending, rejected) | `ComplianceScenariosTest::test_09_…` |
| 10 | Expired permits and expired IDs bring the restrictions back | `ComplianceScenariosTest::test_10_…` |
| 11 | A material policy change requires re-acknowledgement before restricted actions | `ComplianceScenariosTest::test_11_…` |
| 12 | A host cannot view another host's identity documents | `ComplianceAuthorizationTest::test_12_…` |
| 13 | An attendee cannot access host identity documents | `ComplianceAuthorizationTest::test_13_…` |
| 14 | Unauthorized administrators cannot approve verification | `ComplianceAuthorizationTest::test_14_…` |
| 15 | Overrides require permission and a recorded reason | `ComplianceAuthorizationTest::test_15_…` |
| 16 | Historical acknowledgements remain accessible to authorized auditors | `ComplianceAuthorizationTest::test_16_…` |
| 17 | The event's jurisdiction selects the applicable requirements | `ComplianceScenariosTest::test_17_…` |
| 18 | Missing or unverified jurisdiction data is clearly identified | `ComplianceScenariosTest::test_18_…` |
| 19 | Event edits re-evaluate compliance when relevant information changes | `ComplianceScenariosTest::test_19_…` |
| 20 | All sensitive document access and administrative decisions are audited | `ComplianceLifecycleIntegrationTest` |

## Test types

| Type | Where | What it covers |
|---|---|---|
| **Unit** | `tests/Unit/Compliance/` | Pure logic with no database: requirement facts, rule matching, place-name normalisation, document expiry, ID validity, who a policy applies to, legal text, CSV safety. |
| **Feature** | `tests/Feature/Compliance/ComplianceScenariosTest.php` | Scenarios 1 and 3–11, 17–19, through real routes and services. |
| **Authorization** | `tests/Feature/Compliance/ComplianceAuthorizationTest.php` | Scenarios 2 and 12–16. `tests/Feature/ComplianceHardeningTest.php` holds the full permission matrix. |
| **Integration** | `tests/Feature/Compliance/ComplianceLifecycleIntegrationTest.php` | One person's whole journey through real routes: register, apply, ID approval, event, permit, publish, policy change, suspension, export. It then checks every audit entry (scenario 20). |

## Defects found and fixed while writing these tests

- **Registration (scenario 1).** The three policy acknowledgements were only required when the client sent `enforce_policy_acknowledgements`. Without it, registration succeeded with the general "agree" box alone, and acceptance records were written for all three policies. They are now always required: in `RegisterRequest`, in the API's register endpoint, and in `AuthenticationService`.
- **ID upload (scenario 2).** Any signed-in attendee could upload a government ID. Uploading is now limited to event hosts and people with a host application that is not rejected (`HostIdentityVerificationPolicy::create`).
- **Host application acknowledgements.** Submitting a host application wrote acceptance records for all six host acknowledgements, even when none were ticked. This was a gap related to scenario 2. Only the acknowledgements actually ticked are now recorded, and the audit entry states how many. A host who skipped them must accept them on their compliance page, and cannot publish until they do.

  The React host application card (`src/components/HostApplicationCard.tsx`) does not ask for these acknowledgements yet. Hosts who apply through it accept them later.
