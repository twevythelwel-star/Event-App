import React, { useEffect, useState } from "react";
import { Building2, CheckCircle2, RefreshCw, XCircle } from "lucide-react";

import {
  HostApplication,
  approveHostApplication,
  fetchAdminHostApplications,
  rejectHostApplication,
} from "../lib/api";

type Props = {
  onNotify: (message: string) => void;
};

/**
 * Administrator review queue for Event Host applications (live backend only).
 * Approval grants the Event Host role and provisions the applicant's own organizer on the server.
 */
export const HostApplicationsReview: React.FC<Props> = ({ onNotify }) => {
  const [filter, setFilter] = useState<"pending" | "all">("pending");
  const [applications, setApplications] = useState<HostApplication[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [loadError, setLoadError] = useState("");
  const [busyId, setBusyId] = useState<number | null>(null);
  const [notes, setNotes] = useState<Record<number, string>>({});
  const [rejectingId, setRejectingId] = useState<number | null>(null);
  const [rejectReason, setRejectReason] = useState("");

  function load() {
    setIsLoading(true);
    setLoadError("");
    fetchAdminHostApplications(filter)
      .then(setApplications)
      .catch((error) => setLoadError(error instanceof Error ? error.message : "Unable to load applications."))
      .finally(() => setIsLoading(false));
  }

  useEffect(load, [filter]);

  function replace(updated: HostApplication) {
    setApplications((current) =>
      filter === "pending" ? current.filter((a) => a.id !== updated.id) : current.map((a) => (a.id === updated.id ? updated : a))
    );
  }

  async function approve(application: HostApplication) {
    setBusyId(application.id);
    try {
      replace(await approveHostApplication(application.id, notes[application.id]?.trim() || undefined));
      onNotify(`${application.organizationName} approved. ${application.applicantName} is now an Event Host.`);
    } catch (error) {
      onNotify(error instanceof Error ? error.message : "Approval failed.");
    } finally {
      setBusyId(null);
    }
  }

  async function reject(application: HostApplication) {
    if (!rejectReason.trim()) return;
    setBusyId(application.id);
    try {
      replace(await rejectHostApplication(application.id, rejectReason.trim(), notes[application.id]?.trim() || undefined));
      setRejectingId(null);
      setRejectReason("");
      onNotify(`${application.organizationName} application rejected.`);
    } catch (error) {
      onNotify(error instanceof Error ? error.message : "Rejection failed.");
    } finally {
      setBusyId(null);
    }
  }

  return (
    <section className="panel" style={{ padding: "18px 20px", marginBottom: "20px" }} aria-labelledby="host-apps-title">
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", gap: "12px", flexWrap: "wrap" }}>
        <div>
          <span className="section-kicker">LIVE REVIEW QUEUE</span>
          <h2 id="host-apps-title" style={{ display: "flex", alignItems: "center", gap: "8px", margin: "2px 0 0", fontSize: "18px" }}>
            <Building2 size={18} /> Event Host Applications
          </h2>
        </div>
        <div style={{ display: "flex", gap: "8px", alignItems: "center" }}>
          <label className="field-label" style={{ margin: 0 }} htmlFor="host-apps-filter">
            Show
          </label>
          <select id="host-apps-filter" value={filter} onChange={(e) => setFilter(e.target.value as "pending" | "all")}>
            <option value="pending">Pending</option>
            <option value="all">All decisions</option>
          </select>
          <button type="button" className="action-pill-btn" onClick={load} aria-label="Refresh applications">
            <RefreshCw size={14} />
          </button>
        </div>
      </div>

      {isLoading && <p style={{ fontSize: "13px", color: "#64748b" }}>Loading applications…</p>}
      {loadError && (
        <p role="alert" style={{ fontSize: "13px", color: "#b91c1c" }}>
          {loadError}
        </p>
      )}
      {!isLoading && !loadError && applications.length === 0 && (
        <p style={{ fontSize: "13px", color: "#64748b" }}>
          {filter === "pending" ? "No applications waiting for review." : "No applications yet."}
        </p>
      )}

      <div style={{ display: "grid", gap: "12px", marginTop: "14px" }}>
        {applications.map((application) => (
          <article
            key={application.id}
            style={{ border: "1px solid #e7ebe4", borderRadius: "10px", padding: "14px 16px", background: "#fbfcfa" }}
          >
            <div style={{ display: "flex", justifyContent: "space-between", gap: "12px", flexWrap: "wrap" }}>
              <div>
                <strong style={{ fontSize: "15px" }}>{application.organizationName}</strong>
                <div style={{ fontSize: "12px", color: "#475569", marginTop: "2px" }}>
                  {application.applicantName} · {application.applicantEmail} · {application.contactPhone} · {application.country}
                </div>
              </div>
              <span className={`status-pill ${application.status}`} style={{ textTransform: "capitalize", alignSelf: "flex-start" }}>
                {application.status}
              </span>
            </div>

            {application.description && (
              <p style={{ fontSize: "13px", color: "#334155", margin: "8px 0 0" }}>{application.description}</p>
            )}
            {application.websiteOrSocial && (
              <p style={{ fontSize: "12px", color: "#475569", margin: "4px 0 0" }}>Link: {application.websiteOrSocial}</p>
            )}
            <p style={{ fontSize: "11px", color: "#64748b", margin: "6px 0 0" }}>
              Submitted {new Date(application.createdAt).toLocaleString()}
              {application.reviewedAt &&
                ` · Reviewed ${new Date(application.reviewedAt).toLocaleString()} by ${application.reviewedBy ?? "an administrator"}`}
            </p>
            {application.rejectionReason && (
              <p style={{ fontSize: "12px", color: "#b91c1c", margin: "4px 0 0" }}>Reason: {application.rejectionReason}</p>
            )}

            {application.status === "pending" && (
              <div style={{ marginTop: "12px", display: "grid", gap: "8px" }}>
                <label className="field-label" htmlFor={`notes-${application.id}`} style={{ margin: 0 }}>
                  Internal review notes (optional)
                </label>
                <input
                  id={`notes-${application.id}`}
                  type="text"
                  value={notes[application.id] ?? ""}
                  onChange={(e) => setNotes((n) => ({ ...n, [application.id]: e.target.value }))}
                  placeholder="e.g. Verified co-op registration letter"
                />

                {rejectingId === application.id ? (
                  <div style={{ display: "grid", gap: "8px" }}>
                    <label className="field-label" htmlFor={`reject-${application.id}`} style={{ margin: 0 }}>
                      Reason shown to the applicant *
                    </label>
                    <input
                      id={`reject-${application.id}`}
                      type="text"
                      value={rejectReason}
                      onChange={(e) => setRejectReason(e.target.value)}
                      placeholder="e.g. We could not verify the organization's contact details"
                    />
                    <div style={{ display: "flex", gap: "8px" }}>
                      <button
                        type="button"
                        className="primary-button"
                        style={{ background: "#c54338", borderColor: "#c54338" }}
                        disabled={!rejectReason.trim() || busyId === application.id}
                        onClick={() => void reject(application)}
                      >
                        <XCircle size={14} /> Confirm rejection
                      </button>
                      <button type="button" className="action-pill-btn" onClick={() => setRejectingId(null)}>
                        Cancel
                      </button>
                    </div>
                  </div>
                ) : (
                  <div style={{ display: "flex", gap: "8px" }}>
                    <button
                      type="button"
                      className="primary-button"
                      disabled={busyId === application.id}
                      onClick={() => void approve(application)}
                    >
                      <CheckCircle2 size={14} /> Approve as Event Host
                    </button>
                    <button
                      type="button"
                      className="action-pill-btn"
                      disabled={busyId === application.id}
                      onClick={() => {
                        setRejectingId(application.id);
                        setRejectReason("");
                      }}
                    >
                      <XCircle size={14} /> Reject
                    </button>
                  </div>
                )}
              </div>
            )}
          </article>
        ))}
      </div>
    </section>
  );
};
