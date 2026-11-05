import React, { useState } from "react";
import { EventDetails, EventTicketTier } from "../../types";

type Props = {
  event: EventDetails;
  ticket: EventTicketTier;
  currency: "USD" | "JMD";
  onDesignChange: (style: NonNullable<EventTicketTier["designStyle"]>) => void;
  onAccentChange: (color: NonNullable<EventTicketTier["accentColor"]>) => void;
  onBackgroundChange: (style: NonNullable<EventTicketTier["backgroundStyle"]>) => void;
};

const designs = [
  { id: "premium_minimal", name: "Premium Minimal" },
  { id: "vip_dark", name: "VIP Dark" },
  { id: "festival_color", name: "Festival Color" },
  { id: "modern_digital", name: "Modern Digital" },
  { id: "elegant_gold", name: "Elegant Gold" },
  { id: "concert_pass", name: "Concert Pass" },
] as const;

const accents = [
  { id: "indigo", name: "Indigo", color: "#818CF8" },
  { id: "emerald", name: "Emerald", color: "#34D399" },
  { id: "coral", name: "Coral", color: "#FB7185" },
  { id: "cyan", name: "Cyan", color: "#38BDF8" },
  { id: "violet", name: "Violet", color: "#C084FC" },
  { id: "gold", name: "Gold", color: "#FBBF24" },
] as const;

const designBackgrounds: Record<(typeof designs)[number]["id"], string> = {
  premium_minimal: "#F8FAFC",
  vip_dark: "#111827",
  festival_color: "linear-gradient(125deg, #172554, #581C87 65%, #9F1239)",
  modern_digital: "#0B1120",
  elegant_gold: "#1C1917",
  concert_pass: "linear-gradient(115deg, #111827, #312E81)",
};

function eventDateTime(event: EventDetails): string {
  const startDate = event.dateStr || event.eventDateIso || "Event date";
  const endDate = event.endDateIso && event.endDateIso !== event.eventDateIso
    ? event.endDateStr || event.endDateIso
    : "";
  const date = endDate ? `${startDate} – ${endDate}` : startDate;
  const time = [event.startTime, event.endTime].filter(Boolean).join(" – ") || event.timeStr;
  return [date, time].filter(Boolean).join(" · ");
}

