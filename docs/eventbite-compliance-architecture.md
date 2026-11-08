# Compliance architecture (Laravel)

How the compliance features are built, and where each requested structure lives. The code follows the app's existing conventions:
- accounts and roles come from `AccountType`, `UserRole` and `AdminPermission`;
- access is checked by Gates in `App\Support\AdminAuthorization` and by Policy classes;
- audit entries go through `AuditLogService` and the append-only `security_audit_logs` table;
- notifications are queued on the `notifications` queue.

## Structures

| Requested structure | Implementation | Notes |
|---|---|---|
| HostVerification | `HostIdentityVerification` | Status workflow in `HostVerificationStatus`, logic in `HostIdentityVerificationService`. |
| HostIdentityDocument | The document fields of `HostIdentityVerification` | The file is encrypted and kept on the private `local` disk; the path, hash and cipher are hidden from serialization. One document per verification; a replacement is recorded with `replacement_count`. |
| CompliancePolicy | `Compliance\CompliancePolicy` (`compliance_policies`) | Unique identifier and category; `hasMany` versions. |
| PolicyVersion | `Compliance\CompliancePolicyVersion` | Never overwritten once published; `PolicyCatalog` decides the version in force. |
| PolicyAcknowledgement | `PolicyAcceptance` | Append-only; linked to the exact version by `policy_version_id`. |
| Jurisdiction | `Compliance\ComplianceJurisdiction` | Country → state → county → city. |
| RegulatoryAuthority | `Compliance\ComplianceAuthority` | |
| ComplianceRequirement | `Compliance\ComplianceRequirement`, plus `ComplianceEventRule` | Rules decide what an event needs. |
| EventComplianceDeclaration | `EventComplianceAcknowledgement` | One record per confirmation; earlier ones are superseded, not removed. |
| EventComplianceDocument | `EventComplianceEvidence` | Soft-deletes when a host withdraws it before review. |
| ComplianceReview | `Compliance\ComplianceRecordReview` | Reviews of database records and of evidence. |
| ComplianceAuditLog | `ComplianceAuditLog` | The compliance slice of the append-only security audit log. |

## Database

**Foreign keys:**
- on every compliance table;
- migration `000064` added them to the tables from `000061`–`000062`.

**Deletion rules:**
- Acknowledgement records restrict deletion of the person and the version they belong to, so consent history cannot vanish.
- Policy versions restrict deletion of their policy.

**Indexes** cover the queries run on every page and dashboard:
- `(status, effective_at)` on policy versions;
- `(user_id, lifted_at)` on suspensions;
- `(status, priority)` on safety reports;
- `(status, expires_on)` on evidence;
- `events.compliance_suspended_at`.

**Soft deletion** is used only where a record must survive removal: withdrawn evidence. Records that must never disappear are append-only instead: audit logs, acknowledgements and published policy versions.

## Validation: Form Requests

All are in `app/Http/Requests/Compliance/`. Each authorizes before it validates, so a refused request never reaches validation:
- `SubmitComplianceEvidenceRequest`, `ReviewComplianceEvidenceRequest`;
- `StoreIdentityDocumentRequest`, `UpdateIdentityVerificationStatusRequest`;
- `StorePublicationOverrideRequest`, `RevokePublicationOverrideRequest`;
- `SuspendEventRequest`, `SuspendHostRequest`, `ComplianceReasonRequest`;
- `UpdateSafetyReportRequest`, `StoreSafetyReportRequest`;
- `StorePolicyVersionRequest`, `PublishPolicyVersionRequest`;
- `UpdateComplianceAccessRequest`, `AcknowledgePoliciesRequest`.

## Access: Gates and Policies

**Gates** (`AdminAuthorization`):
- `admin.compliance.manage`
- `admin.compliance.identity-documents`
- `admin.compliance.identity-access-grant`
- `admin.compliance.records`
- `admin.compliance.export`
- `admin.compliance.suspend-host`
- `admin.compliance.suspend-event`
- `admin.compliance.policy-publish`
- `admin.compliance.override`

**Policies:**
- `HostIdentityVerificationPolicy`
- `EventComplianceEvidencePolicy` (`viewAny`, `review`, `viewDocument`, `withdraw`)
- `ComplianceSafetyReportPolicy`
- `CompliancePolicyVersionPolicy`

**Identity documents** need a specific grant (`can_access_identity_documents`), whatever the role. A system administrator gives that grant to someone else, never to themselves.

## Documents

**Private storage:** documents are stored on the private `local` disk (`storage/app/private`); nothing is placed on the public disk.

