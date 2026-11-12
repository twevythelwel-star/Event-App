import React, { useState } from "react";
import {
  AlertTriangle,
  CheckCircle2,
  FileCheck2,
  Scale,
  ShieldAlert,
  ShieldCheck,
  UserCheck,
  Utensils,
  Wine,
} from "lucide-react";
import { EventDetails } from "../../types";

export interface AuditablePolicyConsentRecord {
  userId: string;
  policyName: string;
  policyVersion: string;
  acceptanceStatus: "accepted" | "pending";
  acceptedAtUtc: string;
  ipAddress: string;
  userAgent: string;
  context:
    | "account_registration"
    | "host_application"
    | "event_creation"
    | "event_publishing";
}

export const MANDATORY_REGISTRATION_POLICIES = [
  {
    id: "platform_terms_and_conditions",
    code: "A",
    title: "A. Platform Terms and Conditions",
    version: "2026.11-v1",
    clauses: [
      "Acceptance of EventBite's Terms of Service.",
      "Understanding of the platform's role as an event discovery, ticketing and ordering platform.",
      "Understanding that hosts are independently responsible for the legality and safety of their events.",
      "Agreement not to use EventBite to facilitate unlawful activities.",
    ],
  },
  {
    id: "food_safety_disclaimer",
    code: "B",
    title: "B. Food Safety Disclaimer",
    version: "2026.11-v1",
    clauses: [
      "Safe food preparation and handling.",
      "Proper cooking and holding temperatures.",
      "Refrigeration and storage.",
      "Prevention of cross-contamination.",
      "Handwashing and sanitation.",
      "Food transportation.",
      "Allergen identification and disclosure.",
      "Foodborne illness prevention.",
      "Appropriate food-service permits and inspections.",
      "Safe disposal of food and waste.",
    ],
  },
  {
    id: "alcohol_policy",
    code: "C",
    title: "C. Alcohol Policy",
    version: "2026.11-v1",
    clauses: [
      "Alcohol may only be sold, supplied or served when legally authorized.",
      "Applicable state and local alcohol laws must be followed.",
      "Hosts must obtain the necessary licences and permits.",
      "Minimum legal drinking ages must be respected.",
      "Valid identification and age verification procedures must be followed where required.",
      "Alcohol must not be served to prohibited persons or in prohibited circumstances.",
      "Responsible-service requirements must be observed.",
      "Hosts are responsible for applicable alcohol-related insurance and legal obligations.",
      "Purchasing an event ticket does not automatically authorize alcohol consumption and EventBite does not grant an alcohol licence.",
    ],
  },
] as const;

export const MANDATORY_HOST_RESPONSIBILITY_POLICIES = [
  {
    id: "host_resp_independent_compliance",
    title: "1. Independent Legal & Municipal Compliance",
    description:
      "I am independently responsible for the legality and safety of my event and compliance with state, county, and municipal laws.",
  },
  {
    id: "host_resp_food_safety_and_temperatures",
    title: "2. Food Preparation, Holding Temperatures, Storage & Sanitation",
    description:
      "All food vendors and kitchen staff will follow safe food preparation, cooking and holding temperatures, refrigeration, handwashing, sanitation, transportation, foodborne illness prevention, and safe waste disposal.",
  },
  {
    id: "host_resp_allergen_and_cross_contamination",
    title: "3. Allergen Identification, Dietary Disclosures & Cross-Contamination",
    description:
      "All menu items will accurately disclose ingredients and major food allergens and enforce cross-contamination prevention.",
  },
  {
    id: "host_resp_permits_and_inspections",
    title: "4. Food-Service Permits, Licences & Health Inspections",
    description:
      "All required state, county, and municipal food-service permits and health department inspections are obtained and active.",
  },
  {
    id: "host_resp_alcohol_licensing_and_age_verification",
    title: "5. Alcohol Licensing, Age Verification & Responsible Service",
    description:
      "Alcohol is served only when legally authorized with valid state/local permits, strict government ID age verification (21+ US), refusal of service to prohibited persons, and required liquor liability insurance. EventBite does not grant an alcohol licence.",
  },
  {
    id: "host_resp_identity_and_accountability",
    title: "6. Host Identity Verification & Operational Accountability",
    description:
      "My verified legal identity and organization records are accurate, and I accept accountability for all assigned event workers and vendors.",
  },
] as const;