export function TicketPreview({
  event,
  ticket,
  currency,
  onDesignChange,
  onAccentChange,
  onBackgroundChange,
}: Props) {
  const [format, setFormat] = useState<"mobile" | "print">("mobile");
  const accent = accents.find((item) => item.id === ticket.accentColor)?.color || "#818CF8";
  const design = designs.find((item) => item.id === ticket.designStyle) || designs[0];
  const baseBackground = designBackgrounds[design.id];
  const background = ticket.backgroundStyle === "gradient"
    ? `linear-gradient(135deg, ${baseBackground}, ${accent}55)`
    : ticket.backgroundStyle === "glow"
      ? `radial-gradient(ellipse at top right, ${accent}66, transparent 60%), ${baseBackground}`
      : baseBackground;
  const lightDesign = design.id === "premium_minimal";
  const textColor = lightDesign ? "#0F172A" : "#F8FAFC";
  const mutedColor = lightDesign ? "#475569" : "#CBD5E1";
  const price = currency === "JMD"
    ? `J$${Math.round(ticket.priceJMD).toLocaleString()}`
    : new Intl.NumberFormat("en-US", { style: "currency", currency: "USD" }).format(ticket.priceUSD);

  return (
    <section aria-label={`Live ticket preview for ${ticket.name || "new ticket"}`} style={{ display: "grid", gap: 10 }}>
      <div style={{ display: "flex", flexWrap: "wrap", alignItems: "end", gap: 10 }}>
        <label style={{ ...fieldLabel, flex: "1 1 190px" }}>
          Design style
          <select
            aria-label="Ticket design style"
            value={ticket.designStyle || "premium_minimal"}
            onChange={(e) => onDesignChange(e.target.value as NonNullable<EventTicketTier["designStyle"]>)}
            style={control}
          >
            {designs.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
          </select>
        </label>
        <label style={{ ...fieldLabel, flex: "1 1 140px" }}>
          Accent color
          <select
            aria-label="Ticket accent color"
            value={ticket.accentColor || "indigo"}
            onChange={(e) => onAccentChange(e.target.value as NonNullable<EventTicketTier["accentColor"]>)}
            style={control}
          >
            {accents.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
          </select>
        </label>
        <label style={{ ...fieldLabel, flex: "1 1 140px" }}>
          Background
          <select
            aria-label="Ticket background style"
            value={ticket.backgroundStyle || "solid"}
            onChange={(e) => onBackgroundChange(e.target.value as NonNullable<EventTicketTier["backgroundStyle"]>)}
            style={control}
          >
            <option value="solid">Solid</option>
            <option value="gradient">Gradient</option>
            <option value="glow">Soft glow</option>
          </select>
        </label>
        <div role="group" aria-label="Ticket preview format" style={{ display: "flex", gap: 6 }}>
          {(["mobile", "print"] as const).map((item) => (
            <button key={item} type="button" aria-pressed={format === item} onClick={() => setFormat(item)} style={{
              ...control, cursor: "pointer", textTransform: "capitalize",
              borderColor: format === item ? accent : "rgba(148,163,184,.35)",
            }}>{item === "print" ? "Printable" : "Mobile"}</button>
          ))}
        </div>
      </div>

      <div style={{
        display: "flex",
        justifyContent: "center",
        padding: 14,
        overflowX: "auto",
        borderRadius: 12,
        background: "rgba(2,6,23,.55)",
        border: "1px solid rgba(148,163,184,.18)",
      }}>
        <article
          data-ticket-preview-style={design.id}
          data-ticket-preview-format={format}
          style={{
            boxSizing: "border-box",
            width: format === "mobile" ? 280 : 620,
            minWidth: format === "mobile" ? 280 : 520,
            minHeight: format === "mobile" ? 420 : 238,
            padding: format === "mobile" ? 20 : 24,
            borderRadius: design.id === "concert_pass" ? 8 : 18,
            background,
            border: `1px solid ${accent}88`,
            color: textColor,
            boxShadow: `0 14px 35px ${accent}22`,
            position: "relative",
            overflow: "hidden",
            display: "flex",
            flexDirection: format === "mobile" ? "column" : "row",
            gap: 18,
            fontFamily: "Inter, system-ui, sans-serif",
          }}
        >
          <div style={{ flex: 1, minWidth: 0, display: "flex", flexDirection: "column", gap: 12 }}>
            <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 8 }}>
              <span style={{ fontSize: 10, fontWeight: 900, letterSpacing: ".14em", color: accent }}>EVENTBITE</span>
              <span style={{ fontSize: 9, color: mutedColor }}>TICKET DESIGN PREVIEW</span>
            </div>
            <div>
              <div style={{ fontSize: 10, fontWeight: 800, letterSpacing: ".1em", textTransform: "uppercase", color: accent }}>
                {ticket.category}
              </div>
              <h4 style={{ margin: "5px 0", fontSize: 21, lineHeight: 1.15, overflowWrap: "anywhere", color: textColor }}>
                {ticket.name || "Ticket name"}
              </h4>
              <div style={{ fontSize: 13, fontWeight: 750, color: textColor }}>{event.title || "Event name"}</div>
              <p style={{ margin: "7px 0 0", fontSize: 11, lineHeight: 1.5, color: mutedColor, whiteSpace: "pre-wrap" }}>
                {ticket.description || "Ticket description will appear here."}
              </p>
            </div>
            <div style={{ fontSize: 11, lineHeight: 1.6, color: mutedColor }}>
              <div>{eventDateTime(event)}</div>
              <div>{event.venueName || "Venue"}{event.city ? ` · ${event.city}` : ""}</div>
              {ticket.admissionPrivileges && (
                <div style={{ marginTop: 6, color: textColor, overflowWrap: "anywhere" }}>
                  <strong>Admission:</strong> {ticket.admissionPrivileges}
                </div>
              )}
              {ticket.includedFoodAndDrinks && (
                <div style={{ marginTop: 4, color: textColor, overflowWrap: "anywhere" }}>
                  <strong>Included:</strong> {ticket.includedFoodAndDrinks}
                </div>
              )}
              {ticket.benefits?.filter(Boolean).length ? (
                <div style={{ marginTop: 4, color: textColor, overflowWrap: "anywhere" }}>
                  {ticket.benefits.filter(Boolean).slice(0, 3).join(" · ")}
                </div>
              ) : null}
            </div>
            <div style={{ marginTop: "auto", display: "flex", alignItems: "end", justifyContent: "space-between", gap: 8 }}>
              <span style={{ fontSize: 22, fontWeight: 900, color: textColor }}>{price}</span>
              <span style={{ fontSize: 9, fontWeight: 800, color: accent }}>PREVIEW ONLY</span>
            </div>
          </div>
          <div style={{
            width: format === "mobile" ? "100%" : 148,
            flex: format === "mobile" ? "none" : "0 0 148px",
            display: "flex",
            flexDirection: "column",
            alignItems: "center",
            justifyContent: "center",
            gap: 9,
            borderLeft: format === "mobile" ? "none" : `1px dashed ${accent}99`,
            borderTop: format === "mobile" ? `1px dashed ${accent}99` : "none",
            padding: format === "mobile" ? "14px 0 0" : "0 0 0 18px",
          }}>
            <div aria-label="Preview-only QR placeholder; not an admission code" style={{
              width: 92, height: 92, borderRadius: 10, border: `1px solid ${accent}77`,
              background: lightDesign ? "#E2E8F0" : "rgba(2,6,23,.72)",
              display: "grid", placeItems: "center", textAlign: "center", padding: 8,
              color: mutedColor, fontSize: 10, fontWeight: 800,
            }}>QR PLACEHOLDER<br />NOT VALID</div>
            <span style={{ fontSize: 9, textAlign: "center", color: mutedColor }}>Ticket # preview only<br />Secure code added at issue</span>
          </div>
        </article>
      </div>
      <p style={{ margin: 0, fontSize: 10, lineHeight: 1.45, color: "#94A3B8" }}>
        Design preview only. Ticket numbers and secure QR admission credentials are created by the server when tickets are issued.
      </p>
    </section>
  );
}

const fieldLabel: React.CSSProperties = {
  display: "grid",
  gap: 5,
  color: "#CBD5E1",
  fontSize: 10,
  fontWeight: 700,
};

const control: React.CSSProperties = {
  minHeight: 36,
  borderRadius: 8,
  border: "1px solid rgba(148,163,184,.3)",
  background: "#0B1120",
  color: "#F8FAFC",
  padding: "0 9px",
  fontSize: 11,
};
