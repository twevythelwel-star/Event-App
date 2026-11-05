import React from "react";
import { AlertCircle, ShieldCheck } from "lucide-react";
import type { EventDetails } from "../../types";
import { refundPolicyPreviewLines } from "../../lib/refundPolicy";

type Props = {
  event: EventDetails;
  ticketCategories: string[];
  disabled?: boolean;
  onChange: (event: EventDetails) => void;
};

const labelStyle: React.CSSProperties = {
  display: "grid",
  gap: 5,
  color: "#CBD5E1",
  fontSize: 11,
  fontWeight: 700,
};

const inputStyle: React.CSSProperties = {
  width: "100%",
  minHeight: 40,
  padding: "0 10px",
  borderRadius: 8,
  border: "1px solid rgba(148,163,184,.28)",
  background: "#0B1120",
  color: "#F8FAFC",
  fontSize: 12,
};

export function CancellationPolicyEditor({ event, ticketCategories, disabled = false, onChange }: Props) {
  const update = <K extends keyof EventDetails>(key: K, value: EventDetails[K]) => {
    onChange({ ...event, [key]: value });
  };
  const selectedCategories = event.nonRefundableTicketCategories || [];
  const previewEvent: EventDetails = {
    ...event,
    refundPolicyDeadlineHours: event.refundPolicyDeadlineHours ?? 48,
    foodOrderCancellationDeadlineHours: event.foodOrderCancellationDeadlineHours ?? 24,
    foodOrderModificationDeadlineHours: event.foodOrderModificationDeadlineHours ?? 24,
  };

  return (
    <section aria-labelledby="cancellation-policy-title" style={{ padding: 16, borderRadius: 10, background: "#0B1120", border: "1px solid rgba(148,163,184,.22)", display: "grid", gap: 14 }}>
      <div>
        <h3 id="cancellation-policy-title" style={{ margin: 0, color: "#F8FAFC", fontSize: 15 }}>Cancellations and Refunds</h3>
        <p style={{ margin: "5px 0 0", color: "#94A3B8", fontSize: 11 }}>Set event terms. EventBite only records completed manual refunds; it does not process provider-backed refunds or modify food orders.</p>
      </div>

      <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(210px, 1fr))", gap: 10 }}>
        <label style={labelStyle}>Refund request deadline (hours before event)
          <input type="number" min={0} max={720} value={event.refundPolicyDeadlineHours ?? 48} disabled={disabled} onChange={(e) => update("refundPolicyDeadlineHours", Number(e.target.value))} style={inputStyle} />
        </label>
        <label style={labelStyle}>Refund handling
          <select value={event.refundPolicyMode || "manual_full_and_partial"} disabled={disabled} onChange={(e) => update("refundPolicyMode", e.target.value as EventDetails["refundPolicyMode"])} style={inputStyle}>
            <option value="manual_full_only">Full manual refunds only</option>
            <option value="manual_full_and_partial">Full or partial manual refunds</option>
            <option value="non_refundable">No refunds</option>
          </select>
        </label>
        <label style={labelStyle}>If the host cancels
          <select value={event.hostCancellationPolicy || "contact_organizer"} disabled={disabled} onChange={(e) => update("hostCancellationPolicy", e.target.value as EventDetails["hostCancellationPolicy"])} style={inputStyle}>
            <option value="contact_organizer">Contact organizer; no automatic refund</option>
            <option value="manual_refund_if_supported">Manual refund if payment method supports it</option>
          </select>
        </label>
        <label style={labelStyle}>If postponed or rescheduled
          <select value={event.postponementPolicy || "tickets_valid_for_new_date"} disabled={disabled} onChange={(e) => update("postponementPolicy", e.target.value as EventDetails["postponementPolicy"])} style={inputStyle}>
            <option value="tickets_valid_for_new_date">Existing tickets remain valid for new date</option>
            <option value="contact_organizer">Contact organizer for next steps</option>
          </select>
        </label>
        <label style={labelStyle}>Weather cancellation
          <select value={event.weatherCancellationPolicy || "contact_organizer"} disabled={disabled} onChange={(e) => update("weatherCancellationPolicy", e.target.value as EventDetails["weatherCancellationPolicy"])} style={inputStyle}>
            <option value="contact_organizer">Contact organizer; no automatic refund</option>
            <option value="reschedule_tickets_valid">Reschedule; existing tickets remain valid</option>
            <option value="manual_refund_if_supported">Manual refund if payment method supports it</option>
          </select>
        </label>
        <label style={labelStyle}>Food cancellation request deadline (hours before event)
          <input type="number" min={0} max={720} value={event.foodOrderCancellationDeadlineHours ?? 24} disabled={disabled} onChange={(e) => update("foodOrderCancellationDeadlineHours", Number(e.target.value))} style={inputStyle} />
        </label>
        <label style={labelStyle}>Food modification request deadline (hours before event)
          <input type="number" min={0} max={720} value={event.foodOrderModificationDeadlineHours ?? 24} disabled={disabled} onChange={(e) => update("foodOrderModificationDeadlineHours", Number(e.target.value))} style={inputStyle} />
        </label>
      </div>

      {ticketCategories.length > 0 && (
        <fieldset style={{ border: "1px solid rgba(148,163,184,.2)", borderRadius: 8, padding: 10 }}>
          <legend style={{ color: "#CBD5E1", fontSize: 11, fontWeight: 700 }}>Non-refundable ticket types</legend>
          <div style={{ display: "flex", flexWrap: "wrap", gap: 10 }}>
            {ticketCategories.map((category) => (
              <label key={category} style={{ display: "inline-flex", alignItems: "center", gap: 5, color: "#CBD5E1", fontSize: 11 }}>
                <input type="checkbox" checked={selectedCategories.includes(category)} disabled={disabled}
                  onChange={() => update("nonRefundableTicketCategories", selectedCategories.includes(category)
                    ? selectedCategories.filter((item) => item !== category)
                    : [...selectedCategories, category])} />
                {category}
              </label>
            ))}
          </div>
        </fieldset>
      )}

      <label style={labelStyle}>Additional policy notes
        <textarea rows={3} maxLength={2000} value={event.refundPolicyNotes || ""} disabled={disabled} onChange={(e) => update("refundPolicyNotes", e.target.value)} style={{ ...inputStyle, height: "auto", padding: 10, resize: "vertical" }} placeholder="Add organizer-specific terms without promising unsupported payment refunds." />
      </label>

      <div aria-label="Attendee refund policy preview" style={{ padding: 13, borderRadius: 8, background: "rgba(16,185,129,.08)", border: "1px solid rgba(52,211,153,.28)" }}>
        <div style={{ display: "flex", alignItems: "center", gap: 7, color: "#A7F3D0", fontSize: 11, fontWeight: 800, marginBottom: 8 }}><ShieldCheck size={14} /> ATTENDEE POLICY PREVIEW</div>
        <div style={{ display: "grid", gap: 5, color: "#CBD5E1", fontSize: 11, lineHeight: 1.5 }}>
          {refundPolicyPreviewLines(previewEvent).map((line, index) => <p key={index} style={{ margin: 0 }}>{line}</p>)}
        </div>
        <p style={{ display: "flex", alignItems: "flex-start", gap: 5, color: "#FCD34D", fontSize: 10, lineHeight: 1.4, margin: "9px 0 0" }}><AlertCircle size={12} /> Approved cancellation requests are not refunds. Provider-backed refunds and in-app food order modifications are not supported.</p>
      </div>
    </section>
  );
}