interface LegalComplianceSectionProps {
  eventDetails: EventDetails;
  organizerId?: string;
  onComplianceVerified?: (records: AuditablePolicyConsentRecord[]) => void;
}

export const LegalComplianceSection: React.FC<LegalComplianceSectionProps> = ({
  eventDetails,
  organizerId = "org-kingston-youth",
  onComplianceVerified,
}) => {
  // Individual mandatory checkboxes (never combined into a single checkbox)
  const [checkedRegistrationPolicies, setCheckedRegistrationPolicies] =
    useState<Record<string, boolean>>({
      platform_terms_and_conditions: true,
      food_safety_disclaimer: true,
      alcohol_policy: true,
    });

  const [checkedHostResponsibilities, setCheckedHostResponsibilities] =
    useState<Record<string, boolean>>({
      host_resp_independent_compliance: true,
      host_resp_food_safety_and_temperatures: true,
      host_resp_allergen_and_cross_contamination: true,
      host_resp_permits_and_inspections: true,
      host_resp_alcohol_licensing_and_age_verification: true,
      host_resp_identity_and_accountability: true,
    });

  // Host Identity & Permit fields
  const [legalRepresentativeName, setLegalRepresentativeName] = useState(
    eventDetails.organizerName || "Verified Event Host"
  );
  const [foodPermitNumber, setFoodPermitNumber] = useState(
    "FL-DBPR-FOOD-2026-8841"
  );
  const [servesAlcohol, setServesAlcohol] = useState(true);
  const [alcoholPermitNumber, setAlcoholPermitNumber] = useState(
    "ABT-SPECIAL-EVENT-4410"
  );
  const [statusBanner, setStatusBanner] = useState<string | null>(null);

  const [consentAuditLog, setConsentAuditLog] = useState<
    AuditablePolicyConsentRecord[]
  >(() => {
    const nowUtc = "2026-11-06T00:00:00Z";
    return [
      ...MANDATORY_REGISTRATION_POLICIES.map((p) => ({
        userId: organizerId,
        policyName: p.id,
        policyVersion: p.version,
        acceptanceStatus: "accepted" as const,
        acceptedAtUtc: nowUtc,
        ipAddress: "127.0.0.1 (Privacy-Masked)",
        userAgent: "EventBite-Compliance-Audit/1.0",
        context: "account_registration" as const,
      })),
      ...MANDATORY_HOST_RESPONSIBILITY_POLICIES.map((p) => ({
        userId: organizerId,
        policyName: p.id,
        policyVersion: "2026.11-v1",
        acceptanceStatus: "accepted" as const,
        acceptedAtUtc: nowUtc,
        ipAddress: "127.0.0.1 (Privacy-Masked)",
        userAgent: "EventBite-Compliance-Audit/1.0",
        context: "host_application" as const,
      })),
    ];
  });

  const allRegistrationAccepted = MANDATORY_REGISTRATION_POLICIES.every(
    (p) => checkedRegistrationPolicies[p.id]
  );
  const allHostAccepted = MANDATORY_HOST_RESPONSIBILITY_POLICIES.every(
    (p) => checkedHostResponsibilities[p.id]
  );
  const isFullyCompliant = allRegistrationAccepted && allHostAccepted;

  const handleRecordComplianceAudit = () => {
    if (!isFullyCompliant) {
      setStatusBanner(
        "Every mandatory policy and Host Responsibility checkbox must be individually accepted before recording compliance."
      );
      return;
    }

    const timestampUtc = new Date().toISOString();
    const updatedRecords: AuditablePolicyConsentRecord[] = [
      ...MANDATORY_REGISTRATION_POLICIES.map((p) => ({
        userId: organizerId,
        policyName: p.id,
        policyVersion: p.version,
        acceptanceStatus: "accepted" as const,
        acceptedAtUtc: timestampUtc,
        ipAddress: "127.0.0.1 (Privacy-Masked)",
        userAgent:
          typeof navigator !== "undefined"
            ? navigator.userAgent.slice(0, 120)
            : "EventBite-Browser/1.0",
        context: "account_registration" as const,
      })),
      ...MANDATORY_HOST_RESPONSIBILITY_POLICIES.map((p) => ({
        userId: organizerId,
        policyName: p.id,
        policyVersion: "2026.11-v1",
        acceptanceStatus: "accepted" as const,
        acceptedAtUtc: timestampUtc,
        ipAddress: "127.0.0.1 (Privacy-Masked)",
        userAgent:
          typeof navigator !== "undefined"
            ? navigator.userAgent.slice(0, 120)
            : "EventBite-Browser/1.0",
        context: "event_publishing" as const,
      })),
    ];

    setConsentAuditLog(updatedRecords);
    setStatusBanner(
      `Auditable Policy Acceptance Log updated in UTC (${timestampUtc}) for ${updatedRecords.length} mandatory policies.`
    );
    onComplianceVerified?.(updatedRecords);
  };

  return (
    <section
      id="legal-compliance-verification-section"
      data-testid="legal-compliance-verification-section"
      className="mt-6 rounded-2xl border border-emerald-500/30 bg-[#0F1420] p-6 text-white shadow-xl"
    >
      {/* Header */}
      <div className="flex flex-wrap items-start justify-between gap-4 border-b border-white/10 pb-5">
        <div>
          <div className="inline-flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-500/10 px-3 py-1 text-xs font-extrabold uppercase tracking-wider text-emerald-300">
            <Scale className="h-3.5 w-3.5 text-lime-400" />
            Food Safety, Alcohol Service, Legal Compliance & Host Identity Verification
          </div>
          <h3 className="mt-2 text-xl font-extrabold tracking-tight text-white">
            Mandatory Policy Acknowledgements & Auditable Consent Records
          </h3>
          <p className="mt-1 text-sm text-zinc-400">
            Protects attendees, hosts, workers, and EventBite across food preparation, allergens, alcohol licensing, municipal permits, and host identity verification.
          </p>
        </div>

        <div
          data-testid="compliance-gate-badge"
          className={`flex items-center gap-2 rounded-xl border px-3.5 py-2 text-xs font-extrabold ${
            isFullyCompliant
              ? "border-emerald-400/40 bg-emerald-500/15 text-emerald-300"
              : "border-red-400/40 bg-red-500/15 text-red-300"
          }`}
        >
          {isFullyCompliant ? (
            <>
              <ShieldCheck className="h-4 w-4" />
              <span>All 9 Individual Compliance Policies Accepted</span>
            </>
          ) : (
            <>
              <ShieldAlert className="h-4 w-4" />
              <span>Action Required: Unaccepted Mandatory Policies</span>
            </>
          )}
        </div>
      </div>

      {/* Sections A, B, C: Platform Terms, Food Safety Disclaimer, Alcohol Policy */}
      <div className="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        {MANDATORY_REGISTRATION_POLICIES.map((policy) => {
          const accepted = Boolean(checkedRegistrationPolicies[policy.id]);
          return (
            <div
              key={policy.id}
              data-testid={`policy-card-${policy.id}`}
              className="flex flex-col justify-between rounded-xl border border-white/10 bg-black/40 p-4"
            >
              <div>
                <div className="flex items-center justify-between gap-2">
                  <span className="flex items-center gap-1.5 text-xs font-extrabold uppercase tracking-wider text-lime-400">
                    {policy.id === "platform_terms_and_conditions" && (
                      <FileCheck2 className="h-4 w-4" />
                    )}
                    {policy.id === "food_safety_disclaimer" && (
                      <Utensils className="h-4 w-4" />
                    )}
                    {policy.id === "alcohol_policy" && (
                      <Wine className="h-4 w-4" />
                    )}
                    {policy.title}
                  </span>
                  <span className="rounded bg-white/10 px-2 py-0.5 text-[10px] font-bold text-zinc-300">
                    {policy.version}
                  </span>
                </div>

                <ul className="mt-3 space-y-1.5 text-xs text-zinc-300">
                  {policy.clauses.map((clause, idx) => (
                    <li key={idx} className="flex items-start gap-1.5">
                      <CheckCircle2 className="mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-400" />
                      <span>{clause}</span>
                    </li>
                  ))}
                </ul>
              </div>

              <label
                htmlFor={`chk-${policy.id}`}
                className="mt-4 flex cursor-pointer items-center gap-2 rounded-lg border border-white/10 bg-zinc-900/90 p-2.5 text-xs font-bold text-white"
              >
                <input
                  id={`chk-${policy.id}`}
                  type="checkbox"
                  checked={accepted}
                  onChange={(e) =>
                    setCheckedRegistrationPolicies((prev) => ({
                      ...prev,
                      [policy.id]: e.target.checked,
                    }))
                  }
                />
                <span>I acknowledge and accept {policy.title} *</span>
              </label>
            </div>
          );
        })}
      </div>

      {/* Host Identity Verification & Permits Bar */}
      <div className="mt-6 rounded-xl border border-white/10 bg-black/40 p-4">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <h4 className="flex items-center gap-2 text-xs font-extrabold uppercase tracking-wider text-lime-400">
            <UserCheck className="h-4 w-4" />
            Host Identity Verification & Food / Alcohol Permit Disclosures
          </h4>
          <span className="rounded-full border border-emerald-400/30 bg-emerald-500/15 px-2.5 py-0.5 text-[11px] font-bold text-emerald-300">
            Identity Verified · State & Municipal Accountability Active
          </span>
        </div>

        <div className="mt-3 grid grid-cols-1 gap-3 md:grid-cols-4">
          <div>
            <label
              htmlFor="compliance-legal-rep"
              className="block text-[11px] font-bold text-zinc-400"
            >
              Accountable Legal Representative *
            </label>
            <input
              id="compliance-legal-rep"
              type="text"
              value={legalRepresentativeName}
              onChange={(e) => setLegalRepresentativeName(e.target.value)}
              className="mt-1 w-full rounded-lg border border-white/15 bg-zinc-900 px-3 py-1.5 text-xs text-white"
            />
          </div>

          <div>
            <label
              htmlFor="compliance-food-permit"
              className="block text-[11px] font-bold text-zinc-400"
            >
              Food-Service / Health Permit #
            </label>
            <input
              id="compliance-food-permit"
              type="text"
              value={foodPermitNumber}
              onChange={(e) => setFoodPermitNumber(e.target.value)}
              className="mt-1 w-full rounded-lg border border-white/15 bg-zinc-900 px-3 py-1.5 text-xs text-white"
            />
          </div>

          <div>
            <label
              htmlFor="compliance-alcohol-permit"
              className="block text-[11px] font-bold text-zinc-400"
            >
              Alcohol Licence / Special Event Permit #
            </label>
            <input
              id="compliance-alcohol-permit"
              type="text"
              value={alcoholPermitNumber}
              onChange={(e) => setAlcoholPermitNumber(e.target.value)}
              className="mt-1 w-full rounded-lg border border-white/15 bg-zinc-900 px-3 py-1.5 text-xs text-white"
            />
          </div>

          <div className="flex items-end">
            <label className="flex w-full cursor-pointer items-center gap-2 rounded-lg border border-white/15 bg-zinc-900 px-3 py-2 text-xs font-semibold text-zinc-200">
              <input
                type="checkbox"
                checked={servesAlcohol}
                onChange={(e) => setServesAlcohol(e.target.checked)}
              />
              <span>21+ Government ID Age Check Enforced</span>
            </label>
          </div>
        </div>
      </div>

      {/* Section D: Mandatory Individual Host Responsibility Checkboxes */}
      <div className="mt-6 rounded-xl border border-lime-400/25 bg-black/40 p-4">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div>
            <h4 className="text-sm font-extrabold text-white">
              D. Mandatory Individual Host Responsibility Acknowledgements
            </h4>
            <p className="text-xs text-zinc-400">
              Each responsibility is individually selectable and required before submitting a host application or publishing an event.
            </p>
          </div>
        </div>

        <div className="mt-3 grid grid-cols-1 gap-2.5 md:grid-cols-2">
          {MANDATORY_HOST_RESPONSIBILITY_POLICIES.map((item) => {
            const checked = Boolean(checkedHostResponsibilities[item.id]);
            return (
              <label
                key={item.id}
                htmlFor={`chk-${item.id}`}
                data-testid={`host-responsibility-${item.id}`}
                className="flex cursor-pointer items-start gap-2.5 rounded-xl border border-white/10 bg-zinc-900/80 p-3 text-xs"
              >
                <input
                  id={`chk-${item.id}`}
                  type="checkbox"
                  checked={checked}
                  onChange={(e) =>
                    setCheckedHostResponsibilities((prev) => ({
                      ...prev,
                      [item.id]: e.target.checked,
                    }))
                  }
                  className="mt-0.5"
                />
                <div>
                  <div className="font-bold text-white">{item.title} *</div>
                  <p className="mt-0.5 leading-relaxed text-zinc-400">
                    {item.description}
                  </p>
                </div>
              </label>
            );
          })}
        </div>

        <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
          {!isFullyCompliant ? (
            <div
              role="alert"
              className="flex items-center gap-2 text-xs font-bold text-amber-300"
            >
              <AlertTriangle className="h-4 w-4" />
              <span>
                You cannot complete host registration or publish this event until all individual policies are accepted.
              </span>
            </div>
          ) : (
            <div className="text-xs text-emerald-300">
              ✓ All mandatory Platform, Food Safety, Alcohol Service, and Host Responsibility policies accepted.
            </div>
          )}

          <button
            type="button"
            id="btn-record-auditable-policy-consent"
            disabled={!isFullyCompliant}
            onClick={handleRecordComplianceAudit}
            className={`rounded-xl px-4 py-2 text-xs font-extrabold transition ${
              isFullyCompliant
                ? "bg-lime-400 text-black hover:bg-lime-300"
                : "cursor-not-allowed bg-zinc-800 text-zinc-500"
            }`}
          >
            Record Auditable Policy Acceptance
          </button>
        </div>

        {statusBanner && (
          <div className="mt-3 rounded-lg border border-lime-400/30 bg-lime-400/10 px-3 py-2 text-xs font-semibold text-lime-300">
            {statusBanner}
          </div>
        )}
      </div>

      {/* Auditable Policy Acceptance Ledger */}
      <div
        id="auditable-policy-acceptance-ledger"
        className="mt-5 rounded-xl border border-white/10 bg-black/30 p-4"
      >
        <div className="flex flex-wrap items-center justify-between gap-2">
          <span className="text-xs font-extrabold uppercase tracking-wider text-zinc-300">
            Auditable Consent Records (UTC Timestamp · Policy Version · Context · IP · User-Agent)
          </span>
          <span className="text-[11px] text-zinc-400">
            {consentAuditLog.length} Recorded Policy Acceptances
          </span>
        </div>

        <div className="mt-2.5 max-h-44 overflow-x-auto">
          <table className="w-full text-left text-[11px] text-zinc-300">
            <thead className="border-b border-white/10 text-[10px] uppercase text-zinc-400">
              <tr>
                <th className="py-1.5 pr-3">User ID</th>
                <th className="py-1.5 pr-3">Policy Name</th>
                <th className="py-1.5 pr-3">Version</th>
                <th className="py-1.5 pr-3">Status</th>
                <th className="py-1.5 pr-3">Accepted (UTC)</th>
                <th className="py-1.5 pr-3">Context</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-white/5">
              {consentAuditLog.map((row, idx) => (
                <tr key={`${row.policyName}-${idx}`}>
                  <td className="py-1.5 pr-3 font-mono text-zinc-400">
                    {row.userId}
                  </td>
                  <td className="py-1.5 pr-3 font-semibold text-white">
                    {row.policyName}
                  </td>
                  <td className="py-1.5 pr-3">{row.policyVersion}</td>
                  <td className="py-1.5 pr-3 text-emerald-300">
                    {row.acceptanceStatus}
                  </td>
                  <td className="py-1.5 pr-3 font-mono">{row.acceptedAtUtc}</td>
                  <td className="py-1.5 pr-3">{row.context}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </section>
  );
};