**Identity documents:**
- encrypted at rest;
- opened only through a 5-minute signed link issued to one administrator;
- logged as personal-data access on every view.

**Permit and licence documents:**
- downloaded through `EventRequirementsController@document`;
- authorized by `EventComplianceEvidencePolicy::viewDocument`: the event's own hosts, and compliance managers whose scope covers the event;
- everyone else gets 404;
- every download is audited (`compliance_document_downloaded`).

## Audit events

Sensitive actions dispatch events in `app/Events/Compliance`:

| Event | Covers | Also notifies |
|---|---|---|
| `ComplianceActionRecorded` | General compliance actions: submissions, withdrawals, downloads, overrides, safety reports, policy drafts and publishing, access grants, exports | No |
| `ComplianceEvidenceReviewed` | Permit and licence reviews | Yes |
| `PublishingSuspensionChanged` | Event and host suspensions | Yes |
| `IdentityVerificationReviewed` | ID decisions. The identity service writes its own personal-data audit entry. | Yes |

**How the events reach the audit log:**
- Each event implements `ComplianceAuditable` and `ShouldDispatchAfterCommit`.
- `WriteComplianceAuditLog` writes one audit entry per event, found by event discovery.
- If the surrounding transaction rolls back, the event is never dispatched: no audit entry and no notification.

## Notifications (queued)

All are in `app/Notifications/Compliance/`, use `ShouldQueue`, run on the `notifications` queue and send by mail:

| Notification | Sent when | Sent by |
|---|---|---|
| `HostVerificationReviewedNotification` | An ID decision is made (not when a review merely starts) | `SendComplianceNotifications` |
| `ComplianceDocumentReviewedNotification` | A permit or licence is verified or rejected; sent to the submitter | `SendComplianceNotifications` |
| `PublishingSuspensionNotification` | An event, or a host's publishing, is suspended or reinstated; sent to the hosts | `SendComplianceNotifications` |
| `ComplianceDocumentExpiringNotification` | A verified permit, licence or approved ID expires in 30 or 7 days, or expired yesterday | `compliance:notify-expiring`, scheduled daily at 07:30 |

## Transactions

Multi-step operations commit together or not at all:
- **Evidence:** a submission (if recording it fails, the stored file is removed); a review, together with its review record; a withdrawal.
- **Overrides:** granting and revoking.
- **Suspensions:** an event suspension; a host suspension, including unpublishing their events.
- **Reinstatements and other updates:** reinstating an event or a host, safety report updates, access grants.
- **Identity:** an identity decision, together with its host application and audit entry.
- **Policies:** publishing a policy (retiring the previous version), and recording acknowledgements.

## Rate limits

Named limiters, per signed-in person (or per IP when signed out), are defined in `AppServiceProvider`:

| Limiter | Limit | Covers |
|---|---|---|
| `identity-verification` | 10 per minute | ID upload (web and API), ID decisions (web and API), opening a document, signed links, breach lockdown |
| `compliance-sensitive` | 30 per minute | Evidence submission, withdrawal and download; evidence reviews; overrides |

## Code layout

Compliance logic is split across services, controllers, middleware and console commands.

**Services (`app/Services/Compliance`):**
- `EventComplianceService`
- `EventRequirementChecklist`
- `JurisdictionResolver`
- `EventPublicationGates`
- `PolicyCatalog`
- `ComplianceReviewWorkflow`
- `HostComplianceOverview`

**Controllers:** thin, one per area:
- event requirements;
- host compliance;
- policy acknowledgement;
- safety reports;
- legal pages;
- in the admin console: the compliance dashboard, policies, exports, overrides, rules and the jurisdiction database.

**Middleware:** `RequirePolicyAcknowledgement`.

**Console commands:**
- `compliance:notify-expiring`
- `compliance:flag-due-reviews`
- `compliance:import-census`
- `compliance:purge-expired-id-documents`

## Tests

`tests/Feature/ComplianceHardeningTest.php` (9 tests) covers:
- the full permission matrix for every compliance ability;
- private storage and authorized, audited downloads;
- withdrawal with soft deletion;
- queued notifications;
- after-commit audit events, including a rollback that leaves no trace;
- expiry reminders;
- Form Request authorization and validation;
- per-person rate limits;
- foreign keys.

Publication gates and the remaining permissions are covered by `EventPublicationGatesTest`, `AdminComplianceDashboardTest`, `PolicyVersioningTest`, `HostComplianceDashboardTest`, `EventRequirementChecklistTest`, `ComplianceDatabaseTest` and `LegalComplianceAndPolicyAcceptanceTest`.
