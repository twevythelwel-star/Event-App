import React, { useEffect, useState } from "react";
import { Building2 } from "lucide-react";

import { HostApplication, fetchMyHostApplications, submitHostApplication } from "../lib/api";

type Props = {
  defaultPhone?: string | null;
  onNotify: (message: string) => void;
};

/**
 * Lets a signed-in attendee apply to become an Event Host and follow the decision (live backend only).
 */
export const HostApplicationCard: React.FC<Props> = ({ defaultPhone, onNotify }) => {
  const [latest, setLatest] = useState<HostApplication | null | undefined>(undefined);
  const [isOpen, setIsOpen] = useState(false);
  const [organizationName, setOrganizationName] = useState("");
  const [description, setDescription] = useState("");
  const [country, setCountry] = useState<"JM" | "US">("JM");
  const [contactPhone, setContactPhone] = useState(defaultPhone ?? "");
  const [website, setWebsite] = useState("");
  const [error, setError] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    fetchMyHostApplications()
      .then((applications) => setLatest(applications[0] ?? null))
      .catch(() => setLatest(null));
  }, []);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError("");
    setIsSubmitting(true);
    try {
      const created = await submitHostApplication({
        organization_name: organizationName.trim(),
        description: description.trim() || undefined,
        country,
        contact_phone: contactPhone.trim(),
        website_or_social: website.trim() || undefined,
      });
      setLatest(created);
      setIsOpen(false);
      onNotify("Application submitted. An administrator will review it.");
    } catch (err) {
      setError(err instanceof Error ? err.message : "Unable to submit the application.");
    } finally {
      setIsSubmitting(false);
    }
  }

  if (latest === undefined) return null;

  const canApply = !latest || latest.status === "rejected";

  return (
    <section className="panel" style={{ padding: "14px 18px", marginBottom: "20px" }} aria-labelledby="host-apply-title">
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", gap: "12px", flexWrap: "wrap" }}>
        <div>
          <h2 id="host-apply-title" style={{ display: "flex", alignItems: "center", gap: "8px", margin: 0, fontSize: "15px" }}>
            <Building2 size={16} /> Run your own fish fry
          </h2>
          <p style={{ fontSize: "12px", color: "#475569", margin: "2px 0 0" }}>
            {latest?.status === "pending" && `Your application for ${latest.organizationName} is waiting for review.`}
            {latest?.status === "approved" &&
              `${latest.organizationName} was approved. Reload the page to open the Event Host dashboard.`}
            {latest?.status === "rejected" &&
              `Your application for ${latest.organizationName} was not approved: ${latest.rejectionReason}. You can apply again.`}
            {!latest && "Apply for an Event Host account to sell tickets and food for your own events."}
          </p>
        </div>
        {canApply && !isOpen && (
          <button type="button" className="primary-button" onClick={() => setIsOpen(true)}>
            Apply to host events
          </button>
        )}
      </div>

      {isOpen && (
        <form onSubmit={handleSubmit} style={{ display: "grid", gap: "10px", marginTop: "12px", maxWidth: "560px" }}>
          <label className="field-label" style={{ margin: 0 }}>
            Organization or brand name *
            <input type="text" value={organizationName} onChange={(e) => setOrganizationName(e.target.value)} required minLength={3} />
          </label>
          <label className="field-label" style={{ margin: 0 }}>
            What events will you run?
            <textarea value={description} onChange={(e) => setDescription(e.target.value)} rows={3} maxLength={2000} />
          </label>
          <label className="field-label" style={{ margin: 0 }}>
            Country *
            <select value={country} onChange={(e) => setCountry(e.target.value as "JM" | "US")}>
              <option value="JM">Jamaica</option>
              <option value="US">United States</option>
            </select>
          </label>
          <label className="field-label" style={{ margin: 0 }}>
            Contact phone *
            <input type="tel" value={contactPhone} onChange={(e) => setContactPhone(e.target.value)} required />
          </label>
          <label className="field-label" style={{ margin: 0 }}>
            Website or social link
            <input type="text" value={website} onChange={(e) => setWebsite(e.target.value)} />
          </label>
          {error && (
            <p role="alert" style={{ color: "#b91c1c", fontSize: "13px", margin: 0 }}>
              {error}
            </p>
          )}
          <div style={{ display: "flex", gap: "8px" }}>
            <button type="submit" className="primary-button" disabled={isSubmitting}>
              {isSubmitting ? "Submitting…" : "Submit application"}
            </button>
            <button type="button" className="action-pill-btn" onClick={() => setIsOpen(false)}>
              Cancel
            </button>
          </div>
        </form>
      )}
    </section>
  );
};
