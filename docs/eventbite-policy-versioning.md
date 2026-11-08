# Policy versioning and acknowledgement records

## Policies and versions

Each policy is a series of `compliance_policy_versions` rows (`CompliancePolicyVersion`).

| Field | Column |
|---|---|
| Unique policy identifier | `policy_name`. The pair (policy_name, version) is unique. |
| Version number | `version` |
| Title | `title` |
| Category | `category`: terms of service, privacy, food safety, alcohol, host responsibility, event compliance declarations, safety, payments and refunds, other |
| Full policy content | `body` |
| Effective date | `effective_at`. Empty means "when published". |
| Publication date | `published_at` |
| Status | `status`: draft, published, retired |
| Applicable user roles | `applicable_roles`: every user, attendees, event hosts, administrators |
| Applicable jurisdictions | `applicable_jurisdictions`: state codes. Empty means everywhere. A jurisdiction-specific policy applies to people who host events, or hold orders, in those states. |
| Acknowledgement mandatory | `acknowledgement_mandatory` |
| Re-acknowledgement required | `requires_reacknowledgement`: a material change |

**Change summary:** each version also records what changed (`change_summary`), who drafted and published it, and `source`:
- `built_in` is the original text;
- `admin` is a version an administrator published.

**The original text is stored too:** the 12 built-in policies (3 account policies, 6 host acknowledgements and 3 groups of event declarations) are stored as their first version by migration `000062`. So the database holds the exact wording people accepted.

## History is never overwritten

- **Drafts:** may be edited or deleted.
- **Published versions:** their text and settings cannot change, and they cannot be deleted. The model refuses either.
- **New versions:** publishing a new version marks the previous one retired; nothing is replaced. A version number cannot be reused.

## Which version is in force

`PolicyCatalog` decides:

- **Current version:** the latest published version whose effective date has passed. A version published with a future date is listed as "Published, not yet in force". Until then, the previous version stays in force.
- **Required version:** the latest in-force version that requires re-acknowledgement, otherwise the first version. A person is up to date when they accepted the required version or a later one.
- **Minor changes:** a minor change (re-acknowledgement not required) does not ask anyone to accept again.

## When a policy materially changes

1. **Publish the new version.** A system administrator publishes the draft on Compliance Dashboard → Policies.
2. **Identify users affected.** On publishing, the people the version applies to who are not up to date with it are counted. The count is shown and audited. Version history offers "Who must acknowledge (CSV)" for every version.
3. **Require acknowledgement at the next relevant interaction.** The `RequirePolicyAcknowledgement` middleware sends a signed-in person to `/policies/acknowledge` on their next page view, then back to where they were going. Each policy shows its full text and what changed, and has its own checkbox. Hosts can also accept from their Compliance and verification page.
4. **Prevent restricted actions until acknowledgement is completed.** Until then:
   - every action that changes something (buying, creating or publishing events, and so on) is refused;
   - web forms go to the acknowledgement page;
   - JSON clients get **428 Precondition Required**, with the policies to accept.

   Reading, signing out and the acknowledgement itself always work.

   On top of that:
   - an unaccepted host acknowledgement blocks event publishing (`EventPublicationGates`);
   - a material change to the event declarations makes every event's acknowledgement out of date, until the host confirms the declarations again.
5. **Preserve previous acknowledgement records.**
   - Every acknowledgement is a new `policy_acceptances` row, linked by `policy_version_id` to the exact version accepted.
   - Records are append-only: the model refuses to change or delete them, so earlier records always stay.

**Original versions are not re-enforced:** they are asked for at their own interactions (registration, host application, event creation), not by the middleware.

## Separate acknowledgement records

Each moment has its own records, stored as `policy_acceptances.context`:

| Moment | Context | Written by |
|---|---|---|
| User registration | `account_registration` | `LegalComplianceService::recordRegistrationAcceptances` |
| Host application | `host_application` | `LegalComplianceService::recordHostApplicationAcceptances` |
| Event creation | `event_creation` | `EventComplianceService::record`. Also writes an `EventComplianceAcknowledgement` row. |
| Material event changes | `event_material_change_reacknowledgement` | `EventComplianceService::record` |
| Updated policies | `policy_update` | `PolicyCatalog::record` (acknowledgement page and host compliance page) |

## Tests

`tests/Feature/PolicyVersioningTest.php` has 6 tests.
