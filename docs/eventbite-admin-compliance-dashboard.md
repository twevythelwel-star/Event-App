# Administrator compliance dashboard

Admin console → **Compliance Dashboard** (`/admin/console/compliance-centre`, routes `admin.console.compliance-centre.*`).

**Code:**
- Controllers: `ComplianceCentreController`, `CompliancePolicyController` and `ComplianceExportController`.
- Permit and licence reviews reuse the existing `ComplianceRulesController@evidence` queue. It now has type and jurisdiction filters.

## Who can do what

Every action is checked by a Laravel Gate in `App\Support\AdminAuthorization`, or by a Policy class. No check relies on a role name.

| Ability | Who |
|---|---|
| `admin.compliance.manage`: dashboard, permit and licence review, declarations, expiring documents, safety reports, host and event suspension, drafting policies, the jurisdiction database | System administrators, and delegated administrators granted **Compliance management** |
| `admin.compliance.identity-documents` (`HostIdentityVerificationPolicy`): the identity queue, ID decisions, opening ID documents, the identity export | **Only administrators specifically granted identity document access.** Being a system administrator is not enough. |
| `admin.compliance.identity-access-grant` | A system administrator, for another administrator, never for themselves. Every change is audited. |
| `admin.compliance.records`: consent and audit records and their exports | Compliance managers who may read audit logs (system administrators, or delegates with audit-log access) |
| `admin.compliance.policy-publish` (`CompliancePolicyVersionPolicy::publish`) | System administrators |

**Other rules:**
- **Scope:** delegated administrators with an assigned scope only see the events, reports and hosts of their organizers.
- **No self-review:** nobody reviews their own ID or permit, or suspends themselves.

**Identity documents elsewhere:** the old routes now use the same policy as the dashboard:
- the host-applications page;
- `admin.host-verification.*`;
- `api/v1/admin/host-verifications`.

On the host-applications page, an administrator without the grant sees only that an ID was submitted, with no details.

**Breach lockdown:** this needs a system administrator who also holds identity access.

## Areas

- **Overview:** queue counts, with a filter by state.
- **Identity:**
  - review submissions;
  - open a document through a 5-minute signed link, logged as PII access;
  - approve, reject, request additional documents, require re-verification, or suspend.
- **Food permits / Alcohol licences:** the permit and licence queue, filtered by type and state.
- **Declarations:**
  - each current event's Event Compliance acknowledgement: current, out of date or missing;
  - filter by state and county;
  - the event page shows the declarations ticked, the publication gates and the history;
  - **suspend an event** here.
- **Expiring:** verified permits and licences, and approved host IDs (identity-granted administrators only), that have expired or expire within 60 days.
- **Safety reports:**
  - **Intake:** signed-in users file reports from an event page ("Report a safety issue"). Food, allergen and alcohol reports start as urgent.
  - **Handling:** administrators assign, investigate, resolve or dismiss. Resolving or dismissing needs a written finding.
- **Hosts:** each host's identity, outstanding acknowledgements and publishing status. You can **suspend publishing privileges**, optionally unpublishing their events, and restore them.
- **Policies:**
  - shows the current version of every policy, and how many people accepted it;
  - a compliance manager drafts a new version, or a brand-new acknowledgement;
  - a system administrator publishes it, which retires the previous version.
- **Consent & audit:** policy acceptances (who, version, when, context, IP) and the compliance audit trail, both searchable.
- **Access:** grant or remove compliance management and identity document access.
- **Exports (CSV):**
  - permits and licences;
  - declarations;
  - safety reports;
  - host suspensions;
  - identity status;
  - consent records;
  - the audit trail.

  Each export:
  - is authorized for what it contains;
  - is scoped to the administrator's organizers;
  - is audited;
  - neutralises spreadsheet formulas.

  Exports never include ID documents, document numbers, storage paths or hashes.
- **Maintain jurisdiction requirements:** links to the existing Compliance Database.

## Suspensions

- **Event suspension:**
  - unpublishes the event and closes ordering;
  - adds a publication gate (`event_suspended`) that no override can lift.
  - **Reinstating:** clears the gate but leaves the event unpublished. The host publishes it again, through every gate.
- **Host publishing suspension (`host_publishing_suspensions`):**
  - adds a `host_suspended` gate on each of the host's events;
  - the host's compliance page shows "Publishing suspended" with the reason;
  - lifting it is recorded with a reason.
- **Audit:** both kinds are audited (`compliance_event_*`, `compliance_host_*`).

## Policy versions

`PolicyCatalog` (scoped per request) overlays published `compliance_policy_versions` on the built-in policies in `LegalComplianceService`.

**Built-in policies:** a new version keeps the form fields and changes only the version, title and text. Registration and host applications keep working.

**New acknowledgements:** a newly published acknowledgement joins the catalog. When either kind is published:
- every host sees it on their Compliance and verification page ("Not accepted" or "Updated: accept again");
- for a host acknowledgement, `EventPublicationGates::missingHostPolicies` blocks publishing until the host accepts it.

## Demo data

**Demo delegated administrator:** has compliance management, and no identity document access. To try the identity queue locally, sign in as the system administrator and grant access to the demo administrator on the Access page.

## Tests

`tests/Feature/AdminComplianceDashboardTest.php` has 7 tests.
